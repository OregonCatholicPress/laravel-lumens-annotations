<?php

namespace ProAI\Annotations\Metadata;

use Illuminate\Console\AppNamespaceDetectorTrait;
use ProAI\Annotations\Filesystem\ClassFinder as FilesystemClassFinder;

class ClassFinder
{
    /**
     * The class finder instance.
     */
    protected FilesystemClassFinder $finder;

    /**
     * The application namespace.
     */
    protected ?string $namespace = null;

    /**
     * Create a new metadata builder instance.
     */
    public function __construct(FilesystemClassFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Get all classes for a given namespace.
     */
    public function getClassesFromNamespace(?string $namespace = null): array
    {
        $namespace = $namespace ?: $this->getAppNamespace();

        $path = $this->convertNamespaceToPath($namespace);

        return $this->finder->findClasses($path);
    }

    /**
     * Convert given namespace to file path.
     */
    protected function convertNamespaceToPath(string $namespace): ?string
    {
        // strip app namespace
        $appNamespace = $this->getAppNamespace();

        if (substr($namespace, 0, strlen($appNamespace)) != $appNamespace) {
            return null;
        }

        $subNamespace = substr($namespace, strlen($appNamespace));

        // replace \ with / to get the correct file path
        $subPath = str_replace('\\', '/', $subNamespace);

        // create path
        return app('path') . '/' . $subPath;
    }

    /**
     * Get the application namespace.
     *
     * @throws \RuntimeException
     */
    public function getAppNamespace(): string
    {
        if (! is_null($this->namespace)) {
            return $this->namespace;
        }

        $composer = json_decode(file_get_contents(base_path().'/composer.json'), true);

        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath(app('path')) == realpath(base_path().'/'.$pathChoice)) {
                    return $this->namespace = $namespace;
                }
            }
        }
        
        throw new RuntimeException('Unable to detect application namespace.');
    }
}
