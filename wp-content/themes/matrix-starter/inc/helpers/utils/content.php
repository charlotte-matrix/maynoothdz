<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outer wrapper for page / singular body content.
 */
function matrix_content_container_classes(): string
{
    return 'mx-auto w-full max-w-[1280px] px-5 py-10 md:px-10';
}

/**
 * Narrow column for long-form reading (posts, resources, etc.).
 */
function matrix_content_single_inner_classes(): string
{
    return 'mx-auto w-full max-w-[720px]';
}

/**
 * Article / entry wrapper classes (block editor prose).
 */
function matrix_content_article_classes(): string
{
    return 'entry-content prose prose-lg max-w-none';
}
