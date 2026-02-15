<?php

namespace App\Infrastructure\Persistence;

use PDO;

abstract class BaseRepository
{
    public function __construct(protected PDO $pdo) {}
}
