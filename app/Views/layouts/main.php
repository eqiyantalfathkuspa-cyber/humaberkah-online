<?php
/** @var string $title */
/** @var string $viewPath */
$currentUser = App\Core\Auth::user();
$currentRole = $currentUser['role_code'] ?? $currentUser['role'] ?? null;
$canAccessAdmin = App\Policies\Permissions::can($currentRole, 'admin.dashboard');
$canManageServices = App\Policies\Permissions::can($currentRole, 'admin.services.manage');
$canManageApplications = App\Policies\Permissions::can($currentRole, 'admin.applications.manage');
$canManageComplaints = App\Policies\Permissions::can($currentRole, 'admin.complaints.manage');
$canViewChecklist = App\Policies\Permissions::can($currentRole, 'admin.checklist.view');
$canViewEvaluations = App\Policies\Permissions::can($currentRole, 'admin.evaluations.view');
$canViewRtl = App\Policies\Permissions::can($currentRole, 'admin.rtl.view');
$canViewImpact = App\Policies\Permissions::can($currentRole, 'admin.impact.view');
$canViewMadani = App\Policies\Permissions::can($currentRole, 'admin.madani.view');
$canViewPortal = App\Policies\Permissions::can($currentRole, 'portal.view');
$canViewUsers = App\Policies\Permissions::can($currentRole, 'admin.users.manage');
$canViewAudit = App\Policies\Permissions::can($currentRole, 'admin.audit.view');
$canViewSurveys = App\Policies\Permissions::can($currentRole, 'admin.surveys.view');
$canViewEvidence = App\Policies\Permissions::can($currentRole, 'admin.evidence.view');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'HUMA MANTAP') ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="/assets/js/site.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <div class="page-shell">
        <div class="dayak" aria-hidden="true"></div>
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <?php require __DIR__ . '/../partials/header.php'; ?>
        <?php require __DIR__ . '/../partials/nav.php'; ?>

        <main class="content" id="main-content" tabindex="-1">
            <?php require $viewPath; ?>
        </main>
        <?php require __DIR__ . '/../partials/footer.php'; ?>
    </div>
</body>
</html>
