<?php
/** @var string $title */
/** @var string $error */
?>
<section class="auth-shell">
    <div class="auth-card">
        <h2>Masuk ke HUMA BERKAH</h2>
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
                    <option value="SISWA_GURU">Siswa / Guru / Tendik</option>
                    <option value="ADMIN">Admin</option>
                </select>
            </label>
            <button type="submit" class="btn primary">Masuk</button>
        </form>
        <div class="auth-choices">
            <a href="/"> Tamu </a>
            <span>Siswa / Guru / Tendik </span>
            <span>Admin </span>
        </div>
    </div>
</section>
