<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Application\DTO\CreateUser;
use App\Modules\User\Application\UseCase\CreateUserUseCase;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $surnames = trim((string) ($_POST['surnames'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $roleValue = trim((string) ($_POST['role'] ?? ''));
    $curp = trim((string) ($_POST['curp'] ?? '')) ?: null;
    $nss = trim((string) ($_POST['nss'] ?? '')) ?: null;
    $birthdateRaw = trim((string) ($_POST['birthdate'] ?? '')) ?: null;
    $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
    $address = trim((string) ($_POST['address'] ?? '')) ?: null;
    $department = trim((string) ($_POST['department'] ?? '')) ?: null;
    $category = trim((string) ($_POST['category'] ?? '')) ?: null;
    $salary = (float) ($_POST['salary'] ?? 0);
    $workStartDateRaw = trim((string) ($_POST['work_start_date'] ?? '')) ?: null;

    if ($name === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if ($surnames === '') {
        $errors[] = 'Los apellidos son obligatorios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo no es válido.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Las contraseñas no coinciden.';
    }
    $role = RoleEnum::tryFrom($roleValue);
    if ($role === null) {
        $errors[] = 'El rol seleccionado no es válido.';
    }

    if (empty($errors)) {
        $birthdate = $birthdateRaw !== null
            ? DateTimeImmutable::createFromFormat('Y-m-d', $birthdateRaw) ?: null
            : null;
        $workStartDate = $workStartDateRaw !== null
            ? DateTimeImmutable::createFromFormat('Y-m-d', $workStartDateRaw) ?: null
            : null;

        $useCase = $container->get(CreateUserUseCase::class);
        $useCase->execute(new CreateUser(
            email: $email,
            password: $password,
            name: $name,
            surnames: $surnames,
            role: $role,
            active: true,
            curp: $curp,
            birthdate: $birthdate,
            address: $address,
            phone: $phone,
            department: $department,
            category: $category,
            nss: $nss,
            salary: $salary,
            workStartDate: $workStartDate,
        ));

        header('Location: ./listado.php?created=1');
        exit;
    }
}

$container->get(RendererInterface::class)->render('./registrar.latte', [
    'roles' => RoleEnum::cases(),
    'errors' => $errors,
    'old' => $_POST,
]);
