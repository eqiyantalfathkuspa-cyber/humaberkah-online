<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApplicationTrackingLimiter;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\TrackingCode;
use App\Core\View;
use PDOException;
use Throwable;

final class SurveyController
{
    private const FAILURE_MESSAGE = 'Kode tidak valid, pengajuan belum selesai, atau survei sudah dikirim.';
    private const ASPECTS = [
        'KECEPATAN' => 'speed_rating',
        'KEJELASAN' => 'clarity_rating',
        'KERAMAHAN' => 'courtesy_rating',
    ];

    public static function form(): void
    {
        self::setPrivateHeaders();
        View::render('pages/survey_form', [
            'title' => 'Survei kepuasan layanan',
            'error' => '',
            'trackingCode' => '',
            'ratings' => [],
            'suggestion' => '',
        ]);
    }

    public static function submit(): void
    {
        self::setPrivateHeaders();
        $trackingCode = strtoupper(self::postString('tracking_code'));
        $suggestion = self::postString('suggestion');
        $honeypot = self::postString('website');
        $ratings = [];
        foreach (self::ASPECTS as $aspect => $field) {
            $ratings[$field] = self::rating($_POST[$field] ?? null);
        }

        if ($honeypot !== '') {
            self::renderForm('Pengiriman tidak dapat diproses.', 422, $trackingCode, $ratings, $suggestion);
            return;
        }
        if (
            in_array(null, $ratings, true)
            || !mb_check_encoding($suggestion, 'UTF-8')
            || mb_strlen($suggestion, 'UTF-8') > 1000
        ) {
            self::renderForm('Penilaian wajib 1 sampai 5 dan saran maksimal 1.000 karakter.', 422, $trackingCode, $ratings, $suggestion);
            return;
        }

        $database = Auth::databaseName();
        $ipAddress = Auth::clientIp();
        if ($ipAddress === '') {
            self::renderForm('Survei tidak dapat diproses dari koneksi ini. Silakan coba lagi.', 503, $trackingCode, $ratings, $suggestion);
            return;
        }
        $ipHash = hash('sha256', $ipAddress);
        $pdo = Db::pdo($database);

        try {
            $result = ApplicationTrackingLimiter::withIpLock(
                $ipAddress,
                $database,
                static function () use ($pdo, $database, $ipHash, $trackingCode, $ratings, $suggestion): string {
                    $pdo->beginTransaction();
                    try {
                        Db::run(
                            'INSERT IGNORE INTO survey_ip_quotas (ip_hash, window_started_at, submission_count) VALUES (?, NOW(), 0)',
                            [$ipHash],
                            $database
                        );
                        $quota = Db::one(
                            'SELECT submission_count, window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR) AS expired FROM survey_ip_quotas WHERE ip_hash = ? FOR UPDATE',
                            [$ipHash],
                            $database
                        );
                        if ($quota === null) {
                            throw new \RuntimeException('Catatan kuota survei tidak dapat dibaca.');
                        }
                        $count = (int) $quota['submission_count'];
                        if ((int) $quota['expired'] === 1) {
                            $count = 0;
                            Db::run(
                                'UPDATE survey_ip_quotas SET window_started_at = NOW(), submission_count = 0 WHERE ip_hash = ?',
                                [$ipHash],
                                $database
                            );
                        }
                        if ($count >= 5) {
                            $pdo->rollBack();
                            return 'limited';
                        }
                        Db::run(
                            'UPDATE survey_ip_quotas SET submission_count = submission_count + 1 WHERE ip_hash = ?',
                            [$ipHash],
                            $database
                        );

                        if (!TrackingCode::isValid($trackingCode)) {
                            $pdo->commit();
                            return 'invalid';
                        }
                        $application = Db::one(
                            'SELECT id, service_id FROM applications WHERE tracking_code = ? AND status = ? FOR UPDATE',
                            [$trackingCode, 'SELESAI'],
                            $database
                        );
                        if ($application === null) {
                            $pdo->commit();
                            return 'invalid';
                        }
                        Db::run(
                            'INSERT INTO surveys (application_id, service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?, ?)',
                            [
                                (int) $application['id'],
                                (int) $application['service_id'],
                                $ratings['speed_rating'],
                                $ratings['clarity_rating'],
                                $ratings['courtesy_rating'],
                                $suggestion !== '' ? $suggestion : null,
                            ],
                            $database
                        );
                        Auth::audit(
                            null,
                            'GUEST',
                            'survey_submitted',
                            'surveys',
                            null,
                            'Survei kepuasan layanan tersimpan.',
                            $database
                        );
                        $pdo->commit();
                        return 'saved';
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $exception;
                    }
                }
            );
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            self::renderForm(self::FAILURE_MESSAGE, 422, '', $ratings, $suggestion);
            return;
        }

        if ($result === 'limited') {
            self::renderForm('Batas pengiriman survei per alamat IP telah tercapai. Silakan coba lagi nanti.', 429, $trackingCode, $ratings, $suggestion);
            return;
        }
        if ($result !== 'saved') {
            self::renderForm(self::FAILURE_MESSAGE, 422, '', $ratings, $suggestion);
            return;
        }

        $_SESSION['survey_submitted'] = true;
        if (!headers_sent()) {
            header('Location: /survei/terima', true, 303);
        }
        http_response_code(303);
    }

    public static function thankYou(): void
    {
        self::setPrivateHeaders();
        $submitted = !empty($_SESSION['survey_submitted']);
        unset($_SESSION['survey_submitted']);
        View::render('pages/survey_thank_you', [
            'title' => 'Terima kasih',
            'submitted' => $submitted,
        ]);
    }

    public static function adminIndex(): void
    {
        $summaries = Db::all(
            'SELECT s.id AS service_id, s.code AS service_code, s.name AS service_name, COUNT(v.id) AS respondent_count, AVG(v.speed_rating) AS speed_average, AVG(v.clarity_rating) AS clarity_average, AVG(v.courtesy_rating) AS courtesy_average FROM services s LEFT JOIN surveys v ON v.service_id = s.id GROUP BY s.id, s.code, s.name ORDER BY s.code'
        );
        $suggestions = Db::all(
            'SELECT s.code AS service_code, s.name AS service_name, v.suggestion, v.created_at FROM surveys v INNER JOIN services s ON s.id = v.service_id WHERE v.suggestion IS NOT NULL AND v.suggestion <> ? ORDER BY v.created_at DESC, v.id DESC LIMIT 50',
            ['']
        );
        foreach ($summaries as &$summary) {
            $summary['respondent_count'] = (int) $summary['respondent_count'];
            foreach (['speed_average', 'clarity_average', 'courtesy_average'] as $column) {
                $summary[$column] = $summary[$column] === null
                    ? 'BELUM ADA DATA'
                    : number_format((float) $summary[$column], 2, ',', '.');
            }
            $summary['small_sample'] = $summary['respondent_count'] < 5;
        }
        unset($summary);
        View::render('pages/admin_surveys', [
            'title' => 'Rekap survei kepuasan',
            'summaries' => $summaries,
            'suggestions' => $suggestions,
        ]);
    }

    public static function export(): void
    {
        $rows = Db::all(
            'SELECT s.code AS service_code, s.name AS service_name, v.speed_rating, v.clarity_rating, v.courtesy_rating, v.suggestion, v.created_at FROM surveys v INNER JOIN services s ON s.id = v.service_id ORDER BY v.created_at, v.id'
        );
        Auth::audit(
            (int) (Auth::user()['id'] ?? 0),
            'ADMIN',
            'survey_exported',
            'surveys',
            null,
            'Ekspor CSV rekap survei (' . count($rows) . ' baris).'
        );
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="rekap-survei.csv"');
            header('Cache-Control: no-store');
        }
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Tidak dapat membuka keluaran ekspor CSV.');
        }
        fputcsv($output, ['Kode layanan', 'Nama layanan', 'Kecepatan', 'Kejelasan', 'Keramahan', 'Saran', 'Waktu'], ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($output, array_map(self::csvCell(...), array_values($row)), ',', '"', '\\');
        }
        fclose($output);
    }

    private static function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if (preg_match('/^[=+\-@]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    }

    private static function rating(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 && $value <= 5 ? $value : null;
        }
        if (!is_string($value) || preg_match('/^[1-5]$/D', $value) !== 1) {
            return null;
        }
        return (int) $value;
    }

    private static function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private static function renderForm(
        string $error,
        int $status,
        string $trackingCode,
        array $ratings,
        string $suggestion
    ): void {
        http_response_code($status);
        View::render('pages/survey_form', [
            'title' => 'Survei kepuasan layanan',
            'error' => $error,
            'trackingCode' => $trackingCode,
            'ratings' => $ratings,
            'suggestion' => $suggestion,
        ]);
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }
}
