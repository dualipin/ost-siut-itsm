<?php

namespace App\Infrastructure\Persistence\Repository;

use PDO;

abstract class PdoBaseRepository
{
    public function __construct(protected PDO $pdo) {}
}
