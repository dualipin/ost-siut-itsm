<?php

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\CredentialCardHelper;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$userRepository = $container->get(UserRepositoryInterface::class);
$renderer = $container->get(RendererInterface::class);
$appConfig = $container->get(AppConfig::class);

$uid = filter_input(INPUT_GET, 'uid', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';

$statusCode = 200;
$result = [
    'isValid' => false,
    'message' => 'No fue posible validar la credencial.',
    'holderName' => null,
    'holderRole' => null,
    'vigencia' => 'NO VIGENTE',
    'issuedBy' => 'SUTITSM',
];

if ($uid === false || $uid === null || $token === '') {
    $statusCode = 400;
    $result['message'] = 'El enlace de validacion es invalido o incompleto.';
} else {
    $user = $userRepository->findById($uid);

    if ($user === null) {
        $statusCode = 404;
        $result['message'] = 'No existe un registro asociado a esta credencial.';
    } else {
        $expectedToken = CredentialCardHelper::buildValidationToken($user);

        if (!hash_equals($expectedToken, $token)) {
            $statusCode = 403;
            $result['message'] = 'La firma de validacion de la credencial no es valida.';
        } else {
            $isActiveMember = $user->active && $user->role !== RoleEnum::NoAgremiado;

            $result = [
                'isValid' => true,
                'message' => $isActiveMember
                    ? 'La credencial esta vigente y el titular pertenece al padron activo.'
                    : 'La credencial corresponde a un registro no vigente.',
                'holderName' => trim($user->personalInfo->name . ' ' . $user->personalInfo->surnames),
                'holderRole' => $user->workData->category,
                'vigencia' => CredentialCardHelper::resolveVigencia($user),
                'issuedBy' => 'Sindicato Unico de Trabajadores del Instituto Tecnologico Superior de Macuspana',
            ];
        }
    }
}

http_response_code($statusCode);

$renderer->render(__DIR__ . '/validar-credencial.latte', [
    'result' => $result,
    'siteUrl' => rtrim($appConfig->baseUrl, '/') !== ''
        ? rtrim($appConfig->baseUrl, '/')
        : 'https://siutitsm.com.mx',
]);
