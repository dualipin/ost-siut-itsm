<?php

declare(strict_types=1);

namespace App\Modules\User\Application\UseCase;

use App\Modules\User\Application\DTO\CreateUser;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Modules\User\Domain\ValueObject\BankingData;
use App\Modules\User\Domain\ValueObject\PersonalInfo;
use App\Modules\User\Domain\ValueObject\WorkData;
use RuntimeException;

final readonly class CreateUserUseCase
{
    public function __construct(private UserRepositoryInterface $userRepository) {}

    public function execute(CreateUser $dto): bool
    {
        $passwordHash = password_hash($dto->password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException("No se pudo generar el hash de la contrasena del usuario.");
        }

        $user = new User(
            id: 0,
            email: $dto->email,
            role: $dto->role,
            active: $dto->active,
            personalInfo: new PersonalInfo(
                name: $dto->name,
                surnames: $dto->surnames,
                curp: $dto->curp,
                birthdate: $dto->birthdate,
                address: $dto->address,
                phone: $dto->phone,
                photo: $dto->photo,
            ),
            bankingData: new BankingData(
                bankName: $dto->bankName,
                interbankCode: $dto->interbankCode,
                bankAccount: $dto->bankAccount,
            ),
            workData: new WorkData(
                category: $dto->category,
                department: $dto->department,
                nss: $dto->nss,
                salary: $dto->salary,
                workStartDate: $dto->workStartDate,
            ),
        );

        return $this->userRepository->save($user, $passwordHash);
    }
}
