#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $root);
}
require $root . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($root)->safeLoad();

use App\Services\PermissionRegistry;

$fail = 0;
$pass = function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . "  {$label}\n";
    if (!$ok) {
        $fail++;
    }
};

try {
    $db = \App\Core\Database::getInstance();
    $db->query('SELECT slug FROM permissions LIMIT 1');
    $pass('permissions table', true);
    $db->query("SELECT role FROM role_permissions WHERE role='manager' AND permission_slug='invoices.view' LIMIT 1");
    $pass('role_permissions seeded', true);
    $db->query('SELECT id FROM employee_invites LIMIT 1');
    $pass('employee_invites table', true);
    $cols = $db->query("SHOW COLUMNS FROM company_users LIKE 'display_name'")->fetch();
    $pass('company_users.display_name', (bool)$cols);
} catch (\Throwable $e) {
    $pass('DB schema', false);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$pass('PermissionRegistry manager role', PermissionRegistry::isValidRole('manager'));
$pass('Owner assign admin', PermissionRegistry::canAssignRole('owner', 'admin'));
$pass('Cashier cannot assign admin', !PermissionRegistry::canAssignRole('cashier', 'admin'));

echo $fail === 0 ? "\nROLES READY CHECK PASS\n" : "\nROLES READY CHECK FAIL\n";
exit($fail === 0 ? 0 : 1);
