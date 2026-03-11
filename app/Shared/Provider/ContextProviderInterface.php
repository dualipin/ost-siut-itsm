<?php

namespace App\Shared\Provider;

/**
 * @template T
 */
interface ContextProviderInterface
{
    /**
     * @return T|null
     */
    public function get();
}
