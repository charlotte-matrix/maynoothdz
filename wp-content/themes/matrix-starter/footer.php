<?php get_template_part('template-parts/footer/newsletter'); ?>
<?php get_template_part('template-parts/footer/footer'); ?>
<?php
$show_back_to_top = function_exists('get_field')
  ? (bool) get_field('back_to_top_settings_enable_back_to_top', 'option')
  : true;
if ($show_back_to_top) {
  get_template_part('template-parts/footer/back-to-top');
}
?>

<?php wp_footer(); ?>


</body>

</html>