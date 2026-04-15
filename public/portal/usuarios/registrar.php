<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\RedirectResponse;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Application\DTO\CreateUser;
use App\Modules\User\Application\UseCase\CreateUserUseCase;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Enum\RoleEnum;
use Psr\Container\ContainerInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

authorize($container);

$request = new FormRequest();
$renderer = $container->get(RendererInterface::class);
$errors = [];
$old = [];

if ($request->isSubmitted()) {
    $formData = mapRegisterUserFormData($request);
    $old = extractOldInput($request);
    $errors = validateRegisterUserFormData($container, $formData);

    if ($errors === []) {
        $errors = createUser($container, $formData);
    }
}

renderRegisterUserPage($renderer, $errors, $old);

function authorize(ContainerInterface $container): void
{
    $runner = $container->get(MiddlewareRunner::class);
    $middleware = $container->get(MiddlewareFactory::class);

    $runner->runOrRedirect($middleware->auth());
}

/**
 * @return array{
 *     name: string,
 *     surnames: string,
 *     email: string,
 *     password: string,
 *     password_confirm: string,
 *     role: string,
 *     curp: ?string,
 *     nss: ?string,
 *     birthdate: ?string,
 *     phone: ?string,
 *     address: ?string,
 *     department: ?string,
 *     category: ?string,
 *     salary: float,
 *     work_start_date: ?string
 * }
 */
function mapRegisterUserFormData(FormRequest $request): array
{
    return [
        'name' => (string) $request->input('name', ''),
        'surnames' => (string) $request->input('surnames', ''),
        'email' => (string) $request->input('email', ''),
        'password' => (string) $request->input('password', ''),
        'password_confirm' => (string) $request->input('password_confirm', ''),
        'role' => (string) $request->input('role', ''),
        'curp' => nullableInput($request, 'curp'),
        'nss' => normalizeNssValue(nullableInput($request, 'nss')),
        'birthdate' => nullableInput($request, 'birthdate'),
        'phone' => normalizePhoneValue(nullableInput($request, 'phone')),
        'address' => nullableInput($request, 'address'),
        'department' => nullableInput($request, 'department'),
        'category' => nullableInput($request, 'category'),
        'salary' => $request->float('salary', 0.0),
        'work_start_date' => nullableInput($request, 'work_start_date'),
    ];
}

function nullableInput(FormRequest $request, string $key): ?string
{
    $value = (string) $request->input($key, '');

    return $value !== '' ? $value : null;
}

function normalizePhoneValue(?string $value): ?string
{
    return normalizeDigitsValue($value);
}

function normalizeNssValue(?string $value): ?string
{
    return normalizeDigitsValue($value);
}

function normalizeDigitsValue(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $value);

    if ($digits === null || $digits === '') {
        return null;
    }

    return $digits;
}

/**
 * @param array{
 *     name: string,
 *     surnames: string,
 *     email: string,
 *     password: string,
 *     password_confirm: string,
 *     role: string,
 *     curp: ?string,
 *     nss: ?string,
 *     birthdate: ?string,
 *     phone: ?string,
 *     address: ?string,
 *     department: ?string,
 *     category: ?string,
 *     salary: float,
 *     work_start_date: ?string
 * } $formData
 * @return list<string>
 */
function validateRegisterUserFormData(ContainerInterface $container, array $formData): array
{
    $errors = [];

    if ($formData['name'] === '') {
        $errors[] = 'El nombre es obligatorio.';
    }

    if ($formData['surnames'] === '') {
        $errors[] = 'Los apellidos son obligatorios.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo no es válido.';
    }

    if (strlen($formData['password']) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if ($formData['password'] !== $formData['password_confirm']) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (RoleEnum::tryFrom($formData['role']) === null) {
        $errors[] = 'El rol seleccionado no es válido.';
    }

    if (!isValidDateValue($formData['birthdate'])) {
        $errors[] = 'La fecha de nacimiento no es válida.';
    }

    if (!isValidDateValue($formData['work_start_date'])) {
        $errors[] = 'La fecha de ingreso no es válida.';
    }

    if ($formData['salary'] < 0) {
        $errors[] = 'El salario no puede ser negativo.';
    }

    if (!isValidPhoneValue($formData['phone'])) {
        $errors[] = 'El telefono debe tener 10 digitos.';
    }

    if (!isValidNssValue($formData['nss'])) {
        $errors[] = 'El NSS debe tener 11 digitos.';
    }

    if (
        filter_var($formData['email'], FILTER_VALIDATE_EMAIL)
        && userEmailAlreadyExists($container, $formData['email'])
    ) {
        $errors[] = 'Ya existe un usuario registrado con ese correo.';
    }

    return $errors;
}

function isValidPhoneValue(?string $value): bool
{
    if ($value === null) {
        return true;
    }

    return preg_match('/^\d{10}$/', $value) === 1;
}

function isValidNssValue(?string $value): bool
{
    if ($value === null) {
        return true;
    }

    return preg_match('/^\d{11}$/', $value) === 1;
}

function isValidDateValue(?string $value): bool
{
    if ($value === null) {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function userEmailAlreadyExists(ContainerInterface $container, string $email): bool
{
    $userRepository = $container->get(UserRepositoryInterface::class);

    return $userRepository->findByEmail($email) !== null;
}

/**
 * @param array{
 *     name: string,
 *     surnames: string,
 *     email: string,
 *     password: string,
 *     password_confirm: string,
 *     role: string,
 *     curp: ?string,
 *     nss: ?string,
 *     birthdate: ?string,
 *     phone: ?string,
 *     address: ?string,
 *     department: ?string,
 *     category: ?string,
 *     salary: float,
 *     work_start_date: ?string
 * } $formData
 * @return list<string>
 */
function createUser(ContainerInterface $container, array $formData): array
{
    try {
        $wasCreated = $container->get(CreateUserUseCase::class)->execute(
            createUserDtoFromFormData($formData),
        );
    } catch (RuntimeException) {
        return ['No fue posible registrar el usuario.'];
    }

    if (!$wasCreated) {
        return ['No fue posible registrar el usuario.'];
    }

    redirectToUserList();
}

/**
 * @param array{
 *     name: string,
 *     surnames: string,
 *     email: string,
 *     password: string,
 *     password_confirm: string,
 *     role: string,
 *     curp: ?string,
 *     nss: ?string,
 *     birthdate: ?string,
 *     phone: ?string,
 *     address: ?string,
 *     department: ?string,
 *     category: ?string,
 *     salary: float,
 *     work_start_date: ?string
 * } $formData
 */
function createUserDtoFromFormData(array $formData): CreateUser
{
    return new CreateUser(
        email: $formData['email'],
        password: $formData['password'],
        name: $formData['name'],
        surnames: $formData['surnames'],
        role: RoleEnum::from($formData['role']),
        active: true,
        curp: $formData['curp'],
        birthdate: parseDateValue($formData['birthdate']),
        address: $formData['address'],
        phone: $formData['phone'],
        department: $formData['department'],
        category: $formData['category'],
        nss: $formData['nss'],
        salary: $formData['salary'],
        workStartDate: parseDateValue($formData['work_start_date']),
    );
}

function parseDateValue(?string $value): ?DateTimeImmutable
{
    if ($value === null) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date === false ? null : $date;
}

/**
 * @return array<string, mixed>
 */
function extractOldInput(FormRequest $request): array
{
    return $request->only(
        'name',
        'surnames',
        'email',
        'role',
        'curp',
        'nss',
        'birthdate',
        'phone',
        'address',
        'department',
        'category',
        'salary',
        'work_start_date',
    );
}

/**
 * @param list<string> $errors
 * @param array<string, mixed> $old
 */
function renderRegisterUserPage(RendererInterface $renderer, array $errors, array $old): void
{
    $renderer->render(__DIR__ . '/registrar.latte', [
        'roles' => RoleEnum::cases(),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function redirectToUserList(): never
{
    (new RedirectResponse('./listado.php?created=1'))->send();

    throw new RuntimeException('Unreachable redirect flow.');
}
