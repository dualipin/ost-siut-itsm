<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

final readonly class DeleteTransparencyUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private FileStorageInterface $fileStorage,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(int $id): void
    {
        $transparency = $this->repository->findById($id);
        
        if ($transparency === null) {
            throw TransparencyNotFoundException::withId($id);
        }

        $this->transactionManager->transactional(function () use ($id, $transparency) {
            $attachments = $this->repository->findAttachmentsByTransparencyId($id);
            
            // Primero se borra de la BD
            $this->repository->delete($id);

            // Luego borramos los archivos físicos sabiendo que ya no pertenecen en SQL
            foreach ($attachments as $attachment) {
                $this->fileStorage->delete($attachment->filePath, $transparency->isPrivate);
            }
        });
    }
}
