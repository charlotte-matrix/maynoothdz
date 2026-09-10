<?php
/**
 * Index template
 */
get_header();

/* Legacy blog index — hero/filter settings removed from theme options. */
$settings = [];

/* ── background image ───────────────────────────────────────────────── */
$hero_bg = null;

if ($hero_bg) {
  $bg_url         = esc_url($hero_bg['url']);
  $section_style  = "style=\"background-image:url('{$bg_url}');background-size:cover;background-position:center;\"";
  $fallback_class = '';
} else {
  $section_style  = '';
  $fallback_class = 'bg-primary';
}

/* ── heading + texts ─────────────────────────────────────────────────── */
$hero_tag     = $settings['hero_heading_tag']  ?? 'h1';
$hero_text    = $settings['hero_heading_text'] ?? 'Our Blog';
$sub_text     = $settings['hero_subheading_text'] ?? 'Take a look at what we’ve built';
$filter_title = $settings['filter_section_title'] ?? 'Filter News';

// Clamp heading tag to allowed
$allowed_tags = ['h1','h2','h3','h4','h5','h6','p','span'];
if (!in_array($hero_tag, $allowed_tags, true)) {
  $hero_tag = 'h1';
}
?>
<main id="main-content" class="site-main">
<style>
  /* room for the line under each thumb */
  .testimonial-indicators .indicator-slide{ position:relative; padding-bottom:32px; }
  .testimonial-indicators .testimonial-nav-btn{ position:relative; display:inline-flex; }
  .testimonial-indicators .slick-current .testimonial-nav-btn::after{
    content:"";
    position:absolute; left:50%; transform:translateX(-50%); top:calc(100% + 8px);
    width:2px; height:26px; background:var(--indicator-ring, #dc2626); border-radius:9999px;
  }
</style>

<div class="w-full">
  <section class="flex overflow-hidden relative mt-[4rem] <?php echo esc_attr($fallback_class); ?>">
    <div class="flex flex-col items-center mx-auto w-full bg-black" <?php echo $section_style; ?>>
      <div class="overflow-hidden relative max-w-[1158px] px-5 w-full hero-background">

        <div class="flex z-0 flex-col pt-14 pb-8 w-full max-md:max-w-full">

          <!-- Breadcrumb Navigation -->
          <nav class="flex gap-2 items-center self-start mb-4" aria-label="Breadcrumb">
            <div class="pr-2 w-[30px]">
              <div class="flex w-full min-h-[21px]" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M9 22V12H15V22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
            </div>
            <ol class="flex gap-2 items-center pt-0.5">
              <li class="flex gap-2 items-center">
                <a href="<?php echo esc_url(home_url('/')); ?>"
                   class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2"
                   aria-label="Home">Home</a>
                <?php if (!is_front_page()) : ?>
                  <div class="flex gap-2 items-center pt-0.5 w-4 text-white" aria-hidden="true">
                    <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                <?php endif; ?>
              </li>

              <?php
              $blog_page_id = get_option('page_for_posts');
              $is_blog_home = is_home() && !is_front_page();

              if ($is_blog_home || is_category() || is_single() || is_tag() || is_date() || is_author()) {
                $resources_page_id = get_page_by_path('resources');
                if ($resources_page_id) { ?>
                  <li class="flex gap-2 items-center">
                    <a href="<?php echo esc_url(get_permalink($resources_page_id)); ?>"
                       class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2"
                       aria-label="Resources">Resources</a>
                    <?php if (!is_home() && !is_post_type_archive('Blog') && !is_page($resources_page_id->ID)) : ?>
                      <div class="flex gap-2 items-center pt-0.5 w-4" aria-hidden="true">
                        <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </div>
                    <?php endif; ?>
                  </li>
                <?php }

                if (is_single()) {
                  $categories = get_the_category();
                  if (!empty($categories)) {
                    $category = $categories[0]; ?>
                    <li class="flex gap-2 items-center">
                      <a href="<?php echo esc_url(get_category_link($category->term_id)); ?>"
                         class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2"
                         aria-label="<?php echo esc_attr($category->name); ?>">
                        <?php echo esc_html($category->name); ?>
                      </a>
                      <div class="flex gap-2 items-center pt-0.5 w-4" aria-hidden="true">
                        <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </div>
                    </li>
                  <?php }
                }
              }

              echo '<li><span class="text-sm font-semibold leading-none text-white">';
              if (is_single() || is_page()) {
                the_title();
              } elseif (is_category()) {
                single_cat_title();
              } elseif (is_tag()) {
                single_tag_title();
              } elseif (is_author()) {
                the_author();
              } elseif (is_date()) {
                if (is_day())      echo esc_html(get_the_date('F j, Y'));
                elseif (is_month()) echo esc_html(get_the_date('F Y'));
                elseif (is_year())  echo esc_html(get_the_date('Y'));
              } elseif (is_search()) {
                echo 'Search Results for "' . esc_html(get_search_query()) . '"';
              } elseif (is_404()) {
                echo 'Page Not Found';
              } elseif (is_post_type_archive('Blog')) {
                echo 'Blog';
              } elseif ($is_blog_home) {
                echo 'What\'s new at Paul Tobin';
              }
              echo '</span></li>';
              ?>
            </ol>
          </nav>

          <!-- Main Heading Section -->
          <header class="w-full max-md:max-w-full">
            <?php
              printf(
                '<%1$s class="text-6xl font-bold leading-tight text-white max-md:max-w-full max-md:text-4xl">%2$s</%1$s>',
                esc_attr($hero_tag),
                esc_html($hero_text)
              );
            ?>
            <?php if (!empty($sub_text)): ?>
              <p class="mt-2 text-xl leading-snug text-white max-md:max-w-full">
                <?php echo esc_html($sub_text); ?>
              </p>
            <?php endif; ?>
          </header>
        </div>

        <!-- Filter and Search Section -->
        <div class="flex overflow-hidden z-0 flex-wrap gap-6 items-end pb-14 w-full max-md:max-w-full">

          <!-- Filter Section -->
          <?php
            $all_cats = get_terms([
              'taxonomy'   => 'category',
              'hide_empty' => true,
            ]);

            $current_slug = 'all';
            if (is_tax('category')) {
              $queried = get_queried_object();
              if ($queried && !empty($queried->slug)) {
                $current_slug = $queried->slug;
              }
            }
          ?>
          <div class="flex-1 pb-2 text-base shrink max-md:max-w-full">
            <span class="mb-2 font-bold text-white"><?php echo esc_html($filter_title); ?></span>
            <div role="radiogroup"
                 aria-label="Filter news by category"
                 class="flex flex-wrap gap-4 items-start mt-2 w-full font-medium max-md:max-w-full">
              <button
                type="button"
                role="radio"
                class="gap-2 px-6 py-2 whitespace-nowrap bg-white rounded-lg hover:text-black hover:bg-[#0098D8] btn filter-btn focus:outline-none focus:ring-2 focus:ring-offset-2"
                data-filter="all"
                aria-checked="<?php echo $current_slug === 'all' ? 'true' : 'false'; ?>"
                tabindex="<?php echo $current_slug === 'all' ? '0' : '-1'; ?>">
                All Blog
              </button>

              <?php foreach ($all_cats as $cat):
                $slug    = esc_attr($cat->slug);
                $name    = esc_html($cat->name);
                $checked = ($slug === $current_slug) ? 'true' : 'false';
              ?>
                <button
                  type="button"
                  role="radio"
                  class="gap-2 px-6 py-2 whitespace-nowrap bg-white rounded-lg hover:bg-[#0098D8] filter-btn hover:border-white hover:text-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
                  data-filter="<?php echo $slug; ?>"
                  aria-checked="<?php echo $checked; ?>"
                  tabindex="<?php echo $checked === 'true' ? '0' : '-1'; ?>">
                  <?php echo $name; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Search Section -->
          <div class="flex items-center w-96">
            <form class="flex w-full" role="search" aria-label="Search articles" method="get" action="<?php echo esc_url(home_url('/')); ?>">
              <input type="hidden" name="s" value="" />
              <div class="flex-1 my-auto text-base min-h-14 text-slate-600">
                <div class="flex-1 w-full">
                  <div class="flex flex-1 justify-between items-center px-4 py-3 bg-white rounded-l size-full">
                    <label for="article-search" class="sr-only">Search articles</label>
                    <input
                      type="search"
                      id="article-search"
                      name="s"
                      placeholder="Search articles"
                      class="flex-1 px-4 py-3 bg-white rounded-l border-none size-full text-slate-600 placeholder-slate-600"
                      aria-label="Search articles"
                    />
                  </div>
                </div>
              </div>

              <button type="submit"
                      class="flex gap-2 justify-center items-center px-6 py-4 bg-primary border-2 border-white rounded-none min-h-14 w-[72px] max-md:px-5 search-btn hover:bg-[#0098D8]"
                      aria-label="Search">
                <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M21 21.0408L16.65 16.6908M19 11.0408C19 15.459 15.4183 19.0408 11 19.0408C6.58172 19.0408 3 15.459 3 11.0408C3 6.62249 6.58172 3.04077 11 3.04077C15.4183 3.04077 19 6.62249 19 11.0408Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- POSTS GRID + TOTAL + CLEAR -->
  <section class="flex overflow-hidden relative">
    <div class="flex flex-col items-center pt-5 pb-5 mx-auto w-full max-w-[1158px] px-5">
      <div class="flex flex-col gap-8 pt-12 pb-14 w-full bg-white">

        <!-- Heading: Total posts + Clear Filters Button -->
        <div class="flex justify-between items-center w-full">
          <span class="text-2xl font-bold leading-7 text-slate-600">
            <?php echo (int) (wp_count_posts()->publish ?? 0); ?> posts
          </span>
          <button
            type="button"
            id="clear-filters"
            class="flex gap-2 items-center px-4 py-2 bg-[#F9FAFB] hover:bg-blue rounded cursor-pointer h-[42px] w-fit whitespace-nowrap hover:opacity-90 hidden"
            aria-label="Clear filters">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 4.04102L4 12.041M4 4.04102L12 12.041" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span class="text-sm font-semibold leading-5">Clear filters</span>
          </button>
        </div>

        <!-- Blog Posts Grid — property-card design -->
        <main id="main-content" class="w-full" role="main" aria-label="Blog posts">
          <!-- Below lg: 2 columns with the 3rd item spanning 2 cols; At lg: 3 equal columns -->
          <div class="grid grid-cols-1 gap-12 w-full sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 max-md:gap-8 max-sm:gap-6">
            <?php
            $args = [
              'post_type'      => 'post',
              'posts_per_page' => 12,
              'paged'          => max(1, get_query_var('paged')),
            ];
            $query = new WP_Query($args);

            if ($query->have_posts()):
              $idx = 0;
              while ($query->have_posts()): $query->the_post();

                $post_id    = get_the_ID();
                $post_url   = get_permalink();
                $post_title = get_the_title();
                $post_date  = get_the_date('j F Y');

                // reading time
                $content    = get_post_field('post_content', $post_id);
                $word_count = str_word_count(wp_strip_all_tags($content));
                $minutes    = max(1, (int) ceil($word_count / 200));
                $read_time  = $minutes . ' min' . ($minutes > 1 ? 's' : '');

                // categories for client-side filtering
                $terms = get_the_terms($post_id, 'category') ?: [];
                $cats  = implode(' ', wp_list_pluck($terms, 'slug'));

                // featured image & alt (fallback to title)
                $thumb_id = get_post_thumbnail_id($post_id);
                $img_alt  = $thumb_id ? (get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $post_title) : $post_title;
                $img_html = $thumb_id
                  ? wp_get_attachment_image($thumb_id, 'large', false, [
                      'alt'     => esc_attr($img_alt),
                      'class'   => 'w-full h-full object-cover',
                      'loading' => 'lazy',
                    ])
                  : '';

                // 3rd, 6th, 9th… span rules
                $span_classes = ($idx % 3 === 2)
                  ? 'sm:col-span-2 md:col-span-2 lg:col-span-1'
                  : 'sm:col-span-1 md:col-span-1 lg:col-span-1';
                ?>
                <a href="<?php echo esc_url($post_url); ?>"
                   class="group flex flex-col items-start h-[318px] max-md:h-[280px] max-sm:h-[250px] focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 project-card <?php echo esc_attr($span_classes); ?>"
                   data-categories="<?php echo esc_attr($cats); ?>"
                   aria-label="<?php echo esc_attr('Read full article: ' . $post_title); ?>">
                  <div class="flex overflow-hidden flex-col justify-center items-center w-full flex-[1_0_0] relative">
                    <?php if ($img_html) : ?>
                      <div class="absolute inset-0 w-full h-full">
                        <?php echo $img_html; ?>
                      </div>
                    <?php endif; ?>

                    <!-- Hover/focus gradient overlay (same as property design) -->
                    <div
                      class="absolute inset-0 opacity-0 transition-opacity duration-300 pointer-events-none group-hover:opacity-100 group-focus:opacity-100"
                      style="background: linear-gradient(0deg, rgba(0, 152, 216, 0.25) 0%, rgba(0, 152, 216, 0.25) 100%);"
                      aria-hidden="true"
                    ></div>

                    <!-- Foreground content slab -->
                    <div class="box-border flex flex-col justify-end items-start p-8 w-full flex-[1_0_0] max-sm:p-6 relative z-10">
                      <div class="flex flex-col items-start px-8 py-4 bg-gray-200 max-md:px-6 max-md:py-3 max-sm:px-5 max-sm:py-3">
                        <span class="font-secondary font-semibold text-[2.125rem] leading-[2.5rem] tracking-[-0.01rem] text-[#0A1119]">
                          <div class="transition-colors duration-200">
                            <!-- Keep h3 for your filter/search JS -->
                            <h3 class="text-[inherit] leading-[inherit] font-[inherit]">
                              <?php echo esc_html($post_title); ?>
                            </h3>
                          </div>
                        </span>
                        <p class="font-normal text-[1rem] leading-[1.625rem] text-[#434B53]">
                          <?php echo esc_html($post_date); ?> • <?php echo esc_html($read_time); ?>
                        </p>
                      </div>
                    </div>
                  </div>
                </a>
                <?php
                $idx++;
              endwhile;
              wp_reset_postdata();
            else:
              echo '<div class="py-12 text-center"><p class="text-lg text-gray-600">No Blog found.</p></div>';
            endif;
            ?>
          </div>
        </main>

        <!-- Pagination (uses your helper if present) -->
        <div class="flex justify-center items-center pt-8 w-full">
          <?php if (function_exists('my_custom_pagination')) { my_custom_pagination(); } ?>
        </div>

      </div>
    </div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const buttons      = document.querySelectorAll('.filter-btn');
  const cards        = document.querySelectorAll('.project-card');
  const searchInput  = document.getElementById('article-search');
  const clearFilters = document.getElementById('clear-filters');

  if (!clearFilters) {
    console.warn('⚠️ #clear-filters button not found');
    return;
  }

  let activeFilter = 'all';
  let searchTerm   = '';

  function cardVisible(card) {
    const cats = (card.getAttribute('data-categories') || '').split(' ').filter(Boolean);
    const titleEl = card.querySelector('h3');
    const title   = titleEl ? titleEl.textContent.toLowerCase() : '';
    const matchesCategory = (activeFilter === 'all') || cats.includes(activeFilter);
    const matchesSearch   = (searchTerm === '') || (title.indexOf(searchTerm) !== -1);
    return matchesCategory && matchesSearch;
  }

  function applyFilter() {
    cards.forEach(card => {
      card.style.display = cardVisible(card) ? '' : 'none';
    });
    const needsClear = (activeFilter !== 'all') || (searchTerm !== '');
    clearFilters.classList.toggle('hidden', !needsClear);
  }

  function setActiveFilterButton(nextBtn) {
    buttons.forEach((b) => {
      const isActive = b === nextBtn;
      b.setAttribute('aria-checked', isActive ? 'true' : 'false');
      b.setAttribute('tabindex', isActive ? '0' : '-1');
    });
  }

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      setActiveFilterButton(btn);
      activeFilter = btn.getAttribute('data-filter');
      applyFilter();
    });

    btn.addEventListener('keydown', (e) => {
      const key = e.key;
      if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(key)) {
        return;
      }

      if (key === 'Enter' || key === ' ') {
        e.preventDefault();
        btn.click();
        return;
      }

      e.preventDefault();
      const list = Array.from(buttons);
      const currentIndex = list.indexOf(btn);
      let nextIndex = currentIndex;

      if (key === 'ArrowRight' || key === 'ArrowDown') {
        nextIndex = (currentIndex + 1) % list.length;
      } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
        nextIndex = (currentIndex - 1 + list.length) % list.length;
      } else if (key === 'Home') {
        nextIndex = 0;
      } else if (key === 'End') {
        nextIndex = list.length - 1;
      }

      const nextBtn = list[nextIndex];
      if (!nextBtn) return;
      setActiveFilterButton(nextBtn);
      nextBtn.focus();
      activeFilter = nextBtn.getAttribute('data-filter');
      applyFilter();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      searchTerm = searchInput.value.trim().toLowerCase();
      applyFilter();
    });
  }

  clearFilters.addEventListener('click', () => {
    activeFilter = 'all';
    buttons.forEach(b => {
      b.setAttribute('aria-checked', b.getAttribute('data-filter') === 'all' ? 'true' : 'false');
      b.setAttribute('tabindex', b.getAttribute('data-filter') === 'all' ? '0' : '-1');
    });
    if (searchInput) {
      searchInput.value = '';
      searchTerm = '';
    }
    applyFilter();
  });

  applyFilter();
});
</script>

 </main>
<?php get_footer(); ?>
