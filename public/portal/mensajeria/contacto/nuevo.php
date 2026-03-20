<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\CreateContactMessageUseCase;
use App\Modules\Messaging\Domain\Exception\ContactMessageValidationException;
use App\Shared\Context\UserProviderInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

$request = new FormRequest();

if ($request->isSubmitted()) {
    /** @var CreateContactMessageUseCase $useCase */
    $useCase = $container->get(CreateContactMessageUseCase::class);

    $asunto = $request->input("asunto");
    $mensaje = $request->input("mensaje");

    $attachments = [];
    if (isset($_FILES['adjuntos'])) {
        $files = $_FILES['adjuntos'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['name'][$i]) {
                $attachments[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
        }
    }

    try {
        $useCase->execute(
            name: $user->name,
            email: $user->email,
            phone: null,
            subject: is_string($asunto) ? $asunto : null,
            message: is_string($mensaje) ? $mensaje : "",
            attachments: $attachments,
            senderId: $user->id,
        );

        JsonResponse::created([
            "tipo" => true,
            "message" => "Tu mensaje ha sido enviado correctamente al sindicato.",
        ])->send();
        exit;
    } catch (ContactMessageValidationException $e) {
        JsonResponse::badRequest($e->getMessage())->send();
        exit;
    } catch (\Throwable $th) {
        JsonResponse::serverError("Error al enviar el mensaje. Inténtalo más tarde.")->send();
        exit;
    }
}

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . '/nuevo.latte', [
    'authUser' => $user,
]);
