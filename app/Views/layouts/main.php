<?php
/** @var string $title */
/** @var string $viewPath */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'HUMA MANTAP') ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand-wrap">
                <div class="brand-mark" aria-label="Logo placeholder">MAN</div>
                <div>
                    <div class="eyebrow">PROTOTIPE</div>
                    <h1 class="brand-title">HUMA MANTAP</h1>
                </div>
            </div>
            <nav class="topnav" aria-label="Navigasi utama">
                <a href="/">Beranda</a>
                <a href="/layanan">Layanan</a>
                <a href="/laporan">Laporan</a>
            </nav>
        </header>

        <main class="content">
            <?php require $viewPath; ?>
        </main>
    </div>
</body>
</html>
