<?php

namespace App\Core;

class Logger
{
    private static string $logDir = '';

    private static function getLogDir(): string
    {
        if (self::$logDir === '') {
            self::$logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $date    = date('Y-m-d');
        $time    = date('Y-m-d H:i:s');
        $file    = self::getLogDir() . "/app-{$date}.log";
        $ctx     = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line    = "[{$time}] [{$level}] {$message}{$ctx}" . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $msg, array $ctx = []): void  { self::write('INFO',  $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('ERROR', $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void  { self::write('WARN',  $msg, $ctx); }
    public static function debug(string $msg, array $ctx = []): void { self::write('DEBUG', $msg, $ctx); }
}
