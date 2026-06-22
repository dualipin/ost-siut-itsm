<?php

namespace App\Http\Actions\Loan;

use App\Http\Actions\Action;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ReviewLoanListAction extends Action
{
    private const array ALLOWED_STATUSES = ['solicitado', 'en_espera', 'borrador', 'aprobado', 'rechazado', 'activo', 'liquidado', 'reestructurado', 'todos'];
    private const array ALLOWED_ORDERS = ['fecha', 'folio'];
    private const array STATUS_LABELS = [
        'borrador'   => 'Borrador',
        'solicitado' => 'Solicitado',
        'en_espera'  => 'En espera',
        'aprobado'   => 'Aprobado',
        'rechazado'  => 'Rechazado',
        'activo'     => 'Activo',
        'liquidado'  => 'Liquidado',
        'reestructurado' => 'Reestructurado',
    ];
    private const array STATUS_BADGES = [
        'borrador'   => 'bg-secondary-subtle text-secondary',
        'solicitado' => 'bg-warning-subtle text-warning',
        'en_espera'  => 'bg-dark-subtle text-secondary',
        'aprobado'   => 'bg-info-subtle text-info',
        'rechazado'  => 'bg-danger-subtle text-danger',
        'activo'     => 'bg-success-subtle text-success',
        'liquidado'  => 'bg-secondary-subtle text-secondary',
        'reestructurado' => 'bg-primary-subtle text-primary',
    ];

    public function __construct(
        LoggerInterface $logger,
        private PDO $pdo,
        private UserContextInterface $userContext,
    ) {
        parent::__construct($logger);
    }

    public function action(): ResponseInterface
    {
        $user = $this->userContext->get();
        if (!$user) {
            return $this->respondWithData(['message' => 'No autorizado'], 401);
        }

        $allowedRoles = [RoleEnum::Lider, RoleEnum::Finanzas, RoleEnum::Admin];
        if (!in_array($user->role, $allowedRoles, true)) {
            return $this->respondWithData(['message' => 'Acceso denegado'], 403);
        }

        $params = $this->request->getQueryParams();

        $statusFilter = $this->sanitizeStatus($params['status'] ?? 'todos');
        $search = trim((string) ($params['search'] ?? ''));
        $fechaDesde = trim((string) ($params['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($params['fecha_hasta'] ?? ''));
        $orderBy = $this->sanitizeOrderBy($params['order'] ?? 'fecha');
        $orderDir = $this->sanitizeOrderDir($params['dir'] ?? 'asc');

        $where = ['l.deletion_date IS NULL'];
        $binds = [];

        if ($statusFilter === 'todos') {
            $where[] = "l.status IN ('borrador','solicitado','en_espera','aprobado','rechazado','activo','liquidado','reestructurado')";
        } else {
            $where[] = 'l.status = :status';
            $binds['status'] = $statusFilter;
        }

        if ($search !== '') {
            $where[] = "(COALESCE(NULLIF(TRIM(l.folio),''), CONCAT('SIUT-FOLIO-',l.loan_id)) LIKE :search OR CAST(l.loan_id AS CHAR) LIKE :search OR CONCAT(u.name,' ',u.surnames) LIKE :search OR u.email LIKE :search)";
            $binds['search'] = '%' . $search . '%';
        }

        if ($fechaDesde !== '') {
            $where[] = 'DATE(l.application_date) >= :fecha_desde';
            $binds['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta !== '') {
            $where[] = 'DATE(l.application_date) <= :fecha_hasta';
            $binds['fecha_hasta'] = $fechaHasta;
        }

        $orderClause = $orderBy === 'folio'
            ? "folio " . strtoupper($orderDir) . ", l.loan_id " . strtoupper($orderDir)
            : "l.application_date " . strtoupper($orderDir) . ", l.loan_id " . strtoupper($orderDir);

        $sql = "
            SELECT
                l.loan_id,
                COALESCE(NULLIF(TRIM(l.folio),''), CONCAT('SIUT-FOLIO-',l.loan_id)) AS folio,
                l.status,
                l.requested_amount,
                l.applied_interest_rate,
                l.term_fortnights,
                l.application_date,
                l.requires_restructuring,
                l.original_loan_id,
                DATEDIFF(NOW(), l.application_date)        AS days_elapsed,
                CONCAT(u.name, ' ', u.surnames)            AS borrower_name,
                u.email                                    AS borrower_email,
                u.department                               AS borrower_department,
                (SELECT COUNT(*) FROM loan_payment_configuration lpc WHERE lpc.loan_id = l.loan_id AND lpc.document_status = 'pendiente') AS pending_docs,
                (SELECT COUNT(*) FROM loan_payment_configuration lpc2 WHERE lpc2.loan_id = l.loan_id) AS total_configs
            FROM loans l
            INNER JOIN users u ON u.user_id = l.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderClause}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'borrador'   => 0,
            'solicitado' => 0,
            'en_espera'  => 0,
            'total'      => count($rows),
            'con_docs_pendientes' => 0,
        ];

        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[$row['status']] ?? ucfirst((string) $row['status']);
            $row['status_badge'] = self::STATUS_BADGES[$row['status']] ?? 'bg-light text-dark';
            $row['is_restructuring'] = !empty($row['original_loan_id']) || (bool) $row['requires_restructuring'];
            $row['application_date_label'] = date('d/m/Y H:i', strtotime((string) $row['application_date']));
            $row['requested_amount_label'] = '$' . number_format((float) $row['requested_amount'], 2, ',', '.');
            $row['days_elapsed'] = (int) $row['days_elapsed'];

            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
            if ((int) $row['pending_docs'] > 0) {
                $summary['con_docs_pendientes']++;
            }
        }
        unset($row);

        return $this->respondWithData([
            'data'    => $rows,
            'summary' => $summary,
        ]);
    }

    private function sanitizeStatus(string $status): string
    {
        $s = trim($status);
        return in_array($s, self::ALLOWED_STATUSES, true) ? $s : 'todos';
    }

    private function sanitizeOrderBy(string $order): string
    {
        $o = trim($order);
        return in_array($o, self::ALLOWED_ORDERS, true) ? $o : 'fecha';
    }

    private function sanitizeOrderDir(string $dir): string
    {
        $d = strtolower(trim($dir));
        return ($d === 'asc' || $d === 'desc') ? $d : 'asc';
    }
}
