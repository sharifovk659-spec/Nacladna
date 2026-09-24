<?php

namespace App\Middleware;

use App\Core\Response;

class OwnerMiddleware
{
    public static function check(): void
    {
        CurrentCompanyMiddleware::check();
        if (!CurrentCompanyMiddleware::isOwner()) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                Response::json(['error' => 'Только владелец компании'], 403);
            }
            http_response_code(403);
            echo 'Доступ запрещён';
            exit;
        }
    }
}
