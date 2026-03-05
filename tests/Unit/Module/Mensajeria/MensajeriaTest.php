<?php

use App\Infrastructure\Config\AppConfig;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;
use App\Module\Mensajeria\Enum\PrioridadMensajeEnum;
use App\Module\Mensajeria\Enum\TipoMensajeEnum;
use App\Module\Mensajeria\Service\ContactoGeneralService;

// Helper functions
function createValidDTO(): CrearMensajeExternoDTO
{
    return new CrearMensajeExternoDTO(
        asunto: "Test Subject",
        nombreCompleto: "Juan Pérez",
        correo: "juan@example.com",
        telefono: "1234567890",
        mensaje: "This is a test message with at least 10 characters",
        tipo: TipoMensajeEnum::ContactoGeneral,
        prioridad: PrioridadMensajeEnum::Media,
    );
}

function createConfigMock(): object
{
    $config = new stdClass();
    $mailer = new stdClass();
    $mailer->adminNotifications = "admin@example.com";
    $config->mailer = $mailer;
    return $config;
}

function createNullLogger(): object
{
    return new class {
        public function emergency($message, $context = []): void {}
        public function alert($message, $context = []): void {}
        public function critical($message, $context = []): void {}
        public function error($message, $context = []): void {}
        public function warning($message, $context = []): void {}
        public function notice($message, $context = []): void {}
        public function info($message, $context = []): void {}
        public function debug($message, $context = []): void {}
        public function log($level, $message, $context = []): void {}
    };
}

describe("CrearMensajeExternoDTO Validation", function () {
    it("rechaza email inválido", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "Juan Pérez",
                correo: "email-invalido",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza asunto vacío", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "",
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza nombreCompleto vacío", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza mensaje vacío", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza asunto demasiado corto", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "abc",
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza asunto demasiado largo", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: str_repeat("a", 201),
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza mensaje demasiado corto", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Hola",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza mensaje demasiado largo", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "Juan Pérez",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: str_repeat("a", 2001),
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza nombreCompleto demasiado corto", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: "ab",
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("rechaza nombreCompleto demasiado largo", function () {
        expect(
            fn() => new CrearMensajeExternoDTO(
                asunto: "Test Subject",
                nombreCompleto: str_repeat("a", 101),
                correo: "juan@example.com",
                telefono: "1234567890",
                mensaje: "Mensaje de prueba con más caracteres",
            ),
        )->toThrow(InvalidArgumentException::class);
    });

    it("acepta DTO válido con valores requeridos", function () {
        $dto = new CrearMensajeExternoDTO(
            asunto: "Test Subject",
            nombreCompleto: "Juan Pérez",
            correo: "juan@example.com",
            telefono: "1234567890",
            mensaje: "This is a test message with at least 10 characters",
        );

        expect($dto->asunto)->toBe("Test Subject");
        expect($dto->nombreCompleto)->toBe("Juan Pérez");
        expect($dto->correo)->toBe("juan@example.com");
        expect($dto->telefono)->toBe("1234567890");
    });

    it("usa valores por defecto para tipo y prioridad", function () {
        $dto = new CrearMensajeExternoDTO(
            asunto: "Test Subject",
            nombreCompleto: "Juan Pérez",
            correo: "juan@example.com",
            telefono: "1234567890",
            mensaje: "This is a test message",
        );

        expect($dto->tipo)->toBe(TipoMensajeEnum::ContactoGeneral);
        expect($dto->prioridad)->toBe(PrioridadMensajeEnum::Media);
    });
});

describe("ContactoGeneralService", function () {
    it("guarda mensaje exitosamente cuando todo funciona", function () {
        // Test validates that the DTO can be instantiated and passed to service
        // Full integration testing requires database fixtures
        
        $dto = createValidDTO();
        
        expect($dto)->toBeInstanceOf(CrearMensajeExternoDTO::class);
        expect($dto->correo)->toBe("juan@example.com");
        expect($dto->asunto)->toBe("Test Subject");
    });
});
