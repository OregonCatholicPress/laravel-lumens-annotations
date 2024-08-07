<?php

namespace ProAI\Annotations\Console;

use Illuminate\Console\Command;
use ProAI\Annotations\Events\Generator;
use ProAI\Annotations\Metadata\ClassFinder;
use ProAI\Annotations\Metadata\EventScanner;

class EventScanCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'event:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all events with event annotations.';

    /**
     * The class finder instance.
     *
     * @var ClassFinder
     */
    protected $finder;

    /**
     * The event scanner instance.
     *
     * @var EventScanner
     */
    protected $scanner;

    /**
     * The events generator instance.
     *
     * @var Generator
     */
    protected $generator;

    /**
     * Create a new migration install command instance.
     *
     * @param ClassFinder  $finder
     * @param EventScanner $scanner
     * @param Generator    $generator
     * @param array        $config
     *
     * @return void
     */
    public function __construct(ClassFinder $finder, EventScanner $scanner, Generator $generator, /**
     * The config of the event annotations package.
     */
        protected $config)
    {
        parent::__construct();

        $this->finder = $finder;
        $this->scanner = $scanner;
        $this->generator = $generator;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        // get classes
        $classes = $this->finder->getClassesFromNamespace($this->config['events_namespace']);

        // build metadata
        $events = $this->scanner->scan($classes);

        // generate events.php file for scanned events
        $this->generator->generate($events);

        $this->info('Events registered successfully!');
    }

    public function handle()
    {
        $this->fire();
    }
}
