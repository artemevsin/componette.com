<?php

namespace App\Model\Commands\Addons\Github;

use App\Model\Commands\BaseCommand;
use App\Model\ORM\Addon\Addon;
use App\Model\ORM\Addon\AddonRepository;
use App\Model\ORM\Github\GithubRepository;
use App\Model\ORM\GithubRelease\GithubRelease;
use App\Model\ORM\GithubRelease\GithubReleaseRepository;
use App\Model\WebServices\Github\GithubService;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SynchronizeReleasesCommand extends BaseCommand
{

    /** @var AddonRepository */
    private $addonRepository;

    /** @var GithubRepository */
    private $githubRepository;

    /** @var GithubReleaseRepository */
    private $githubReleaseRepository;

    /** @var GithubService */
    private $github;

    /**
     * @param AddonRepository $addonRepository
     * @param GithubRepository $githubRepository
     * @param GithubReleaseRepository $githubReleaseRepository
     * @param GithubService $github
     */
    public function __construct(
        AddonRepository $addonRepository,
        GithubRepository $githubRepository,
        GithubReleaseRepository $githubReleaseRepository,
        GithubService $github
    )
    {
        parent::__construct();
        $this->addonRepository = $addonRepository;
        $this->githubRepository = $githubRepository;
        $this->githubReleaseRepository = $githubReleaseRepository;
        $this->github = $github;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('addons:github:sync:releases')
            ->setDescription('Synchronize addon releases');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $addons = $this->addonRepository->findActive();

        $counter = 0;

        /** @var Addon[] $addons */
        foreach ($addons as $addon) {
            // Fetch all already saved github releases
            $storedReleases = $addon->github->releases->get()->fetchPairs('gid');

            // Get all releases
            $responses = $this->github->allReleases($addon->owner, $addon->name);
            if ($responses) {

                foreach ((array) $responses as $response) {

                    // Get response body as releases
                    $releases = $response->getJsonBody();
                    if ($releases) {
                        // Iterate over all releases
                        foreach ($releases as $release) {

                            $releaseId = $release['id'];

                            // Try find release by ID
                            if (isset($storedReleases[$releaseId])) {
                                // Use already added
                                $githubRelease = $storedReleases[$releaseId];
                            } else {
                                // Create new one
                                $githubRelease = new GithubRelease();
                                $githubRelease->gid = $release['id'];
                            }

                            $githubRelease->name = $release['name'];
                            $githubRelease->tag = $release['tag_name'];
                            $githubRelease->draft = (bool) $release['draft'];
                            $githubRelease->prerelease = (bool) $release['prerelease'];
                            $githubRelease->createdAt = new DateTime($release['created_at']);
                            $githubRelease->publishedAt = new DateTime($release['published_at']);
                            $githubRelease->body = $release['body'];

                            // If its new one
                            if (!$githubRelease->isPersisted()) {
                                $addon->github->releases->add($githubRelease);
                            }

                            // Unset from array
                            unset($storedReleases[$releaseId]);
                        }
                    } else {
                        $output->writeln('Skip (no releases): ' . $addon->fullname);
                    }
                }

                // Save new or updated releases
                $this->githubRepository->persistAndFlush($addon->github);

                // If there are some left releases, remove them
                if (count($storedReleases) > 0) {
                    foreach ($storedReleases as $release) {
                        $this->githubReleaseRepository->remove($release);
                    }
                }
            } else {
                $output->writeln('Skip (no response): ' . $addon->fullname);
            }

            $counter++;
        }

        $output->writeln(sprintf('Updated %s addons', $counter));
    }

}