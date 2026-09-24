<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findByTelegramId(int $telegramId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare("SELECT * FROM users WHERE telegram_id = ? LIMIT 1");
        $st->execute([$telegramId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function createOrUpdate(array $tgUser): array
    {
        $db = Database::getInstance();
        $existing = self::findByTelegramId((int)$tgUser['id']);

        if ($existing) {
            $db->prepare(
                "UPDATE users SET telegram_username=?, first_name=?, last_name=?, photo_url=?, last_login_at=NOW(), updated_at=NOW() WHERE id=?"
            )->execute([
                $tgUser['username'] ?? null,
                $tgUser['first_name'] ?? '',
                $tgUser['last_name'] ?? '',
                $tgUser['photo_url'] ?? null,
                $existing['id'],
            ]);
            return self::findByTelegramId((int)$tgUser['id']);
        }

        $db->prepare(
            "INSERT INTO users (telegram_id, telegram_username, first_name, last_name, photo_url, status, last_login_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())"
        )->execute([
            (int)$tgUser['id'],
            $tgUser['username'] ?? null,
            $tgUser['first_name'] ?? '',
            $tgUser['last_name'] ?? '',
            $tgUser['photo_url'] ?? null,
        ]);

        return self::findByTelegramId((int)$tgUser['id']);
    }

    public static function getCompany(int $userId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT c.*, cu.role FROM companies c
             JOIN company_users cu ON cu.company_id = c.id
             WHERE cu.user_id = ? AND cu.status = 'active' AND c.status = 'active'
             ORDER BY c.id ASC LIMIT 1"
        );
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: null;
    }
}
