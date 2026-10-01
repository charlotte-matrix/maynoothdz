<?php
/**
 * Accordion — native details/summary, one open at a time.
 */

$items = get_sub_field('items') ?: [];

if (!is_array($items) || $items === []) {
    return;
}

$group_name = 'flex-faq-' . wp_unique_id();
?>
<section aria-label="<?php echo esc_attr__('Accordion', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-accordion">
    <div class="container flex-demo">
        <div class="flex-narrow flex-faq">
            <?php foreach ($items as $item) :
                $summary = trim((string) ($item['summary'] ?? ''));
                $body    = (string) ($item['body'] ?? '');
                $open    = !empty($item['open_by_default']);

                if ($summary === '') {
                    continue;
                }
                ?>
                <details name="<?php echo esc_attr($group_name); ?>" <?php echo $open ? 'open' : ''; ?>>
                    <summary><?php echo esc_html($summary); ?></summary>
                    <?php if (trim(wp_strip_all_tags($body)) !== '') : ?>
                        <div><?php echo wp_kses_post($body); ?></div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
