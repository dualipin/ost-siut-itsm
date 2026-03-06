<?php

namespace App\Module\Mensajeria\Service;

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;
use App\Module\Mensajeria\Repository\MensajeRepository;
use Psr\Log\LoggerInterface;

use function dirname;

final readonly class ContactoGeneralService
{
    public function __construct(
        private MensajeRepository $repository,
        private AppConfig $config,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private RendererInterface $renderer,
    ) {}

    public function enviarMensaje(CrearMensajeExternoDTO $mensaje): void
    {
        try {
            // 1. Guardar en DB
            $this->repository->registrarMensajeExterno($mensaje);

            // 2. Enviar email de confirmación
            try {
                $this->mailer->send(
                    addresses: [$mensaje->correo],
                    subject: "Confirmación de envío de mensaje",
                    body: $this->renderer->renderToString(
                        __DIR__ .
                            "/../../../../templates/emails/mensajeria/contacto-general.latte",
                        [
                            "nombreCompleto" => $mensaje->nombreCompleto,
                            "asunto" => $mensaje->asunto,
                        ],
                    ),
                );
            } catch (\Throwable $e) {
                $this->logger->error("Error al enviar email de confirmación", [
                    "correo" => $mensaje->correo,
                    "error" => $e->getMessage(),
                ]);
            }

            // 3. Enviar email al admin
            try {
                $this->mailer->send(
                    addresses: [$this->config->mailer->adminNotifications],
                    subject: "Mensaje recibido",
                    body: "Se ha recibido un nuevo mensaje de contacto general:\n\n" .
                        "Nombre: {$mensaje->nombreCompleto}\n" .
                        "Correo: {$mensaje->correo}\n" .
                        "Mensaje: {$mensaje->mensaje}",
                );
            } catch (\Throwable $e) {
                $this->logger->error("Error al enviar email al administrador", [
                    "asunto" => $mensaje->asunto,
                    "error" => $e->getMessage(),
                ]);
                // No re-lanzar: mensaje guardado correctamente en DB
            }
        } catch (\PDOException $e) {
            $this->logger->error("Error al guardar mensaje en base de datos", [
                "asunto" => $mensaje->asunto,
                "correo" => $mensaje->correo,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);
            throw $e; // Re-lanzar: fallo crítico
        }
    }
}
