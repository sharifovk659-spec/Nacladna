<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\EmployeeInvite;

class JoinController
{
    public function show(string $token): void
    {
        $invite = EmployeeInvite::findPendingByToken($token);
        if (!$invite) {
            http_response_code(404);
            $pageTitle = 'Приглашение';
            $error = 'Ссылка недействительна или истекла.';
            ob_start();
            require ROOT_DIR . '/views/join/invalid.php';
            $content = ob_get_clean();
            require ROOT_DIR . '/views/layouts/app.php';
            return;
        }

        $_SESSION['employee_invite_token'] = $token;

        $pageTitle = 'Приглашение в команду';
        $companyName = (string)$invite['company_name'];
        $displayName = (string)$invite['display_name'];
        $role = (string)$invite['role'];
        $inviteToken = $token;
        ob_start();
        require ROOT_DIR . '/views/join/show.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }
}
