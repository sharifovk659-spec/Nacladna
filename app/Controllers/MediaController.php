<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\Company;

class MediaController
{
    /** Public company logo (HTTPS-safe; no filesystem paths exposed). */
    public function companyLogo(string $companyId): void
    {
        $id = (int)$companyId;
        if ($id <= 0) {
            http_response_code(404);
            exit;
        }

        $db = Database::getInstance();
        $st = $db->prepare(
            'SELECT logo_path FROM companies WHERE id = ? AND status = ? LIMIT 1'
        );
        $st->execute([$id, 'active']);
        $row = $st->fetch();
        if (!$row || empty($row['logo_path'])) {
            http_response_code(404);
            exit;
        }

        $full = Company::resolveLogoFile((string)$row['logo_path']);
        if ($full === null) {
            http_response_code(404);
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            http_response_code(404);
            exit;
        }

        $mtime = filemtime($full) ?: time();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($full));
        header('Cache-Control: public, max-age=86400');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        readfile($full);
        exit;
    }
}
