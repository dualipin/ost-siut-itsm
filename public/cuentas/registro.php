<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\User\Application\DTO\CreateUser;
use App\Modules\User\Application\UseCase\CreateUserUseCase;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Enum\RoleEnum;
use Dompdf\Dompdf;
use Psr\Container\ContainerInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$request = new FormRequest();
$renderer = $container->get(RendererInterface::class);

$errors = [];
$old = [];

if ($request->isSubmitted()) {
	$formData = mapRegisterFormData($request);
	$old = extractOldInput($formData);
	$errors = validateRegisterFormData($container, $formData);

	if ($errors === []) {
		$errors = registerAgremiado($container, $formData);
	}
}

$renderer->render("./registro.latte", [
	"errors" => $errors,
	"old" => $old,
	"roles" => [RoleEnum::Agremiado, RoleEnum::NoAgremiado],
]);

/**
 * @return array{
 *   name: string,
 *   surnames: string,
 *   address: string,
 *   phone: string,
 *   email: string,
 *   category: string,
 *   department: string,
 *   nss: string,
 *   curp: string,
 *   birthdate: string,
 *   work_start_date: string,
 *   role: string,
 *   password: string,
 *   password_confirm: string
 * }
 */
function mapRegisterFormData(FormRequest $request): array
{
	return [
		"name" => trim((string) $request->input("name", "")),
		"surnames" => trim((string) $request->input("surnames", "")),
		"address" => trim((string) $request->input("address", "")),
		"phone" => trim((string) $request->input("phone", "")),
		"email" => trim((string) $request->input("email", "")),
		"category" => trim((string) $request->input("category", "")),
		"department" => trim((string) $request->input("department", "")),
		"nss" => trim((string) $request->input("nss", "")),
		"curp" => strtoupper(trim((string) $request->input("curp", ""))),
		"birthdate" => trim((string) $request->input("birthdate", "")),
		"work_start_date" => trim((string) $request->input("work_start_date", "")),
		"role" => trim((string) $request->input("role", "")),
		"password" => (string) $request->input("password", ""),
		"password_confirm" => (string) $request->input("password_confirm", ""),
	];
}

/**
 * @param array{
 *   name: string,
 *   surnames: string,
 *   address: string,
 *   phone: string,
 *   email: string,
 *   category: string,
 *   department: string,
 *   nss: string,
 *   curp: string,
 *   birthdate: string,
 *   work_start_date: string,
 *   role: string,
 *   password: string,
 *   password_confirm: string
 * } $formData
 * @return list<string>
 */
function validateRegisterFormData(ContainerInterface $container, array $formData): array
{
	$errors = [];

	if ($formData["name"] === "") {
		$errors[] = "El nombre es obligatorio.";
	}

	if ($formData["surnames"] === "") {
		$errors[] = "Los apellidos son obligatorios.";
	}

	if ($formData["address"] === "") {
		$errors[] = "La dirección es obligatoria.";
	}

	if (!preg_match('/^\d{10}$/', $formData["phone"])) {
		$errors[] = "El teléfono debe contener exactamente 10 dígitos.";
	}

	if (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
		$errors[] = "El correo electrónico no es válido.";
	}

	if ($formData["category"] === "") {
		$errors[] = "La categoría es obligatoria.";
	}

	if ($formData["department"] === "") {
		$errors[] = "El departamento es obligatorio.";
	}

	if (!preg_match('/^\d{11}$/', $formData["nss"])) {
		$errors[] = "El NSS debe contener exactamente 11 dígitos.";
	}

	if (!preg_match('/^[A-Z0-9]{18}$/', $formData["curp"])) {
		$errors[] = "La CURP debe contener 18 caracteres alfanuméricos.";
	}

	if (!isValidDateValue($formData["birthdate"])) {
		$errors[] = "La fecha de nacimiento no es válida.";
	}

	if (!isValidDateValue($formData["work_start_date"])) {
		$errors[] = "La fecha de ingreso no es válida.";
	}

	if (strlen($formData["password"]) < 6) {
		$errors[] = "La contraseña debe tener al menos 6 caracteres.";
	}

	if ($formData["password"] !== $formData["password_confirm"]) {
		$errors[] = "Las contraseñas no coinciden.";
	}

	if (!in_array($formData["role"], [RoleEnum::Agremiado->value, RoleEnum::NoAgremiado->value], true)) {
		$errors[] = "El rol seleccionado no es válido para el registro público.";
	}

	/** @var UserRepositoryInterface $userRepository */
	$userRepository = $container->get(UserRepositoryInterface::class);
	if (
		filter_var($formData["email"], FILTER_VALIDATE_EMAIL)
		&& $userRepository->findByEmail($formData["email"]) !== null
	) {
		$errors[] = "Ya existe un usuario registrado con ese correo electrónico.";
	}

	return $errors;
}

function isValidDateValue(string $value): bool
{
	$date = \DateTimeImmutable::createFromFormat("!Y-m-d", $value);

	return $date !== false && $date->format("Y-m-d") === $value;
}

/**
 * @param array{
 *   name: string,
 *   surnames: string,
 *   address: string,
 *   phone: string,
 *   email: string,
 *   category: string,
 *   department: string,
 *   nss: string,
 *   curp: string,
 *   birthdate: string,
 *   work_start_date: string,
 *   role: string,
 *   password: string,
 *   password_confirm: string
 * } $formData
 * @return list<string>
 */
function registerAgremiado(ContainerInterface $container, array $formData): array
{
	$dto = new CreateUser(
		email: $formData["email"],
		password: $formData["password"],
		name: $formData["name"],
		surnames: $formData["surnames"],
		role: RoleEnum::from($formData["role"]),
		active: true,
		curp: $formData["curp"],
		birthdate: parseDateValue($formData["birthdate"]),
		address: $formData["address"],
		phone: $formData["phone"],
		category: $formData["category"],
		department: $formData["department"],
		nss: $formData["nss"],
		workStartDate: parseDateValue($formData["work_start_date"]),
	);

	try {
		/** @var CreateUserUseCase $createUserUseCase */
		$createUserUseCase = $container->get(CreateUserUseCase::class);
		$created = $createUserUseCase->execute($dto);
	} catch (\RuntimeException) {
		return ["No fue posible completar el registro en este momento."];
	}

	if (!$created) {
		return ["No fue posible completar el registro. Verifica que el correo no esté duplicado."];
	}

	try {
		sendWelcomeMail($container, $formData["email"], $formData["name"]);
	} catch (\Throwable $exception) {
		error_log($exception->getMessage());
	}

	streamRegistrationCertificate($container, $formData);

	return [];
}

function parseDateValue(string $value): ?\DateTimeImmutable
{
	$date = \DateTimeImmutable::createFromFormat("!Y-m-d", $value);

	return $date === false ? null : $date;
}

function sendWelcomeMail(ContainerInterface $container, string $email, string $name): void
{
	/** @var MailerInterface $mailer */
	$mailer = $container->get(MailerInterface::class);
	/** @var RendererInterface $renderer */
	$renderer = $container->get(RendererInterface::class);

	$body = $renderer->renderToString(__DIR__ . "/../../templates/emails/welcome-agremiado.latte", [
		"name" => $name,
		"year" => date("Y"),
	]);

	$mailer->send(
		[$email],
		"Bienvenido al registro de agremiados OST-SIUT-ITSM",
		$body,
		"Hola {$name}, tu registro fue recibido correctamente. Bienvenido(a) a OST-SIUT-ITSM.",
	);
}

/**
 * @param array{
 *   name: string,
 *   surnames: string,
 *   address: string,
 *   phone: string,
 *   email: string,
 *   category: string,
 *   department: string,
 *   nss: string,
 *   curp: string,
 *   birthdate: string,
 *   work_start_date: string,
 *   role: string,
 *   password: string,
 *   password_confirm: string
 * } $formData
 */
function streamRegistrationCertificate(ContainerInterface $container, array $formData): never
{
	/** @var RendererInterface $renderer */
	$renderer = $container->get(RendererInterface::class);
	/** @var Dompdf $pdf */
	$pdf = $container->get(Dompdf::class);

	$logoPath = __DIR__ . "/../assets/images/logo.webp";
	$logoSrc = null;

	if (is_file($logoPath)) {
		$logoData = file_get_contents($logoPath);

		if (is_string($logoData) && $logoData !== "") {
			$logoSrc = "data:image/webp;base64," . base64_encode($logoData);
		}
	}

	$primaryColor = "#611232";

	try {
		$colorConfig = $container->get(GetColorUseCase::class)->execute();

		if ($colorConfig !== null && $colorConfig->primary !== "") {
			$primaryColor = $colorConfig->primary;
		}
	} catch (\Throwable) {
		// Mantener color institucional por defecto si no se pudo resolver configuración.
	}

	$html = $renderer->renderToString(__DIR__ . "/../../templates/documents/agremiado-registration-certificate.latte", [
		"user" => $formData,
		"issuedAt" => (new \DateTimeImmutable())->format("d/m/Y H:i"),
		"logoSrc" => $logoSrc,
		"primaryColor" => $primaryColor,
	]);

	$pdf->loadHtml($html);
	$pdf->render();

	$filename = "constancia-registro-" . date("YmdHis") . ".pdf";
	$pdf->stream($filename, ["Attachment" => true]);

	exit;
}

/**
 * @param array{
 *   name: string,
 *   surnames: string,
 *   address: string,
 *   phone: string,
 *   email: string,
 *   category: string,
 *   department: string,
 *   nss: string,
 *   curp: string,
 *   birthdate: string,
 *   work_start_date: string,
 *   role: string,
 *   password: string,
 *   password_confirm: string
 * } $formData
 * @return array<string, string>
 */
function extractOldInput(array $formData): array
{
	unset($formData["password"], $formData["password_confirm"]);

	return $formData;
}
