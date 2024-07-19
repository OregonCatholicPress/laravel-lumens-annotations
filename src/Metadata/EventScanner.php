<?php

namespace ProAI\Annotations\Metadata;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

class EventScanner
{
    /**
     * Create a new metadata builder instance.
     */
    public function __construct(
        /**
         * The annotation reader instance.
         */
        protected AnnotationReader $reader,
        /**
         * The config of the event annotations package.
         */
        protected array $config
    )
    {
    }

    /**
     * Build metadata from all entity classes.
     */
    public function scan(array $classes): array
    {
        $metadata = [];

        foreach ($classes as $class) {
            $eventListenMetadata = $this->parseClass($class);

            if ($eventListenMetadata) {
                $metadata[$eventListenMetadata][] = $class;
            }
        }

        return $metadata;
    }

    /**
     * Parse a class
     */
    public function parseClass(string $class): ?array
    {
        $reflectionClass = new ReflectionClass($class);

        // check if class is controller
        $annotation = $this->reader->getClassAnnotation($reflectionClass, \ProAI\Annotations\Annotations\Hears::class);
        if ($annotation) {
            $class = $annotation->value;

            if (isset($this->config['events_namespace']) && !str_starts_with($class, (string) $this->config['events_namespace'])) {
                $class = $this->config['events_namespace'] . '\\' . $class;
            }

            return $class;
        }

        return null;
    }
}
