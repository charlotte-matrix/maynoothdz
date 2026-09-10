<?php
/**
 * Template Part: Single Post Hero (Accessible Version)
 */

$section_id = 'page-hero-' . wp_generate_uuid4();

$background_image_url = get_the_post_thumbnail_url(get_the_ID(), 'full') ?: get_template_directory_uri() . '/assets/images/title_bar.jpg';
$page_title = get_the_title();

$breadcrumb_home_label = 'Home';
$breadcrumb_home_url   = home_url('/');

?>
<div class="mt-[10rem]"></div>
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="pt-14 pb-12 text-white bg-center bg-cover bg-primary"
  role="region"
  aria-labelledby="<?php echo esc_attr($section_id); ?>-title"
>
  <div class="px-4 mx-auto w-[1170px] max-md:w-[750px] max-sm:w-auto">
    <div class="flex flex-col justify-start items-start text-left max-sm:block"">

      <!-- Breadcrumb -->
      <nav class="mb-4 text-center" aria-label="Breadcrumb">
        <ol class="inline-flex flex-wrap items-center space-x-1 text-white capitalize" role="list">
          <li class="text-left">
            <a href="<?php echo esc_url($breadcrumb_home_url); ?>"
               class="inline-block my-5 duration-200 hover:text-red-800 focus:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-800 focus:ring-offset-2"
               aria-label="Go to Home page">
              <span><?php echo esc_html($breadcrumb_home_label); ?></span>
            </a>
          </li>
          <li aria-hidden="true"><span>/</span></li>
          <li class="text-left">
            <span class="flex font-bold text-left text-white underline" aria-current="page"><?php echo esc_html($page_title); ?></span>
          </li>
        </ol>
      </nav>

      <!-- Title -->
      <header class="text-center max-sm:text-left max-sm:w-full">
        <h1 id="<?php echo esc_attr($section_id); ?>-title"
            class="mb-2.5 text-4xl font-bold leading-9 text-left text-white font-primary">
                   <?php echo esc_html($page_title); ?>
        </h1>
      </header>

    </div>
  </div>
</section>