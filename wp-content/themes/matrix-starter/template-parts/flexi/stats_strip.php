<?php
/**
 * Stats strip — baseline figures with optional count-up.
 */

$stats       = get_sub_field('stats') ?: [];
$source_note = (string) get_sub_field('source_note');
$source_id   = 'source-' . wp_unique_id();

if (!is_array($stats) || $stats === []) {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Stats strip', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-stats">
    <div class="stats container">
        <?php foreach ($stats as $stat) :
            $value    = trim((string) ($stat['value'] ?? ''));
            $animate  = !empty($stat['animate']) && is_numeric($value);
            $decimals = (int) ($stat['decimals'] ?? 0);
            $suffix   = (string) ($stat['suffix'] ?? '');
            $label    = (string) ($stat['label'] ?? '');
            $display  = matrix_dz_format_stat_display($value, $animate, $decimals, $suffix);

            if ($value === '') {
                continue;
            }
            ?>
            <div class="stat reveal">
                <?php if ($animate) : ?>
                    <strong
                        data-count="<?php echo esc_attr($value); ?>"
                        <?php echo $decimals > 0 ? 'data-decimals="' . esc_attr((string) $decimals) . '"' : ''; ?>
                        <?php echo $suffix !== '' ? 'data-suffix="' . esc_attr($suffix) . '"' : ''; ?>
                    ><?php echo esc_html($display); ?></strong>
                <?php else : ?>
                    <strong><?php echo esc_html($display); ?></strong>
                <?php endif; ?>

                <?php if ($label !== '') : ?>
                    <p><?php echo wp_kses_post($label); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (trim(wp_strip_all_tags($source_note)) !== '') : ?>
            <p class="source" id="<?php echo esc_attr($source_id); ?>"><?php echo wp_kses_post($source_note); ?></p>
        <?php endif; ?>
    </div>
</section>
