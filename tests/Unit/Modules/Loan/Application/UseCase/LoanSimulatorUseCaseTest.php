<?php

declare(strict_types=1);

use App\Modules\Loan\Application\UseCase\LoanSimulatorUseCase;
use App\Modules\Loan\Application\UseCase\ListIncomeTypesUseCase;
use App\Modules\Loan\Domain\DTO\LoanSimulationDTO;
use App\Modules\Loan\Domain\DTO\LoanSimulationDiscountDTO;
use App\Modules\Loan\Domain\Entity\IncomeType;

it('successfully runs loan simulation calculation', function (): void {
    $incomeType1 = new IncomeType(
        id: 1,
        name: 'Periodic Income',
        description: 'Periodic Income Desc',
        isPeriodic: true,
        isActive: true,
        frequencyDays: 15,
        paymentMonth: null,
        paymentDay: null,
    );

    $incomeType2 = new IncomeType(
        id: 2,
        name: 'Another Income',
        description: 'Another Income Desc',
        isPeriodic: false,
        isActive: true,
        frequencyDays: null,
        paymentMonth: 12,
        paymentDay: 31,
    );

    $listIncomeTypesMock = $this->createMock(ListIncomeTypesUseCase::class);
    $listIncomeTypesMock->expects($this->once())
        ->method('execute')
        ->willReturn([$incomeType1, $incomeType2]);

    $useCase = new LoanSimulatorUseCase($listIncomeTypesMock);

    // Simulation request with valid IDs (1, 2) and an invalid ID (99)
    $dto = new LoanSimulationDTO(
        montoPrestamo: 10000.0,
        fechaOtorgamiento: '2026-06-20',
        mesesPagar: 6,
        diasAdicionales: 0,
        tasaInteres: 6.0,
        descuentos: [
            new LoanSimulationDiscountDTO(monto: 5000.0, tipoId: 1, cantidad: 4, fechaPago: '2026-07-15'),
            new LoanSimulationDiscountDTO(monto: 5000.0, tipoId: 2, cantidad: 1),
            new LoanSimulationDiscountDTO(monto: 1000.0, tipoId: 99, cantidad: 1), // invalid
        ],
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeArray()
        ->and($result['montoPrestamo'])->toBe(10000.0)
        ->and($result['tasaInteresMensual'])->toBe(6.0)
        ->and($result['formasPago'])->toBeArray();
});


