<?php

namespace App\Models;

use App\Core\Database;

class Subscription
{
    public const TRIAL_DAYS = 3;
    public const PLAN_TRIAL = 'trial';
    public const PLAN_BUSINESS = 'business';

    /** @var int[] */
    public const ALLOWED_PERIODS = [1, 3, 6, 12];

    public static function findForCompany(int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT *
             FROM subscriptions
             WHERE company_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([$companyId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function historyForCompany(int $companyId, int $limit = 30): array
    {
        $db = Database::getInstance();
        $limit = max(1, min(100, $limit));
        $st = $db->prepare(
            "SELECT id, action, plan, period_months, starts_at, ends_at, status, actor_type, notes, created_at
             FROM subscription_history
             WHERE company_id = ?
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $st->execute([$companyId]);
        return $st->fetchAll() ?: [];
    }

    public static function createTrial(int $companyId): array
    {
        $existing = self::findForCompany($companyId);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $trialEnd = date('Y-m-d H:i:s', strtotime('+' . self::TRIAL_DAYS . ' days'));

        $db->prepare(
            "INSERT INTO subscriptions
             (company_id, plan, period_months, trial_start, trial_end, starts_at, ends_at, status)
             VALUES (?, ?, NULL, ?, ?, ?, ?, 'trial')"
        )->execute([
            $companyId,
            self::PLAN_TRIAL,
            $now,
            $trialEnd,
            $now,
            $trialEnd,
        ]);

        $sub = self::findForCompany($companyId);
        if (!$sub) {
            throw new \RuntimeException('Не удалось создать пробную подписку.');
        }

        self::logHistory(
            $companyId,
            (int)$sub['id'],
            'trial_started',
            self::PLAN_TRIAL,
            null,
            $now,
            $trialEnd,
            'trial',
            'system',
            null,
            'Пробный период ' . self::TRIAL_DAYS . ' дня'
        );

        return $sub;
    }

    public static function assertPeriodMonths(int $months): int
    {
        if (!in_array($months, self::ALLOWED_PERIODS, true)) {
            throw new \InvalidArgumentException('Допустимые периоды: 1, 3, 6 или 12 месяцев.');
        }
        return $months;
    }

    public static function adminActivate(int $companyId, int $periodMonths, ?int $adminUserId = null, ?string $notes = null): array
    {
        self::assertCompanyExists($companyId);
        $periodMonths = self::assertPeriodMonths($periodMonths);

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $ends = self::addMonths($now, $periodMonths);

        $sub = self::findForCompany($companyId);
        if ($sub) {
            $db->prepare(
                "UPDATE subscriptions
                 SET plan = ?, period_months = ?, trial_start = NULL, trial_end = NULL,
                     starts_at = ?, ends_at = ?, status = 'active', updated_at = NOW()
                 WHERE id = ? AND company_id = ?"
            )->execute([
                self::PLAN_BUSINESS,
                $periodMonths,
                $now,
                $ends,
                (int)$sub['id'],
                $companyId,
            ]);
        } else {
            $db->prepare(
                "INSERT INTO subscriptions
                 (company_id, plan, period_months, starts_at, ends_at, status)
                 VALUES (?, ?, ?, ?, ?, 'active')"
            )->execute([$companyId, self::PLAN_BUSINESS, $periodMonths, $now, $ends]);
        }

        $db->prepare("UPDATE companies SET status = 'active' WHERE id = ?")->execute([$companyId]);

        $updated = self::findForCompany($companyId);
        self::logHistory(
            $companyId,
            (int)($updated['id'] ?? 0),
            'activated',
            self::PLAN_BUSINESS,
            $periodMonths,
            $now,
            $ends,
            'active',
            'admin',
            $adminUserId,
            $notes ?? "Активация на {$periodMonths} мес."
        );

        return $updated ?: [];
    }

    public static function adminExtend(int $companyId, int $periodMonths, ?int $adminUserId = null, ?string $notes = null): array
    {
        self::assertCompanyExists($companyId);
        $periodMonths = self::assertPeriodMonths($periodMonths);

        $sub = self::findForCompany($companyId);
        if (!$sub) {
            return self::adminActivate($companyId, $periodMonths, $adminUserId, $notes);
        }

        $baseTs = time();
        $currentEnd = strtotime((string)($sub['ends_at'] ?? $sub['trial_end'] ?? ''));
        if ($currentEnd !== false && $currentEnd > $baseTs) {
            $baseTs = $currentEnd;
        }
        $base = date('Y-m-d H:i:s', $baseTs);
        $newEnd = self::addMonths($base, $periodMonths);

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE subscriptions
             SET plan = ?, period_months = ?, starts_at = COALESCE(starts_at, ?), ends_at = ?,
                 status = 'active', trial_start = NULL, trial_end = NULL, updated_at = NOW()
             WHERE id = ? AND company_id = ?"
        )->execute([
            self::PLAN_BUSINESS,
            $periodMonths,
            date('Y-m-d H:i:s'),
            $newEnd,
            (int)$sub['id'],
            $companyId,
        ]);

        $updated = self::findForCompany($companyId);
        self::logHistory(
            $companyId,
            (int)($updated['id'] ?? 0),
            'extended',
            self::PLAN_BUSINESS,
            $periodMonths,
            (string)($updated['starts_at'] ?? $base),
            $newEnd,
            'active',
            'admin',
            $adminUserId,
            $notes ?? "Продление на {$periodMonths} мес."
        );

        return $updated ?: [];
    }

    public static function expireIfNeeded(array $sub): void
    {
        if (($sub['status'] ?? '') === 'expired') {
            return;
        }

        $ends = self::endsAtTimestamp($sub);
        if ($ends === null || $ends >= time()) {
            return;
        }

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE subscriptions SET status = 'expired', updated_at = NOW() WHERE id = ? AND company_id = ?"
        )->execute([(int)$sub['id'], (int)$sub['company_id']]);

        self::logHistory(
            (int)$sub['company_id'],
            (int)$sub['id'],
            'expired',
            (string)($sub['plan'] ?? self::PLAN_TRIAL),
            isset($sub['period_months']) ? (int)$sub['period_months'] : null,
            (string)($sub['starts_at'] ?? ''),
            (string)($sub['ends_at'] ?? $sub['trial_end'] ?? ''),
            'expired',
            'system',
            null,
            'Срок подписки истёк'
        );
    }

    public static function isWritable(?array $sub): bool
    {
        if (!$sub) {
            return false;
        }
        if (!in_array($sub['status'] ?? '', ['trial', 'active'], true)) {
            return false;
        }
        $ends = self::endsAtTimestamp($sub);
        return $ends === null || $ends >= time();
    }

    public static function daysRemaining(?array $sub): int
    {
        if (!$sub) {
            return 0;
        }
        $ends = self::endsAtTimestamp($sub);
        if ($ends === null) {
            return 0;
        }
        return max(0, (int)ceil(($ends - time()) / 86400));
    }

    public static function sessionStatusFromRow(?array $sub): string
    {
        if (!$sub) {
            return 'expired';
        }

        $ends = self::endsAtTimestamp($sub);
        if ($ends !== null && $ends < time()) {
            return 'expired';
        }

        if (!in_array($sub['status'] ?? '', ['trial', 'active'], true)) {
            return (string)($sub['status'] ?? 'expired');
        }

        if (($sub['plan'] ?? '') === self::PLAN_TRIAL || ($sub['status'] ?? '') === 'trial') {
            return 'trial';
        }

        return 'active';
    }

    public static function planLabel(?array $sub): string
    {
        if (!$sub) {
            return '—';
        }
        if (($sub['plan'] ?? '') === self::PLAN_TRIAL) {
            return 'Пробный период (' . self::TRIAL_DAYS . ' дня)';
        }
        $months = (int)($sub['period_months'] ?? 0);
        if ($months > 0) {
            return 'Business · ' . $months . ' ' . self::monthsWord($months);
        }
        return 'Business';
    }

    public static function periodOptions(): array
    {
        return [
            1 => ['months' => 1, 'label' => '1 месяц', 'price' => '100 с.'],
            3 => ['months' => 3, 'label' => '3 месяца', 'price' => '270 с.'],
            6 => ['months' => 6, 'label' => '6 месяцев', 'price' => '500 с.'],
            12 => ['months' => 12, 'label' => '12 месяцев', 'price' => '900 с.'],
        ];
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'trial_started' => 'Старт пробного периода',
            'activated' => 'Активация',
            'extended' => 'Продление',
            'expired' => 'Истекла',
            'renewal_requested' => 'Запрос продления',
            default => $action,
        };
    }

    private static function endsAtTimestamp(array $sub): ?int
    {
        $raw = $sub['ends_at'] ?? $sub['trial_end'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $ts = strtotime((string)$raw);
        return $ts === false ? null : $ts;
    }

    private static function addMonths(string $fromDatetime, int $months): string
    {
        $dt = new \DateTimeImmutable($fromDatetime);
        return $dt->modify('+' . $months . ' months')->format('Y-m-d H:i:s');
    }

    private static function assertCompanyExists(int $companyId): void
    {
        $db = Database::getInstance();
        $st = $db->prepare('SELECT id FROM companies WHERE id = ? LIMIT 1');
        $st->execute([$companyId]);
        if (!$st->fetch()) {
            throw new \RuntimeException('Компания не найдена.');
        }
    }

    private static function monthsWord(int $n): string
    {
        if ($n === 1) {
            return 'месяц';
        }
        if ($n >= 2 && $n <= 4) {
            return 'месяца';
        }
        return 'месяцев';
    }

    public static function logHistory(
        int $companyId,
        ?int $subscriptionId,
        string $action,
        string $plan,
        ?int $periodMonths,
        ?string $startsAt,
        ?string $endsAt,
        string $status,
        string $actorType,
        ?int $actorId,
        ?string $notes
    ): void {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO subscription_history
             (company_id, subscription_id, action, plan, period_months, starts_at, ends_at, status, actor_type, actor_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $companyId,
            $subscriptionId,
            $action,
            $plan,
            $periodMonths,
            $startsAt,
            $endsAt,
            $status,
            $actorType,
            $actorId,
            $notes,
        ]);
    }
}
