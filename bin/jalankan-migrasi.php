<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';
require __DIR__ . '/../app/Core/SqlStatementParser.php';

use App\Core\Config;
use App\Core\Db;
use App\Core\SqlStatementParser;

$database = $argv[1] ?? 'default';
$defaultDirectory = __DIR__ . '/../database/migrations';
$directory = $argv[2] ?? $defaultDirectory;
$resolvedDirectory = realpath($directory);
$projectRoot = realpath(__DIR__ . '/..');

if ($resolvedDirectory === false || !is_dir($resolvedDirectory)) {
    fwrite(STDERR, "Direktori migrasi tidak ditemukan.\n");
    exit(1);
}

if ($argv[2] ?? false) {
    $insideProject = $projectRoot !== false
        && str_starts_with(
            strtolower($resolvedDirectory . DIRECTORY_SEPARATOR),
            strtolower($projectRoot . DIRECTORY_SEPARATOR)
        );
    if (!in_array(strtolower($database), ['test', 'humamantap_test'], true) || !$insideProject) {
        fwrite(STDERR, "Direktori migrasi khusus hanya dapat digunakan di dalam proyek dengan basis data test.\n");
        exit(1);
    }
}

try {
    $pdo = Db::pdo($database);
} catch (Throwable $exception) {
    fwrite(STDERR, "Koneksi basis data migrasi gagal: " . $exception->getMessage() . "\n");
    exit(1);
}

exit(runMigrations($pdo, $database, $resolvedDirectory));

function migrationSchemaState(PDO $pdo, string $migrationName): string
{
    $specifications = [
        '001_schema_init.sql' => [
            'tables' => ['id_counters' => ['name', 'current_value', 'updated_at']],
            'columnDefinitions' => [
                'id_counters.name' => ['data_type' => 'varchar', 'character_maximum_length' => 64, 'is_nullable' => 'NO'],
                'id_counters.current_value' => ['data_type' => 'bigint', 'column_type' => 'bigint unsigned', 'is_nullable' => 'NO'],
            ],
            'indexes' => ['id_counters.PRIMARY'],
            'indexDefinitions' => [
                'id_counters.PRIMARY' => ['columns' => ['name'], 'unique' => true],
            ],
        ],
        '002_auth_schema.sql' => [
            'tables' => [
                'roles' => ['id', 'code', 'name', 'description', 'created_at'],
                'users' => ['id', 'role_id', 'identifier', 'full_name', 'password_hash', 'is_active', 'last_login_at', 'created_at'],
                'login_attempts' => ['id', 'user_id', 'identifier', 'ip_address', 'attempt_type', 'note', 'created_at'],
                'audit_logs' => ['id', 'actor_id', 'actor_role', 'action', 'entity_type', 'entity_id', 'message', 'created_at'],
            ],
            'columnDefinitions' => [
                'roles.code' => ['data_type' => 'varchar', 'character_maximum_length' => 32, 'is_nullable' => 'NO'],
                'users.role_id' => ['data_type' => 'int', 'column_type' => 'int unsigned', 'is_nullable' => 'NO'],
                'users.identifier' => ['data_type' => 'varchar', 'character_maximum_length' => 120, 'is_nullable' => 'NO'],
                'users.password_hash' => ['data_type' => 'varchar', 'character_maximum_length' => 255, 'is_nullable' => 'NO'],
                'users.is_active' => ['data_type' => 'tinyint', 'is_nullable' => 'NO'],
                'login_attempts.attempt_type' => ['data_type' => 'enum', 'column_type' => "enum('success','failure','locked')", 'is_nullable' => 'NO'],
                'audit_logs.action' => ['data_type' => 'varchar', 'character_maximum_length' => 80, 'is_nullable' => 'NO'],
            ],
            'triggers' => ['trg_audit_logs_no_update', 'trg_audit_logs_no_delete'],
            'indexes' => [
                'roles.PRIMARY', 'roles.uq_roles_code',
                'users.PRIMARY', 'users.uq_users_role_identifier', 'users.idx_users_role',
                'login_attempts.PRIMARY', 'login_attempts.idx_login_attempts_user',
                'login_attempts.idx_login_attempts_identifier', 'login_attempts.idx_login_attempts_ip',
                'audit_logs.PRIMARY', 'audit_logs.idx_audit_logs_actor',
            ],
            'foreignKeys' => [
                'users.fk_users_role',
                'login_attempts.fk_login_attempts_user',
                'audit_logs.fk_audit_logs_actor',
            ],
            'indexDefinitions' => [
                'roles.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'roles.uq_roles_code' => ['columns' => ['code'], 'unique' => true],
                'users.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'users.uq_users_role_identifier' => ['columns' => ['role_id', 'identifier'], 'unique' => true],
                'users.idx_users_role' => ['columns' => ['role_id'], 'unique' => false],
                'login_attempts.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'login_attempts.idx_login_attempts_user' => ['columns' => ['user_id'], 'unique' => false],
                'login_attempts.idx_login_attempts_identifier' => ['columns' => ['identifier'], 'unique' => false],
                'login_attempts.idx_login_attempts_ip' => ['columns' => ['ip_address'], 'unique' => false],
                'audit_logs.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'audit_logs.idx_audit_logs_actor' => ['columns' => ['actor_id'], 'unique' => false],
            ],
            'foreignKeyDefinitions' => [
                'users.fk_users_role' => ['column' => 'role_id', 'table' => 'roles', 'referenced_column' => 'id', 'delete' => 'RESTRICT', 'update' => 'CASCADE'],
                'login_attempts.fk_login_attempts_user' => ['column' => 'user_id', 'table' => 'users', 'referenced_column' => 'id', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
                'audit_logs.fk_audit_logs_actor' => ['column' => 'actor_id', 'table' => 'users', 'referenced_column' => 'id', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ],
            'triggerDefinitions' => [
                'trg_audit_logs_no_update' => ['table' => 'audit_logs', 'event' => 'UPDATE', 'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs append-only'"],
                'trg_audit_logs_no_delete' => ['table' => 'audit_logs', 'event' => 'DELETE', 'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs append-only'"],
            ],
        ],
        '003_user_session_revocation.sql' => [
            'columns' => ['users.auth_token'],
            'columnDefinitions' => [
                'users.auth_token' => ['data_type' => 'char', 'character_maximum_length' => 64, 'is_nullable' => 'YES'],
            ],
        ],
        '004_service_catalog.sql' => [
            'tables' => [
                'service_categories' => ['id', 'code', 'name', 'created_at', 'updated_at'],
                'services' => ['id', 'code', 'name', 'category_id', 'mode', 'is_active', 'created_at', 'updated_at'],
                'service_steps' => ['id', 'service_id', 'step_no', 'step_name'],
                'service_standards' => ['service_id', 'fee_text', 'sla_text', 'responsible_unit', 'required_documents_text', 'updated_by', 'updated_at'],
            ],
            'columnDefinitions' => [
                'services.category_id' => ['data_type' => 'bigint', 'column_type' => 'bigint unsigned', 'is_nullable' => 'NO'],
                'services.mode' => ['data_type' => 'enum', 'column_type' => "enum('formulir','informasi','tatap_muka','modul_pengaduan')", 'is_nullable' => 'NO'],
                'service_steps.step_no' => ['data_type' => 'tinyint', 'column_type' => 'tinyint unsigned', 'is_nullable' => 'NO'],
                'service_standards.fee_text' => ['data_type' => 'varchar', 'character_maximum_length' => 500, 'is_nullable' => 'YES'],
            ],
            'indexes' => [
                'service_categories.PRIMARY', 'service_categories.uq_service_categories_code',
                'services.PRIMARY', 'services.uq_services_code', 'services.idx_services_category',
                'service_steps.PRIMARY', 'service_steps.uq_service_steps_service_step',
                'service_standards.PRIMARY',
            ],
            'foreignKeys' => [
                'services.fk_services_category',
                'service_steps.fk_service_steps_service',
                'service_standards.fk_service_standards_service',
                'service_standards.fk_service_standards_updated_by',
            ],
            'indexDefinitions' => [
                'service_categories.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'service_categories.uq_service_categories_code' => ['columns' => ['code'], 'unique' => true],
                'services.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'services.uq_services_code' => ['columns' => ['code'], 'unique' => true],
                'services.idx_services_category' => ['columns' => ['category_id'], 'unique' => false],
                'service_steps.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'service_steps.uq_service_steps_service_step' => ['columns' => ['service_id', 'step_no'], 'unique' => true],
                'service_standards.PRIMARY' => ['columns' => ['service_id'], 'unique' => true],
            ],
            'foreignKeyDefinitions' => [
                'services.fk_services_category' => ['column' => 'category_id', 'table' => 'service_categories', 'referenced_column' => 'id', 'delete' => 'RESTRICT', 'update' => 'CASCADE'],
                'service_steps.fk_service_steps_service' => ['column' => 'service_id', 'table' => 'services', 'referenced_column' => 'id', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
                'service_standards.fk_service_standards_service' => ['column' => 'service_id', 'table' => 'services', 'referenced_column' => 'id', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
                'service_standards.fk_service_standards_updated_by' => ['column' => 'updated_by', 'table' => 'users', 'referenced_column' => 'id', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ],
        ],
        '005_applications.sql' => [
            'tables' => [
                'applications' => ['id', 'service_id', 'applicant_user_id', 'tracking_code', 'request_text', 'status', 'created_at', 'updated_at'],
                'application_history' => ['id', 'application_id', 'old_status', 'new_status', 'changed_by', 'created_at'],
            ],
            'columnDefinitions' => [
                'applications.tracking_code' => [
                    'data_type' => 'char',
                    'character_maximum_length' => 23,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'applications.status' => ['data_type' => 'enum', 'column_type' => "enum('diajukan','diproses','selesai','ditolak')", 'is_nullable' => 'NO'],
                'applications.request_text' => ['data_type' => 'text', 'is_nullable' => 'NO'],
                'application_history.new_status' => ['data_type' => 'enum', 'column_type' => "enum('diajukan','diproses','selesai','ditolak')", 'is_nullable' => 'NO'],
            ],
            'triggers' => ['trg_application_history_no_update', 'trg_application_history_no_delete'],
            'indexes' => [
                'applications.PRIMARY', 'applications.uq_applications_tracking_code',
                'applications.idx_applications_status_created', 'applications.idx_applications_applicant',
                'application_history.PRIMARY', 'application_history.idx_application_history_application',
            ],
            'foreignKeys' => [
                'applications.fk_applications_service',
                'applications.fk_applications_applicant',
                'application_history.fk_application_history_application',
                'application_history.fk_application_history_user',
            ],
            'indexDefinitions' => [
                'applications.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'applications.uq_applications_tracking_code' => ['columns' => ['tracking_code'], 'unique' => true],
                'applications.idx_applications_status_created' => ['columns' => ['status', 'created_at'], 'unique' => false],
                'applications.idx_applications_applicant' => ['columns' => ['applicant_user_id'], 'unique' => false],
                'application_history.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'application_history.idx_application_history_application' => ['columns' => ['application_id', 'id'], 'unique' => false],
            ],
            'foreignKeyDefinitions' => [
                'applications.fk_applications_service' => ['column' => 'service_id', 'table' => 'services', 'referenced_column' => 'id', 'delete' => 'RESTRICT', 'update' => 'CASCADE'],
                'applications.fk_applications_applicant' => ['column' => 'applicant_user_id', 'table' => 'users', 'referenced_column' => 'id', 'delete' => 'RESTRICT', 'update' => 'CASCADE'],
                'application_history.fk_application_history_application' => ['column' => 'application_id', 'table' => 'applications', 'referenced_column' => 'id', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
                'application_history.fk_application_history_user' => ['column' => 'changed_by', 'table' => 'users', 'referenced_column' => 'id', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ],
            'triggerDefinitions' => [
                'trg_application_history_no_update' => ['table' => 'application_history', 'event' => 'UPDATE', 'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'application_history append-only'"],
                'trg_application_history_no_delete' => ['table' => 'application_history', 'event' => 'DELETE', 'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'application_history append-only'"],
            ],
        ],
        '006_application_tracking_attempts.sql' => [
            'tables' => ['application_tracking_attempts' => ['id', 'ip_address', 'attempt_type', 'created_at']],
            'columnDefinitions' => [
                'application_tracking_attempts.ip_address' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 45,
                    'is_nullable' => 'NO',
                ],
                'application_tracking_attempts.attempt_type' => [
                    'data_type' => 'enum',
                    'column_type' => "enum('failure','locked')",
                    'is_nullable' => 'NO',
                ],
            ],
            'indexes' => [
                'application_tracking_attempts.PRIMARY',
                'application_tracking_attempts.idx_application_tracking_attempts_ip_type_time',
            ],
            'indexDefinitions' => [
                'application_tracking_attempts.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'application_tracking_attempts.idx_application_tracking_attempts_ip_type_time' => ['columns' => ['ip_address', 'attempt_type', 'created_at'], 'unique' => false],
            ],
        ],
        '007_application_documents.sql' => [
            'tables' => [
                'application_documents' => [
                    'id', 'application_id', 'nama_asli', 'nama_acak', 'mime_terdeteksi',
                    'ukuran', 'sha256', 'created_at',
                ],
            ],
            'columnDefinitions' => [
                'application_documents.application_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'NO',
                ],
                'application_documents.nama_asli' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 255,
                    'is_nullable' => 'YES',
                ],
                'application_documents.nama_acak' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 68,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'application_documents.mime_terdeteksi' => [
                    'data_type' => 'enum',
                    'column_type' => "enum('image/jpeg','image/png')",
                    'is_nullable' => 'NO',
                ],
                'application_documents.ukuran' => [
                    'data_type' => 'int',
                    'column_type' => 'int unsigned',
                    'is_nullable' => 'NO',
                ],
                'application_documents.sha256' => [
                    'data_type' => 'char',
                    'character_maximum_length' => 64,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
            ],
            'indexes' => [
                'application_documents.PRIMARY',
                'application_documents.uq_application_documents_nama_acak',
                'application_documents.idx_application_documents_application',
            ],
            'indexDefinitions' => [
                'application_documents.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'application_documents.uq_application_documents_nama_acak' => ['columns' => ['nama_acak'], 'unique' => true],
                'application_documents.idx_application_documents_application' => ['columns' => ['application_id', 'id'], 'unique' => false],
            ],
            'foreignKeys' => ['application_documents.fk_application_documents_application'],
            'foreignKeyDefinitions' => [
                'application_documents.fk_application_documents_application' => [
                    'column' => 'application_id',
                    'table' => 'applications',
                    'referenced_column' => 'id',
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE',
                ],
            ],
        ],
        '008_complaints.sql' => [
            'tables' => [
                'complaints' => [
                    'id', 'ticket_no', 'track_token', 'jenis', 'isi', 'kontak', 'user_id',
                    'status', 'response_note', 'reject_reason', 'created_at', 'updated_at',
                ],
                'complaint_history' => [
                    'id', 'complaint_id', 'event_type', 'old_status', 'new_status', 'changed_by', 'created_at',
                ],
                'complaint_ip_quotas' => ['ip_hash', 'window_started_at', 'submission_count'],
            ],
            'columnDefinitions' => [
                'complaints.ticket_no' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 32,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'complaints.track_token' => [
                    'data_type' => 'char',
                    'character_maximum_length' => 23,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'complaints.jenis' => [
                    'data_type' => 'enum',
                    'column_type' => "enum('pengaduan','aspirasi','saran','pertanyaan')",
                    'is_nullable' => 'NO',
                ],
                'complaints.isi' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 4000,
                    'is_nullable' => 'NO',
                ],
                'complaints.kontak' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 120,
                    'is_nullable' => 'YES',
                ],
                'complaints.user_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'YES',
                ],
                'complaints.status' => [
                    'data_type' => 'enum',
                    'column_type' => "enum('diterima','diklasifikasi','ditindaklanjuti','selesai','ditolak')",
                    'is_nullable' => 'NO',
                ],
                'complaints.response_note' => ['data_type' => 'text', 'is_nullable' => 'YES'],
                'complaints.reject_reason' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 4000,
                    'is_nullable' => 'YES',
                ],
                'complaint_history.event_type' => [
                    'data_type' => 'enum',
                    'column_type' => "enum('status','tanggapan')",
                    'is_nullable' => 'NO',
                ],
                'complaint_history.complaint_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'NO',
                ],
                'complaint_history.changed_by' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'YES',
                ],
                'complaint_ip_quotas.ip_hash' => [
                    'data_type' => 'char',
                    'character_maximum_length' => 64,
                    'is_nullable' => 'NO',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'complaint_ip_quotas.submission_count' => [
                    'data_type' => 'tinyint',
                    'column_type' => 'tinyint unsigned',
                    'is_nullable' => 'NO',
                ],
            ],
            'indexes' => [
                'complaints.PRIMARY',
                'complaints.uq_complaints_ticket_no',
                'complaints.uq_complaints_track_token',
                'complaints.idx_complaints_status_type_created',
                'complaints.idx_complaints_user_created',
                'complaint_history.PRIMARY',
                'complaint_history.idx_complaint_history_complaint',
                'complaint_ip_quotas.PRIMARY',
            ],
            'indexDefinitions' => [
                'complaints.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'complaints.uq_complaints_ticket_no' => ['columns' => ['ticket_no'], 'unique' => true],
                'complaints.uq_complaints_track_token' => ['columns' => ['track_token'], 'unique' => true],
                'complaints.idx_complaints_status_type_created' => ['columns' => ['status', 'jenis', 'created_at'], 'unique' => false],
                'complaints.idx_complaints_user_created' => ['columns' => ['user_id', 'created_at'], 'unique' => false],
                'complaint_history.PRIMARY' => ['columns' => ['id'], 'unique' => true],
                'complaint_history.idx_complaint_history_complaint' => ['columns' => ['complaint_id', 'id'], 'unique' => false],
                'complaint_ip_quotas.PRIMARY' => ['columns' => ['ip_hash'], 'unique' => true],
            ],
            'foreignKeys' => [
                'complaints.fk_complaints_user',
                'complaint_history.fk_complaint_history_complaint',
                'complaint_history.fk_complaint_history_user',
            ],
            'foreignKeyDefinitions' => [
                'complaints.fk_complaints_user' => [
                    'column' => 'user_id', 'table' => 'users', 'referenced_column' => 'id',
                    'delete' => 'SET NULL', 'update' => 'CASCADE',
                ],
                'complaint_history.fk_complaint_history_complaint' => [
                    'column' => 'complaint_id', 'table' => 'complaints', 'referenced_column' => 'id',
                    'delete' => 'CASCADE', 'update' => 'CASCADE',
                ],
                'complaint_history.fk_complaint_history_user' => [
                    'column' => 'changed_by', 'table' => 'users', 'referenced_column' => 'id',
                    'delete' => 'SET NULL', 'update' => 'CASCADE',
                ],
            ],
            'triggers' => ['trg_complaint_history_no_update', 'trg_complaint_history_no_delete'],
            'triggerDefinitions' => [
                'trg_complaint_history_no_update' => [
                    'table' => 'complaint_history', 'event' => 'UPDATE',
                    'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'complaint_history append-only'",
                ],
                'trg_complaint_history_no_delete' => [
                    'table' => 'complaint_history', 'event' => 'DELETE',
                    'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'complaint_history append-only'",
                ],
            ],
        ],
        '009_surveys.sql' => [
            'tables' => [
                'surveys' => [
                    'id', 'application_id', 'service_id', 'speed_rating', 'clarity_rating',
                    'courtesy_rating', 'suggestion', 'created_at',
                ],
                'survey_ip_quotas' => ['ip_hash', 'window_started_at', 'submission_count'],
            ],
            'tableDefinitions' => [
                'surveys' => ['engine' => 'InnoDB', 'character_set' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
                'survey_ip_quotas' => ['engine' => 'InnoDB', 'character_set' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
            ],
            'columnDefinitions' => [
                'surveys.id' => [
                    'data_type' => 'bigint', 'column_type' => 'bigint unsigned', 'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => 'auto_increment',
                ],
                'surveys.application_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'surveys.service_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'surveys.speed_rating' => [
                    'data_type' => 'tinyint',
                    'column_type' => 'tinyint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'surveys.clarity_rating' => [
                    'data_type' => 'tinyint',
                    'column_type' => 'tinyint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'surveys.courtesy_rating' => [
                    'data_type' => 'tinyint',
                    'column_type' => 'tinyint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'surveys.suggestion' => [
                    'data_type' => 'varchar',
                    'character_maximum_length' => 1000,
                    'is_nullable' => 'YES',
                    'default_value' => null,
                    'extra' => '',
                    'character_set_name' => 'utf8mb4',
                    'collation_name' => 'utf8mb4_unicode_ci',
                ],
                'surveys.created_at' => [
                    'data_type' => 'datetime', 'column_type' => 'datetime', 'is_nullable' => 'NO',
                    'default_value' => 'CURRENT_TIMESTAMP', 'extra' => 'DEFAULT_GENERATED',
                ],
                'survey_ip_quotas.ip_hash' => [
                    'data_type' => 'char',
                    'character_maximum_length' => 64,
                    'is_nullable' => 'NO',
                    'default_value' => null,
                    'extra' => '',
                    'character_set_name' => 'ascii',
                    'collation_name' => 'ascii_bin',
                ],
                'survey_ip_quotas.window_started_at' => [
                    'data_type' => 'datetime', 'column_type' => 'datetime', 'is_nullable' => 'NO',
                    'default_value' => null, 'extra' => '',
                ],
                'survey_ip_quotas.submission_count' => [
                    'data_type' => 'tinyint',
                    'column_type' => 'tinyint unsigned',
                    'is_nullable' => 'NO',
                    'default_value' => '0', 'extra' => '',
                ],
            ],
            'indexes' => [
                'surveys.PRIMARY',
                'surveys.uq_surveys_application',
                'surveys.idx_surveys_service_created',
                'survey_ip_quotas.PRIMARY',
            ],
            'indexDefinitions' => [
                'surveys.PRIMARY' => ['columns' => ['id'], 'unique' => true, 'type' => 'BTREE', 'prefix_lengths' => [null]],
                'surveys.uq_surveys_application' => ['columns' => ['application_id'], 'unique' => true, 'type' => 'BTREE', 'prefix_lengths' => [null]],
                'surveys.idx_surveys_service_created' => ['columns' => ['service_id', 'created_at'], 'unique' => false, 'type' => 'BTREE', 'prefix_lengths' => [null, null]],
                'survey_ip_quotas.PRIMARY' => ['columns' => ['ip_hash'], 'unique' => true, 'type' => 'BTREE', 'prefix_lengths' => [null]],
            ],
            'foreignKeys' => [
                'surveys.fk_surveys_application',
                'surveys.fk_surveys_service',
            ],
            'foreignKeyDefinitions' => [
                'surveys.fk_surveys_application' => [
                    'column' => 'application_id', 'table' => 'applications', 'referenced_column' => 'id',
                    'delete' => 'CASCADE', 'update' => 'CASCADE',
                ],
                'surveys.fk_surveys_service' => [
                    'column' => 'service_id', 'table' => 'services', 'referenced_column' => 'id',
                    'delete' => 'RESTRICT', 'update' => 'CASCADE',
                ],
            ],
        ],
    ];
    $specification = $specifications[$migrationName] ?? null;
    if ($specification === null) {
        return 'unknown';
    }

    $expected = 0;
    $present = 0;
    $complete = 0;
    foreach (($specification['tables'] ?? []) as $table => $columns) {
        $expected++;
        $tableCheck = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $tableCheck->execute([$table]);
        if ($tableCheck->fetchColumn() === false) {
            continue;
        }
        $present++;
        $columnCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $tableComplete = true;
        foreach ($columns as $column) {
            $columnCheck->execute([$table, $column]);
            if ((int) $columnCheck->fetchColumn() !== 1) {
                $tableComplete = false;
                break;
            }
        }
        if ($tableComplete) {
            $complete++;
        }
    }
    foreach (($specification['columns'] ?? []) as $qualifiedColumn) {
        [$table, $column] = explode('.', $qualifiedColumn, 2);
        $expected++;
        $columnCheck = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $columnCheck->execute([$table, $column]);
        if ($columnCheck->fetchColumn() !== false) {
            $present++;
            $complete++;
        }
    }
    foreach (($specification['columnDefinitions'] ?? []) as $qualifiedColumn => $definition) {
        [$table, $column] = explode('.', $qualifiedColumn, 2);
        $expected++;
        $columnCheck = $pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $columnCheck->execute([$table, $column]);
        $actualDefinition = $columnCheck->fetch(PDO::FETCH_ASSOC);
        if ($actualDefinition !== false) {
            $present++;
            if (
                strtolower((string) $actualDefinition['DATA_TYPE']) === $definition['data_type']
                && (!isset($definition['character_maximum_length']) || (int) $actualDefinition['CHARACTER_MAXIMUM_LENGTH'] === $definition['character_maximum_length'])
                && $actualDefinition['IS_NULLABLE'] === $definition['is_nullable']
                && (!isset($definition['column_type']) || strtolower((string) $actualDefinition['COLUMN_TYPE']) === $definition['column_type'])
                && (!array_key_exists('default_value', $definition) || $actualDefinition['COLUMN_DEFAULT'] === $definition['default_value'])
                && (!isset($definition['extra']) || strtolower((string) $actualDefinition['EXTRA']) === strtolower($definition['extra']))
                && (!isset($definition['character_set_name']) || strtolower((string) $actualDefinition['CHARACTER_SET_NAME']) === $definition['character_set_name'])
                && (!isset($definition['collation_name']) || strtolower((string) $actualDefinition['COLLATION_NAME']) === $definition['collation_name'])
            ) {
                $complete++;
            }
        }
    }
    foreach (($specification['triggers'] ?? []) as $trigger) {
        $expected++;
        $triggerCheck = $pdo->prepare(
            'SELECT 1 FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = ?'
        );
        $triggerCheck->execute([$trigger]);
        if ($triggerCheck->fetchColumn() !== false) {
            $present++;
            $complete++;
        }
    }
    foreach (($specification['indexes'] ?? []) as $qualifiedIndex) {
        [$table, $index] = explode('.', $qualifiedIndex, 2);
        $expected++;
        $indexCheck = $pdo->prepare(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1'
        );
        $indexCheck->execute([$table, $index]);
        if ($indexCheck->fetchColumn() !== false) {
            $present++;
            $complete++;
        }
    }
    foreach (($specification['foreignKeys'] ?? []) as $qualifiedForeignKey) {
        [$table, $constraint] = explode('.', $qualifiedForeignKey, 2);
        $expected++;
        $foreignKeyCheck = $pdo->prepare(
            "SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'"
        );
        $foreignKeyCheck->execute([$table, $constraint]);
        if ($foreignKeyCheck->fetchColumn() !== false) {
            $present++;
            $complete++;
        }
    }
    foreach (($specification['indexDefinitions'] ?? []) as $qualifiedIndex => $definition) {
        [$table, $index] = explode('.', $qualifiedIndex, 2);
        $expected++;
        $indexCheck = $pdo->prepare(
            'SELECT column_name AS COLUMN_NAME, non_unique AS NON_UNIQUE, index_type AS INDEX_TYPE, sub_part AS SUB_PART FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY seq_in_index'
        );
        $indexCheck->execute([$table, $index]);
        $actualRows = $indexCheck->fetchAll(PDO::FETCH_ASSOC);
        if ($actualRows !== []) {
            $present++;
            $actualColumns = array_column($actualRows, 'COLUMN_NAME');
            $actualUnique = (int) $actualRows[0]['NON_UNIQUE'] === 0;
            $actualType = strtoupper((string) $actualRows[0]['INDEX_TYPE']);
            $actualPrefixLengths = array_column($actualRows, 'SUB_PART');
            if (
                $actualColumns === $definition['columns']
                && $actualUnique === $definition['unique']
                && (!isset($definition['type']) || $actualType === $definition['type'])
                && (!isset($definition['prefix_lengths']) || $actualPrefixLengths === $definition['prefix_lengths'])
            ) {
                $complete++;
            }
        }
    }
    foreach (($specification['tableDefinitions'] ?? []) as $table => $definition) {
        $expected++;
        $tableCheck = $pdo->prepare(
            'SELECT t.engine AS ENGINE, t.table_collation AS TABLE_COLLATION, c.character_set_name AS CHARACTER_SET_NAME FROM information_schema.tables AS t LEFT JOIN information_schema.collations AS c ON c.collation_name = t.table_collation WHERE t.table_schema = DATABASE() AND t.table_name = ?'
        );
        $tableCheck->execute([$table]);
        $actual = $tableCheck->fetch(PDO::FETCH_ASSOC);
        if ($actual !== false) {
            $present++;
            if (
                strcasecmp((string) $actual['ENGINE'], $definition['engine']) === 0
                && strcasecmp((string) $actual['CHARACTER_SET_NAME'], $definition['character_set']) === 0
                && strcasecmp((string) $actual['TABLE_COLLATION'], $definition['collation']) === 0
            ) {
                $complete++;
            }
        }
    }
    foreach (($specification['foreignKeyDefinitions'] ?? []) as $qualifiedForeignKey => $definition) {
        [$table, $constraint] = explode('.', $qualifiedForeignKey, 2);
        $expected++;
        $foreignKeyCheck = $pdo->prepare(
            'SELECT kcu.column_name AS COLUMN_NAME, kcu.referenced_table_name AS REFERENCED_TABLE_NAME, kcu.referenced_column_name AS REFERENCED_COLUMN_NAME, rc.delete_rule AS DELETE_RULE, rc.update_rule AS UPDATE_RULE FROM information_schema.key_column_usage AS kcu INNER JOIN information_schema.referential_constraints AS rc ON rc.constraint_schema = kcu.constraint_schema AND rc.table_name = kcu.table_name AND rc.constraint_name = kcu.constraint_name WHERE kcu.table_schema = DATABASE() AND kcu.table_name = ? AND kcu.constraint_name = ?'
        );
        $foreignKeyCheck->execute([$table, $constraint]);
        $actual = $foreignKeyCheck->fetch(PDO::FETCH_ASSOC);
        if ($actual !== false) {
            $present++;
            if (
                $actual['COLUMN_NAME'] === $definition['column']
                && $actual['REFERENCED_TABLE_NAME'] === $definition['table']
                && $actual['REFERENCED_COLUMN_NAME'] === $definition['referenced_column']
                && strtoupper((string) $actual['DELETE_RULE']) === $definition['delete']
                && strtoupper((string) $actual['UPDATE_RULE']) === $definition['update']
            ) {
                $complete++;
            }
        }
    }
    foreach (($specification['triggerDefinitions'] ?? []) as $trigger => $definition) {
        $expected++;
        $triggerCheck = $pdo->prepare(
            'SELECT event_object_table AS EVENT_OBJECT_TABLE, event_manipulation AS EVENT_MANIPULATION, action_timing AS ACTION_TIMING, action_statement AS ACTION_STATEMENT FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = ?'
        );
        $triggerCheck->execute([$trigger]);
        $actual = $triggerCheck->fetch(PDO::FETCH_ASSOC);
        if ($actual !== false) {
            $present++;
            $normalize = static fn (string $value): string => strtolower((string) preg_replace('/\s+/', '', $value));
            if (
                $actual['EVENT_OBJECT_TABLE'] === $definition['table']
                && strtoupper((string) $actual['EVENT_MANIPULATION']) === $definition['event']
                && strtoupper((string) $actual['ACTION_TIMING']) === 'BEFORE'
                && $normalize((string) $actual['ACTION_STATEMENT']) === $normalize($definition['statement'])
            ) {
                $complete++;
            }
        }
    }

    if ($present === 0) {
        return 'none';
    }
    return $complete === $expected ? 'complete' : 'partial';
}

function isOwnedTestFixture(PDO $pdo, string $actualDatabase): bool
{
    $fixtureName = getenv('HUMA_TEST_DATABASE_NAME');
    $fixtureToken = getenv('HUMA_TEST_FIXTURE_TOKEN');
    if (
        $fixtureName === false
        || !preg_match('/^humamantap_test_[a-f0-9]{24}$/', $fixtureName)
        || $actualDatabase !== $fixtureName
        || $fixtureToken === false
        || !preg_match('/^[a-f0-9]{64}$/', $fixtureToken)
    ) {
        return false;
    }
    try {
        $ownership = $pdo->query(
            'SELECT fixture_token FROM __huma_test_fixture WHERE fixture_id = 1'
        )->fetchColumn();
        return is_string($ownership) && hash_equals($fixtureToken, $ownership);
    } catch (Throwable) {
        return false;
    }
}

function migrationLedgersMatch(PDO $pdo): bool
{
    $columns = [
        'schema_migrations' => [
            'id' => ['column_type' => 'int unsigned', 'nullable' => 'NO'],
            'migration_name' => ['column_type' => 'varchar(255)', 'nullable' => 'NO'],
            'applied_at' => ['column_type' => 'datetime', 'nullable' => 'NO'],
        ],
        'schema_migration_runs' => [
            'migration_name' => ['column_type' => 'varchar(255)', 'nullable' => 'NO'],
            'status' => ['column_type' => "enum('running','failed','applied')", 'nullable' => 'NO'],
            'checksum' => ['column_type' => 'char(64)', 'nullable' => 'NO'],
            'statement_count' => ['column_type' => 'int unsigned', 'nullable' => 'NO'],
            'last_completed_statement' => ['column_type' => 'int unsigned', 'nullable' => 'NO'],
            'error_message' => ['column_type' => 'text', 'nullable' => 'YES'],
            'started_at' => ['column_type' => 'datetime', 'nullable' => 'NO'],
            'updated_at' => ['column_type' => 'datetime', 'nullable' => 'NO'],
            'completed_at' => ['column_type' => 'datetime', 'nullable' => 'YES'],
        ],
    ];
    $expectedIndexes = [
        'schema_migrations' => [
            'PRIMARY' => ['id'],
            'uq_schema_migrations_name' => ['migration_name'],
        ],
        'schema_migration_runs' => [
            'PRIMARY' => ['migration_name'],
        ],
    ];

    foreach ($columns as $table => $expectedColumns) {
        $columnQuery = $pdo->prepare(
            'SELECT column_name AS COLUMN_NAME, column_type AS COLUMN_TYPE, is_nullable AS IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $columnQuery->execute([$table]);
        $actualColumns = [];
        foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $actualColumns[$column['COLUMN_NAME']] = [
                'column_type' => strtolower((string) $column['COLUMN_TYPE']),
                'nullable' => $column['IS_NULLABLE'],
            ];
        }
        ksort($actualColumns);
        ksort($expectedColumns);
        if ($actualColumns !== $expectedColumns) {
            return false;
        }

        foreach ($expectedIndexes[$table] as $index => $expectedIndexColumns) {
            $indexQuery = $pdo->prepare(
                'SELECT column_name AS COLUMN_NAME, non_unique AS NON_UNIQUE FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY seq_in_index'
            );
            $indexQuery->execute([$table, $index]);
            $indexRows = $indexQuery->fetchAll(PDO::FETCH_ASSOC);
            if (
                array_column($indexRows, 'COLUMN_NAME') !== $expectedIndexColumns
                || $indexRows === []
                || (int) $indexRows[0]['NON_UNIQUE'] !== 0
            ) {
                return false;
            }
        }
    }

    return true;
}

function runMigrations(PDO $pdo, string $database, string $directory): int
{
    try {
        $databaseStatement = $pdo->query('SELECT DATABASE()');
        $actualDatabase = (string) $databaseStatement->fetchColumn();
        $databaseStatement->closeCursor();
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migrator tidak dapat mengidentifikasi basis data aktif: " . $exception->getMessage() . "\n");
        return 1;
    }
    if ($actualDatabase === '') {
        fwrite(STDERR, "Migrator tidak dapat mengidentifikasi basis data aktif.\n");
        return 1;
    }
    $defaultDirectory = realpath(__DIR__ . '/../database/migrations');
    $isFixtureDirectory = $defaultDirectory === false || $directory !== $defaultDirectory;
    $ownedTestFixture = isOwnedTestFixture($pdo, $actualDatabase);
    if ($isFixtureDirectory && !$ownedTestFixture) {
        fwrite(STDERR, "Direktori migrasi fixture hanya diizinkan pada fixture tes milik proses saat ini.\n");
        return 1;
    }

    $testCrashAt = getenv('HUMA_MIGRATOR_TEST_CRASH_AT');
    if ($testCrashAt !== false && $testCrashAt !== '') {
        if (!$isFixtureDirectory || !$ownedTestFixture
            || !preg_match('/^(?:after_exec:[1-9][0-9]*|before_finalize|after_finalize)$/', $testCrashAt)) {
            fwrite(STDERR, "Fault injection migrator hanya diizinkan untuk fixture pada humamantap_test.\n");
            return 1;
        }
    }

    $lockName = 'huma-migrate:' . substr(hash('sha256', $actualDatabase), 0, 32);
    try {
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$lockName]);
        $lockAcquired = (int) $lock->fetchColumn() === 1;
        $lock->closeCursor();
        if (!$lockAcquired) {
            fwrite(STDERR, "Migrator lain sedang berjalan atau lock tidak tersedia.\n");
            return 1;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration_name VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_schema_migrations_name (migration_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migration_runs (
                migration_name VARCHAR(255) NOT NULL,
                status ENUM('RUNNING','FAILED','APPLIED') NOT NULL,
                checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                statement_count INT UNSIGNED NOT NULL,
                last_completed_statement INT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                PRIMARY KEY (migration_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!migrationLedgersMatch($pdo)) {
                fwrite(STDERR, "Struktur ledger migrasi tidak sesuai; perlu rekonsiliasi manual.\n");
                return 1;
            }

            $sqlModeStatement = $pdo->query('SELECT @@SESSION.sql_mode');
            $sqlModes = explode(',', (string) $sqlModeStatement->fetchColumn());
            $sqlModeStatement->closeCursor();
            $noBackslashEscapes = in_array('NO_BACKSLASH_ESCAPES', $sqlModes, true);
            $ansiQuotes = in_array('ANSI_QUOTES', $sqlModes, true);
            $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql');
            if ($files === false || $files === []) {
                echo "Tidak ada migrasi SQL untuk dijalankan.\n";
                return 0;
            }
            sort($files, SORT_STRING);
            $migrationNames = array_map('basename', $files);
            if (!$isFixtureDirectory) {
                foreach (['schema_migrations', 'schema_migration_runs'] as $ledgerTable) {
                    foreach (Db::all(
                        'SELECT migration_name FROM ' . $ledgerTable,
                        [],
                        $database
                    ) as $ledgerRow) {
                        if (!in_array($ledgerRow['migration_name'], $migrationNames, true)) {
                            fwrite(
                                STDERR,
                                "Ledger $ledgerTable memuat migrasi tanpa file sumber: {$ledgerRow['migration_name']}. "
                                . "Perlu rekonsiliasi manual.\n"
                            );
                            return 1;
                        }
                    }
                }
            }

            foreach ($files as $file) {
                $migrationName = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    fwrite(STDERR, "Gagal membaca migrasi $migrationName.\n");
                    return 1;
                }
                try {
                    $statements = SqlStatementParser::parse($sql, $noBackslashEscapes, $ansiQuotes);
                } catch (\RuntimeException $exception) {
                    fwrite(STDERR, "Gagal mem-parsing migrasi $migrationName: " . $exception->getMessage() . "\n");
                    return 1;
                }
                if ($statements === []) {
                    fwrite(STDERR, "Tidak ada pernyataan SQL valid di $migrationName.\n");
                    return 1;
                }
                $checksum = hash('sha256', $sql);

                $ledgerRow = Db::one(
                    'SELECT 1 FROM schema_migrations WHERE migration_name = ?',
                    [$migrationName],
                    $database
                );
                $previousRun = Db::one(
                    'SELECT status, checksum, last_completed_statement, statement_count, error_message FROM schema_migration_runs WHERE migration_name = ?',
                    [$migrationName],
                    $database
                );
                if ($ledgerRow !== null && $previousRun === null) {
                    fwrite(
                        STDERR,
                        "Migrasi $migrationName memiliki ledger lama tanpa checksum/checkpoint schema_migration_runs. "
                        . "Migrator tidak mengadopsi ledger lama otomatis; cocokkan skema dan ledger secara manual.\n"
                    );
                    return 1;
                }
                if ($ledgerRow !== null && $previousRun !== null) {
                    $schemaState = migrationSchemaState($pdo, $migrationName);
                    if (
                        $previousRun['status'] !== 'APPLIED'
                        || !hash_equals((string) $previousRun['checksum'], $checksum)
                        || (int) $previousRun['statement_count'] !== count($statements)
                        || (int) $previousRun['last_completed_statement'] !== count($statements)
                        || ($schemaState !== 'unknown' && $schemaState !== 'complete')
                    ) {
                        fwrite(
                            STDERR,
                            "Migrasi $migrationName tidak konsisten: ledger, checksum, checkpoint, atau skema aktual berbeda. "
                            . "Perlu rekonsiliasi manual.\n"
                        );
                        return 1;
                    }
                    echo "SKIP $migrationName\n";
                    continue;
                }
                if ($previousRun !== null) {
                    fwrite(
                        STDERR,
                        "Migrasi $migrationName diblokir: status {$previousRun['status']}, "
                        . "statement selesai {$previousRun['last_completed_statement']}/{$previousRun['statement_count']}. "
                        . "Perlu pemeriksaan dan pemulihan manual; migrasi tidak dijalankan ulang otomatis.\n"
                    );
                    return 1;
                }
                $schemaState = migrationSchemaState($pdo, $migrationName);
                if (!in_array($schemaState, ['none', 'unknown'], true)) {
                    fwrite(
                        STDERR,
                        "Migrasi $migrationName belum tercatat tetapi artefak skemanya {$schemaState}. "
                        . "Migrasi tidak dijalankan; perlu rekonsiliasi manual.\n"
                    );
                    return 1;
                }

                Db::run(
                    'INSERT INTO schema_migration_runs (migration_name, status, checksum, statement_count) VALUES (?, ?, ?, ?)',
                    [$migrationName, 'RUNNING', $checksum, count($statements)],
                    $database
                );

                $completed = 0;
                try {
                    foreach ($statements as $index => $statement) {
                        $statementNumber = $index + 1;
                        $pdo->exec($statement);
                        if ($testCrashAt === 'after_exec:' . $statementNumber) {
                            exit(86);
                        }
                        $completed = $statementNumber;
                        Db::run(
                            'UPDATE schema_migration_runs SET last_completed_statement = ? WHERE migration_name = ?',
                            [$completed, $migrationName],
                            $database
                        );
                    }

                    if ($testCrashAt === 'before_finalize') {
                        exit(87);
                    }

                    $pdo->beginTransaction();
                    try {
                        Db::run(
                            'INSERT INTO schema_migrations (migration_name) VALUES (?)',
                            [$migrationName],
                            $database
                        );
                        Db::run(
                            'UPDATE schema_migration_runs SET status = ?, last_completed_statement = ?, completed_at = NOW(), error_message = NULL WHERE migration_name = ?',
                            ['APPLIED', $completed, $migrationName],
                            $database
                        );
                        $pdo->commit();
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $exception;
                    }
                    if ($testCrashAt === 'after_finalize') {
                        exit(88);
                    }
                    echo "OK $migrationName\n";
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = substr($exception->getMessage(), 0, 4000);
                    Db::run(
                        'UPDATE schema_migration_runs SET status = ?, last_completed_statement = ?, error_message = ? WHERE migration_name = ?',
                        ['FAILED', $completed, $message, $migrationName],
                        $database
                    );
                    fwrite(
                        STDERR,
                        "Gagal menjalankan migrasi $migrationName setelah $completed/" . count($statements)
                        . " statement berhasil: " . $exception->getMessage() . "\n"
                    );
                    return 1;
                }
            }

            return 0;
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
            $lockReleased = (int) $release->fetchColumn() === 1;
            $release->closeCursor();
            if (!$lockReleased) {
                throw new RuntimeException('Lock migrator tidak dapat dilepas secara eksplisit.');
            }
        }

    } catch (Throwable $exception) {
        fwrite(STDERR, "Migrator gagal: " . $exception->getMessage() . "\n");
        return 1;
    }
}
