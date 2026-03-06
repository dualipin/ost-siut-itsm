<?php

namespace App\Modules\Setting\Repository;

use App\Infrastructure\Persistence\Repository\BaseRepository;
use App\Modules\Setting\Entity\Color;

final class SettingRepository extends BaseRepository implements
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
        $this->pdo->exec("INSERT IGNORE INTO system_colors (id) VALUES (1)");

        $stmt = $this->pdo->prepare("
        UPDATE system_colors SET
            c_primary = :primary,
            c_secondary = :secondary,
            c_success = :success,
            c_info = :info,
            c_warning = :warning,
            c_danger = :danger,
            c_light = :light,
            c_dark = :dark,
            c_white = :white,
            c_body = :body,
            c_body_background = :body_background
        WHERE id = 1
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
}
