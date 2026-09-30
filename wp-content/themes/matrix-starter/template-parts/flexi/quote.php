<?php
/**
 * Quote / highlight — pull quote with attribution.
 */

$quote_text  = (string) get_sub_field('quote_text');
$attribution = trim((string) get_sub_field('attribution'));

if (trim(wp_strip_all_tags($quote_text)) === '') {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Quote', 'matrix-starter'); ?>" class="dz-flexi flex-quote">
    <div class="container flex-demo">
        <div class="article-copy">
            <blockquote>
                <p>“<?php echo wp_kses_post($quote_text); ?>”</p>
                <?php if ($attribution !== '') : ?>
                    <cite><?php echo esc_html($attribution); ?></cite>
                <?php endif; ?>
            </blockquote>
        </div>
    </div>
</section>
