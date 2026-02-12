#!/usr/bin/env bash
set -euo pipefail

# Apply email_logs schema patch on the live server via server-side DB execution.
# This endpoint runs on the site host, so live DB network restrictions are respected.
#
# Usage:
#   bash scripts/db/deploy_email_logs_schema_patch.sh

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

: "${WF_ADMIN_TOKEN:?WF_ADMIN_TOKEN not set}"

WF_DEPLOY_BASE_URL="${WF_DEPLOY_BASE_URL:-https://whimsicalfrog.us}"
PATCH_BASENAME="patch_email_logs_schema.sql"
PATCH_LOCAL="/tmp/${PATCH_BASENAME}"

cat >"${PATCH_LOCAL}" <<'SQL'
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `email_subject` varchar(500) DEFAULT NULL,
  `content` longtext,
  `email_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'sent',
  `error_message` text,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `order_id` varchar(50) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `cc_email` text,
  `bcc_email` text,
  `reply_to` varchar(255) DEFAULT NULL,
  `is_html` tinyint(1) NOT NULL DEFAULT '1',
  `headers_json` longtext,
  `attachments_json` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_status` (`status`),
  KEY `idx_to_email` (`to_email`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='subject');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `subject` VARCHAR(500) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='email_subject');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `email_subject` VARCHAR(500) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='content');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `content` LONGTEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='email_type');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `email_type` VARCHAR(100) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='status');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT ''sent''', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='error_message');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `error_message` TEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='sent_at');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `sent_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='order_id');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `order_id` VARCHAR(50) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='created_by');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `created_by` VARCHAR(100) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='cc_email');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `cc_email` TEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='bcc_email');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `bcc_email` TEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='reply_to');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `reply_to` VARCHAR(255) NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='is_html');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `is_html` TINYINT(1) NOT NULL DEFAULT 1', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='headers_json');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `headers_json` LONGTEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='attachments_json');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `attachments_json` LONGTEXT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='email_logs' AND column_name='created_at');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `email_logs` MODIFY COLUMN `email_type` VARCHAR(100) NULL;
ALTER TABLE `email_logs` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'sent';
ALTER TABLE `email_logs` MODIFY COLUMN `content` LONGTEXT NULL;

SET @e := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='email_logs' AND index_name='idx_email_type');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD INDEX `idx_email_type` (`email_type`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='email_logs' AND index_name='idx_status');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD INDEX `idx_status` (`status`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='email_logs' AND index_name='idx_to_email');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD INDEX `idx_to_email` (`to_email`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='email_logs' AND index_name='idx_sent_at');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD INDEX `idx_sent_at` (`sent_at`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @e := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='email_logs' AND index_name='idx_order_id');
SET @q := IF(@e=0, 'ALTER TABLE `email_logs` ADD INDEX `idx_order_id` (`order_id`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `email_logs`
SET `subject` = `email_subject`
WHERE (`subject` IS NULL OR `subject` = '')
  AND `email_subject` IS NOT NULL
  AND `email_subject` != '';
SQL

RESTORE_URL="${WF_DEPLOY_BASE_URL%/}/api/database_maintenance.php?action=restore_database"
echo "Applying patch via ${RESTORE_URL} ..."
TMP_RESP="/tmp/wf_email_logs_patch_response.json"
HTTP_CODE=$(curl -sS -o "${TMP_RESP}" -w "%{http_code}" \
  -X POST \
  -F "backup_file=@${PATCH_LOCAL}" \
  -F "ignore_errors=1" \
  -F "admin_token=${WF_ADMIN_TOKEN}" \
  "${RESTORE_URL}")

echo "HTTP ${HTTP_CODE}"
cat "${TMP_RESP}"
echo

if [[ "${HTTP_CODE}" -lt 200 || "${HTTP_CODE}" -ge 300 ]]; then
  echo "Patch request failed." >&2
  exit 1
fi

rm -f "${PATCH_LOCAL}"
echo "Done."
