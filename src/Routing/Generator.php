<?php

namespace ProAI\Annotations\Routing;

use Illuminate\Filesystem\Filesystem;

class Generator
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Path to routes storage directory.
     */
    protected string $path;

    /**
     * path to routes.php file.
     */
    protected string $routesFile;

    public function __construct(Filesystem $files, string $path, string $routesFile)
    {
        $this->files = $files;
        $this->path = $path;
        $this->routesFile = $this->path . '/' . $routesFile;
    }

    /**
     * Generate routes from metadata and save to file.
     */
    public function generate(array $metadata): void
    {
        // clean or make (if not exists) model storage directory
        if (! $this->files->exists($this->path)) {
            $this->files->makeDirectory($this->path);
        }

        // generate routes
        $routes = $this->generateRoutes($metadata);

        // create routes.php
        $this->files->put($this->routesFile, $routes);
    }

    /**
     * Clean model directory.
     */
    public function clean(): void
    {
        if ($this->files->exists($this->routesFile)) {
            $this->files->delete($this->routesFile);
        }
    }

    /**
     * Generate routes from metadata.
     */
    public function generateRoutes(array $metadata): string
    {
        $contents = '<?php' . PHP_EOL;
        $contents .= '$app = app(); ' . PHP_EOL;
        $contents .= '$router = $app->router; ' . PHP_EOL;

        foreach($metadata as $name => $controllerMetadata) {
            $contents .= PHP_EOL . "// Routes in controller '" . $name . "'" . PHP_EOL;

            foreach($controllerMetadata as $routeMetadata) {
                $options = [];

                // as option
                if (! empty($routeMetadata['as'])) {
                    $options[] = "'as' => '".$routeMetadata['as']."'";
                }

                // middleware option
                if (! empty($routeMetadata['middleware'])) {
                    if (is_array($routeMetadata['middleware'])) {
                        $flat = $this::arrayFlatten($routeMetadata['middleware']);
                        $middleware = "['".implode("', '",$flat)."']";
                    } else {
                        $middleware = "'".$routeMetadata['middleware']."'";
                    }
                    $options[] = "'middleware' => ".$middleware;
                }

                // uses option
                $options[] = "'uses' => '".$routeMetadata['controller']."@".$routeMetadata['controllerMethod']."'";

                $contents .= "\$router"."->".strtolower($routeMetadata['httpMethod'])."('".$routeMetadata['uri']."', [".implode(", ", $options)."]);" . PHP_EOL;
            }
        }


        return $contents;
    }

    function arrayFlatten($array)
    {
        if (!is_array($array)) { 
            return false; 
        } 
        $result = array(); 
        foreach ($array as $key => $value) { 
            if (is_array($value)) { 
                $result = array_merge($result, $this::arrayFlatten($value));
            } else { 
                $result = array_merge($result, array($key => $value));
            } 
        }

        return $result; 
    }
}
