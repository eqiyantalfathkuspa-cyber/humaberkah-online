<?php
/** @var string $title */
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Dashboard Admin</h2>
    </div>
    <p class="lede">Area admin. BELUM ADA DATA.</p>
    <form method="post" action="/logout">
        <?= '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">'; ?>
        <button type="submit" class="btn danger">Logout</button>
    </form>
</section>
