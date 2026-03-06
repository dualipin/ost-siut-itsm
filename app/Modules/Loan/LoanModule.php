<?php

namespace App\Modules\Loan;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

class LoanModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
