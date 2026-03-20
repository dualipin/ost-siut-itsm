<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Repository;

/**
 * Dashboard data for Administrador (task-oriented, operational view).
 */
interface DashboardAdministradorRepositoryInterface
{
    /**
     * Count of loans in 'borrador' status (new requests).
     */
    public function getNewLoanRequestsCount(): int;

    /**
     * Pending user documents (status = 'pendiente').
     * Returns array of {document_id: int, user_id: int, user_name: string, document_type: string, created_at: string, days_waiting: int}.
     */
    public function getPendingDocuments(): array;

    /**
     * Open message threads (status IN ('pending', 'open')).
     * Returns array of {thread_id: int, subject: string, sender_name: string, created_at: string, hours_elapsed: int, status: string}.
     */
    public function getOpenMessageThreads(): array;

    /**
     * Users registered without role (role = 'no_agremiado' and active = TRUE).
     * Returns count.
     */
    public function getUnassignedUsersCount(): int;

    /**
     * Kanban data: loans grouped by status with details.
     * Returns array of {status: string, loans: [{folio, name, days_in_status, is_overdue}]}.
     */
    public function getLoanKanbanData(): array;

    /**
     * Recent user registrations (last 10).
     * Returns array of {user_id: int, name: string, email: string, role: string, created_at: string, days_since_registered: int}.
     */
    public function getRecentUsers(int $limit = 10): array;

    /**
     * Failed login attempts (last 5, from auth_logs).
     * Returns array of {email: string, ip_address: string, attempts: int, last_attempt: string}.
     */
    public function getRecentFailedLogins(): array;

    /**
     * Failed email deliveries (mail_queue with status = 'failed').
     * Returns count.
     */
    public function getFailedMailQueueCount(): int;
}
