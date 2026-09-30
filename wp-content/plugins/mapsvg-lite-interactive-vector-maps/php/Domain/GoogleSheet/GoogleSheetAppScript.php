<?php

namespace MapSVG;

/**
 * Manages bi-directional sync between MapSVG and a Google Sheet via
 * an AppScript Web App deployed by the user.
 *
 * ## Security model
 *
 * A shared SECRET is established once during setup, then stored encrypted
 * (AES-256-CBC keyed on WordPress's AUTH_KEY constant).
 *
 * Every subsequent request in both directions is authenticated with
 * HMAC-SHA256 over "timestamp:action" and includes a Unix timestamp that
 * must be within 30 seconds of the receiver's clock to prevent replay attacks.
 *
 * ## Signature placement
 *
 * - MapSVG → AppScript: `signature` field inside the JSON body
 *   (AppScript's doPost cannot read custom HTTP headers).
 * - AppScript → MapSVG: `X-MapSVG-Signature` HTTP header
 *   (sent via UrlFetchApp, read by PHP as `x_mapsvg_signature`).
 */
class GoogleSheetAppScript
{
    private const CIPHER = 'aes-256-cbc';

    // ──────────────────────────────────────────────────────────────────────────
    // Setup handshake
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * One-time setup: generate a SECRET, send it to the AppScript Web App
     * (authenticated by the user-provided setup key), then encrypt and persist
     * it in the schema record.
     *
     * @param string     $setupKey     One-time key shown by AppScript on first run.
     * @param string     $appScriptUrl Deployed AppScript Web App URL.
     * @param Schema     $schema       Schema to attach the secret to.
     * @return array{ok?:bool, error?:string}
     */
    public static function setup(string $setupKey, string $appScriptUrl, Schema $schema, string $syncUrl = ''): array
    {
        if (empty($setupKey) || empty($appScriptUrl)) {
            return ['error' => 'setupKey and appScriptUrl are required.'];
        }

        $settings = ImportSettingsService::getForSchema($schema);
        $secret = wp_generate_password(32, false);

        $response = SafeRemoteUrl::post($appScriptUrl, [
            'timeout'     => 15,
            'redirection' => 0,
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode([
                'setupKey'  => $setupKey,
                'secret'    => $secret,
                'timestamp' => time(),
                'syncUrl'   => $syncUrl,
                'sheetName' => $settings['gsSheetName'] ?? 'Sheet1',
                'idColumn'  => $settings['gsIdFieldName'] ?? $schema->getPrimaryKeyFieldName(),
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['error' => SafeRemoteUrl::publicErrorMessage($response, 'Could not reach AppScript.')];
        }

        $code = wp_remote_retrieve_response_code($response);

        // Google Apps Script Web Apps always return 302 after running doPost.
        // The actual JSON response is served at the Location URL — fetch it with a clean GET.
        if ($code === 302) {
            $location = wp_remote_retrieve_header($response, 'location');
            if (empty($location)) {
                return ['error' => 'AppScript redirect had no Location header.'];
            }
            $response = SafeRemoteUrl::get($location, [
                'timeout' => 15,
            ]);
            if (is_wp_error($response)) {
                return ['error' => SafeRemoteUrl::publicErrorMessage($response, 'Could not retrieve AppScript response.')];
            }
            $code = wp_remote_retrieve_response_code($response);
        }

        if ($code !== 200) {
            return ['error' => 'AppScript returned HTTP ' . $code . '. Check the URL and try again.'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['error'])) {
            return ['error' => 'AppScript error: ' . $body['error']];
        }

        ImportSettingsService::updateForSchema($schema, [
            'gsSecret'       => self::encryptSecret($secret),
            'gsAppScriptUrl' => $appScriptUrl,
        ]);

        return ['ok' => true];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reset handshake
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send a signed "reset" action to AppScript so it wipes its properties and
     * regenerates a fresh setup key, then clear the secret from the schema.
     *
     * @param Schema $schema
     * @return array{ok?:bool, error?:string}
     */
    public static function reset(Schema $schema): array
    {
        $settings = ImportSettingsService::getForSchema($schema);
        if (empty($settings['gsAppScriptUrl']) || empty($settings['gsSecret'])) {
            return ['error' => 'Not connected.'];
        }

        $secret = self::decryptSecret((string) $settings['gsSecret']);
        if ($secret === null) {
            return ['error' => 'Could not decrypt secret. The connection may be broken.'];
        }

        $timestamp = time();
        $action    = 'reset';
        $signature = self::sign($timestamp, $action, $secret);

        $response = SafeRemoteUrl::post($settings['gsAppScriptUrl'], [
            'timeout'     => 15,
            'redirection' => 0,
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode([
                'timestamp' => $timestamp,
                'action'    => $action,
                'signature' => $signature,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['error' => SafeRemoteUrl::publicErrorMessage($response, 'Could not reach AppScript.')];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 302) {
            $location = wp_remote_retrieve_header($response, 'location');
            if (!empty($location)) {
                $response = SafeRemoteUrl::get($location, [
                    'timeout' => 15,
                ]);
                if (is_wp_error($response)) {
                    return ['error' => SafeRemoteUrl::publicErrorMessage($response, 'Could not retrieve AppScript response.')];
                }
                $code = wp_remote_retrieve_response_code($response);
            }
        }

        if ($code !== 200) {
            return ['error' => 'AppScript returned HTTP ' . $code . '.'];
        }

        ImportSettingsService::updateForSchema($schema, [
            'gsSecret'       => '',
            'gsAppScriptUrl' => '',
        ]);

        return ['ok' => true];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // URL helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build the sync endpoint URL for a given collection and schema name.
     *
     * Override the base URL for local/ngrok development by defining
     * MAPSVG_REST_BASE_URL in wp-config.php, e.g.:
     *   define('MAPSVG_REST_BASE_URL', 'https://your-ngrok-subdomain.ngrok-free.app');
     *
     * @param string $collection  "objects" or "regions"
     * @param string $schemaName  Schema name (e.g. "my_db")
     * @return string             Full sync URL
     */
    public static function buildSyncUrl(string $collection, string $schemaName): string
    {
        if (defined('MAPSVG_REST_BASE_URL')) {
            $base = rtrim(\MAPSVG_REST_BASE_URL, '/') . '/wp-json/mapsvg/v1';
        } else {
            $base = rtrim(get_rest_url(null, 'mapsvg/v1'), '/');
        }
        return $base . '/' . $collection . '/' . $schemaName . '/sync';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Encryption helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Encrypt a plaintext secret using AES-256-CBC keyed on AUTH_KEY.
     * Returns base64( IV || ciphertext ).
     */
    public static function encryptSecret(string $secret): string
    {
        $key       = substr(hash('sha256', AUTH_KEY, true), 0, 32);
        $ivLen     = openssl_cipher_iv_length(self::CIPHER);
        $iv        = openssl_random_pseudo_bytes($ivLen);
        $encrypted = openssl_encrypt($secret, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value previously returned by encryptSecret().
     * Returns null if decryption fails (e.g. AUTH_KEY was rotated).
     */
    public static function decryptSecret(string $stored): ?string
    {
        $raw    = base64_decode($stored, true);
        if ($raw === false) return null;
        $key    = substr(hash('sha256', AUTH_KEY, true), 0, 32);
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $iv     = substr($raw, 0, $ivLen);
        $data   = substr($raw, $ivLen);
        $result = openssl_decrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $result !== false ? $result : null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HMAC signing
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Compute HMAC-SHA256 over "timestamp:action".
     *
     * Using only timestamp + action (not the full row payload) avoids
     * JSON key-ordering discrepancies between PHP and JavaScript while
     * still providing authentication + replay protection.
     */
    public static function sign(int $timestamp, string $action, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . ':' . $action, $secret);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Incoming request verification (AppScript → MapSVG)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Verify an incoming request from AppScript.
     *
     * Expects:
     *   - JSON body: { timestamp: <unix>, action: "upsert"|"delete", row: {...} }
     *   - Header: X-MapSVG-Signature: <hex HMAC>
     *
     * @param \WP_REST_Request $request
     * @param Schema           $schema
     * @return bool
     */
    public static function verifyRequest(\WP_REST_Request $request, Schema $schema): bool
    {
        $settings = ImportSettingsService::getForSchema($schema);
        if (empty($settings['gsSecret'])) {
            return false;
        }

        $secret = self::decryptSecret((string) $settings['gsSecret']);
        if ($secret === null) {
            return false;
        }

        $body = $request->get_json_params();
        if (empty($body)) {
            return false;
        }

        $timestamp = isset($body['timestamp']) ? (int) $body['timestamp'] : 0;
        if (abs(time() - $timestamp) > 30) {
            return false;
        }

        $signature = $request->get_header('x_mapsvg_signature');
        if (empty($signature)) {
            return false;
        }

        $action   = $body['action'] ?? 'upsert';
        $expected = self::sign($timestamp, $action, $secret);

        return hash_equals($expected, $signature);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Outgoing push (MapSVG → AppScript)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Push a row change to the AppScript Web App.
     * Uses `blocking: false` so the user's request is not held up.
     *
     * @param array  $rowData  Associative array of field name → value.
     * @param string $action   "upsert" or "delete".
     * @param Schema $schema
     */
    public static function push(array $rowData, string $action, Schema $schema): void
    {
        $settings = ImportSettingsService::getForSchema($schema);
        if (empty($settings['gsAppScriptUrl']) || empty($settings['gsSecret'])) {
            return;
        }

        $secret = self::decryptSecret((string) $settings['gsSecret']);
        if ($secret === null) {
            return;
        }

        $timestamp = time();
        $signature = self::sign($timestamp, $action, $secret);

        $payload = [
            'timestamp' => $timestamp,
            'action'    => $action,
            'row'       => $rowData,
            'signature' => $signature,
        ];

        // Validate before fire-and-forget so a poisoned gsAppScriptUrl cannot SSRF.
        $validated = SafeRemoteUrl::validate((string) $settings['gsAppScriptUrl']);
        if (is_wp_error($validated)) {
            return;
        }

        SafeRemoteUrl::post($validated, [
            'timeout'     => 3,
            'blocking'    => false,
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode($payload),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Row upsert helper
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Insert or update a single row via Repository::upsert(), keyed on
     * schema->gsIdFieldName (or schema's primary key field by default).
     * Returns the resulting entity.
     *
     * @param array      $row
     * @param Repository $repo
     * @param Schema     $schema
     * @return mixed
     */
    public static function upsertRow(array $row, Repository $repo, Schema $schema)
    {
        $settings = ImportSettingsService::getForSchema($schema);
        $idFieldName = $settings['gsIdFieldName'] ?? $schema->getPrimaryKeyFieldName();
        return $repo->upsert($row, $idFieldName);
    }
}
