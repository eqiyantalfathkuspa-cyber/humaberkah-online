<?php
/** @var string $title */
/** @var string $error */
?>
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-banner">PROTOTIPE</div>
        <h2>Masuk ke HUMA MANTAP</h2>
        <p class="auth-copy">Pilih jenis akun sesuai identitas Anda.</p>
        <?php if ($error !== ''): ?>
            <div class="auth-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/login" class="auth-form">
            <?= 
                '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">';
            ?>
            <label>
                <span>Username / NISN / NIP</span>
                <input type="text" name="identifier" autocomplete="username" required>
            </label>
            <label>
                <span>Kata sandi</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <label>
                <span>Jenis akun</span>
                <select name="login_role" required>
                    <option value="SISWA_GURU">Siswa / Guru</option>
                    <option value="ADMIN">Admin Pokja 6</option>
                </select>
            </label>
            <button type="submit" class="btn primary">Masuk</button>
        </form>
        <div class="auth-choices">
            <a href="/">Guest</a>
            <span>Siswa / Guru</span>
            <span>Admin Pokja 6</span>
        </div>
    </div>
</section>
