<?php

namespace ProAI\Annotations\Annotations;

/**
 * @SuppressWarnings(PHPMD.ShortVariableName)
 */
abstract class HttpMethod
{
    public string $value;

    public string $as;

    /**
     * @var mixed
     */
    public $middleware;
}
