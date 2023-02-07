<?php

namespace ProAI\Annotations\Console;

use Illuminate\Console\Command;
use ProAI\Annotations\Metadata\ClassFinder;
use ProAI\Annotations\Metadata\RouteScanner;
use ProAI\Annotations\Routing\Generator;

class RouteScanCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all routes with route annotations.';

    /**
     * The class finder instance.
     */
    protected ClassFinder $finder;

    /**
     * The route scanner instance.
     */
    protected RouteScanner $scanner;

    /**
     * The routes generator instance.
     */
    protected Generator $generator;

    /**
     * The config of the route annotations package.
     */
    protected array $config;

    /**
     * Create a new migration install command instance.
     */
    public function __construct(ClassFinder $finder, RouteScanner $scanner, Generator $generator, array $config)
    {
        parent::__construct();

        $this->finder = $finder;
        $this->scanner = $scanner;
        $this->generator = $generator;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     */
    public function fire(): void
    {
        // get classes
        $classes = $this->finder->getClassesFromNamespace($this->config['routes_namespace']);

        // build metadata
        $routes = $this->scanner->scan($classes);

        // generate routes.php file for scanned routes
        $this->generator->generate($routes);

        $this->info('Routes registered successfully!');
    }

    /**
     * Catch the artisan call
     */
    public function handle(): void
    {
        $this->fire();
    }
}
