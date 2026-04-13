<?php

namespace App\Modules\Loan\Application\Service;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Folio;
use DateTimeImmutable;

final readonly class FolioGenerator
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository
    ) {}

    /**
     * Generate next folio in format SIN-YYYY-NNN
     */
    public function generate(?DateTimeImmutable $date = null): Folio
    {
        $date = $date ?? new DateTimeImmutable();
        $year = (int) $date->format('Y');
        
        $lastFolio = $this->loanRepository->getLastFolioForYear($year);
        
        $sequence = $lastFolio ? $lastFolio->sequence() + 1 : 1;
        $sequenceStr = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        
        return new Folio('SIN', $year, $sequenceStr);
    }
}
