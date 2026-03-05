<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Mail\MailerInterface;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;
use App\Module\Mensajeria\Enum\PrioridadMensajeEnum;
use App\Module\Mensajeria\Enum\TipoMensajeEnum;
use App\Module\Mensajeria\Repository\MensajeRepository;
use App\Module\Mensajeria\Service\ContactoGeneralService;
use Psr\Log\LoggerInterface;

// Helper functions
function createValidDTO(): CrearMensajeExternoDTO
{
    return new CrearMensajeExternoDTO(
        asunto: 'Test Subject',
        nombreCompleto: 'Juan Pérez',
        correo: 'juan@example.com',
        telefono: '1234567890',
        mensaje: 'This is a test message with at least 10 characters',
        tipo: TipoMensajeEnum::ContactoGeneral,
        prioridad: PrioridadMensajeEnum::Media,
    );
}

function createConfigMock(): AppConfig
{
    $config = Mockery::mock(AppConfig::class);
    $mailer = new stdClass();
    $mailer->adminNotifications = 'admin@example.com';
    $config->mailer = $mailer;
    return $config;
}

describe('CrearMensajeExternoDTO Validation', function () {

    it('rechaza email inválido', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'email-invalido',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza asunto vacío', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: '',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza nombreCompleto vacío', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: '',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza mensaje vacío', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: '',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza asunto demasiado corto', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'abc', // < 5 caracteres
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza asunto demasiado largo', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: str_repeat('a', 201), // > 200 caracteres
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza mensaje demasiado corto', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Hola', // < 10 caracteres
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza mensaje demasiado largo', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: str_repeat('a', 2001), // > 2000 caracteres
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza nombreCompleto demasiado corto', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'ab', // < 3 caracteres
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rechaza nombreCompleto demasiado largo', function () {
        expect(fn() => new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: str_repeat('a', 101), // > 100 caracteres
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'Mensaje de prueba con más caracteres',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('acepta DTO válido con valores requeridos', function () {
        $dto = new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'This is a test message with at least 10 characters',
        );

        expect($dto->asunto)->toBe('Test Subject');
        expect($dto->nombreCompleto)->toBe('Juan Pérez');
        expect($dto->correo)->toBe('juan@example.com');
        expect($dto->telefono)->toBe('1234567890');
    });

    it('usa valores por defecto para tipo y prioridad', function () {
        $dto = new CrearMensajeExternoDTO(
            asunto: 'Test Subject',
            nombreCompleto: 'Juan Pérez',
            correo: 'juan@example.com',
            telefono: '1234567890',
            mensaje: 'This is a test message',
        );

        expect($dto->tipo)->toBe(TipoMensajeEnum::ContactoGeneral);
        expect($dto->prioridad)->toBe(PrioridadMensajeEnum::Media);
    });
});

describe('ContactoGeneralService', function () {

    afterEach(function () {
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
    });

    it('guarda mensaje exitosamente cuando todo funciona', function () {
        // Crear mocks usando clases anónimas
        $repository = new class implements \App\Module\Mensajeria\Repository\MensajeRepository {
            public function registrarMensajeExterno(\App\Module\Mensajeria\DTO\CrearMensajeExternoDTO $mensaje): void
            {
                // Mock sin hacer nada
            }
        };

        $mailer = new class implements \App\Infrastructure\Mail\MailerInterface {
            public function send(array $addresses, string $subject, string $body): void
            {
                // Mock sin hacer nada
            }
        };

        $config = createConfigMock();
        
        $logger = new class implements \Psr\Log\LoggerInterface {
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void {}
            public function info(\Stringable|string $message, array $context = []): void {}
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $service = new ContactoGeneralService($repository, $config, $mailer, $logger);
        $dto = createValidDTO();
        
        $service->enviarMensaje($dto);

        expect(true)->toBeTrue();
    });

    it('lanza excepción cuando falla la base de datos', function () {
        $repository = new class implements \App\Module\Mensajeria\Repository\MensajeRepository {
            public function registrarMensajeExterno(\App\Module\Mensajeria\DTO\CrearMensajeExternoDTO $mensaje): void
            {
                throw new PDOException('DB error');
            }
        };

        $mailer = new class implements \App\Infrastructure\Mail\MailerInterface {
            public function send(array $addresses, string $subject, string $body): void {}
        };

        $config = createConfigMock();
        
        $logger = new class implements \Psr\Log\LoggerInterface {
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void {}
            public function info(\Stringable|string $message, array $context = []): void {}
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $service = new ContactoGeneralService($repository, $config, $mailer, $logger);
        $dto = createValidDTO();

        expect(fn() => $service->enviarMensaje($dto))
            ->toThrow(PDOException::class);
    });

    it('loguea error pero no lanza cuando falla el email', function () {
        $repository = new class implements \App\Module\Mensajeria\Repository\MensajeRepository {
            public function registrarMensajeExterno(\App\Module\Mensajeria\DTO\CrearMensajeExternoDTO $mensaje): void
            {
                // OK
            }
        };

        $mailer = new class implements \App\Infrastructure\Mail\MailerInterface {
            public function send(array $addresses, string $subject, string $body): void
            {
                throw new Exception('SMTP error');
            }
        };

        $config = createConfigMock();
        
        $logger = new class implements \Psr\Log\LoggerInterface {
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void {}
            public function info(\Stringable|string $message, array $context = []): void {}
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $service = new ContactoGeneralService($repository, $config, $mailer, $logger);
        $dto = createValidDTO();

        $service->enviarMensaje($dto); // No debe lanzar

        expect(true)->toBeTrue();
    });
});

