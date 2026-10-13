<?php
$meterValue = isset($meterValue) ? (float) $meterValue : null;
$meterMaximum = isset($meterMax) ? (float) $meterMax : 5.0;
if ($meterMaximum <= 0) {
    $meterMaximum = 5.0;
}
$meterScore = $meterValue === null ? null : max(0.0, min($meterMaximum, $meterValue));
$meterWidth = $meterScore === null ? null : $meterScore / $meterMaximum * 100;
$meterLabel = $meterScore === null
    ? 'Belum ada data'
    : 'Skor ' . number_format($meterScore, 2, ',', '.') . ' dari ' . number_format($meterMaximum, 2, ',', '.');
?>
<svg class="meter-bar" viewBox="0 0 100 8" role="img" aria-label="<?= e($meterLabel) ?>" preserveAspectRatio="none">
    <rect class="bg" width="100" height="8" rx="4"/>
    <?php if ($meterWidth !== null): ?>
        <rect class="fill" width="<?= round($meterWidth, 2) ?>" height="8" rx="4"/>
    <?php endif; ?>
</svg>