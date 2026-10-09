<?php
/** @var string $title */
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Portal pengguna</h2>
    </div>
    <p class="lede">Portal SISWA / GURU. Belum ada data yang ditampilkan pada tahap ini.</p>
    <form method="post" action="/logout">
        <?= '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">'; ?>
        <button type="submit" class="btn danger">Logout</button>
    </form>
</section>
