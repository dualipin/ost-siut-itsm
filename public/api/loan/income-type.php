<?php


use App\Bootstrap;
use App\Http\Response\JsonResponse;
use App\Modules\Loan\Domain\Repository\IncomeTypeRepositoryInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();
$incomeTypeRepository = $container->get(IncomeTypeRepositoryInterface::class);

$incomeTypes = $incomeTypeRepository->getIncomeTypes();

$response = JsonResponse::ok($incomeTypes);

$response->send();