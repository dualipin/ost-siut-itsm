<?php

namespace App\Shared\Context;

namespace App\Shared\Context;

/**
 * @template T
 */
interface ContextInterface
{
    /**
     * @return T
     */
    public function get();

    /**
     * @param T $value
     */
    public function set($value): void;

    public function clear(): void;
}
