Mobile layout broken
Tablet layout broken
Safari-specific issues
Firefox rendering issues
Chrome-only functionality
iOS menu problems
Android touch issues

# Coverage (check `browser` — see Latest Results → Browser)
# These need real browsers or AI vision — not server-side PHP.
#
# Preflight (partial): missing viewport meta; Chrome-only UA sniffing in HTML.
#
# AI / external integration:
#   1) Filter `msm_browser_ai_coverage` ($rows, $url) → return coverage rows:
#        [ ['line' => 'Mobile layout broken', 'status' => 'pass|fail|ai', 'detail' => '...'], ... ]
#   2) Or save option `msm_browser_ai_last` = [ 'coverage' => [ ...same rows... ], 'at' => time() ]
#
# Suggested pipeline: BrowserStack (or Playwright) screenshots at 390 / 768 / desktop
# → Gemini/Claude vision with the doc lines as a checklist → write rows via filter/option.
#
# Until AI results exist, the check SKIPs (lines show status AI) so digests stay quiet.
