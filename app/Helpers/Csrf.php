<?php

namespace App\Helpers;

class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function generate(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function token(): string
    {
        return self::generate();
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function verify(string $token): bool
    {
        $stored = $_SESSION[self::TOKEN_KEY] ?? '';
        return $stored !== '' && hash_equals($stored, $token);
    }

    public static function verifyRequest(): void
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!self::verify($token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid CSRF token']));
        }
    }
}
