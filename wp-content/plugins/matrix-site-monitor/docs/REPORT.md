# HTML download report

Internal triage report for Matrix Site Monitor.

## Behaviour

- **Format:** HTML file download only (no PDF / email attach yet)
- **Source:** Rolling map `msm_check_results` (last known status per check)
- **Where:** Tools → Site Monitor — **Download report** on Failures, Overview, and All results
- **Capability:** `manage_options` + nonce `msm_download_report`

## Report layout

1. Header — site name, URL, generated-at
2. Summary — fail / warn / pass / skip counts
3. Failures — full detail (label, category, severity, message, time, source, failed coverage lines)
4. Passes — brief list (label + category)
5. Skips — brief list

Filename: `msm-report-{site-slug}-{Y-m-d-His}.html`

## Implementation

- `includes/class-report.php` — build + stream download
- `Admin::handle_download_report` → `admin_post_msm_download_report`
