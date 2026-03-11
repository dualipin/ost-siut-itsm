<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\User\Application\DTO\UserSummary;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
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
    /**
     * @return UserSummary[]
     */
    public function listado(bool $onlyActive = false): array
    {
        $where = $onlyActive
            ? "WHERE delete_at IS NULL AND active = 1"
            : "WHERE delete_at IS NULL";

        $stmt = $this->pdo->query(
            "SELECT user_id, name, surnames, email, role, active, department
             FROM users
             {$where}
             ORDER BY surnames, name",
        );

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = new UserSummary(
                id: (int) $row["user_id"],
                name: (string) $row["name"],
                surnames: (string) $row["surnames"],
                email: (string) $row["email"],
                role: RoleEnum::tryFrom((string) $row["role"]) ?? RoleEnum::NoAgremiado,
                active: (bool) $row["active"],
                department: $row["department"] !== null ? (string) $row["department"] : null,
            );
        }

        return $result;
    }

    public function updateByAdmin(
        int $userId,
        string $name,
        string $surnames,
        string $email,
        RoleEnum $role,
        bool $active,
        ?string $curp,
        ?string $birthdate,
        ?string $address,
        ?string $phone,
        ?string $department,
        ?string $category,
        ?string $nss,
        float $salary,
        ?string $workStartDate,
    ): bool {
        $stmt = $this->pdo->prepare(
            "
            UPDATE users
            SET
                name            = :name,
                surnames        = :surnames,
                email           = :email,
                role            = :role,
                active          = :active,
                curp            = :curp,
                birthdate       = :birthdate,
                address         = :address,
                phone           = :phone,
                department      = :department,
                category        = :category,
                nss             = :nss,
                salary          = :salary,
                work_start_date = :work_start_date
            WHERE user_id = :user_id AND delete_at IS NULL
            LIMIT 1
            ",
        );

        return $stmt->execute([
            "name"           => $name,
            "surnames"       => $surnames,
            "email"          => $email,
            "role"           => $role->value,
            "active"         => (int) $active,
            "curp"           => $curp,
            "birthdate"      => $birthdate,
            "address"        => $address,
            "phone"          => $phone,
            "department"     => $department,
            "category"       => $category,
            "nss"            => $nss,
            "salary"         => $salary,
            "work_start_date" => $workStartDate,
            "user_id"        => $userId,
        ]);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET delete_at = NOW() WHERE user_id = :id AND delete_at IS NULL LIMIT 1",
        );

        $stmt->execute(["id" => $id]);

        return $stmt->rowCount() === 1;
    }

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

    public function updateProfile(
        int $userId,
        ?string $address,
        ?string $phone,
        ?string $email,
        ?string $photoPath,
        ?string $curp,
    ): bool {
        $stmt = $this->pdo->prepare(
            "
            UPDATE users
            SET
                address = :address,
                phone = :phone,
                email = :email,
                curp = :curp,
                photo = COALESCE(:photo, photo)
            WHERE user_id = :user_id AND delete_at IS NULL
            LIMIT 1
            ",
        );

        return $stmt->execute([
            "address" => $address,
            "phone" => $phone,
            "email" => $email,
            "curp" => $curp,
            "photo" => $photoPath,
            "user_id" => $userId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function findDocumentsByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT document_type, file_path
            FROM user_documents
            WHERE user_id = :user_id
            ORDER BY updated_at DESC
            ",
        );

        $stmt->execute(["user_id" => $userId]);

        $documents = [];

        while ($row = $stmt->fetch()) {
            $documentType = (string) ($row["document_type"] ?? "");
            $filePath = (string) ($row["file_path"] ?? "");

            if ($documentType === "" || $filePath === "") {
                continue;
            }

            if (!isset($documents[$documentType])) {
                $documents[$documentType] = $filePath;
            }
        }

        return $documents;
    }

    public function upsertDocument(
        int $userId,
        DocumentTypeEnum $documentType,
        string $filePath,
    ): bool {
        $findStmt = $this->pdo->prepare(
            "
            SELECT document_id
            FROM user_documents
            WHERE user_id = :user_id AND document_type = :document_type
            ORDER BY updated_at DESC
            LIMIT 1
            ",
        );

        $findStmt->execute([
            "user_id" => $userId,
            "document_type" => $documentType->value,
        ]);

        $row = $findStmt->fetch();

        if ($row) {
            $stmt = $this->pdo->prepare(
                "
                UPDATE user_documents
                SET file_path = :file_path, status = 'pendiente', observation = NULL
                WHERE document_id = :document_id
                ",
            );

            return $stmt->execute([
                "file_path" => $filePath,
                "document_id" => (int) $row["document_id"],
            ]);
        }

        $stmt = $this->pdo->prepare(
            "
            INSERT INTO user_documents (user_id, document_type, file_path, status)
            VALUES (:user_id, :document_type, :file_path, 'pendiente')
            ",
        );

        return $stmt->execute([
            "user_id" => $userId,
            "document_type" => $documentType->value,
            "file_path" => $filePath,
        ]);
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
