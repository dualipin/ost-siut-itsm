<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    header('Location: ./listado.php');
    exit;
}

$userRepository = $container->get(UserRepositoryInterface::class);
$user = $userRepository->findById($id);

if ($user === null) {
    header('Location: ./listado.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $surnames = trim((string) ($_POST['surnames'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $roleValue = trim((string) ($_POST['role'] ?? ''));
    $active = (bool) ($_POST['active'] ?? false);
    $curp = trim((string) ($_POST['curp'] ?? '')) ?: null;
    $birthdate = trim((string) ($_POST['birthdate'] ?? '')) ?: null;
    $address = trim((string) ($_POST['address'] ?? '')) ?: null;
    $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
    $department = trim((string) ($_POST['department'] ?? '')) ?: null;
    $category = trim((string) ($_POST['category'] ?? '')) ?: null;
    $nss = trim((string) ($_POST['nss'] ?? '')) ?: null;
    $salary = (float) ($_POST['salary'] ?? 0);
    $workStartDate = trim((string) ($_POST['work_start_date'] ?? '')) ?: null;

    if ($name === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if ($surnames === '') {
        $errors[] = 'Los apellidos son obligatorios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo no es válido.';
    }
    $role = RoleEnum::tryFrom($roleValue);
    if ($role === null) {
        $errors[] = 'El rol seleccionado no es válido.';
    }

    if (empty($errors)) {
        $userRepository->updateByAdmin(
            userId: $id,
            name: $name,
            surnames: $surnames,
            email: $email,
            role: $role,
            active: $active,
            curp: $curp,
            birthdate: $birthdate,
            address: $address,
            phone: $phone,
            department: $department,
            category: $category,
            nss: $nss,
            salary: $salary,
            workStartDate: $workStartDate,
        );

        header('Location: ./detalle.php?id=' . $id . '&updated=1');
        exit;
    }
}

$container->get(RendererInterface::class)->render('./editar.latte', [
    'user' => $user,
    'roles' => RoleEnum::cases(),
    'errors' => $errors,
]);