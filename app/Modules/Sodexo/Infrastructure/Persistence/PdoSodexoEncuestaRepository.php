<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Sodexo\Domain\Entity\SodexoEncuesta;
use App\Modules\Sodexo\Domain\Repository\SodexoEncuestaRepositoryInterface;
use DateTimeImmutable;
use Exception;

final class PdoSodexoEncuestaRepository extends PdoBaseRepository implements SodexoEncuestaRepositoryInterface
{
    public function guardar(SodexoEncuesta $encuesta): bool
    {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO sodexo_encuesta (
                user_id,
                tipo_empleado,
                adm_dic_puntualidad,
                adm_dic_asistencia,
                adm_ene_puntualidad,
                adm_ene_asistencia,
                adm_feb_puntualidad,
                adm_feb_asistencia,
                adm_mar_puntualidad,
                adm_mar_asistencia,
                doc_dic_pagado,
                doc_mar_pagado,
                firma_curp
            ) VALUES (
                :user_id,
                :tipo_empleado,
                :adm_dic_puntualidad,
                :adm_dic_asistencia,
                :adm_ene_puntualidad,
                :adm_ene_asistencia,
                :adm_feb_puntualidad,
                :adm_feb_asistencia,
                :adm_mar_puntualidad,
                :adm_mar_asistencia,
                :doc_dic_pagado,
                :doc_mar_pagado,
                :firma_curp
            )
            ON DUPLICATE KEY UPDATE
                tipo_empleado       = VALUES(tipo_empleado),
                adm_dic_puntualidad = VALUES(adm_dic_puntualidad),
                adm_dic_asistencia  = VALUES(adm_dic_asistencia),
                adm_ene_puntualidad = VALUES(adm_ene_puntualidad),
                adm_ene_asistencia  = VALUES(adm_ene_asistencia),
                adm_feb_puntualidad = VALUES(adm_feb_puntualidad),
                adm_feb_asistencia  = VALUES(adm_feb_asistencia),
                adm_mar_puntualidad = VALUES(adm_mar_puntualidad),
                adm_mar_asistencia  = VALUES(adm_mar_asistencia),
                doc_dic_pagado      = VALUES(doc_dic_pagado),
                doc_mar_pagado      = VALUES(doc_mar_pagado),
                firma_curp          = VALUES(firma_curp),
                updated_at          = CURRENT_TIMESTAMP
            ",
        );

        $stmt->execute([
            'user_id'             => $encuesta->userId,
            'tipo_empleado'       => $encuesta->tipoEmpleado,
            'adm_dic_puntualidad' => $encuesta->admDicPuntualidad,
            'adm_dic_asistencia'  => $encuesta->admDicAsistencia,
            'adm_ene_puntualidad' => $encuesta->admEnePuntualidad,
            'adm_ene_asistencia'  => $encuesta->admEneAsistencia,
            'adm_feb_puntualidad' => $encuesta->admFebPuntualidad,
            'adm_feb_asistencia'  => $encuesta->admFebAsistencia,
            'adm_mar_puntualidad' => $encuesta->admMarPuntualidad,
            'adm_mar_asistencia'  => $encuesta->admMarAsistencia,
            'doc_dic_pagado'      => (int) $encuesta->docDicPagado,
            'doc_mar_pagado'      => (int) $encuesta->docMarPagado,
            'firma_curp'          => $encuesta->firmaCurp,
        ]);

        return $stmt->rowCount() >= 1;
    }

    public function buscarPorUsuario(int $userId): ?SodexoEncuesta
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT se.*, u.name, u.surnames, u.category, u.department
            FROM sodexo_encuesta se
            INNER JOIN users u ON u.user_id = se.user_id
            WHERE se.user_id = :user_id
            LIMIT 1
            ",
        );

        $stmt->execute(['user_id' => $userId]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @return SodexoEncuesta[]
     */
    public function listarTodas(): array
    {
        $stmt = $this->pdo->query(
            "
            SELECT se.*, u.name, u.surnames, u.category, u.department
            FROM sodexo_encuesta se
            INNER JOIN users u ON u.user_id = se.user_id
            ORDER BY u.surnames, u.name
            ",
        );

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $this->mapRow($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): SodexoEncuesta
    {
        return new SodexoEncuesta(
            id:               (int) $row['encuesta_id'],
            userId:           (int) $row['user_id'],
            tipoEmpleado:     (string) $row['tipo_empleado'],

            userName:         (string) ($row['name']       ?? ''),
            userSurnames:     (string) ($row['surnames']   ?? ''),
            userCategory:     (string) ($row['category']   ?? ''),
            userDepartment:   (string) ($row['department'] ?? ''),

            admDicPuntualidad: isset($row['adm_dic_puntualidad']) ? (float) $row['adm_dic_puntualidad'] : null,
            admDicAsistencia:  isset($row['adm_dic_asistencia'])  ? (float) $row['adm_dic_asistencia']  : null,
            admEnePuntualidad: isset($row['adm_ene_puntualidad']) ? (float) $row['adm_ene_puntualidad'] : null,
            admEneAsistencia:  isset($row['adm_ene_asistencia'])  ? (float) $row['adm_ene_asistencia']  : null,
            admFebPuntualidad: isset($row['adm_feb_puntualidad']) ? (float) $row['adm_feb_puntualidad'] : null,
            admFebAsistencia:  isset($row['adm_feb_asistencia'])  ? (float) $row['adm_feb_asistencia']  : null,
            admMarPuntualidad: isset($row['adm_mar_puntualidad']) ? (float) $row['adm_mar_puntualidad'] : null,
            admMarAsistencia:  isset($row['adm_mar_asistencia'])  ? (float) $row['adm_mar_asistencia']  : null,

            docDicPagado: (bool) ($row['doc_dic_pagado'] ?? false),
            docMarPagado: (bool) ($row['doc_mar_pagado'] ?? false),

            firmaCurp: isset($row['firma_curp']) ? (string) $row['firma_curp'] : null,
            createdAt: $this->parseDateTime($row['created_at'] ?? null),
            updatedAt: $this->parseDateTime($row['updated_at'] ?? null),
        );
    }

    private function parseDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
