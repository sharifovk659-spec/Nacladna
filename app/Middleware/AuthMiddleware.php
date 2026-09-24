<?php

namespace App\Middleware;

use App\Core\Response;

class AuthMiddleware
{
    public static function check(): void
    {
        if (empty($_SESSION['user_id'])) {
            if (self::isApi()) {
                Response::json(['error' => 'Unauthorized'], 401);
            }
            Response::redirect('/mini-app');
        }
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    private static function isApi(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}
