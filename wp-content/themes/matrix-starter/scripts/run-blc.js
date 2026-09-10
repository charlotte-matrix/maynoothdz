#!/usr/bin/env node
/**
 * Broken link crawl (broken-link-checker).
 *
 * Base URL resolution (first match wins):
 *   1. First CLI argument:  node scripts/run-blc.js https://example.com/
 *   2. process.env.BASE_URL
 *   3. process.env.WP_HOME
 *   4. Default below (Local WP — override via .env or CLI)
 *
 * npm:  npm run test:links -- http://127.0.0.1:10101/
 */
const { exec } = require('child_process');
const fs = require('fs');
const url = require('url');
require('dotenv').config({ path: '.env' });   // ← load .env first

/* ── 1. Decide which URL to scan ───────────────────────────── */
const cliUrl = process.argv[2];
const base =
  cliUrl ||
  process.env.BASE_URL ||
  process.env.WP_HOME ||
  'http://localhost:10101';

const parsed = new url.URL(base);

/* ── 2. Output folder ─────────────────────────────────────── */
const reportDir = 'tests/link-report';
fs.mkdirSync(reportDir, { recursive: true });

console.log(`🔗  Crawling for broken links: ${parsed.href}`);

/* ── 3. Run broken-link-checker ───────────────────────────── */
exec(
  `npx blc "${parsed.href}" --recursive --ordered ` +
  `--filter-level 3 --follow --exclude ".*/wp-admin/.*"`,
  { maxBuffer: 1024 * 500 },
  (err, stdout, stderr) => {
    const file = `${reportDir}/blc.txt`;
    const output = [stdout, stderr].filter(Boolean).join('\n');
    fs.writeFileSync(file, output);
    console.log(output);

    if (err) {
      console.error(`❌  Broken links found (or crawl failed) – see ${file}`);
      process.exit(1);
    } else {
      console.log('✅  No broken links detected');
    }
  }
);