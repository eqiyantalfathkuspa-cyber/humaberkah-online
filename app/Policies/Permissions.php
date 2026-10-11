<?php
declare(strict_types=1);

namespace App\Policies;

final class Permissions
{
    public const MAP = [
        'home.public' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'services.catalog' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'services.detail' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'applications.submit' => ['SISWA', 'GURU'],
        'applications.track' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'applications.track.lookup' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'complaints.submit' => ['GUEST', 'SISWA', 'GURU'],
        'complaints.track' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'complaints.track.lookup' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'portal.complaints.view' => ['SISWA', 'GURU'],
        'surveys.submit' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'login' => ['GUEST', 'SISWA', 'GURU', 'ADMIN'],
        'logout' => ['SISWA', 'GURU', 'ADMIN'],
        'portal.view' => ['SISWA', 'GURU'],
        'admin.dashboard' => ['ADMIN'],
        'admin.users.manage' => ['ADMIN'],
        'admin.users.create' => ['ADMIN'],
        'admin.users.role' => ['ADMIN'],
        'admin.users.deactivate' => ['ADMIN'],
        'admin.users.reset_password' => ['ADMIN'],
        'admin.audit.view' => ['ADMIN'],
        'admin.services.manage' => ['ADMIN'],
        'admin.services.edit' => ['ADMIN'],
        'admin.services.update' => ['ADMIN'],
        'admin.applications.manage' => ['ADMIN'],
        'admin.applications.detail' => ['ADMIN'],
        'admin.applications.status' => ['ADMIN'],
        'admin.applications.documents.download' => ['ADMIN'],
        'admin.complaints.manage' => ['ADMIN'],
        'admin.complaints.detail' => ['ADMIN'],
        'admin.complaints.status' => ['ADMIN'],
        'admin.complaints.response' => ['ADMIN'],
        'admin.surveys.view' => ['ADMIN'],
        'admin.surveys.export' => ['ADMIN'],
        'admin.evidence.view' => ['ADMIN'],
        'admin.evidence.upload' => ['ADMIN'],
        'admin.evidence.download' => ['ADMIN'],
        'admin.evidence.verify' => ['ADMIN'],
        'admin.checklist.view' => ['ADMIN'],
        'admin.evaluations.view' => ['ADMIN'],
        'admin.evaluations.create' => ['ADMIN'],
        'admin.rtl.view' => ['ADMIN'],
        'admin.rtl.create' => ['ADMIN'],
        'admin.rtl.update_status' => ['ADMIN'],
        'admin.impact.view' => ['ADMIN'],
        'admin.impact.create' => ['ADMIN'],
    ];

    public static function can(?string $role, string $ability): bool
    {
        $normalizedRole = $role === null ? 'GUEST' : strtoupper((string) $role);
        return in_array($normalizedRole, self::routeRoles($ability), true);
    }

    public static function routeRoles(string $ability): array
    {
        return self::MAP[$ability] ?? ['GUEST'];
    }
}
