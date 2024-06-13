<?php

namespace ProAI\Annotations\Filesystem;

use Symfony\Component\Finder\Finder;

class ClassFinder
{
    /**
     * Find all the class and interface names in a given directory.
     */
    public function findClasses(string $directory): array
    {
        $classes = [];
        foreach (Finder::create()->in($directory)->name('*.php') as $file) {
            $classes[] = $this->findClass($file->getRealPath());
        }

        return array_filter($classes);
    }

    /**
     * Extract the class name from the file at the given path.
     */
    public function findClass(string $path): ?string
    {
        $namespace = null;
        $tokens = token_get_all(file_get_contents($path));
        foreach ($tokens as $key => $token) {
            if ($this->tokenIsNamespace($token)) {
                $namespace = $this->getNamespace($key + 2, $tokens);
            } elseif ($this->tokenIsClassOrInterface($token) && !$this->tokenIsDoubleColon($tokens[$key-1])) {
                // We want to notice the class/interface DEFINITION, we don't want to be distracted by Classname::class in a PHP Attribute.
                return ltrim($namespace . '\\' . $this->getClass($key + 2, $tokens), '\\');
            }
        }

        return null;
    }

    /**
     * Find the namespace in the tokens starting at a given key.
     */
    protected function getNamespace(int $key, array $tokens): ?string
    {
        $namespace = null;
        $tokenCount = count($tokens);

        for ($i = $key; $i < $tokenCount; $i++) {
            if ($this->isPartOfNamespace($tokens[$i])) {
                $namespace .= $tokens[$i][1];
            } elseif ($tokens[$i] == ';') {
                return $namespace;
            }
        }

        return null;
    }

    /**
     * Find the class in the tokens starting at a given key.
     */
    protected function getClass(int $key, array $tokens): ?string
    {
        $class = null;
        $tokenCount = count($tokens);

        for ($i = $key; $i < $tokenCount; $i++) {
            if ($this->isPartOfClass($tokens[$i])) {
                $class .= $tokens[$i][1];
            } elseif ($this->isWhitespace($tokens[$i])) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Determine if the given token is a namespace keyword.
     */
    protected function tokenIsNamespace(array|string $token): bool
    {
        return is_array($token) && $token[0] == T_NAMESPACE;
    }

    /**
     * Determine if the given token is a class or interface keyword.
     */
    protected function tokenIsClassOrInterface(array|string $token): bool
    {
        return is_array($token) && ($token[0] == T_CLASS || $token[0] == T_INTERFACE);
    }

    /**
     * Determine if the given token is a double-colon separator between Class & static var/constant/method.
     *
     * @param  array|string  $token
     * @return bool
     */
    protected function tokenIsDoubleColon($token)
    {
        return is_array($token) && T_DOUBLE_COLON == $token[0];
    }
    /**
     * Determine if the given token is part of the namespace.
     */
    protected function isPartOfNamespace(array|string $token): bool
    {
        /** @see https://www.php.net/manual/en/migration80.incompatible.php#migration80.incompatible.tokenizer */
        return is_array($token) && ($token[0] == T_NAME_QUALIFIED || $token[0] == T_NS_SEPARATOR);
    }

    /**
     * Determine if the given token is part of the class.
     */
    protected function isPartOfClass(array|string $token): bool
    {
        return is_array($token) && $token[0] == T_STRING;
    }

    /**
     * Determine if the given token is whitespace.
     */
    protected function isWhitespace(array|string $token): bool
    {
        return is_array($token) && $token[0] == T_WHITESPACE;
    }
}
