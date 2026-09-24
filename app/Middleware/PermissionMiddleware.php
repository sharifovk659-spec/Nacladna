<?php

namespace App\Middleware;

use App\Core\Response;
use App\Models\Permission;

class PermissionMiddleware
{
    public static function require(string $permission): void
    {
        CurrentCompanyMiddleware::check();
        if (!self::can($permission)) {
            $message = 'Недостаточно прав для этого действия.';
            if (self::isApi()) {
                Response::json(['error' => $message, 'code' => 'forbidden'], 403);
            }
            http_response_code(403);
            $_SESSION['flash_error'] = $message;
            Response::redirect('/dashboard');
        }
    }

    public static function can(string $permission): bool
    {
        if (CurrentCompanyMiddleware::role() === 'owner') {
            return true;
        }
        return Permission::has($permission);
    }

    private static function isApi(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}
