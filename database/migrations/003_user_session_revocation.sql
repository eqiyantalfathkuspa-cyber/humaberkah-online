ALTER TABLE users ADD COLUMN auth_token CHAR(64) NULL AFTER last_login_at;
