<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Entity;

use App\Modules\Transparency\Domain\Enum\AttachmentType;

final readonly class TransparencyAttachment
{
    public function __construct(
        public ?int $id,
        public int $transparencyId,
        public string $filePath,
        public string $mimeType,
        public AttachmentType $attachmentType,
        public ?string $description
    ) {
    }
}
