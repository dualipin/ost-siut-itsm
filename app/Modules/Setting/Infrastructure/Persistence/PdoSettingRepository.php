<?php

namespace App\Modules\Setting\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Setting\Domain\Entity\Color;
use App\Modules\Setting\Domain\Repository\SettingRepositoryInterface;

final class PdoSettingRepository extends PdoBaseRepository implements
    SettingRepositoryInterface
{
    public function getColors(): ?Color
    {
        $colors = $this->pdo
            ->query("SELECT * FROM system_colors LIMIT 1")
            ->fetch();

        if ($colors) {
            return new Color(
                primary: $colors["c_primary"],
                secondary: $colors["c_secondary"],
                success: $colors["c_success"],
                info: $colors["c_info"],
                warning: $colors["c_warning"],
                danger: $colors["c_danger"],
                light: $colors["c_light"],
                dark: $colors["c_dark"],
                white: $colors["c_white"],
                body: $colors["c_body"],
                bodyBackground: $colors["c_body_background"],
            );
        }

        return null;
    }

    public function updateColors(Color $colors): void
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO system_colors (
            id, c_primary, c_secondary, c_success, c_info, c_warning, c_danger,
            c_light, c_dark, c_white, c_body, c_body_background
        ) VALUES (
            1, :primary, :secondary, :success, :info, :warning, :danger,
            :light, :dark, :white, :body, :body_background
        )
        ON DUPLICATE KEY UPDATE
            c_primary = VALUES(c_primary),
            c_secondary = VALUES(c_secondary),
            c_success = VALUES(c_success),
            c_info = VALUES(c_info),
            c_warning = VALUES(c_warning),
            c_danger = VALUES(c_danger),
            c_light = VALUES(c_light),
            c_dark = VALUES(c_dark),
            c_white = VALUES(c_white),
            c_body = VALUES(c_body),
            c_body_background = VALUES(c_body_background)
        ");

        $stmt->execute([
            ":primary" => $colors->primary,
            ":secondary" => $colors->secondary,
            ":success" => $colors->success,
            ":info" => $colors->info,
            ":warning" => $colors->warning,
            ":danger" => $colors->danger,
            ":light" => $colors->light,
            ":dark" => $colors->dark,
            ":white" => $colors->white,
            ":body" => $colors->body,
            ":body_background" => $colors->bodyBackground,
        ]);
    }

    public function resetColors(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_colors (
                id, c_primary, c_secondary, c_success, c_info, c_warning, c_danger,
                c_light, c_dark, c_white, c_body, c_body_background
            ) VALUES (
                1, '#611232', '#a57f2c', '#38b44a', '#17a2b8', '#efb73e', '#df382c',
                '#e9ecef', '#002f2a', '#ffffff', '#212529', '#f8f9fa'
            )
            ON DUPLICATE KEY UPDATE
                c_primary = '#611232',
                c_secondary = '#a57f2c',
                c_success = '#38b44a',
                c_info = '#17a2b8',
                c_warning = '#efb73e',
                c_danger = '#df382c',
                c_light = '#e9ecef',
                c_dark = '#002f2a',
                c_white = '#ffffff',
                c_body = '#212529',
                c_body_background = '#f8f9fa'
        ");
        $stmt->execute();
    }
}
