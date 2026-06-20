<?php

namespace App\Http\Actions\Loan;

use App\Http\Actions\Action;
use App\Modules\Loan\Application\UseCase\ListIncomeTypesUseCase;
use App\Modules\Loan\Domain\Repository\IncomeTypeRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ListIncomeTypesAction extends Action
{
    public function __construct(LoggerInterface $logger, private ListIncomeTypesUseCase $listIncomeTypesUseCase)
    {
        parent::__construct($logger);
    }
    public function action(): ResponseInterface
    {
        $incomeTypes = $this->listIncomeTypesUseCase->execute();
        return $this->respondWithData($incomeTypes);
    }
}
