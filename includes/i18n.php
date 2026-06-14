<?php

declare(strict_types=1);

const SUPPORTED_LOCALES = ['th', 'en'];
const LOCALE_COOKIE = 'badomen_locale';

function currentLocale(): string
{
    static $locale;
    if (is_string($locale)) {
        return $locale;
    }

    $candidate = strtolower((string)($_SESSION['locale'] ?? $_COOKIE[LOCALE_COOKIE] ?? 'th'));
    $locale = in_array($candidate, SUPPORTED_LOCALES, true) ? $candidate : 'th';
    return $locale;
}

function setCurrentLocale(string $locale): void
{
    $locale = strtolower(trim($locale));
    if (!in_array($locale, SUPPORTED_LOCALES, true)) {
        $locale = 'th';
    }

    $_SESSION['locale'] = $locale;
    setcookie(LOCALE_COOKIE, $locale, [
        'expires' => time() + (365 * 86400),
        'path' => appBasePath() . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function translate(string $key, array $replace = []): string
{
    static $catalog = [
        'th' => [
            'brand.tagline' => 'ค้นหาและจัดการกิจกรรมในที่เดียว',
            'footer.discover' => 'ค้นหากิจกรรม',
            'footer.my_events' => 'กิจกรรมของฉัน',
            'footer.organizer' => 'สำหรับผู้จัดงาน',
            'footer.manage' => 'จัดการกิจกรรม',
            'footer.participants' => 'ตรวจสอบผู้เข้าร่วม',
            'footer.account' => 'บัญชีและระบบ',
            'footer.login' => 'เข้าสู่ระบบ',
            'footer.register' => 'สมัครสมาชิก',
            'footer.newsletter' => 'ข่าวสารกิจกรรม',
            'footer.newsletter_hint' => 'ระบบแจ้งเตือนทางเว็บและอีเมลจะใช้ข้อมูลกิจกรรมจริงเท่านั้น',
            'footer.language' => 'ภาษา',
            'common.thai' => 'ไทย',
            'common.english' => 'English',
        ],
        'en' => [
            'brand.tagline' => 'Discover and manage events in one place',
            'footer.discover' => 'Discover events',
            'footer.my_events' => 'My events',
            'footer.organizer' => 'For organizers',
            'footer.manage' => 'Manage events',
            'footer.participants' => 'Manage attendees',
            'footer.account' => 'Account',
            'footer.login' => 'Sign in',
            'footer.register' => 'Create account',
            'footer.newsletter' => 'Event updates',
            'footer.newsletter_hint' => 'Web and email notifications use real event data only.',
            'footer.language' => 'Language',
            'common.thai' => 'ไทย',
            'common.english' => 'English',
        ],
    ];

    $value = $catalog[currentLocale()][$key] ?? $catalog['th'][$key] ?? $key;
    foreach ($replace as $name => $replacement) {
        $value = str_replace(':' . $name, (string)$replacement, $value);
    }
    return $value;
}

function t(string $key, array $replace = []): string
{
    return translate($key, $replace);
}

function localizeDocumentHtml(string $html): string
{
    $locale = currentLocale();

    return (string)preg_replace_callback(
        '/<html\b([^>]*)>/i',
        static function (array $matches) use ($locale): string {
            $attributes = (string)$matches[1];
            $attributes = preg_replace('/\s+lang=(["\']).*?\1/i', '', $attributes) ?? $attributes;
            $attributes = preg_replace('/\s+data-theme=(["\']).*?\1/i', '', $attributes) ?? $attributes;
            return '<html lang="' . $locale . '"' . $attributes . '>';
        },
        $html,
        1
    );
}
