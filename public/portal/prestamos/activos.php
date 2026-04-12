<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();
$db = $container->get(\PDO::class);

$allowedStatuses = [
	'all',
	'activo',
	'solicitado',
	'borrador',
	'aprobado',
	'en_espera',
	'rechazado',
	'liquidado',
	'reestructurado',
];

$statusFilter = trim((string) ($_GET['status'] ?? 'activo'));
if (!in_array($statusFilter, $allowedStatuses, true)) {
	$statusFilter = 'activo';
}

$filters = [
	'status' => $statusFilter,
	'search' => trim((string) ($_GET['search'] ?? '')),
	'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
	'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
];

$where = ['l.deletion_date IS NULL', 'l.user_id = :current_user_id'];
$params = [
	'current_user_id' => (int) ($currentUser?->id ?? 0),
];

if ($filters['status'] !== 'all') {
	if ($filters['status'] === 'activo') {
		$where[] = "l.status IN ('activo', 'desembolsado')";
	} else {
		$where[] = 'l.status = :status';
		$params['status'] = $filters['status'];
	}
}

if ($filters['search'] !== '') {
	$where[] = "(l.folio LIKE :search OR CONCAT(u.name, ' ', u.surnames) LIKE :search OR u.email LIKE :search)";
	$params['search'] = '%' . $filters['search'] . '%';
}

$referenceDateExpression = 'COALESCE(l.disbursement_date, l.approval_date, l.application_date)';

if ($filters['fecha_desde'] !== '') {
	$where[] = "DATE($referenceDateExpression) >= :fecha_desde";
	$params['fecha_desde'] = $filters['fecha_desde'];
}

if ($filters['fecha_hasta'] !== '') {
	$where[] = "DATE($referenceDateExpression) <= :fecha_hasta";
	$params['fecha_hasta'] = $filters['fecha_hasta'];
}

$sql = "
	SELECT
		l.loan_id,
		l.folio,
		l.status,
		l.requested_amount,
		l.approved_amount,
		l.outstanding_balance,
		l.application_date,
		l.approval_date,
		l.disbursement_date,
		l.first_payment_date,
		l.last_scheduled_payment_date,
		$referenceDateExpression AS reference_date,
		l.rejection_reason,
		CONCAT(u.name, ' ', u.surnames) AS borrower_name,
		u.email,
		u.role,
		(SELECT COUNT(*)
		 FROM loan_amortization la
		 WHERE la.loan_id = l.loan_id
		   AND la.active = 1
		   AND la.days_overdue > 0) AS overdue_installments,
		(SELECT COALESCE(MAX(la.days_overdue), 0)
		 FROM loan_amortization la
		 WHERE la.loan_id = l.loan_id
		   AND la.active = 1) AS max_days_overdue
	FROM loans l
	INNER JOIN users u ON u.user_id = l.user_id
	WHERE " . implode(' AND ', $where) . "
	ORDER BY reference_date DESC, l.loan_id DESC
";

$statement = $db->prepare($sql);
$statement->execute($params);

$loans = $statement->fetchAll(\PDO::FETCH_ASSOC);

$statusLabels = [
	'activo' => 'Activo',
	'desembolsado' => 'Activo',
	'solicitado' => 'Solicitado',
	'borrador' => 'Borrador',
	'aprobado' => 'Aprobado',
	'en_espera' => 'En espera',
	'rechazado' => 'Rechazado',
	'liquidado' => 'Liquidado',
	'reestructurado' => 'Reestructurado',
];

$statusBadgeClasses = [
	'activo' => 'bg-success-subtle text-success',
	'desembolsado' => 'bg-success-subtle text-success',
	'solicitado' => 'bg-warning-subtle text-warning',
	'borrador' => 'bg-light text-dark',
	'aprobado' => 'bg-info-subtle text-info',
	'en_espera' => 'bg-dark-subtle text-dark',
	'rechazado' => 'bg-danger-subtle text-danger',
	'liquidado' => 'bg-secondary-subtle text-secondary',
	'reestructurado' => 'bg-primary-subtle text-primary',
];

$summary = [
	'total' => count($loans),
	'balance' => 0.0,
	'approved' => 0.0,
	'overdue' => 0,
];

foreach ($loans as &$loan) {
	$loan['borrower_name'] = trim((string) $loan['borrower_name']);
	$loan['status_label'] = $statusLabels[$loan['status']] ?? ucfirst((string) $loan['status']);
	$loan['status_badge_classes'] = $statusBadgeClasses[$loan['status']] ?? 'bg-light text-dark';
	$loan['reference_date_label'] = !empty($loan['reference_date'])
		? date('d/m/Y', strtotime((string) $loan['reference_date']))
		: '—';
	$loan['application_date_label'] = !empty($loan['application_date'])
		? date('d/m/Y H:i', strtotime((string) $loan['application_date']))
		: '—';
	$loan['disbursement_date_label'] = !empty($loan['disbursement_date'])
		? date('d/m/Y H:i', strtotime((string) $loan['disbursement_date']))
		: '—';
	$loan['first_payment_date_label'] = !empty($loan['first_payment_date'])
		? date('d/m/Y', strtotime((string) $loan['first_payment_date']))
		: '—';
	$loan['last_payment_date_label'] = !empty($loan['last_scheduled_payment_date'])
		? date('d/m/Y', strtotime((string) $loan['last_scheduled_payment_date']))
		: '—';
	$loan['requested_amount_label'] = '$' . number_format((float) $loan['requested_amount'], 2);
	$loan['approved_amount_label'] = $loan['approved_amount'] !== null
		? '$' . number_format((float) $loan['approved_amount'], 2)
		: '—';
	$loan['outstanding_balance_label'] = '$' . number_format((float) $loan['outstanding_balance'], 2);
	$loan['days_overdue_label'] = (int) $loan['max_days_overdue'] > 0
		? (int) $loan['max_days_overdue'] . ' días'
		: 'Al corriente';

	$summary['balance'] += (float) $loan['outstanding_balance'];
	$summary['approved'] += (float) ($loan['approved_amount'] ?? 0);
	if ((int) $loan['overdue_installments'] > 0) {
		$summary['overdue']++;
	}
}
unset($loan);

$statusOptions = [
	['value' => 'all', 'label' => 'Todos'],
	['value' => 'activo', 'label' => 'Activos'],
	['value' => 'solicitado', 'label' => 'Solicitados'],
	['value' => 'borrador', 'label' => 'Borradores'],
	['value' => 'aprobado', 'label' => 'Aprobados'],
	['value' => 'en_espera', 'label' => 'En espera'],
	['value' => 'rechazado', 'label' => 'Rechazados'],
	['value' => 'liquidado', 'label' => 'Liquidados'],
	['value' => 'reestructurado', 'label' => 'Reestructurados'],
];

$renderer->render(__DIR__ . '/activos.latte', [
	'loans' => $loans,
	'filters' => $filters,
	'statusOptions' => $statusOptions,
	'summary' => $summary,
]);
