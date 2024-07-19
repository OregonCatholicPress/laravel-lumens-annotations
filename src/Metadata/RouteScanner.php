<?php

namespace ProAI\Annotations\Metadata;

use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RouteScanner
{
    /**
     * Create a new metadata builder instance.
     */
    public function __construct(/**
     * The annotation reader instance.
     */
        protected AnnotationReader $reader
    ) {
        // OA namespace is for swagger annotations. We want to ignore those.
        $this->reader->addGlobalIgnoredNamespace("OA");
    }

    /**
     * Build metadata from all entity classes.
     */
    public function scan(array $classes): array
    {
        $metadata = [];

        foreach ($classes as $class) {
            $controllerMetadata = $this->parseClass($class);

            if ($controllerMetadata) {
                $metadata[$class] = $controllerMetadata;
            }
        }

        return $metadata;
    }

    /**
     * Parse a class.
     */
    public function parseClass(string $class): ?array
    {
        $reflectionClass = new ReflectionClass($class);

        // check if class is controller
        $annotation = $this->reader->getClassAnnotation($reflectionClass, \ProAI\Annotations\Annotations\Controller::class);
        if ($annotation) {
            return $this->parseController($class);
        }

        return null;
    }

    /**
     * Parse a controller class.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function parseController(string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $classAnnotations = $this->reader->getClassAnnotations($reflectionClass);

        $controllerMetadata = [];
        // find entity parameters and plugins
        foreach ($classAnnotations as $annotation) {
            // controller attributes
            if ($annotation instanceof \ProAI\Annotations\Annotations\Controller) {
                $prefix = $annotation->prefix;
                $middleware = $annotation->middleware;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Middleware) {
                $middleware = $annotation->value;
            }

            // resource controller
            if ($annotation instanceof \ProAI\Annotations\Annotations\Resource) {
                $resourceMethods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
                if (! empty($annotation->only)) {
                    $resourceMethods = array_intersect($resourceMethods, $annotation->only);
                } elseif (! empty($annotation->except)) {
                    $resourceMethods = array_diff($resourceMethods, $annotation->except);
                }
                $resource = [
                    'name' => $annotation->value,
                    'methods' => $resourceMethods
                ];
            }
        }

        // find routes
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $name = $reflectionMethod->getName();
            $methodAnnotations = $this->reader->getMethodAnnotations($reflectionMethod);

            $routeMetadata = [];
            // controller method is resource route
            if (! empty($resource) && in_array($name, $resource['methods'])) {
                $routeMetadata = [
                    'uri' => $resource['name'] . $this->getResourcePath($name, $resource['name']),
                    'controller' => $class,
                    'controllerMethod' => $name,
                    'httpMethod' => $this->getResourceHttpMethod($name),
                    'as' => $resource['name'] . '.' . $name,
                    'middleware' => ''
                ];
            }

            // controller method is route
            $routes = $this->hasHttpMethodAnnotation($name, $methodAnnotations);
            if ($routes) {
                $routeMetadata = [];
                foreach ($routes as $route) {
                    $routeMetadata[] = [
                        'uri' => $route['uri'],
                        'controller' => $class,
                        'controllerMethod' => $name,
                        'httpMethod' => $route['httpMethod'],
                        'as' => $route['as'],
                        'middleware' => $route['middleware']
                    ];
                }
            }

            // add more route options to route metadata
            if (! empty($routeMetadata)) {
                if (!isset($routeMetadata[0])) {
                    $temp =  [];
                    $temp[] = $routeMetadata;
                    $routeMetadatas  = $temp;
                } else {
                    $routeMetadatas  = $routeMetadata;
                }
                $idx = 0;
                foreach ($routeMetadatas as $routeMetadata) {
                    $idx++;

                    // add other method annotations
                    foreach ($methodAnnotations as $annotation) {
                        if ($annotation instanceof \ProAI\Annotations\Annotations\Middleware) {
                            if (!empty($middleware) && isset($routeMetadata['middleware'])) {
                                $routeMetadata['middleware'] = [$middleware, $annotation->value];
                                continue;
                            }

                            $routeMetadata['middleware'] = $annotation->value;
                        }
                    }

                    // add global prefix and middleware
                    if (! empty($prefix)) {
                        $routeMetadata['uri'] = $prefix . '/' . $routeMetadata['uri'];
                    }
                    if (! empty($middleware) && empty($routeMetadata['middleware'])) {
                        $routeMetadata['middleware'] = $middleware;
                    }

                    $controllerMetadata[$name . $idx] = $routeMetadata;
                }
            }
        }

        return $controllerMetadata;
    }

    /**
     * Get resource http method.
     */
    protected function getResourceHttpMethod(string $method): string
    {
        $resourceHttpMethods = [
            'index' => 'GET',
            'create' => 'GET',
            'store' => 'POST',
            'show' => 'GET',
            'edit' => 'GET',
            'update' => 'PUT',
            'destroy' => 'DELETE'
        ];

        return $resourceHttpMethods[$method] ?? null;
    }

    /**
     * Get resource path.
     */
    protected function getResourcePath(string $method, string $name): ?string
    {
        $name = preg_replace('/.*\/([^\/]*)$/', '$1', $name);
        $name = Str::singular($name);
        $name = preg_replace_callback('/[-_](\w)/', fn ($matches) => strtoupper((string) $matches[1]), $name);
        $idd = $name . "Id";
        $resourcePaths = [
            'index' => '',
            'create' => 'create',
            'store' => '',
            'show' => '/{' . $idd . '}',
            'edit' => '/{' . $idd . '}/edit',
            'update' => '/{' . $idd . '}',
            'destroy' => '/{id}'
        ];

        return $resourcePaths[$method] ?? null;
    }

    /**
     * Check for http method.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function hasHttpMethodAnnotation(string $name, array $methodAnnotations): array|bool
    {
        $parseAnnotation = function ($httpMethod, $annotation) {
            // options
            $ass         = (! empty($annotation->as)) ? $annotation->as : '';
            $middleware = (! empty($annotation->middleware)) ? $annotation->middleware : '';
            $uri = (empty($annotation->value)) ? str_replace("_", "-", Str::snake($name)) : $annotation->value;

            return [
                  'uri' => $uri,
                  'httpMethod' => $httpMethod,
                  'as' => $ass,
                  'middleware' => $middleware
            ];
        };

        $return = [];
        foreach ($methodAnnotations as $annotation) {
            // check for http method annotation
            if ($annotation instanceof \ProAI\Annotations\Annotations\Get) {
                $httpMethod = 'GET';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                //     break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Post) {
                $httpMethod = 'POST';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                //break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Options) {
                $httpMethod = 'OPTIONS';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                // break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Put) {
                $httpMethod = 'PUT';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                //break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Patch) {
                $httpMethod = 'PATCH';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                // break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Delete) {
                $httpMethod = 'DELETE';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                //break;
            }
            if ($annotation instanceof \ProAI\Annotations\Annotations\Any) {
                $httpMethod = 'ANY';
                $return[] = $parseAnnotation($httpMethod, $annotation);
                //break;
            }
        }

        return count($return) ? $return : false;
    }
}
