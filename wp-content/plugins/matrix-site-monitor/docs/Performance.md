Slow page load
High Time To First Byte (TTFB)
Poor Core Web Vitals
Largest Contentful Paint (LCP) too slow
Cumulative Layout Shift (CLS)
High Total Blocking Time
Excessive JavaScript
Render blocking CSS
Large unoptimised images
Missing lazy loading
Missing page caching
Cache not purging
Object cache disabled
Slow database queries
Excessive autoloaded options
Oversized wp_options table
Huge post revisions table
High CPU usage
High memory usage
PHP workers exhausted

# Coverage (check `performance` — see Latest Results → Performance)
# Automated: home fetch >3s, script count/size, blocking CSS count, large images (>500KB),
#   missing lazy-load, object cache drop-in, autoload size, wp_options size, revisions, memory peak.
# Partial: TTFB via cURL starttransfer_time; page-cache headers/plugins; sample DB timing.
# Manual: Core Web Vitals / LCP / CLS / TBT (Lighthouse/CrUX); cache purge drills; CPU / PHP workers
#   (filters: msm_performance_cwv_issue, msm_performance_cpu_issue, msm_performance_workers_issue,
#   msm_performance_cache_purge_issue).
