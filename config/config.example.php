<?php
return [
    // Nama, lingkungan, dan alamat lokal aplikasi.
    'app' => [
        // Nama aplikasi yang ditampilkan.
        'name' => 'HUMA MANTAP',
        // Lingkungan aktif; gunakan development untuk lokal.
        'env' => 'development',
        // URL dasar aplikasi untuk pengalihan dan tautan.
        'base_url' => 'http://localhost:8000',
    ],
    // Profil koneksi basis data yang digunakan aplikasi dan tes.
    'databases' => [
        // Basis data utama untuk penggunaan lokal.
        'default' => [
            // Host server MySQL/MariaDB.
            'host' => 'localhost',
            // Port server MySQL/MariaDB.
            'port' => 3306,
            // Nama basis data utama.
            'name' => 'humamantap_dev',
            // Pengguna basis data dengan hak minimum yang diperlukan.
            'user' => 'GANTI_DENGAN_PENGGUNA_DATABASE',
            // Sandi pengguna basis data.
            'pass' => 'GANTI_DENGAN_SANDI_ANDA',
            // Charset koneksi basis data.
            'charset' => 'utf8mb4',
            // Collation koneksi basis data.
            'collation' => 'utf8mb4_unicode_ci',
        ],
        // Profil terpisah untuk suite tes.
        'test' => [
            // Host server MySQL/MariaDB.
            'host' => 'localhost',
            // Port server MySQL/MariaDB.
            'port' => 3306,
            // Nama basis data tes yang diwajibkan aplikasi.
            'name' => 'humamantap_test',
            // Pengguna basis data yang dapat membuat dan menghapus fixture tes.
            'user' => 'GANTI_DENGAN_PENGGUNA_DATABASE',
            // Sandi pengguna basis data.
            'pass' => 'GANTI_DENGAN_SANDI_ANDA',
            // Charset koneksi basis data.
            'charset' => 'utf8mb4',
            // Collation koneksi basis data.
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    // Pengaturan batas permintaan, sesi, dan unggahan.
    'security' => [
        // Kunci acak rahasia minimal 32 byte untuk hashing alamat IP.
        'ip_hash_key' => 'GANTI_DENGAN_KUNCI_ACAK_MINIMAL_32_BYTE',
        // Lama sesi dalam detik.
        'session_lifetime_seconds' => 7200,
        // Batas percobaan login per identitas.
        'login_attempt_limit' => 5,
        // Batas percobaan per alamat IP.
        'ip_attempt_limit' => 30,
        // Jendela penguncian dalam detik.
        'lockout_window_seconds' => 15 * 60,
        // Ukuran maksimum unggahan dalam byte.
        'upload_max_size_bytes' => 5 * 1024 * 1024,
        // Ekstensi berkas unggahan yang diizinkan.
        'allowed_upload_types' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
    // Daftar proxy tepercaya; kosongkan bila aplikasi tidak berada di belakang proxy.
    'trusted_proxies' => [],
];
