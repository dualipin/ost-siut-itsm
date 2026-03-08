<?php

namespace App\Shared\Context;

/**
 * @template T
 */
interface ContextInterface
{
    /**
     * @return T|null
     */
    public function get();

    public function clear(): void;
}
