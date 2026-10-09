<?php
/** @var array<int, array<string, mixed>> $categories */
/** @var array<string, array<int, array<string, mixed>>> $servicesByCategory */
$categories = $categories ?? [];
$servicesByCategory = $servicesByCategory ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Katalog layanan</h2>
    </div>
    <?php if ($categories === []): ?>
        <p class="lede">BELUM ADA DATA.</p>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <section class="service-category">
                <h3><?= e((string) $category['name']) ?></h3>
                <?php $categoryServices = $servicesByCategory[$category['code']] ?? []; ?>
                <?php if ($categoryServices === []): ?>
                    <p>BELUM ADA DATA.</p>
                <?php else: ?>
                    <ul class="service-list">
                        <?php foreach ($categoryServices as $service): ?>
                            <li>
                                <a href="/layanan/<?= e((string) $service['code']) ?>">
                                    <strong><?= e((string) $service['code']) ?></strong>
                                    <?= e((string) $service['name']) ?>
                                </a>
                                <span><?= e(App\Core\ServiceCatalog::modeLabel((string) $service['mode'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
