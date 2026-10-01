<?php
$heading = trim((string) get_sub_field('heading'));
$intro = trim((string) get_sub_field('intro'));
$uid = wp_unique_id('nl-');
?>
<section aria-label="<?php echo esc_attr__('Newsletter sign-up', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-newsletter">
  <div class="container flex-demo">
    <section aria-labelledby="<?php echo esc_attr($uid); ?>-title" class="community-newsletter">
      <div>
        <?php if ($heading !== '') : ?><h2 id="<?php echo esc_attr($uid); ?>-title"><?php echo esc_html($heading); ?></h2><?php endif; ?>
        <?php if ($intro !== '') : ?><p><?php echo esc_html($intro); ?></p><?php endif; ?>
      </div>
      <div>
        <form data-dz-newsletter>
          <div class="newsletter-fields">
            <label>
              <span class="newsletter-label"><?php esc_html_e('Full name', 'matrix-starter'); ?></span>
              <input autocomplete="name" maxlength="120" name="name" placeholder="e.g. Alex Murphy" required type="text">
            </label>
            <label>
              <span class="newsletter-label"><?php esc_html_e('Email', 'matrix-starter'); ?></span>
              <input autocomplete="email" maxlength="254" name="email" placeholder="e.g. alex@example.com" required type="email">
            </label>
            <button class="button" type="submit"><?php esc_html_e('Subscribe', 'matrix-starter'); ?></button>
          </div>
          <div class="newsletter-consent">
            <input id="<?php echo esc_attr($uid); ?>-consent" name="consent" required type="checkbox">
            <label for="<?php echo esc_attr($uid); ?>-consent">
              <?php esc_html_e('I agree to the DZ', 'matrix-starter'); ?>
              <button class="inline-policy" data-info="privacy" type="button"><?php esc_html_e('privacy policy', 'matrix-starter'); ?></button>
              <?php esc_html_e('and', 'matrix-starter'); ?>
              <button class="inline-policy" data-info="cookies" type="button"><?php esc_html_e('cookie policy', 'matrix-starter'); ?></button>.
            </label>
          </div>
        </form>
        <div aria-live="polite" hidden data-newsletter-feedback class="newsletter-feedback" role="status">
          <p><?php esc_html_e('Your request is ready. Send the email to ask for community updates; your subscription is not yet confirmed.', 'matrix-starter'); ?></p>
          <a class="button small" data-newsletter-email href="#"><?php esc_html_e('Send email request ↗', 'matrix-starter'); ?></a>
        </div>
      </div>
    </section>
  </div>
</section>
