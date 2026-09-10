<?php
/**
 * Abstract base for checks.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

abstract class Check_Base implements Check_Interface {
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_SKIP = 'skip';

    /**
     * Docs category — override in child or rely on registry map.
     */
    public function category(): string {
        return Check_Registry::category_for($this->id());
    }

    /**
     * Build a uniform result array.
     *
     * @param string $status  pass, fail, or skip.
     * @param string $message Result message.
     * @param float  $start   microtime(true) at start.
     * @param array<string, mixed> $extra Extra fields (e.g. coverage).
     */
    protected function make_result(string $status, string $message, float $start, array $extra = []): array {
        $status = in_array($status, [self::STATUS_PASS, self::STATUS_FAIL, self::STATUS_SKIP], true)
            ? $status
            : self::STATUS_FAIL;

        return array_merge([
            'id'       => $this->id(),
            'label'    => $this->label(),
            'category' => $this->category(),
            'status'   => $status,
            'ok'       => $status !== self::STATUS_FAIL,
            'skipped'  => $status === self::STATUS_SKIP,
            'severity' => $this->severity(),
            'message'  => $message,
            'duration' => round(microtime(true) - $start, 3),
        ], $extra);
    }

    /**
     * Pass/fail helper (legacy).
     *
     * @param bool   $ok      Whether passed.
     * @param string $message Result message.
     * @param float  $start   microtime(true) at start.
     */
    protected function result(bool $ok, string $message, float $start): array {
        return $this->make_result($ok ? self::STATUS_PASS : self::STATUS_FAIL, $message, $start);
    }

    /**
     * Pass result.
     *
     * @param string               $message Result message.
     * @param float                $start   microtime(true) at start.
     * @param array<string, mixed> $extra   Extra fields.
     */
    protected function pass(string $message, float $start, array $extra = []): array {
        return $this->make_result(self::STATUS_PASS, $message, $start, $extra);
    }

    /**
     * Fail result.
     *
     * @param string               $message Result message.
     * @param float                $start   microtime(true) at start.
     * @param array<string, mixed> $extra   Extra fields.
     */
    protected function fail(string $message, float $start, array $extra = []): array {
        return $this->make_result(self::STATUS_FAIL, $message, $start, $extra);
    }

    /**
     * Skip result.
     *
     * @param string               $message Result message.
     * @param float                $start   microtime(true) at start.
     * @param array<string, mixed> $extra   Extra fields.
     */
    protected function skip(string $message, float $start, array $extra = []): array {
        return $this->make_result(self::STATUS_SKIP, $message, $start, $extra);
    }

    /**
     * Relaxed development QA rules (skip Redis, file editor, public debug, XML-RPC).
     * Controlled by Settings qa_profile: auto | development | live.
     */
    protected function is_dev_environment(): bool {
        return Settings::is_dev_profile();
    }
}
