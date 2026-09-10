Corrupt tables
Missing tables
Large transients
Orphaned metadata
Spam comments
Excessive revisions
Failed database upgrades

# Coverage (check `db_hygiene` — see Latest Results → Database)
# Automated: CHECK TABLE sample on core tables; SHOW TABLES for core WP tables;
#   transient / spam / revision counts; orphaned postmeta & usermeta counts;
#   db_version vs WordPress $wp_db_version (pending upgrade.php).
# Thresholds: spam >100, revisions >500, transients >1000, orphan postmeta >50 / usermeta >20.
