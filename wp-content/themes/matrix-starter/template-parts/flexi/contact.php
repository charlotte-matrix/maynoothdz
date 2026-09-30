<?php
$heading = trim((string) get_sub_field('heading'));
$address = (string) get_sub_field('address');
$email = trim((string) get_sub_field('email'));
if ($heading === '' && $address === '' && $email === '') { return; }
?>
<section aria-label="<?php echo esc_attr__('Contact', 'matrix-starter'); ?>" class="dz-flexi flex-contact-block">
  <div class="container flex-demo">
    <div class="flex-contact flex-narrow">
      <div>
        <?php if ($heading !== '') : ?><h2><?php echo esc_html($heading); ?></h2><?php endif; ?>
        <?php if ($address !== '') : ?><p><?php echo wp_kses_post($address); ?></p><?php endif; ?>
      </div>
      <?php if ($email !== '') : ?>
        <a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?> ↗</a>
      <?php endif; ?>
    </div>
  </div>
</section>
