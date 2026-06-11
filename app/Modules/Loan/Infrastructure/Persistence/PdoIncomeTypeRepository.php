<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\IncomeType;
use App\Modules\Loan\Domain\Repository\IncomeTypeRepositoryInterface;

class PdoIncomeTypeRepository extends PdoBaseRepository implements IncomeTypeRepositoryInterface
{

    public function getIncomeTypes(bool $all = false): array
    {
        $result = [];
        if ($all) {
            $result = $this->pdo->query("SELECT * FROM cat_income_types ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $result = $this->pdo->query(
                "SELECT * FROM cat_income_types WHERE active = 1 ORDER BY name"
            )->fetchAll(\PDO::FETCH_ASSOC);
        }

        if ($result) {
            $result = array_map(function ($row) {
                return new IncomeType(
                    id: (int)$row['income_type_id'],
                    name: $row['name'],
                    description: $row['description'],
                    isPeriodic: (bool)$row['is_periodic'],
                    isActive: (bool)$row['active'],
                    frequencyDays: $row['frequency_days'] !== null ? (int)$row['frequency_days'] : null,
                    paymentMonth: $row['tentative_payment_month'] !== null ? (int)$row['tentative_payment_month'] : null,
                    paymentDay: $row['tentative_payment_day'] !== null ? (int)$row['tentative_payment_day'] : null,
                );
            }, $result);
        }

        return $result;
    }
}