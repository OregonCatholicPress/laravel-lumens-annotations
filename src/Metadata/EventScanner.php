<?php

namespace ProAI\Annotations\Metadata;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

class EventScanner
{
    /**
     * The annotation reader instance.
     */
    protected AnnotationReader $reader;

    /**
     * The config of the event annotations package.
     */
    protected array $config;

    /**
     * Create a new metadata builder instance.
     */
    public function __construct(AnnotationReader $reader, array $config)
    {
        $this->reader = $reader;
        $this->config = $config;
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
        $annotation = $this->reader->getClassAnnotation($reflectionClass, '\ProAI\Annotations\Annotations\Hears');
        if ($annotation) {
            $class = $annotation->value;

            if (isset($this->config['events_namespace']) && substr($class, 0, strlen($this->config['events_namespace'])) != $this->config['events_namespace']) {
                $class = $this->config['events_namespace'] . '\\' . $class;
            }

            return $class;
        }

        return null;
    }
}
