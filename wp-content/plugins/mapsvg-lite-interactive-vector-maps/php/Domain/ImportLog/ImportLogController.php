<?php

namespace MapSVG;

class ImportLogController extends Controller
{
    /**
     * GET /import-logs?schemaName=xxx
     * Returns up to 100 log entries for the given schema, errors first.
     */
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $schemaName = sanitize_key($request->get_param('schemaName') ?? '');

        if (empty($schemaName)) {
            return static::render(['error' => 'schemaName is required.'], 400);
        }

        $repo  = new ImportLogRepository();
        $items = $repo->findBySchema($schemaName, 100);

        return static::render([
            'items' => array_map(static fn(ImportLog $log) => $log->getData(), $items),
            'total' => count($items),
        ], 200);
    }
}
