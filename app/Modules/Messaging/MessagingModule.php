<?php

namespace App\Modules\Messaging;

use App\Modules\AbstractModule;
use App\Modules\Messaging\Application\UseCase\CreateContactMessageUseCase;
use App\Modules\Messaging\Application\UseCase\CreateQuestionUseCase;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use App\Modules\Messaging\Infrastructure\Persistence\PdoMessageRepository;
use App\Modules\Messaging\Infrastructure\Persistence\PdoMessageThreadRepository;

final class MessagingModule extends AbstractModule
{
	protected const array REPOSITORIES = [
		MessageRepositoryInterface::class => PdoMessageRepository::class,
		MessageThreadRepositoryInterface::class => PdoMessageThreadRepository::class,
	];

	protected const array USE_CASES = [
		CreateContactMessageUseCase::class,
		CreateQuestionUseCase::class,
	];
}
