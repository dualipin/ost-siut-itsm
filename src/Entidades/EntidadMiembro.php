<?php

namespace App\Entidades;

final readonly class EntidadMiembro
{
    public function __construct(
            private string              $nombre,
            private string              $apellidos,
            private ?int                $id = null,
            private ?string             $direccion = null,
            private ?string             $telefono = null,
            private ?string             $correo = null,
            private ?string             $categoria = null,
            private ?string             $departamento = null,
            private ?string             $nss = null,
            private ?string             $curp = null,
            private ?\DateTimeImmutable $fechaNacimiento = null,
            private ?\DateTimeImmutable $fechaIngreso = null,
            private ?string             $contra = null,
            private ?string             $rol = 'agremiado',
            private ?int                $userId = null
    )
    {
        if ($correo !== null && !$this->validateEmail($correo)) {
            throw new \InvalidArgumentException("Correo electrónico inválido: $correo");
        }

        if ($curp !== null && !$this->validateCurp($curp)) {
            throw new \InvalidArgumentException("CURP inválido: $curp");
        }
    }

    private function validateCurp(string $curp): bool
    {
        return strlen($curp) <= 18;
    }

    public function getCurp(): ?string
    {
        return $this->curp;
    }


    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getNombreCompleto(): string
    {
        return $this->nombre . ' ' . $this->apellidos;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function getDepartamento(): ?string
    {
        return $this->departamento;
    }

    public function getNss(): ?string
    {
        return $this->nss;
    }

    public function getContra(): ?string
    {
        return $this->contra;
    }

    public function getRol(): ?string
    {
        return $this->rol;
    }

    public function getFechaNacimiento(): ?\DateTimeImmutable
    {
        return $this->fechaNacimiento;
    }

    public function getFechaIngreso(): ?\DateTimeImmutable
    {
        return $this->fechaIngreso;
    }


}