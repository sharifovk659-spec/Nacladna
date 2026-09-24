<?php

namespace App\Services;

use App\Core\Database;

class ActivityLogger
{
    public static function log(
        string $action,
        ?int $companyId = null,
        ?int $userId    = null,
        ?string $entityType = null,
        ?int $entityId  = null,
        array $metadata = []
    ): void {
        try {
            $db  = Database::getInstance();
            $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
            $meta = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
            $db->prepare("INSERT INTO activity_logs (company_id, user_id, action, entity_type, entity_id, metadata_json, ip_address)
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$companyId, $userId, $action, $entityType, $entityId, $meta, $ip]);
        } catch (\Throwable) {
            // never break main flow due to logging error
        }
    }
}
