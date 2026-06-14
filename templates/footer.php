<?php

$currentYear = date('Y');
$footerAuthenticated = isset($_SESSION['user_id']);
$footerReturnTo = (string)($_SERVER['REQUEST_URI'] ?? '/');
$hideFooterLanguage = (bool)($hideFooterLanguage ?? false);
?>

<footer class="site-footer">
    <section class="footer-cta">
        <div class="footer-cta-text">
            <span class="footer-kicker">BADOMEN PLATFORM</span>
            <h2><?= htmlspecialchars(t('brand.tagline'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(t('footer.newsletter_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="footer-cta-actions">
                <a href="<?= $footerAuthenticated ? '/home_in' : '/login' ?>" class="footer-btn footer-btn-primary">
                    <i class="fa-solid fa-compass"></i>
                    <?= htmlspecialchars(t('footer.discover'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <a href="<?= $footerAuthenticated ? '/dashboard' : '/register' ?>" class="footer-btn footer-btn-ghost">
                    <i class="fa-solid <?= $footerAuthenticated ? 'fa-chart-simple' : 'fa-user-plus' ?>"></i>
                    <?= htmlspecialchars($footerAuthenticated ? t('footer.manage') : t('footer.register'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
        <div class="footer-visual" aria-hidden="true">
            <img class="footer-cta-image" src="/assets/welcome.webp" alt="" width="725" height="432" loading="lazy" decoding="async">
        </div>
    </section>

    <div class="footer-main">
        <div class="footer-brand">
            <strong class="footer-logo">Badomen</strong>
            <p>ค้นหาและจัดการกิจกรรมในที่เดียว ทั้งการค้นหา จองบัตร ติดตามกิจกรรม และเครื่องมือสำหรับผู้จัดงาน</p>
        </div>

        <nav class="footer-links" aria-label="Footer">
            <div class="footer-col">
                <h3><?= htmlspecialchars(t('footer.discover'), ENT_QUOTES, 'UTF-8') ?></h3>
                <a href="/home_in"><?= htmlspecialchars(t('footer.discover'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/join_activity"><?= htmlspecialchars(t('footer.my_events'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="footer-col">
                <h3><?= htmlspecialchars(t('footer.organizer'), ENT_QUOTES, 'UTF-8') ?></h3>
                <a href="/dashboard"><?= htmlspecialchars(t('footer.manage'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/create_activity"><?= htmlspecialchars(t('footer.organizer'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="footer-col">
                <h3><?= htmlspecialchars(t('footer.account'), ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if ($footerAuthenticated): ?>
                    <a href="/profile"><?= htmlspecialchars(t('footer.account'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="/logout">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="/login"><?= htmlspecialchars(t('footer.login'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="/register"><?= htmlspecialchars(t('footer.register'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </div>
        </nav>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?= $currentYear ?> Badomen</p>
        <?php if (!$hideFooterLanguage): ?>
            <form method="post" action="/language" class="footer-language-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($footerReturnTo, ENT_QUOTES, 'UTF-8') ?>">
                <label for="footerLocale"><?= htmlspecialchars(t('footer.language'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="footerLocale" name="locale" onchange="this.form.submit()">
                    <option value="th" <?= currentLocale() === 'th' ? 'selected' : '' ?>><?= htmlspecialchars(t('common.thai'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="en" <?= currentLocale() === 'en' ? 'selected' : '' ?>><?= htmlspecialchars(t('common.english'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </form>
        <?php endif; ?>
    </div>
</footer>
