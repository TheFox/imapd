<?php

namespace TheFox\Storage;

use Symfony\Component\Yaml\Yaml;

class YamlStorage
{
    /**
     * @var string
     */
    private $datadirBasePath = '';

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var bool
     */
    public $dataChanged = false;

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * YamlStorage constructor.
     * @param string|null $filePath
     */
    public function __construct(string $filePath = null)
    {
        if ($filePath !== null) {
            $this->setFilePath($filePath);
        }
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        $rv = false;

        if ($this->dataChanged) {
            if ($this->getFilePath()) {
                $rv = file_put_contents($this->getFilePath(), Yaml::dump($this->data));
            }
            if ($rv) {
                $this->setDataChanged(false);
            }
        }

        return $rv;
    }

    /**
     * @return bool
     */
    public function load(): bool
    {
        if ($this->getFilePath()) {
            if (file_exists($this->getFilePath())) {
                $this->data = Yaml::parse($this->getFilePath());
                return $this->isLoaded(true);
            }
        }

        return false;
    }

    /**
     * @param bool|null $isLoaded
     * @return bool
     */
    public function isLoaded(bool $isLoaded = null): bool
    {
        if ($isLoaded !== null) {
            $this->isLoaded = $isLoaded;
        }

        return $this->isLoaded;
    }

    /**
     * @param string $filePath
     */
    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @param string $datadirBasePath
     */
    public function setDatadirBasePath(string $datadirBasePath)
    {
        $this->datadirBasePath = $datadirBasePath;
    }

    /**
     * @return string
     */
    public function getDatadirBasePath(): string
    {
        return $this->datadirBasePath;
    }

    /**
     * @param bool $changed
     */
    public function setDataChanged(bool $changed = true)
    {
        $this->dataChanged = $changed;
    }

    /**
     * @return bool
     */
    public function getDataChanged(): bool
    {
        return $this->dataChanged;
    }
}
