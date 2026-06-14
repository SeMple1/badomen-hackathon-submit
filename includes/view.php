<?php

declare(strict_types=1);

// ฟังก์ชันสำหรับแสดงมุมมอง (view) โดยรับชื่อเทมเพลตและข้อมูลที่ต้องการส่งไปยังเทมเพลต
function renderView(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    include TEMPLATES_DIR . '/' . $template . '.php';
    $html = ob_get_clean();
    $html = rewriteAppUrls(is_string($html) ? $html : '');
    echo localizeDocumentHtml($html);
}
