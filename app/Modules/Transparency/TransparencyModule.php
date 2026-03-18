<?php

declare(strict_types=1);

namespace App\Modules\Transparency;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Application\UseCase\AddAttachmentUseCase;
use App\Modules\Transparency\Application\UseCase\CreateTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\DeleteTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;
use App\Modules\Transparency\Application\UseCase\ManagePermissionsUseCase;
use App\Modules\Transparency\Application\UseCase\UpdateTransparencyUseCase;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Modules\Transparency\Infrastructure\Persistence\PdoTransparencyRepository;
use App\Modules\Transparency\Infrastructure\Storage\LocalAttachmentStorage;
use DI\ContainerBuilder;
use PDO;

use function DI\create;
use function DI\get;

final class TransparencyModule
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([
            TransparencyRepositoryInterface::class => create(PdoTransparencyRepository::class)
                ->constructor(get(PDO::class)),

            FileStorageInterface::class => create(LocalAttachmentStorage::class)
                ->constructor(
                    dirname(__DIR__, 3) . '/public/uploads/transparency',
                    dirname(__DIR__, 3) . '/uploads/transparency'
                ),

            CreateTransparencyUseCase::class => create()
                ->constructor(
                    get(TransparencyRepositoryInterface::class),
                    get(FileStorageInterface::class),
                    get(TransactionManager::class)
                ),

            UpdateTransparencyUseCase::class => create()
                ->constructor(
                    get(TransparencyRepositoryInterface::class),
                    get(TransactionManager::class)
                ),

            DeleteTransparencyUseCase::class => create()
                ->constructor(
                    get(TransparencyRepositoryInterface::class),
                    get(FileStorageInterface::class),
                    get(TransactionManager::class)
                ),

            AddAttachmentUseCase::class => create()
                ->constructor(
                    get(TransparencyRepositoryInterface::class),
                    get(FileStorageInterface::class),
                    get(TransactionManager::class)
                ),

            ManagePermissionsUseCase::class => create()
                ->constructor(
                    get(TransparencyRepositoryInterface::class),
                    get(TransactionManager::class)
                ),

            GetTransparencyUseCase::class => create()
                ->constructor(get(TransparencyRepositoryInterface::class)),

            ListTransparenciesUseCase::class => create()
                ->constructor(get(TransparencyRepositoryInterface::class)),
        ]);
    }
}
