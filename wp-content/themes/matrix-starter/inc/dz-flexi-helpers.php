<?php
/**
 * Helpers for Maynooth DZ flexible content blocks.
 */

if (!function_exists('matrix_dz_link_attrs')) {
    /**
     * Normalize an ACF link field into url / title / target.
     *
     * @param mixed $link
     * @return array{url:string,title:string,target:string}|null
     */
    function matrix_dz_link_attrs($link): ?array
    {
        if (!is_array($link) || empty($link['url'])) {
            return null;
        }

        return [
            'url'    => (string) $link['url'],
            'title'  => (string) ($link['title'] ?? ''),
            'target' => (string) ($link['target'] ?? ''),
        ];
    }
}

if (!function_exists('matrix_dz_render_button')) {
    /**
     * Render a design-system button from an ACF link field.
     */
    function matrix_dz_render_button($link, string $extra_class = ''): void
    {
        $attrs = matrix_dz_link_attrs($link);
        if (!$attrs) {
            return;
        }

        $classes = trim('button ' . $extra_class);
        $target  = $attrs['target'] !== '' ? $attrs['target'] : '_self';
        $rel     = $target === '_blank' ? ' noopener noreferrer' : '';
        $label   = $attrs['title'] !== '' ? $attrs['title'] : $attrs['url'];
        ?>
        <a
            class="<?php echo esc_attr($classes); ?>"
            href="<?php echo esc_url($attrs['url']); ?>"
            <?php echo $target !== '_self' ? 'target="' . esc_attr($target) . '"' : ''; ?>
            <?php echo $rel !== '' ? 'rel="' . esc_attr(trim($rel)) . '"' : ''; ?>
        ><?php echo esc_html($label); ?></a>
        <?php
    }
}

if (!function_exists('matrix_dz_title_html')) {
    /**
     * Convert textarea title lines into safe HTML with <br>.
     */
    function matrix_dz_title_html(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $title) ?: [];
        $lines = array_map('esc_html', $lines);

        return implode('<br>', $lines);
    }
}

if (!function_exists('matrix_dz_render_breadcrumbs')) {
    /**
     * Compact hero breadcrumbs: Home / current page.
     */
    function matrix_dz_render_breadcrumbs(?int $post_id = null): void
    {
        $post_id = $post_id ?: get_the_ID();
        $label   = $post_id ? get_the_title($post_id) : '';
        if ($label === '') {
            $label = __('Current page', 'matrix-starter');
        }
        ?>
        <nav aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>" class="breadcrumbs">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Home', 'matrix-starter'); ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo esc_html($label); ?></span>
        </nav>
        <?php
    }
}

if (!function_exists('matrix_dz_format_stat_display')) {
    /**
     * Initial visible value for a stats strip cell.
     */
    function matrix_dz_format_stat_display(string $value, bool $animate, int $decimals, string $suffix): string
    {
        if (!$animate || !is_numeric($value)) {
            return $value . $suffix;
        }

        $number = (float) $value;
        $formatted = number_format($number, $decimals, '.', ',');

        return $formatted . $suffix;
    }
}

if (!function_exists('matrix_dz_tag_icon')) {
    /**
     * Theme icon path for a project tag slug.
     */
    function matrix_dz_tag_icon(string $tag): string
    {
        $map = [
            'retrofit'     => 'home-icon.svg',
            'transport'    => 'travel-icon.svg',
            'biodiversity' => 'leaf-icon.svg',
            'nature'       => 'leaf-icon.svg',
            'public-realm' => 'public-realm-icon.svg',
        ];
        $file = $map[$tag] ?? 'leaf-icon.svg';
        return get_template_directory_uri() . '/assets/dz/' . $file;
    }
}

if (!function_exists('matrix_dz_tag_label')) {
    function matrix_dz_tag_label(string $tag): string
    {
        $map = [
            'retrofit'     => 'Retrofit',
            'transport'    => 'Transport',
            'biodiversity' => 'Biodiversity',
            'nature'       => 'Nature',
            'public-realm' => 'Public realm',
        ];
        return $map[$tag] ?? ucfirst(str_replace('-', ' ', $tag));
    }
}

if (!function_exists('matrix_dz_assets_url')) {
    function matrix_dz_assets_url(string $file = ''): string
    {
        return trailingslashit(get_template_directory_uri() . '/assets/dz') . ltrim($file, '/');
    }
}
