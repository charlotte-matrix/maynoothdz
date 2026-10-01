<?php
$steps = get_sub_field('steps') ?: [];
if (!is_array($steps) || $steps === []) { return; }
?>
<section aria-label="<?php echo esc_attr__('Steps', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-steps">
  <div class="container flex-demo">
    <ol class="publishing-steps">
      <?php $i = 0; foreach ($steps as $step) :
        $title = trim((string) ($step['title'] ?? ''));
        if ($title === '') { continue; }
        $i++;
      ?>
        <li>
          <span aria-hidden="true" class="publishing-number"><?php echo esc_html((string) $i); ?></span>
          <div>
            <h3><?php echo esc_html($title); ?></h3>
            <?php if (!empty($step['description'])) : ?><p><?php echo esc_html($step['description']); ?></p><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
