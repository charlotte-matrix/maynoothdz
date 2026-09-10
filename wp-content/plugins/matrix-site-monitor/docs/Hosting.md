Disk nearly full
PHP memory exhausted
PHP version outdated
MySQL down
Redis unavailable
Object cache failing
OPCache disabled
Cron disabled
Email server unavailable
SSL expiring
Domain expiry approaching
Backup failures

# Coverage (check `hosting` — see Latest Results → Hosting)
# Automated: disk threshold, memory_limit/peak, PHP version, MySQL SELECT 1,
#   object-cache probe, OPCache status, cron overdue, SSL expiry window.
# Partial: Redis (when WP_REDIS_HOST / extension), SMTP plugin presence (no test send),
#   UpdraftPlus last backup age.
# Manual: domain expiry (registrar / msm_hosting_domain_expiry filter).
# Related focused checks: disk_space, php_memory, php_wp_versions, object_cache,
#   cron_health, ssl_expiry, backup_status.
