<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Modules\User\Domain\ValueObject\BankingData;
use App\Modules\User\Domain\ValueObject\PersonalInfo;
use App\Modules\User\Domain\ValueObject\WorkData;
use App\Shared\Domain\Enum\RoleEnum;
use DateTimeImmutable;
use Exception;

final class PdoUserRepository extends PdoBaseRepository implements
    UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE user_id = :id AND delete_at IS NULL LIMIT 1",
        );

        $stmt->execute(["id" => $id]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapUser($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE email = :email AND delete_at IS NULL LIMIT 1",
        );

        $stmt->execute(["email" => $email]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapUser($row);
    }

    public function save(User $user, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO users (
                email,
                password_hash,
                role,
                active,
                curp,
                name,
                surnames,
                birthdate,
                address,
                phone,
                photo,
                bank_name,
                interbank_code,
                bank_account,
                category,
                department,
                nss,
                salary,
                work_start_date
            ) VALUES (
                :email,
                :password_hash,
                :role,
                :active,
                :curp,
                :name,
                :surnames,
                :birthdate,
                :address,
                :phone,
                :photo,
                :bank_name,
                :interbank_code,
                :bank_account,
                :category,
                :department,
                :nss,
                :salary,
                :work_start_date
            )
            ON DUPLICATE KEY UPDATE user_id = user_id
            ",
        );

        $stmt->execute([
            "email" => $user->email,
            "password_hash" => $passwordHash,
            "role" => $user->role->value,
            "active" => (int) $user->active,
            "curp" => $user->personalInfo->curp,
            "name" => $user->personalInfo->name,
            "surnames" => $user->personalInfo->surnames,
            "birthdate" => $user->personalInfo->birthdate?->format("Y-m-d"),
            "address" => $user->personalInfo->address,
            "phone" => $user->personalInfo->phone,
            "photo" => $user->personalInfo->photo,
            "bank_name" => $user->bankingData->bankName,
            "interbank_code" => $user->bankingData->interbankCode,
            "bank_account" => $user->bankingData->bankAccount,
            "category" => $user->workData->category,
            "department" => $user->workData->department,
            "nss" => $user->workData->nss,
            "salary" => $user->workData->salary,
            "work_start_date" => $user->workData->workStartDate?->format(
                "Y-m-d",
            ),
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapUser(array $row): User
    {
        return new User(
            id: (int) $row["user_id"],
            email: (string) $row["email"],
            role: RoleEnum::tryFrom((string) $row["role"]) ??
                RoleEnum::NoAgremiado,
            active: (bool) $row["active"],
            personalInfo: new PersonalInfo(
                name: (string) $row["name"],
                surnames: (string) $row["surnames"],
                curp: $row["curp"],
                birthdate: $this->parseDate($row["birthdate"]),
                address: $row["address"],
                phone: $row["phone"],
                photo: $row["photo"],
            ),
            bankingData: new BankingData(
                bankName: $row["bank_name"],
                interbankCode: $row["interbank_code"],
                bankAccount: $row["bank_account"],
            ),
            workData: new WorkData(
                category: $row["category"],
                department: $row["department"],
                nss: $row["nss"],
                salary: (float) $row["salary"],
                workStartDate: $this->parseDate($row["work_start_date"]),
            ),
            lastEntry: $this->parseDateTime($row["last_entry"]),
            createdAt: $this->parseDateTime($row["created_at"]),
            updatedAt: $this->parseDateTime($row["update_at"]),
        );
    }

    private function parseDate(?string $date): ?DateTimeImmutable
    {
        if (!$date) {
            return null;
        }

        $parsedDate = DateTimeImmutable::createFromFormat("Y-m-d", $date);

        return $parsedDate ?: null;
    }

    private function parseDateTime(?string $dateTime): ?DateTimeImmutable
    {
        if (!$dateTime) {
            return null;
        }

        try {
            return new DateTimeImmutable($dateTime);
        } catch (Exception) {
            return null;
        }
    }
}
