<?php

namespace App\Shared\Provider;

/**
 * @template T
 * @implements ContextProviderInterface<T>
 */
abstract class AbstractContextProvider implements ContextProviderInterface
{
    private bool $resolved = false;

    /**
     * @var T|null
     */
    private $cached = null;

    /**
     * @return T|null
     */
    final public function get()
    {
        if (!$this->resolved) {
            $this->cached = $this->resolve();
            $this->resolved = true;
        }

        return $this->cached;
    }

    /**
     * @return T|null
     */
    abstract protected function resolve();
}
