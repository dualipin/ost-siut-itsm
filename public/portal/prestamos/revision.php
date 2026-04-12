<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../bootstrap.php';

$container   = Bootstrap::buildContainer();
$middleware  = $container->get(MiddlewareFactory::class);
$runner      = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());
$runner->runOrRedirect($middleware->role(RoleEnum::Lider, RoleEnum::Finanzas, RoleEnum::Admin));

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();
$db          = $container->get(\PDO::class);

// Filters
$allowedStatuses = ['solicitado', 'en_espera', 'todos'];
$statusFilter    = trim((string) ($_GET['status'] ?? 'solicitado'));
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'solicitado';
}

$search     = trim((string) ($_GET['search'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

$where  = ['l.deletion_date IS NULL'];
$params = [];

if ($statusFilter === 'todos') {
    $where[] = "l.status IN ('solicitado', 'en_espera')";
} else {
    $where[] = 'l.status = :status';
    $params['status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(l.folio LIKE :search OR CONCAT(u.name, ' ', u.surnames) LIKE :search OR u.email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($fechaDesde !== '') {
    $where[] = 'DATE(l.application_date) >= :fecha_desde';
    $params['fecha_desde'] = $fechaDesde;
}

if ($fechaHasta !== '') {
    $where[] = 'DATE(l.application_date) <= :fecha_hasta';
    $params['fecha_hasta'] = $fechaHasta;
}

$sql = "
    SELECT
        l.loan_id,
        l.folio,
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
        (SELECT COUNT(*)
         FROM loan_payment_configuration lpc
         WHERE lpc.loan_id = l.loan_id
           AND lpc.document_status = 'pendiente')  AS pending_docs,
        (SELECT COUNT(*)
         FROM loan_payment_configuration lpc2
         WHERE lpc2.loan_id = l.loan_id)           AS total_configs
    FROM loans l
    INNER JOIN users u ON u.user_id = l.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY l.application_date ASC, l.loan_id ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Status badge and label maps
$statusLabels = [
    'solicitado' => 'Solicitado',
    'en_espera'  => 'En espera',
];
$statusBadges = [
    'solicitado' => 'bg-warning-subtle text-warning',
    'en_espera'  => 'bg-dark-subtle text-secondary',
];

// Summary counts
$summary = [
    'solicitado' => 0,
    'en_espera'  => 0,
    'total'      => count($loans),
    'con_docs_pendientes' => 0,
];

foreach ($loans as &$loan) {
    $loan['status_label']   = $statusLabels[$loan['status']] ?? ucfirst((string) $loan['status']);
    $loan['status_badge']   = $statusBadges[$loan['status']] ?? 'bg-light text-dark';
    $loan['is_restructuring'] = !empty($loan['original_loan_id']) || (bool) $loan['requires_restructuring'];
    $loan['application_date_label'] = date('d/m/Y H:i', strtotime((string) $loan['application_date']));
    $loan['requested_amount_label'] = '$' . number_format((float) $loan['requested_amount'], 2);
    $loan['days_elapsed']   = (int) $loan['days_elapsed'];

    $summary[$loan['status']] = ($summary[$loan['status']] ?? 0) + 1;
    if ((int) $loan['pending_docs'] > 0) {
        $summary['con_docs_pendientes']++;
    }
}
unset($loan);

$renderer->render(__DIR__ . '/revision.latte', [
    'user'         => $currentUser,
    'loans'        => $loans,
    'summary'      => $summary,
    'statusFilter' => $statusFilter,
    'search'       => $search,
    'fechaDesde'   => $fechaDesde,
    'fechaHasta'   => $fechaHasta,
]);
