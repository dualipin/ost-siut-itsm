<?php

namespace App\Modules\Auth\Domain\Exception;

use Exception;

class TooManyAttemptsException extends Exception
{
    public function __construct()
    {
        parent::__construct("Demasiados intentos fallidos. Intenta más tarde");
    }
}
