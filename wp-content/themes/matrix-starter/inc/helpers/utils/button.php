<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility classes for theme buttons (.btn-pace-* in assets/css/app.css).
 *
 * @param string               $variant primary|secondary|secondary-dark|ghost|soft|icon|share|submit|custom
 * @param array<string, mixed> $options   full_mobile (bool) — full width on mobile
 */
function matrix_btn_classes(string $variant = 'primary', array $options = []): string
{
    $map = [
        'primary'        => 'btn btn-pace-primary',
        'secondary'      => 'btn btn-pace-secondary',
        'secondary-dark' => 'btn btn-pace-secondary-dark',
        'ghost'          => 'btn btn-pace-ghost',
        'soft'           => 'btn btn-pace-soft',
        'icon'           => 'btn btn-pace-icon',
        'share'          => 'btn btn-pace-share',
        'submit'         => 'btn btn-pace-primary btn-pace-submit',
        'custom'         => 'btn btn-pace-custom',
    ];

    $classes = $map[$variant] ?? $map['primary'];

    if (!empty($options['full_mobile'])) {
        $classes .= ' btn-pace-full-mobile';
    }

    return $classes;
}
