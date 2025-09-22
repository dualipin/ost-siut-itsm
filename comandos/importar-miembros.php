<?php

use App\Entidades\EntidadMiembro;
use App\Modelos\Miembro;

require_once __DIR__ . '/../vendor/autoload.php';

$opciones = getopt('', ['ruta:']);

if (isset($opciones['ruta'])) {
    try {
        $csv = \League\Csv\Reader::createFromPath($opciones['ruta']);
        $csv->setHeaderOffset(0);

        $registros = $csv->getRecords();

        $faker = Faker\Factory::create();

        $registrados = 0;


        foreach ($registros as $registro) {
            $fechaNacimiento = DateTimeImmutable::createFromFormat('d/m/Y', $registro['fecha_nacimiento']);
            $fechaIngreso = DateTimeImmutable::createFromFormat('d/m/Y', $registro['fecha_ingreso']);

            try {

                $miembro = new EntidadMiembro(
                        nombre: $registro['nombre'],
                        apellidos: $registro['apellidos'],
                        direccion: $registro['direccion'],
                        telefono: $registro['telefono'],
                        correo: $registro['email'] ?: $faker->email(),
                        categoria: $registro['categoria'],
                        departamento: $registro['departamento'],
                        nss: $registro['nss'],
                        curp: $registro['curp'] ?: $faker->realText(18),
                        fechaNacimiento: DateTimeImmutable::createFromFormat('d/m/Y H:i', $registro['fecha_nacimiento']) ?: null,
                        fechaIngreso: DateTimeImmutable::createFromFormat('d/m/Y H:i', $registro['fecha_ingreso']) ?: null,
                        contra: 'temporal',
                        rol: $registro['rol'],
                );

                Miembro::registrarMiembro($miembro);

                $registrados++;
                echo "Se registro {$miembro->getNombre()} {$miembro->getApellidos()}" . PHP_EOL;

            } catch (\Throwable $th) {
                echo $th->getMessage() . PHP_EOL;
            }
        }

        echo "Registros creados: $registrados" . PHP_EOL;

    } catch (\League\Csv\Exception $e) {
        echo "Error al leer el archivo CSV: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
} else {
    echo "No se proporcionó la ruta del archivo CSV. Usando ruta por defecto 'datos.csv'." . PHP_EOL;
}

