-- MedConnect uses the medconnect_db database.
-- Import the official schema and seeds from:
--
--   medconnect-kandy/php-backend/database/schema.sql
--   medconnect-kandy/php-backend/database/migration_opd_tokens.sql
--   (and other migration_*.sql files as needed)
--
-- Then seed data:
--   php medconnect-kandy/php-backend/seed_clinics.php
--   php medconnect-kandy/php-backend/seed_opd_sessions.php
--
CREATE DATABASE IF NOT EXISTS medconnect_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medconnect_db;
