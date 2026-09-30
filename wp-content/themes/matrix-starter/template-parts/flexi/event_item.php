<?php
$title = trim((string) get_sub_field('title'));
$date = (string) get_sub_field('date');
$location = trim((string) get_sub_field('location'));
$link = matrix_dz_link_attrs(get_sub_field('link'));
if ($title === '') { return; }
$ts = $date !== '' ? strtotime($date) : false;
$month = $ts ? strtoupper(date_i18n('M', $ts)) : '';
$day = $ts ? date_i18n('j', $ts) : '';
$meta = [];
if ($ts) { $meta[] = date_i18n('j F Y', $ts); }
if ($location !== '') { $meta[] = $location; }
?>
<section aria-label="<?php echo esc_attr__('Event item', 'matrix-starter'); ?>" class="dz-flexi flex-event-block">
  <div class="container flex-demo">
    <div class="flex-event flex-narrow">
      <div class="event-summary">
        <?php if ($month !== '') : ?>
          <div class="event-date"><span><?php echo esc_html($month); ?></span><strong><?php echo esc_html($day); ?></strong></div>
        <?php endif; ?>
        <div>
          <h3><?php echo esc_html($title); ?></h3>
          <?php if ($meta) : ?><p><?php echo esc_html(implode(' · ', $meta)); ?></p><?php endif; ?>
        </div>
      </div>
      <?php if ($link) : ?>
        <a class="text-link" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['title'] !== '' ? $link['title'] : __('Read the story →', 'matrix-starter')); ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
