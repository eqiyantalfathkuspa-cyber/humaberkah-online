<?php
/** @var array<string, mixed> $service */
/** @var string $informationStepsNotice */
/** @var string $modeLabel */
/** @var string $displayName */
$service = $service ?? [];
$displayName = $displayName ?? (string) ($service['name'] ?? '');
$standards = $service['standards'] ?? [];
$standardValue = static function (string $key) use ($standards): string {
    $value = trim((string) ($standards[$key] ?? ''));
    return $value === '' ? 'BELUM DITETAPKAN' : $value;
};
?>
<section class="page-panel">
    <p class="kicker"><?= e((string) ($service['category_name'] ?? '')) ?></p>
    <div class="section-header">
        <h2><?= e((string) ($service['code'] ?? '')) ?> — <?= e($displayName) ?></h2>
    </div>
    <p><strong>Mode:</strong> <?= e($modeLabel ?? '') ?></p>
    <?php if (($service['code'] ?? '') === 'TU-02'): ?>
        <a class="service-external-link" href="https://lynk.id/man_koraya" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 64 64" role="img" aria-label="Ilustrasi Administrasi Mandiri" focusable="false">
                <rect x="8" y="7" width="38" height="50" rx="5" fill="none" stroke="currentColor" stroke-width="3"/>
                <path d="M17 20h20M17 29h14M17 38h11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <circle cx="45" cy="43" r="13" fill="var(--card)" stroke="currentColor" stroke-width="3"/>
                <path d="M45 36v14M39 43h12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span>
                <strong>Buka Administrasi Mandiri</strong>
                <small>Akses layanan TU-02 melalui Lynk</small>
            </span>
            <span class="service-external-arrow" aria-hidden="true">↗</span>
        </a>
    <?php endif; ?>
    <?php if (($service['code'] ?? '') === 'KES-01'): ?>
        <p class="notice">Isi konseling dan data pribadi sensitif tidak disimpan di sistem.</p>
    <?php endif; ?>

    <section aria-labelledby="standar-heading">
        <h3 id="standar-heading">Standar layanan</h3>
        <dl class="service-standards">
            <dt>SLA</dt><dd><?= e($standardValue('sla_text')) ?></dd>
            <dt>Unit penanggung jawab</dt><dd><?= e($standardValue('responsible_unit')) ?></dd>
            <dt>Dokumen wajib</dt><dd><?= nl2br(e($standardValue('required_documents_text'))) ?></dd>
        </dl>
    </section>

    <section aria-labelledby="tahap-heading">
        <h3 id="tahap-heading">Tahap layanan</h3>
        <ol class="service-steps">
            <?php foreach (($service['steps'] ?? []) as $step): ?>
                <li>
                    <strong><?= e((string) $step['step_name']) ?></strong>
                    <?php if (($service['mode'] ?? '') === 'INFORMASI' && in_array((int) $step['step_no'], [3, 4, 5], true)): ?>
                        <span> — <?= e($informationStepsNotice ?? '') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php if (($service['mode'] ?? '') === 'FORMULIR'): ?>
        <?php $currentRole = App\Core\Auth::user()['role_code'] ?? null; ?>
        <?php if (App\Policies\Permissions::can($currentRole, 'applications.submit')): ?>
            <p><a class="btn primary" href="/layanan/<?= e((string) ($service['code'] ?? '')) ?>/ajukan">Ajukan</a></p>
        <?php elseif ($currentRole === null): ?>
            <p class="lede">Login sebagai SISWA atau GURU untuk mengajukan layanan.</p>
        <?php endif; ?>
    <?php elseif (($service['mode'] ?? '') === 'MODUL_PENGADUAN'): ?>
        <p class="notice">Layanan ini ditangani melalui modul pengaduan.</p>
        <p><a class="btn primary" href="/kahayan">Kirim aspirasi atau pengaduan</a></p>
    <?php endif; ?>
    <p><a href="/layanan">Kembali ke katalog layanan</a></p>
</section>
