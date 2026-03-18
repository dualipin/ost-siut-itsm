<?php

use App\Bootstrap;
use App\Modules\Transparency\Application\UseCase\DeleteTransparencyUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && reset($_POST)) {
    $useCase = $container->get(DeleteTransparencyUseCase::class);
    $id = (int) ($_POST['id'] ?? 0);

    try {
        $useCase->execute($id);
        header("Location: ./listado.php?deleted=1");
        exit;
    } catch (TransparencyNotFoundException $e) {
        header("Location: ./listado.php?error=notfound");
        exit;
    } catch (Exception $e) {
        header("Location: ./listado.php?error=1");
        exit;
    }
}

header("Location: ./listado.php");
exit;
