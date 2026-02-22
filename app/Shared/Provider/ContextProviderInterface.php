<?php

namespace App\Shared\Provider;

/**
 * @template T
 */
interface ContextProviderInterface
{
    /**
     * @return T
     */
    public function get();
}
