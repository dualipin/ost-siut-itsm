<?php

namespace App\Modules\Auth\Domain\Repository;

interface PasswordRecoveryInterface
{
	public function storeMagicLink(string $email, string $token): void;

	public function findEmailByValidToken(
		string $token,
		int $ttlMinutes,
	): ?string;

	public function consumeToken(string $token): void;
}
