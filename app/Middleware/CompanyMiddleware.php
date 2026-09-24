<?php

namespace App\Middleware;

use App\Core\Response;

class CompanyMiddleware
{
    public static function check(): void
    {
        CurrentCompanyMiddleware::check();
    }

    public static function companyId(): int
    {
        return CurrentCompanyMiddleware::companyId();
    }

    public static function role(): string
    {
        return CurrentCompanyMiddleware::role();
    }

    public static function isOwner(): bool
    {
        return CurrentCompanyMiddleware::isOwner();
    }
}
