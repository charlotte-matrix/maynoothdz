<?php
use function Brain\Monkey\Functions\when;

/* ─── Mocks run before each test ─── */
beforeEach(function () {
    // 1. Nonce helper
    when('wp_create_nonce')->justReturn('demo_nonce');

    // 2. do_action() – immediately call the handler function yourself
    when('do_action')->alias(function ($hook) {
        if ($hook === 'admin_post_theme_form_submit') {
            theme_form_submit_handler();      // <- call your real handler
        }
    });

    // 3. get_posts() – return a fake CPT row the handler “created”
    when('get_posts')->justReturn([
        (object) ['ID' => 123]
    ]);

    // 4. get_post_meta() – return the meta you expect
    when('get_post_meta')->alias(function ($id, $key) {
        $map = [
            'name'    => 'Ada Lovelace',
            'email'   => 'ada@example.com',
            'message' => 'Hello world!',
        ];
        return $map[$key] ?? '';
    });
});
