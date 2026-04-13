<?php

namespace App\Modules\Loan\Application\Service;

use DateTimeImmutable;

final readonly class ElectronicSignatureService
{
    /**
     * Generate electronic signature hash based on CURP and timestamp
     * Format: SHA256(CURP + timestamp)
     */
    public function generate(string $curp, ?DateTimeImmutable $timestamp = null): string
    {
        $timestamp = $timestamp ?? new DateTimeImmutable();
        $data = $curp . $timestamp->format('Y-m-d H:i:s');
        
        return hash('sha256', $data);
    }

    /**
     * Verify electronic signature
     */
    public function verify(string $curp, DateTimeImmutable $timestamp, string $signature): bool
    {
        $expectedSignature = $this->generate($curp, $timestamp);
        return hash_equals($expectedSignature, $signature);
    }
}
