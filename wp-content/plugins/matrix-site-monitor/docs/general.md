White Screen of Death (WSOD)
Internal Server Error (500)
Error Establishing Database Connection
Critical Error on Website
Infinite redirect loop
Site stuck in maintenance mode
Homepage loads but inner pages fail
Broken permalinks (404s)
Mixed content after SSL migration
SSL certificate expired
DNS misconfiguration
Domain not resolving
PHP fatal errors
PHP warnings/notices displayed publicly
Missing images after migration
Incorrect file permissions
Uploads failing
Media library broken
Cron jobs not running
Scheduled posts not publishing

# Coverage (check `general` — see Latest Results → General)
# Automated: 500s, DB error text, critical error text, redirect loops, .maintenance,
#   home-vs-inner failures, sample 404s, mixed http:// assets, SSL expiry, fatals/HTML,
#   public display_errors, sample image/attachment HEAD, cron overdue, stuck future posts.
# Partial: WSOD (empty body heuristic), DNS/resolve (gethostbyname), uploads/permissions.
# Related focused checks still exist: http_smoke, maintenance_mode, debug_display,
#   fatal_errors, cron_health, ssl_expiry.
