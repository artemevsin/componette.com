<?php

namespace App\Tasks\Packages;

use App\Model\ORM\Package;
use App\Model\ORM\PackagesRepository;
use App\Model\WebServices\Github\Service;
use App\Tasks\BaseTask;
use Nette\Utils\DateTime;
use Nextras\Orm\Collection\ICollection;

final class UpdateMetadataTask extends BaseTask
{

    /** @var PackagesRepository */
    private $packagesRepository;

    /** @var Service */
    private $github;

    /**
     * @param PackagesRepository $packagesRepository
     * @param Service $github
     */
    function __construct(
        PackagesRepository $packagesRepository,
        Service $github
    )
    {
        $this->packagesRepository = $packagesRepository;
        $this->github = $github;
    }

    /**
     * @param array $args
     * @return int
     */
    public function run(array $args = [])
    {
        /** @var ICollection|Package[] $packages */
        $packages = $this->packagesRepository->findActive();

        if (isset($args['rest']) && $args['rest'] === TRUE) {
            $packages = $packages->findBy(['this->state' => Package::STATE_QUEUED]);
        }

        // DO YOUR JOB ===============================================

        $counter = 0;
        foreach ($packages as $package) {
            list ($owner, $repo) = explode('/', $package->repository);

            // Base metadata
            $response = $this->github->repo($owner, $repo);
            if ($response && !isset($response['message'])) {
                $meta = $package->metadata;

                // Increase counting
                $counter++;

                $meta->owner = $response['owner']['login'];
                $meta->name = $response['full_name'];
                $meta->description = $response['description'];
                $meta->homepage = !empty($response['homepage']) ? $response['homepage'] : NULL;
                $meta->stars = $response['stargazers_count'];
                $meta->watchers = $response['watchers_count'];
                $meta->issues = $response['open_issues_count'];
                $meta->forks = $response['forks_count'];
                $meta->created = new DateTime($response['created_at']);
                $meta->updated = new DateTime($response['updated_at']);
                $meta->pushed = new DateTime($response['pushed_at']);
                $package->state = Package::STATE_ACTIVE;
                $package->updated = new DateTime();
            } else {
                $package->state = Package::STATE_ARCHIVED;
                if (isset($response['message'])) {
                    $this->log('Skip (' . $response['message'] . '): ' . $package->repository);
                } else {
                    $this->log('Skip (base): ' . $package->repository);
                }
            }

            $this->packagesRepository->persistAndFlush($package);
        }

        return $counter;
    }

}
