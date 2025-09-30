<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Entidades\Miembro;
use PDO;

class ServicioMiembros
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function obtenerPorUsuario(int $usuarioId): ?Miembro
  {
    $sql = "SELECT * FROM miembros WHERE fk_usuario = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$usuarioId]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) {
      return null;
    }

    return new Miembro(
      id: (int)$datos['id'],
      nombre: $datos['nombre'],
      apellidos: $datos['apellidos'],
      direccion: $datos['direccion'],
      telefono: $datos['telefono'],
      categoria: $datos['categoria'],
      departamento: $datos['departamento'],
      nss: $datos['nss'],
      curp: $datos['curp'],
      fechaIngreso: $datos['fecha_ingreso'],
      fechaNacimiento: $datos['fecha_nacimiento'],
      salarioQuincenal: $datos['salario_quincenal'] ? (float)$datos['salario_quincenal'] : null,
      fkUsuario: $datos['fk_usuario'] ? (int)$datos['fk_usuario'] : null
    );
  }

  public function obtenerPorId(int $id): ?Miembro
  {
    $sql = "SELECT * FROM miembros WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) {
      return null;
    }

    return new Miembro(
      id: (int)$datos['id'],
      nombre: $datos['nombre'],
      apellidos: $datos['apellidos'],
      direccion: $datos['direccion'],
      telefono: $datos['telefono'],
      categoria: $datos['categoria'],
      departamento: $datos['departamento'],
      nss: $datos['nss'],
      curp: $datos['curp'],
      fechaIngreso: $datos['fecha_ingreso'],
      fechaNacimiento: $datos['fecha_nacimiento'],
      salarioQuincenal: $datos['salario_quincenal'] ? (float)$datos['salario_quincenal'] : null,
      fkUsuario: $datos['fk_usuario'] ? (int)$datos['fk_usuario'] : null
    );
  }

  public function actualizarSalarioQuincenal(int $miembroId, float $salario): bool
  {
    $sql = "UPDATE miembros SET salario_quincenal = ? WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([$salario, $miembroId]);
  }

  public function obtenerTodos(): array
  {
    $sql = "SELECT * FROM miembros ORDER BY apellidos, nombre";
    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
