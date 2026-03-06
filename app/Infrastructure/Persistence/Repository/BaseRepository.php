<?php

namespace App\Infrastructure\Persistence\Repository;

use PDO;

abstract class BaseRepository
{
    public function __construct(protected PDO $pdo) {}
}
