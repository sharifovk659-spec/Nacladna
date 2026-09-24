<?php

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function view(string $template, array $data = []): void
    {
        extract($data);
        $tpl = dirname(__DIR__, 2) . '/views/' . $template . '.php';
        if (!file_exists($tpl)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template);
            exit;
        }
        require $tpl;
    }
}
