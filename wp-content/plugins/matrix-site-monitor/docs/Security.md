Malware detected
Injected JavaScript
Suspicious admin users
File changes
Core files modified
Plugin vulnerabilities
Theme vulnerabilities
XML-RPC attacks
Brute force attempts
Login attempts excessive
File editor enabled
Weak passwords
Directory listing enabled

# Coverage (check `security` — see Latest Results → Security)
# Automated: admin anomalies (new/risky logins), core checksum sample, XML-RPC open,
#   DISALLOW_FILE_EDIT, directory listing on wp-content/uploads.
# Partial: malware/injected JS heuristics (obfuscation, PHP in uploads, script-from-IP);
#   plugin/theme "Tested up to" + optional WPScan token; Limit Login lockout counts.
# Manual: weak passwords (msm_security_weak_password_issue); full malware → Wordfence/host.
# Filters: msm_security_bruteforce_issue, msm_security_weak_password_issue.
# Related: admin_anomalies, security_advisories, xmlrpc, file_editor.
