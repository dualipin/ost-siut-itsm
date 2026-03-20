<?php

namespace App\Modules\Messaging;

use App\Modules\AbstractModule;
use App\Modules\Messaging\Application\UseCase\CreateContactMessageUseCase;
use App\Modules\Messaging\Application\UseCase\CreateQuestionUseCase;
use App\Modules\Messaging\Application\UseCase\GenerateThreadPdfUseCase;
use App\Modules\Messaging\Application\UseCase\GetThreadDetailUseCase;
use App\Modules\Messaging\Application\UseCase\ListPublicFAQUseCase;
use App\Modules\Messaging\Application\UseCase\ListThreadsByTypeUseCase;
use App\Modules\Messaging\Application\UseCase\ReplyToContactUseCase;
use App\Modules\Messaging\Application\UseCase\ReplyToQuestionUseCase;
use App\Modules\Messaging\Application\UseCase\ToggleThreadVisibilityUseCase;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Infrastructure\Persistence\PdoMessageAttachmentRepository;
use App\Modules\Messaging\Infrastructure\Persistence\PdoMessageRepository;
use App\Modules\Messaging\Infrastructure\Persistence\PdoMessageThreadRepository;
use App\Modules\Messaging\Infrastructure\Upload\MessageAttachmentUploader;

final class MessagingModule extends AbstractModule
{
	protected const array REPOSITORIES = [
		MessageRepositoryInterface::class => PdoMessageRepository::class,
		MessageThreadRepositoryInterface::class => PdoMessageThreadRepository::class,
		MessageAttachmentRepositoryInterface::class => PdoMessageAttachmentRepository::class,
	];

	protected const array SERVICES = [
		MessageAttachmentUploader::class,
	];

	protected const array USE_CASES = [
		CreateContactMessageUseCase::class,
		CreateQuestionUseCase::class,
		ListThreadsByTypeUseCase::class,
		GetThreadDetailUseCase::class,
		ReplyToContactUseCase::class,
		ReplyToQuestionUseCase::class,
		ToggleThreadVisibilityUseCase::class,
		ListPublicFAQUseCase::class,
		GenerateThreadPdfUseCase::class,
	];
}

