<?php

namespace App\Infrastructure\Persistence;

interface TransactionManager
{
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function transactional(callable $callback): mixed;
}
