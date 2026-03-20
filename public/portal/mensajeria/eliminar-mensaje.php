<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Modules\Messaging\Application\UseCase\DeleteMessageUseCase;
use App\Shared\Context\UserProviderInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

$request = new FormRequest();

if ($request->isSubmitted()) {
    /** @var DeleteMessageUseCase $useCase */
    $useCase = $container->get(DeleteMessageUseCase::class);

    $messageId = (int) $request->input("message_id");

    try {
        $useCase->execute($messageId, $user->id);

        JsonResponse::ok([
            "success" => true,
            "message" => "Mensaje eliminado correctamente.",
        ])->send();
        exit;
    } catch (\Throwable $th) {
        JsonResponse::badRequest($th->getMessage())->send();
        exit;
    }
}

JsonResponse::badRequest("Solicitud inválida.")->send();
exit;
