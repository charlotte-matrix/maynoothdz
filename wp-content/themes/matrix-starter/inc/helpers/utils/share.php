<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Social share URLs for a post.
 *
 * @return array<string, string>
 */
function matrix_post_share_urls(int $post_id): array
{
    $permalink = get_permalink($post_id);
    if (!$permalink) {
        return [];
    }

    $url   = rawurlencode($permalink);
    $title = rawurlencode(get_the_title($post_id));

    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
        'bluesky'  => 'https://bsky.app/intent/compose?text=' . $title . '%20' . $url,
    ];
}

/**
 * Inline SVG icons for share buttons.
 */
function matrix_share_icon_svg(string $network): string
{
    $icons = [
        'facebook' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'linkedin' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        'bluesky'  => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.353 3.364.136-.02.275-.039.415-.056-.138.022-.276.042-.415.056-3.912.58-7.387 2.526-2.827 8.922 5.043 5.235 6.906-1.135 7.854-4.293.948 3.158 2.425 9.522 7.818 4.293 4.325-6.006 1.112-8.342-2.803-8.922-.139-.014-.277-.034-.415-.056.14.017.279.036.415.056 2.64.297 5.538-.628 6.353-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.203-.659-.298-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8z"/></svg>',
    ];

    return $icons[$network] ?? '';
}
