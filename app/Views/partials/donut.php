<?php
$donutTotal = 0;
foreach ($donutItems as $donutItem) {
    $donutTotal += max(0, (int) ($donutItem['count'] ?? 0));
}
$donutOffset = 0.0;
?>
<svg class="donut" viewBox="0 0 42 42" role="img"
     aria-label="<?= e($donutTitle) ?>: total <?= (int) $donutTotal ?>">
    <circle class="donut-track" cx="21" cy="21" r="15.9155" fill="none" stroke-width="6"/>
    <?php if ($donutTotal > 0): foreach ($donutItems as $donutItem):
        $donutCount = max(0, (int) ($donutItem['count'] ?? 0));
        if ($donutCount === 0) {
            continue;
        }
        $donutPercent = $donutCount / $donutTotal * 100;
        $donutTone = min(5, max(1, (int) ($donutItem['tone'] ?? 5)));
    ?>
        <circle class="donut-seg tone-<?= $donutTone ?>" cx="21" cy="21" r="15.9155"
                fill="none" stroke-width="6"
                stroke-dasharray="<?= round($donutPercent, 2) ?> <?= round(100 - $donutPercent, 2) ?>"
                stroke-dashoffset="<?= round(25 - $donutOffset, 2) ?>"/>
        <?php $donutOffset += $donutPercent; endforeach; endif; ?>
    <text class="donut-num" x="21" y="22.5" text-anchor="middle"><?= (int) $donutTotal ?></text>
    <text class="donut-cap" x="21" y="27" text-anchor="middle">total</text>
</svg>