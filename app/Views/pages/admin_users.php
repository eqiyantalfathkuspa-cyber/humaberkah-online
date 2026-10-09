<?php
/** @var string $title */
/** @var array<int, array<string, mixed>> $users */
$users = $users ?? [];
$message = $message ?? '';
$messageIsError = $messageIsError ?? false;
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Pengguna</h2>
    </div>
    <?php if ($message !== ''): ?>
        <p class="<?= $messageIsError ? 'auth-error' : 'lede' ?>"><?= e($message) ?></p>
    <?php endif; ?>
    <form method="post" action="/admin/pengguna" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <h3>Buat pengguna</h3>
        <label><span>Nama</span><input type="text" name="full_name" required></label>
        <label><span>Identifier</span><input type="text" name="identifier" required></label>
        <label>
            <span>Role</span>
            <select name="role" required>
                <option value="SISWA">Siswa</option>
                <option value="GURU">Guru</option>
                <option value="ADMIN">Admin</option>
            </select>
        </label>
        <label><span>Sandi baru (minimal 10 karakter)</span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
        <button type="submit" class="btn primary">Buat pengguna</button>
    </form>
    <?php if ($users === []): ?>
        <p class="lede">BELUM ADA DATA.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Identifier</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e((string) ($user['full_name'] ?? '')) ?></td>
                        <td><?= e((string) ($user['role_name'] ?? $user['role_code'] ?? '')) ?></td>
                        <td><?= e((string) ($user['identifier'] ?? '')) ?></td>
                        <td><?= e((string) (($user['is_active'] ?? 1) ? 'AKTIF' : 'NONAKTIF')) ?></td>
                        <td>
                            <form method="post" action="/admin/pengguna/<?= e((string) ($user['id'] ?? '')) ?>/role">
                                <?= App\Core\Csrf::field() ?>
                                <label>
                                    <span>Ubah role</span>
                                    <select name="role" required>
                                        <?php foreach (['SISWA' => 'Siswa', 'GURU' => 'Guru', 'ADMIN' => 'Admin'] as $roleCode => $roleName): ?>
                                            <option value="<?= e($roleCode) ?>" <?= ($user['role_code'] ?? '') === $roleCode ? 'selected' : '' ?>><?= e($roleName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn">Simpan role</button>
                            </form>
                            <?php if ((int) ($user['is_active'] ?? 0) === 1): ?>
                                <form method="post" action="/admin/pengguna/<?= e((string) ($user['id'] ?? '')) ?>/nonaktif">
                                    <?= App\Core\Csrf::field() ?>
                                    <button type="submit" class="btn danger">Nonaktifkan</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/admin/pengguna/<?= e((string) ($user['id'] ?? '')) ?>/reset-sandi">
                                <?= App\Core\Csrf::field() ?>
                                <label><span>Reset sandi (minimal 10 karakter)</span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
                                <button type="submit" class="btn">Reset sandi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
