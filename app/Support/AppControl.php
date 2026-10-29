<?php

namespace App\Support;

/**
 * Admin › App control: maintenance mode, the app versions the API accepts,
 * and whether the app's API needs a verified email. Stored in AppSettings;
 * read by the middleware (WebMaintenance, AppGate, RequireVerifiedEmail)
 * and served to the app at GET /api/v1/app-status.
 */
class AppControl
{
    public const MAINTENANCE = 'maintenance';
    public const MAINTENANCE_MESSAGE = 'maintenance_message';
    public const MIN_VERSION = 'min_version';
    public const LATEST_VERSION = 'latest_version';
    public const UPDATE_MESSAGE = 'update_message';
    public const EMAIL_VERIFICATION = 'email_verification_required';

    /*
     * The messages the server sends on its own. Everyone gets them; only an
     * admin can switch one off. Stored as `false` when off, so a missing key
     * means on.
     */
    public const DAILY_REMINDER = 'daily_reminder';
    public const DAILY_REMINDER_AT = 'daily_reminder_at';
    public const BUDGET_ALERT_PUSH = 'budget_alert_push';
    public const MONTHLY_SUMMARY = 'monthly_summary';
    public const BIRTHDAY_WISHES = 'birthday_wishes';

    /** 9 p.m. India time, after most of the day's spending. */
    public const DEFAULT_REMINDER_AT = '21:00';

    public const DEFAULT_MAINTENANCE_MESSAGE = 'Expense Tracker is getting an upgrade. We will be back shortly — your data is safe.';

    public const DEFAULT_UPDATE_MESSAGE = 'This version of Expense Tracker is no longer supported. Update the app to carry on.';

    /** "1.2.0": up to four numbers separated by dots. */
    public const VERSION_PATTERN = '/^\d{1,4}(\.\d{1,4}){0,3}$/';

    public static function maintenance(): bool
    {
        return (bool) AppSettings::get(self::MAINTENANCE, false);
    }

    /** What people are told while it is on: the admin's words, or a friendly default. */
    public static function maintenanceMessage(): string
    {
        $message = trim((string) AppSettings::get(self::MAINTENANCE_MESSAGE, ''));

        return $message !== '' ? $message : self::DEFAULT_MAINTENANCE_MESSAGE;
    }

    public static function customMaintenanceMessage(): ?string
    {
        return AppSettings::get(self::MAINTENANCE_MESSAGE);
    }

    public static function minVersion(): ?string
    {
        return AppSettings::get(self::MIN_VERSION);
    }

    public static function latestVersion(): ?string
    {
        return AppSettings::get(self::LATEST_VERSION);
    }

    public static function updateMessage(): ?string
    {
        return AppSettings::get(self::UPDATE_MESSAGE);
    }

    /**
     * Whether the app's API turns away unverified accounts: the admin's
     * switch once it has been used, else EMAIL_VERIFICATION_REQUIRED_FOR_API.
     */
    public static function emailVerificationRequired(): bool
    {
        $setting = AppSettings::get(self::EMAIL_VERIFICATION);

        return $setting === null ? (bool) config('services.email_check.required_for_api') : (bool) $setting;
    }

    /** True once an admin has set it; false while it follows the server's .env. */
    public static function emailVerificationSetHere(): bool
    {
        return AppSettings::has(self::EMAIL_VERIFICATION);
    }

    /** The daily reminder's time ("21:00", India time), or null while it is off. */
    public static function dailyReminderAt(): ?string
    {
        if (AppSettings::get(self::DAILY_REMINDER) === false) return null;

        return AppSettings::get(self::DAILY_REMINDER_AT, self::DEFAULT_REMINDER_AT);
    }

    public static function budgetAlertPush(): bool
    {
        return AppSettings::get(self::BUDGET_ALERT_PUSH) !== false;
    }

    public static function monthlySummary(): bool
    {
        return AppSettings::get(self::MONTHLY_SUMMARY) !== false;
    }

    public static function birthdayWishes(): bool
    {
        return AppSettings::get(self::BIRTHDAY_WISHES) !== false;
    }

    /**
     * Whether an app that sent this X-App-Version must update first. No
     * header, or one that is not a version, is never blocked: builds from
     * before the header (1.1.0 and older) cannot be told apart.
     */
    public static function isOutdated(?string $appVersion): bool
    {
        $min = self::minVersion();
        $version = self::parseVersion($appVersion);
        if (! $min || ! $version) return false;

        return self::compare($version, $min) < 0;
    }

    /** "1.2.0" from "1.2.0", "v1.2.0" or "1.2.0 (45)"; null when there is none. */
    public static function parseVersion(?string $value): ?string
    {
        return preg_match('/^\s*v?(\d{1,4}(?:\.\d{1,4}){0,3})/i', (string) $value, $m) ? $m[1] : null;
    }

    /** -1, 0 or 1. Missing parts count as 0, so 1.2 equals 1.2.0. */
    public static function compare(string $a, string $b): int
    {
        $left = array_map('intval', explode('.', $a));
        $right = array_map('intval', explode('.', $b));
        $length = max(count($left), count($right));

        return array_pad($left, $length, 0) <=> array_pad($right, $length, 0);
    }

    /** The body of GET /api/v1/app-status. */
    public static function status(): array
    {
        $maintenance = self::maintenance();

        return [
            'maintenance'                 => $maintenance,
            'maintenance_message'         => $maintenance ? self::maintenanceMessage() : null,
            'min_version'                 => self::minVersion(),
            'latest_version'              => self::latestVersion(),
            'update_message'              => self::updateMessage(),
            'email_verification_required' => self::emailVerificationRequired(),
        ];
    }
}
