<?php
$trustedLogoSources = [
    [
        'name' => 'คณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม',
        'label' => 'IT MSU',
        'desc' => 'Information Technology',
        'logo' => '/assets/IT-LOGO.webp',
    ],
    [
        'name' => 'มหาวิทยาลัยมหาสารคาม',
        'label' => 'MSU',
        'desc' => 'Mahasarakham University',
        'logo' => '/assets/MSU-LOGO.png',
    ],
];

$trustedOrganizations = [];
for ($i = 0; $i < 18; $i++) {
    $trustedOrganizations[] = $trustedLogoSources[$i % count($trustedLogoSources)];
}
?>

<section class="trusted-section" aria-labelledby="trusted-title">
    <div class="trusted-inner">
        <div class="trusted-head">
            <h2 id="trusted-title">องค์กรที่ไว้วางใจเรา</h2>
            <p>เครือข่ายจากมหาวิทยาลัยและคณะ พร้อมพื้นที่กิจกรรมสำหรับนักศึกษา</p>
        </div>

        <div class="trusted-logos" aria-label="โลโก้หน่วยงานที่เกี่ยวข้อง">
            <div class="trusted-logo-track">
                <div class="trusted-logo-group">
                    <?php foreach ($trustedOrganizations as $org): ?>
                        <div
                            class="trusted-logo-card"
                            aria-label="<?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <img
                                src="<?= htmlspecialchars($org['logo'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                decoding="async"
                            >
                            <small><?= htmlspecialchars($org['desc'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="trusted-logo-group" aria-hidden="true">
                    <?php foreach ($trustedOrganizations as $org): ?>
                        <div class="trusted-logo-card">
                            <img
                                src="<?= htmlspecialchars($org['logo'], ENT_QUOTES, 'UTF-8') ?>"
                                alt=""
                                loading="lazy"
                                decoding="async"
                            >
                            <small><?= htmlspecialchars($org['desc'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
