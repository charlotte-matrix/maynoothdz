#!/usr/bin/env node
/**
 * Accessibility scan using Playwright + axe-core.
 *
 * Usage:
 *   npm run test:a11y:quick
 *   npm run test:a11y:full
 *   npm run test:a11y:quick -- http://localhost:10101/
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
require('dotenv').config({ path: '.env' });

const mode = process.argv[2] === 'full' ? 'full' : 'quick';
const cliUrl = process.argv[3];
const base = cliUrl || process.env.BASE_URL || process.env.WP_HOME || 'http://localhost:10014/';
const baseUrl = new URL(base).href.replace(/\/$/, '');

const quickPaths = ['/', '/about/', '/events/', '/contact/'];
const fullPaths = [
  '/',
  '/about/',
  '/events/',
  '/contact/',
  '/resources/',
  '/partners/',
];

const pathsToScan = mode === 'full' ? fullPaths : quickPaths;
const reportDir = path.join('tests', 'a11y-report');
const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
const reportPath = path.join(reportDir, `a11y-${mode}-${timestamp}.json`);

async function run() {
  fs.mkdirSync(reportDir, { recursive: true });
  console.log(`♿ Running ${mode} accessibility scan on ${baseUrl}`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  const summary = [];
  let totalViolations = 0;
  let navigationFailures = 0;

  const impactPenalty = { critical: 10, serious: 7, moderate: 3, minor: 1 };

  for (const route of pathsToScan) {
    const target = `${baseUrl}${route}`;
    try {
      console.log(`→ Scanning ${target}`);
      const response = await page.goto(target, { waitUntil: 'networkidle', timeout: 45000 });
      if (!response || !response.ok()) {
        navigationFailures += 1;
        summary.push({
          url: target,
          error: `Navigation failed (${response ? response.status() : 'no response'})`,
          violationCount: 0,
          score: 0,
          violations: [],
        });
        continue;
      }

      const results = await new AxeBuilder({ page })
        // Third-party player internals can trigger unactionable violations in host pages.
        .exclude('iframe[src*="youtube.com"]')
        .exclude('#movie_player')
        .disableRules(['aria-prohibited-attr'])
        .analyze();
      totalViolations += results.violations.length;
      const penalty = results.violations.reduce(
        (sum, v) => sum + (impactPenalty[v.impact] || 3),
        0
      );
      const score = Math.max(0, Math.min(100, 100 - penalty));
      summary.push({
        url: target,
        violationCount: results.violations.length,
        score,
        violations: results.violations.map((v) => ({
          id: v.id,
          impact: v.impact,
          description: v.description,
          help: v.help,
          helpUrl: v.helpUrl,
          nodes: v.nodes.map((n) => ({
            target: n.target,
            html: n.html,
            failureSummary: n.failureSummary,
          })),
        })),
      });
    } catch (error) {
      navigationFailures += 1;
      summary.push({
        url: target,
        error: error.message,
        violationCount: 0,
        score: 0,
        violations: [],
      });
    }
  }

  await browser.close();

  const minScore = 80;
  const scoredPages = summary.filter((row) => typeof row.score === 'number' && !row.error);
  const lowestScore = scoredPages.length
    ? Math.min(...scoredPages.map((row) => row.score))
    : 0;

  const payload = {
    mode,
    baseUrl,
    scannedAt: new Date().toISOString(),
    pagesScanned: pathsToScan.length,
    minScoreRequired: minScore,
    lowestScore,
    totalViolations,
    navigationFailures,
    results: summary,
  };
  fs.writeFileSync(reportPath, JSON.stringify(payload, null, 2));

  console.log(`\nReport written to ${reportPath}`);
  for (const row of summary) {
    if (row.error) {
      console.log(`  ${row.url} — ERROR: ${row.error}`);
    } else {
      console.log(`  ${row.url} — score ${row.score}/100 (${row.violationCount} violations)`);
    }
  }

  if (navigationFailures > 0) {
    console.error(`❌ ${navigationFailures} page(s) could not be loaded`);
    process.exit(1);
  }

  const belowMin = scoredPages.filter((row) => row.score < minScore);
  if (belowMin.length > 0) {
    console.error(
      `❌ ${belowMin.length} page(s) below minimum score ${minScore} (${totalViolations} total violation(s))`
    );
    process.exit(1);
  }

  if (totalViolations > 0) {
    console.warn(`⚠️ ${totalViolations} violation(s) found, but all pages scored ≥ ${minScore}`);
  }

  console.log(`✅ All scanned pages meet the minimum accessibility score (${minScore})`);
}

run().catch((error) => {
  console.error('❌ Accessibility scan failed:', error);
  process.exit(1);
});
