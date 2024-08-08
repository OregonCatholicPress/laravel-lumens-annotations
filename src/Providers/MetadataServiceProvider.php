<?php

namespace ProAI\Annotations\Providers;

use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\ServiceProvider;
use Override;
use ProAI\Annotations\Filesystem\ClassFinder as FilesystemClassFinder;
use ProAI\Annotations\Metadata\ClassFinder;

class MetadataServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     */
    protected bool $defer = true;

    /**
     * Register the application services.
     */
    #[Override]
    public function register(): void
    {
        $this->registerAnnotationReader();

        $this->registerClassFinder();
    }

    /**
     * Register the class finder implementation.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function registerAnnotationReader(): void
    {
        $this->app->singleton('annotations.annotationreader', fn ($app) => new AnnotationReader());
    }

    /**
     * Register the class finder implementation.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function registerClassFinder(): void
    {
        $this->app->singleton('annotations.classfinder', function ($app) {
            $finder = new FilesystemClassFinder();

            return new ClassFinder($finder);
        });
    }

    /**
     * Get the services provided by the provider.
     */
    #[Override]
    public function provides(): array
    {
        return [
            'annotations.classfinder',
            'annotations.annotationreader',
        ];
    }
}
