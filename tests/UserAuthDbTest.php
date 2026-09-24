<?php

namespace Tests;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserAuthDbTest extends TestCase
{
    private static bool $ready = false;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $root);
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }
        if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }
        try {
            Database::getInstance()->query('SELECT 1');
            self::$ready = true;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('Database not available');
        }
    }

    public function testCreateOrUpdateUserAndDuplicateLogin(): void
    {
        $tgId = 900000000 + random_int(1, 999999);
        $created = User::createOrUpdate([
            'id' => $tgId,
            'first_name' => 'New',
            'last_name' => 'User',
            'username' => 'new_user_' . $tgId,
            'photo_url' => 'https://example.com/a.jpg',
        ]);

        $this->assertNotEmpty($created['id']);
        $this->assertSame((string)$tgId, (string)$created['telegram_id']);
        $this->assertSame('New', $created['first_name']);

        $updated = User::createOrUpdate([
            'id' => $tgId,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'username' => 'updated_' . $tgId,
            'photo_url' => 'https://example.com/b.jpg',
        ]);

        $this->assertSame($created['id'], $updated['id']);
        $this->assertSame('Updated', $updated['first_name']);
        $this->assertSame('updated_' . $tgId, $updated['telegram_username']);
        $this->assertNotEmpty($updated['last_login_at']);
        $this->assertNull(User::getCompany((int)$updated['id']));
    }
}
