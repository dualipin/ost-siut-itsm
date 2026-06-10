<?php

namespace App\Infrastructure\Templating\Latte;

use App\Infrastructure\Config\AppConfig;
use App\Shared\Utils\UrlBuilder;
use Latte\Extension;

class LatteExtension extends Extension
{
    public function __construct(
        private readonly UrlBuilder $urlBuilder,
        private readonly AppConfig $settings,
        private readonly ViteHelper $viteHelper,
    ) {}

    public function getFunctions(): array
    {
        return [
            "url" => $this->urlBuilder->to(...),
            "upload" => $this->resolveUploadUrl(...),
            "download" => $this->resolveDownloadUrl(...),
            "vite" => $this->viteHelper->generateTags(...),
        ];
    }

    public function resolveUploadUrl(?string $path): string
    {
        if (!$path) {
            return $this->urlBuilder->to("/assets/images/logo.webp");
        }

        return $this->urlBuilder->to(
            "{$this->settings->upload->publicUrl}/{$path}",
        );
    }

    public function resolveDownloadUrl(?string $path): string
    {
        if (!$path) {
            return '#';
        }

        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $uploadsPosition = strpos($normalizedPath, 'uploads/');

        if ($uploadsPosition !== false) {
            $normalizedPath = substr($normalizedPath, $uploadsPosition);
        } else {
            $normalizedPath = 'uploads/' . ltrim($normalizedPath, '/');
        }

        $normalizedPath = ltrim($normalizedPath, '/');

        return $this->urlBuilder->to('/descargar.php', [
            'path' => $normalizedPath,
        ]);
    }
}
