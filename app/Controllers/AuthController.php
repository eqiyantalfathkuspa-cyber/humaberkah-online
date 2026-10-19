<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public static function loginPage(): void
    {
        if (Auth::user() !== null) {
            $user = Auth::user();
            $role = strtoupper((string) ($user['role_code'] ?? $user['role'] ?? ''));
            $target = $role === 'ADMIN' ? '/admin' : '/portal';
            if (!headers_sent()) {
                header('Location: ' . $target, true, 302);
            }
            return;
        }

        $error = $_GET['error'] ?? '';
        $message = $error === 'expired' ? 'Sesi Anda telah kedaluwarsa. Silakan login kembali.' : '';
        View::render('pages/login', ['title' => 'Login', 'error' => $message]);
    }

    public static function login(): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = strtoupper(trim((string) ($_POST['login_role'] ?? '')));

        $result = Auth::processLogin($identifier, $password, $role);
        if ($result['ok'] !== true) {
            http_response_code(401);
            View::render('pages/login', ['title' => 'Login', 'error' => $result['message'] ?? 'Login gagal.']);
            return;
        }

        $user = $result['user'] ?? Auth::user();
        $roleCode = strtoupper((string) ($user['role_code'] ?? $user['role'] ?? ''));
        if (!headers_sent()) {
            header('Location: ' . ($roleCode === 'ADMIN' ? '/admin' : '/'), true, 302);
        }
    }

    public static function logout(): void
    {
        Auth::logout();
        if (!headers_sent()) {
            header('Location: /login', true, 302);
        }
    }
}
