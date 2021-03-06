<?php

namespace App\Model\Addons;

use Nette\Utils\Arrays;

final class Extra
{

    /** @var array */
    private $data = [];

    /**
     * @param mixed $data
     */
    public function __construct($data)
    {
        if (is_array($data)) {
            $this->data = $data;
        } else {
            $this->data = [];
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function append($key, $value)
    {
        $this->data[$key] = array_merge(isset($this->data[$key]) ? $this->data[$key] : [], (array) $value);
    }

    /**
     * @param string|array $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = NULL)
    {
        return Arrays::get($this->data, $key, $default);
    }

    /**
     * @param mixed $data
     */
    public function setAll($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getAll()
    {
        return $this->data;
    }

    /**
     * @return array|NULL
     */
    public function export()
    {
        if ($this->data) {
            return json_encode($this->data);
        } else {
            return NULL;
        }
    }

}
