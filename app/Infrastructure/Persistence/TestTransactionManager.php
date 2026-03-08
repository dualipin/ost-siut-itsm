<?php

namespace App\Infrastructure\Persistence;

final class TestTransactionManager implements TransactionManager
{
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollBack(): void {}

    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}
