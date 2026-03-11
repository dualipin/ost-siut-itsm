<?php

namespace App\Modules\Publication\Domain\Entity;

use App\Modules\Publication\Domain\Enum\PublicationAttachmentTypeEnum;

final readonly class PublicationAttachment
{
    public function __construct(
        public ?int $id,
        public int $publicationId,
        public string $filePath,
        public string $mimeType,
        public PublicationAttachmentTypeEnum $type,
        public ?string $description = null,
    ) {}
}
