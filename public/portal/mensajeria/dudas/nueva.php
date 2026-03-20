<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\CreateQuestionUseCase;
use App\Modules\Messaging\Domain\Exception\QuestionValidationException;
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
    /** @var CreateQuestionUseCase $useCase */
    $useCase = $container->get(CreateQuestionUseCase::class);

    $mensaje = $request->input("mensaje");
    $adjuntosRaw = $_FILES['adjuntos'] ?? [];
    
    $attachments = [];
    if (!empty($adjuntosRaw['name'][0])) {
        foreach ($adjuntosRaw['name'] as $i => $name) {
            $attachments[] = [
                'name'     => $name,
                'tmp_name' => $adjuntosRaw['tmp_name'][$i],
                'size'     => $adjuntosRaw['size'][$i],
                'type'     => $adjuntosRaw['type'][$i],
                'error'    => $adjuntosRaw['error'][$i],
            ];
        }
    }

    try {
        $useCase->execute(
            name: $user->name,
            email: $user->email,
            question: is_string($mensaje) ? $mensaje : "",
            attachments: $attachments,
            senderId: $user->id,
        );

        JsonResponse::created([
            "tipo" => true,
            "message" => "Tu duda ha sido enviada correctamente. Recibirás una notificación cuando sea respondida.",
        ])->send();
        exit;
    } catch (QuestionValidationException $e) {
        JsonResponse::badRequest($e->getMessage())->send();
        exit;
    } catch (\Throwable $th) {
        JsonResponse::serverError("Error al enviar la duda. Inténtalo más tarde.")->send();
        exit;
    }
}

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . '/nueva.latte', [
    'authUser' => $user,
]);
