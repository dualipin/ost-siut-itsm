<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\Service;

class LoanSimulatorService
{
    public function __construct() {}

    /**
     * @param array $data
     * @return array
     */
    public function simulate(array $data): array
    {
        // Simulate loan calculation logic here
        // For demonstration, we will return the input data with a simulated result

        $simulatedResult = [
            "monthlyPayment" => 500, // This is a placeholder value
            "totalPayment" => 6000, // This is a placeholder value
            "totalInterest" => 1000, // This is a placeholder value
        ];

        return array_merge($data, $simulatedResult);
    }
}
