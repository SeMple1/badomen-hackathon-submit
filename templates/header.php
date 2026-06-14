<?php
$headerAuthenticated = isset($_SESSION['user_id']);
$headerCurrentPath = strtolower(stripAppBasePath((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')));
$headerDisplayName = trim((string)($_SESSION['user_name'] ?? 'บัญชีของฉัน'));
$headerInitial = $headerDisplayName !== '' ? mb_substr($headerDisplayName, 0, 1, 'UTF-8') : 'B';
$headerAvatarUrl = '';
$headerIsGold = false;
$headerEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$headerNormalizeImagePath = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?://|data:image/)#i', $path)) {
        return $path;
    }
    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($localPath) ? '/' . $relativePath : '';
};
if ($headerAuthenticated && function_exists('badomenFetchVipProfile')) {
    try {
        $headerConn = getConnection();
        $headerVipProfile = badomenFetchVipProfile($headerConn, (int)$_SESSION['user_id']);
        $headerConn->close();
        $headerAvatarUrl = $headerNormalizeImagePath((string)($headerVipProfile['avatar_path'] ?? ''));
        $headerIsGold = (bool)($headerVipProfile['is_vip'] ?? false);
    } catch (Throwable) {
        $headerAvatarUrl = '';
        $headerIsGold = false;
    }
}
$headerIsActive = static function (array $paths) use ($headerCurrentPath): bool {
    $current = rtrim($headerCurrentPath, '/') ?: '/';

    foreach ($paths as $path) {
        $path = strtolower(rtrim((string)$path, '/')) ?: '/';

        if ($current === $path) {
            return true;
        }

        if ($path !== '/' && strpos($current . '/', $path . '/') === 0) {
            return true;
        }
    }

    return false;
};
$headerActiveClass = static fn(array $paths): string => $headerIsActive($paths) ? ' is-active' : '';
$headerAriaCurrent = static fn(array $paths): string => $headerIsActive($paths) ? ' aria-current="page"' : '';
?>

<?php if (empty($headerAssetsLoaded)): ?>
    <link rel="stylesheet" href="/style/header.css?v=11">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php endif; ?>

<header id="siteHeader" class="site-header" role="banner">
    <div class="site-header__surface">
        <div class="site-header__inner">
            <a class="site-brand" href="<?= $headerAuthenticated ? '/home_in' : '/' ?>" aria-label="Badomen Find your next moment หน้าแรก">
                <span class="site-brand__mark">
                    <img src="/assets/badomen-logo.webp" alt="" width="44" height="44">
                </span>
                <span class="site-brand__copy">
                    <strong>Badomen</strong>
                    <small>Find your next moment</small>
                </span>
            </a>

            <nav class="site-nav" aria-label="เมนูหลัก">
                <?php if ($headerAuthenticated): ?>
                    <a class="site-nav__link<?= $headerActiveClass(['/home_in']) ?>" href="/home_in"<?= $headerAriaCurrent(['/home_in']) ?>>
                        <i class="fa-solid fa-compass" aria-hidden="true"></i>
                        <span>ค้นหากิจกรรม</span>
                    </a>
                    <a class="site-nav__link<?= $headerActiveClass(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>" href="/join_activity"<?= $headerAriaCurrent(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>>
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        <span>กิจกรรมของฉัน</span>
                    </a>
                    <a class="site-nav__link<?= $headerActiveClass(['/dashboard', '/create_activity', '/editing_activity', '/participants']) ?>" href="/dashboard"<?= $headerAriaCurrent(['/dashboard', '/create_activity', '/editing_activity', '/participants']) ?>>
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        <span>จัดการกิจกรรม</span>
                    </a>
                <?php else: ?>
                    <a class="site-nav__link<?= $headerActiveClass(['/', '/home']) ?>" href="/"<?= $headerAriaCurrent(['/', '/home']) ?>>
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>หน้าแรก</span>
                    </a>
                    <a class="site-nav__link" href="/#activityCards">
                        <i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i>
                        <span>กิจกรรมมาแรง</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="site-header__actions">
                <?php if ($headerAuthenticated): ?>
                    <div class="site-account" data-account-menu>
                        <button class="site-account__button" type="button" data-account-button
                            aria-haspopup="true" aria-expanded="false">
                            <span class="site-account__avatar<?= $headerIsGold ? ' is-gold' : '' ?>"><?php if ($headerAvatarUrl !== ''): ?><img src="<?= $headerEscape($headerAvatarUrl) ?>" alt="รูปโปรไฟล์" onerror="this.remove()"><?php else: ?><?= $headerEscape($headerInitial) ?><?php endif; ?></span>
                            <span class="site-account__copy">
                                <small>สวัสดี</small>
                                <strong><?= $headerEscape($headerDisplayName) ?></strong>
                            </span>
                            <i class="fa-solid fa-chevron-down site-account__chevron" aria-hidden="true"></i>
                        </button>

                        <div class="site-account__dropdown" data-account-panel>
                            <div class="site-account__summary">
                                <span class="site-account__avatar site-account__avatar--large<?= $headerIsGold ? ' is-gold' : '' ?>"><?php if ($headerAvatarUrl !== ''): ?><img src="<?= $headerEscape($headerAvatarUrl) ?>" alt="รูปโปรไฟล์" onerror="this.remove()"><?php else: ?><?= $headerEscape($headerInitial) ?><?php endif; ?></span>
                                <div>
                                    <strong><?= $headerEscape($headerDisplayName) ?></strong>
                                    <small><?= $headerEscape((string)($_SESSION['user_email'] ?? '')) ?></small>
                                </div>
                            </div>
                            <button class="site-account__item<?= $headerActiveClass(['/notifications']) ?>" type="button" data-notification-open<?= $headerAriaCurrent(['/notifications']) ?>>
                                <i class="fa-regular fa-bell" aria-hidden="true"></i><span>การแจ้งเตือน</span>
                            </button>
                            <a class="site-account__item<?= $headerActiveClass(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>" href="/join_activity"<?= $headerAriaCurrent(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>>
                                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>กิจกรรมของฉัน</span>
                            </a>
                            <a class="site-account__item<?= $headerActiveClass(['/profile', '/edit_profile']) ?>" href="/profile"<?= $headerAriaCurrent(['/profile', '/edit_profile']) ?>>
                                <i class="fa-regular fa-user" aria-hidden="true"></i><span>โปรไฟล์ของฉัน</span>
                            </a>
                            <a class="site-account__item site-account__item--gold<?= $headerActiveClass(['/vip']) ?>" href="/vip"<?= $headerAriaCurrent(['/vip']) ?>>
                                <i class="fa-solid fa-crown" aria-hidden="true"></i><span>สมัคร Gold VIP</span>
                            </a>
                            <a class="site-account__item<?= $headerActiveClass(['/create_activity']) ?>" href="/create_activity"<?= $headerAriaCurrent(['/create_activity']) ?>>
                                <i class="fa-solid fa-plus" aria-hidden="true"></i><span>สร้างกิจกรรม</span>
                            </a>
                            <a class="site-account__item<?= $headerActiveClass(['/dashboard', '/editing_activity', '/participants']) ?>" href="/dashboard"<?= $headerAriaCurrent(['/dashboard', '/editing_activity', '/participants']) ?>>
                                <i class="fa-solid fa-table-columns" aria-hidden="true"></i><span>แดชบอร์ด</span>
                            </a>
                            <div class="site-account__separator"></div>
                            <a class="site-account__item site-account__item--danger" href="/logout">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i><span>ออกจากระบบ</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="site-header__login" href="/login">เข้าสู่ระบบ</a>
                    <a class="site-header__cta" href="/register">
                        <span>สมัครฟรี</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <button id="mobileMenuBtn" class="site-menu-button" type="button"
                    aria-label="เปิดเมนู" aria-controls="mobileMenu" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    <div id="mobileMenu" class="site-mobile-menu" aria-hidden="true">
        <div class="site-mobile-menu__inner">
            <?php if ($headerAuthenticated): ?>
                <div class="site-mobile-profile">
                    <span class="site-account__avatar site-account__avatar--large<?= $headerIsGold ? ' is-gold' : '' ?>"><?php if ($headerAvatarUrl !== ''): ?><img src="<?= $headerEscape($headerAvatarUrl) ?>" alt="รูปโปรไฟล์" onerror="this.remove()"><?php else: ?><?= $headerEscape($headerInitial) ?><?php endif; ?></span>
                    <div>
                        <small>เข้าสู่ระบบในชื่อ</small>
                        <strong><?= $headerEscape($headerDisplayName) ?></strong>
                    </div>
                </div>
                <a class="<?= $headerActiveClass(['/home_in']) ?>" href="/home_in"<?= $headerAriaCurrent(['/home_in']) ?>><i class="fa-solid fa-compass" aria-hidden="true"></i><span>ค้นหากิจกรรม</span></a>
                <a class="<?= $headerActiveClass(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>" href="/join_activity"<?= $headerAriaCurrent(['/join_activity', '/my_activity', '/tickets', '/my_tickets']) ?>><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>กิจกรรมของฉัน</span></a>
                <a class="<?= $headerActiveClass(['/dashboard', '/editing_activity', '/participants']) ?>" href="/dashboard"<?= $headerAriaCurrent(['/dashboard', '/editing_activity', '/participants']) ?>><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>จัดการกิจกรรม</span></a>
                <a class="<?= $headerActiveClass(['/create_activity']) ?>" href="/create_activity"<?= $headerAriaCurrent(['/create_activity']) ?>><i class="fa-solid fa-plus" aria-hidden="true"></i><span>สร้างกิจกรรมใหม่</span></a>
                <button class="<?= $headerActiveClass(['/notifications']) ?>" type="button" data-notification-open<?= $headerAriaCurrent(['/notifications']) ?>><i class="fa-regular fa-bell" aria-hidden="true"></i><span>การแจ้งเตือน</span></button>
                <a class="<?= $headerActiveClass(['/profile', '/edit_profile']) ?>" href="/profile"<?= $headerAriaCurrent(['/profile', '/edit_profile']) ?>><i class="fa-regular fa-user" aria-hidden="true"></i><span>โปรไฟล์</span></a>
                <a class="<?= $headerActiveClass(['/vip']) ?>" href="/vip"<?= $headerAriaCurrent(['/vip']) ?>><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Gold VIP</span></a>
                <a class="site-mobile-menu__danger" href="/logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i><span>ออกจากระบบ</span>
                </a>
            <?php else: ?>
                <span class="site-mobile-menu__eyebrow">สำรวจกิจกรรมกับ Badomen</span>
                <a class="<?= $headerActiveClass(['/', '/home']) ?>" href="/"<?= $headerAriaCurrent(['/', '/home']) ?>><i class="fa-solid fa-house" aria-hidden="true"></i><span>หน้าแรก</span></a>
                <a href="/#activityCards"><i class="fa-solid fa-fire-flame-curved"></i><span>กิจกรรมมาแรง</span></a>
                <div class="site-mobile-menu__auth">
                    <a class="site-mobile-menu__login" href="/login">เข้าสู่ระบบ</a>
                    <a class="site-mobile-menu__cta" href="/register">สมัครสมาชิกฟรี</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if ($headerAuthenticated): ?>
<div class="notification-popup" id="notificationPopup" aria-hidden="true">
    <div class="notification-popup__backdrop" data-notification-close></div>
    <section class="notification-popup__panel" role="dialog" aria-modal="true" aria-labelledby="notificationPopupTitle">
        <header>
            <div>
                <span>NOTIFICATION CENTER</span>
                <h2 id="notificationPopupTitle">การแจ้งเตือน</h2>
            </div>
            <button type="button" data-notification-close aria-label="ปิด">×</button>
        </header>
        <div class="notification-popup__list" id="notificationPopupList">
            <p class="notification-popup__empty">กำลังโหลดการแจ้งเตือน...</p>
        </div>
        <footer>
            <button type="button" id="notificationMarkRead">อ่านแล้วทั้งหมด</button>
            <button type="button" id="appFeedbackOpen">ให้ feedback แอป</button>
            <a href="/notifications">เปิดหน้ารวม</a>
        </footer>
    </section>
</div>
<?php require __DIR__ . '/partials/experience_popup.php'; ?>
<?php endif; ?>

<script>
(() => {
    const header = document.querySelector('#siteHeader');
    const menuButton = document.querySelector('#mobileMenuBtn');
    const mobileMenu = document.querySelector('#mobileMenu');
    const account = document.querySelector('[data-account-menu]');
    const accountButton = document.querySelector('[data-account-button]');
    const notificationPopup = document.getElementById('notificationPopup');
    const notificationList = document.getElementById('notificationPopupList');
    const notificationMarkRead = document.getElementById('notificationMarkRead');
    let notificationCsrf = '';
    let lastScrollY = window.scrollY;
    let scrollFrame = 0;

    const closeMobile = () => {
        menuButton?.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
        mobileMenu?.classList.remove('is-open');
        mobileMenu?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('has-mobile-menu');
    };

    const closeAccount = () => {
        account?.classList.remove('is-open');
        accountButton?.setAttribute('aria-expanded', 'false');
    };

    const closeNotifications = () => {
        notificationPopup?.classList.remove('is-open');
        notificationPopup?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('notification-open');
    };

    const renderNotifications = (items) => {
        if (!notificationList) return;
        notificationList.textContent = '';
        if (!items.length) {
            notificationList.innerHTML = '<p class="notification-popup__empty">ยังไม่มีการแจ้งเตือน</p>';
            return;
        }
        items.slice(0, 20).forEach((item) => {
            const link = document.createElement('a');
            link.href = item.action_url || '/notifications';
            link.className = 'notification-popup__item' + (item.read ? '' : ' is-unread');
            const strong = document.createElement('strong');
            strong.textContent = item.title;
            const body = document.createElement('p');
            body.textContent = item.body;
            const time = document.createElement('time');
            time.textContent = item.created_at;
            link.append(strong, body, time);
            notificationList.appendChild(link);
        });
    };

    const openNotifications = async () => {
        closeAccount();
        closeMobile();
        notificationPopup?.classList.add('is-open');
        notificationPopup?.setAttribute('aria-hidden', 'false');
        document.body.classList.add('notification-open');
        try {
            const response = await fetch('/notifications?format=json', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            notificationCsrf = data.csrf || '';
            renderNotifications(data.notifications || []);
        } catch (_) {
            if (notificationList) notificationList.innerHTML = '<p class="notification-popup__empty">โหลดการแจ้งเตือนไม่สำเร็จ</p>';
        }
    };

    menuButton?.addEventListener('click', () => {
        const open = !mobileMenu?.classList.contains('is-open');
        closeAccount();
        menuButton.classList.toggle('is-open', open);
        menuButton.setAttribute('aria-expanded', String(open));
        mobileMenu?.classList.toggle('is-open', open);
        mobileMenu?.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('has-mobile-menu', open);
    });

    accountButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = !account?.classList.contains('is-open');
        closeMobile();
        account?.classList.toggle('is-open', open);
        accountButton.setAttribute('aria-expanded', String(open));
    });

    mobileMenu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobile));
    document.addEventListener('click', closeAccount);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobile();
            closeAccount();
            closeNotifications();
        }
    });

    document.querySelectorAll('[data-notification-open]').forEach((button) => button.addEventListener('click', openNotifications));
    document.querySelectorAll('[data-notification-close]').forEach((button) => button.addEventListener('click', closeNotifications));
    notificationMarkRead?.addEventListener('click', async () => {
        if (!notificationCsrf) return;
        await fetch('/notifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            credentials: 'same-origin',
            body: new URLSearchParams({ _csrf: notificationCsrf })
        });
        notificationList?.querySelectorAll('.is-unread').forEach((item) => item.classList.remove('is-unread'));
    });
    document.getElementById('appFeedbackOpen')?.addEventListener('click', () => {
        closeNotifications();
        window.BadomenExperience?.open({ feedback_type: 'app' });
    });

    window.addEventListener('scroll', () => {
        if (scrollFrame) return;
        scrollFrame = requestAnimationFrame(() => {
            const currentY = window.scrollY;
            header?.classList.toggle('is-compact', currentY > 16);
            header?.classList.toggle('is-hidden', currentY > lastScrollY && currentY > 180);
            if (Math.abs(currentY - lastScrollY) > 4) lastScrollY = currentY;
            scrollFrame = 0;
        });
    }, { passive: true });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 820) closeMobile();
    });
})();

</script>
