<?php
use App\Bootstrap;
use App\Module\Usuario\Entity\RolEnum;
use App\Module\Usuario\Entity\Usuario;
use App\Module\Usuario\Repository\UsuarioRepository;
use Faker\Factory;
use League\Csv\Reader;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$opciones = getopt("", ["path:"]);

$path = $opciones["path"] ?? null;

if (isset($path)) {
    echo "Buscando en $path" . PHP_EOL;
    try {
        $repo = $container->get(UsuarioRepository::class);
        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();

        $faker = Factory::create();

        $count = 0;

        $defaultPassword = password_hash("temporal", PASSWORD_DEFAULT);

        foreach ($records as $record) {
            $fechaNacimiento = !empty($record["fecha_nacimiento"])
                ? (DateTimeImmutable::createFromFormat(
                    "d/m/Y H:i",
                    $record["fecha_nacimiento"],
                ) ?:
                null)
                : null;
            $fechaIngreso = !empty($record["fecha_ingreso"])
                ? (DateTimeImmutable::createFromFormat(
                    "d/m/Y H:i",
                    $record["fecha_ingreso"],
                ) ?:
                null)
                : null;

            $usuario = new Usuario(
                nombre: $record["nombre"],
                apellidos: $record["apellidos"],
                email: $record["email"] ?: $faker->email(),
                passwordHash: $defaultPassword,
                rol: RolEnum::tryFrom($record["rol"]),
                curp: $record["curp"],
                fechaNacimiento: $fechaNacimiento,
                direccion: $record["direccion"],
                telefono: $record["telefono"],
                categoria: $record["categoria"],
                departamento: $record["departamento"],
                nss: $record["nss"],
                fechaIngresoLaboral: $fechaIngreso,
            );

            $repo->registrarUsuario($usuario);

            $count++;

            echo "El usuario: {$usuario->nombre} {$usuario->apellidos} fue registrado" .
                PHP_EOL;
        }
    } catch (\League\Csv\Exception $th) {
        echo $th->getMessage() . PHP_EOL;
    }
} else {
    echo "No se proporcionó la ruta del archivo CSV." . PHP_EOL;
}
