<?php
// template-parts/blog/content.php

// Get the current queried object (category, tag, date, etc.)
$queried_object = get_queried_object();
$category_slug = is_category() ? $queried_object->slug : 'all';

// Legacy blog partial — hero/filter settings removed from theme options.
$settings = [];

// 2) Background image logic
$hero_bg = null;

if ($hero_bg) {
$bg_url = esc_url($hero_bg['url']);
$section_style = "style=\"background-image:url('{$bg_url}');background-size:cover;background-position:center;\"";
$fallback_class = '';
} else {
// fallback Tailwind if no image
$section_style = '';
$fallback_class = 'bg-primary';
}

// 3) Hero heading tag & text
$hero_tag  = $settings['hero_heading_tag']   ?? 'h1';
$hero_text = $settings['hero_heading_text']  ?? "What's new at Tyrecare";

// 4) Sub-heading
$sub_text  = $settings['hero_subheading_text'] ?? 'Latest and greatest.';

// 5) Filter title
$filter_title = $settings['filter_section_title'] ?? 'Filter by';

?>
<div class="mt-[7rem] w-full" x-data="{
      activeCategory: '<?php echo esc_js($category_slug); ?>',
      setCategory(category) {
          window.location.href = category === 'all' ? '/resources/' : '/category/' + category;
      }
  }">
  <section class="flex overflow-hidden relative bg-primary">
      <div <?php echo $section_style; ?>  class="flex flex-col items-center mx-auto w-full bg-primary">
          <div class="overflow-hidden relative max-w-[1408px] max-xxl:px-5 w-full hero-background">

              <div class="flex z-0 flex-col pt-14 pb-8 w-full max-md:max-w-full">

                  <!-- Breadcrumb Navigation -->
                  <nav class="flex gap-2 items-center self-start mb-4" aria-label="Breadcrumb">
                      <div class="pr-2 w-[30px]">
                          <div class="flex w-full min-h-[21px]" aria-hidden="true">
                              <svg width="21" height="21" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                  <path d="M9 22V12H15V22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>
                          </div>
                      </div>
         <ol class="flex gap-2 items-center pt-0.5 min-w-60">
                          <li class="flex gap-2 items-center">
                              <a href="<?php echo esc_url(home_url()); ?>" class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2" aria-label="Home">
                                  Home
                              </a>
                              <?php if (!is_front_page()) : // Only show arrow if not on home page ?>
                              <div class="flex gap-2 items-center pt-0.5 w-4 text-white" aria-hidden="true">
                                 <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                  </svg>

                              </div>
                              <?php endif; ?>
                          </li>

                          <?php
                          // Determine if we're on the main blog archive page (often the "Posts Page")
                          $blog_page_id = get_option('page_for_posts');
                          $is_blog_home = is_home() && !is_front_page(); // `is_home()` is true for the Posts Page, `!is_front_page()` ensures it's not the static front page.

                          // If we are on the blog home (Posts Page) or a category/single post from a standard post type
                          if ($is_blog_home || is_category() || is_single() || is_tag() || is_date() || is_author()) {
                              // Add "Resources" or your blog archive link here
                              $resources_page_id = get_page_by_path('resources'); // Get the page object for 'resources'
                              if ($resources_page_id) {
                                  ?>
                                  <li class="flex gap-2 items-center">
                                      <a href="<?php echo esc_url(get_permalink($resources_page_id)); ?>" class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2" aria-label="Resources">
                                          Resources
                                      </a>
                                      <?php if (!is_home() && !is_post_type_archive('projects') && !is_page($resources_page_id->ID)) : // Only show arrow if there's more to come after Resources ?>
                                      <div class="flex gap-2 items-center pt-0.5 w-4" aria-hidden="true">
                                 <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                  </svg>
                                      </div>
                                      <?php endif; ?>
                                  </li>
                                  <?php
                              }

                              // Category breadcrumb for single posts or category archives
                              if (is_single()) {
                                  $categories = get_the_category();
                                  if (!empty($categories)) {
                                      $category = $categories[0]; // Get the first category
                                      echo '<li class="flex gap-2 items-center">';
                                      echo '<a href="' . esc_url(get_category_link($category->term_id)) . '" class="text-sm font-semibold leading-none text-white whitespace-nowrap hover:text-white focus:text-white focus:outline-2 focus:outline-white focus:outline-offset-2" aria-label="' . esc_attr($category->name) . '">';
                                      echo esc_html($category->name);
                                      echo '</a>';
                                      echo '<div class="flex gap-2 items-center pt-0.5 w-4" aria-hidden="true">';
                                      echo '<svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M5.99023 12.2104L9.99023 8.21045L5.99023 4.21045" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                  </svg>';
                                      echo '</div>';
                                      echo '</li>';
                                  }
                              }
                          }

                          // Current page/post/archive title
                          echo '<li><span class="text-sm font-semibold leading-none text-white">';
                          if (is_single()) {
                              the_title();
                          } elseif (is_page()) {
                              the_title();
                          } elseif (is_category()) {
                              single_cat_title();
                          } elseif (is_tag()) {
                              single_tag_title();
                          } elseif (is_author()) {
                              the_author();
                          } elseif (is_date()) {
                              if (is_day()) {
                                  echo get_the_date('F j, Y');
                              } elseif (is_month()) {
                                  echo get_the_date('F Y');
                              } elseif (is_year()) {
                                  echo get_the_date('Y');
                              }
                          } elseif (is_search()) {
                              echo 'Search Results for "' . get_search_query() . '"';
                          } elseif (is_404()) {
                              echo 'Page Not Found';
                          } elseif (is_post_type_archive('projects')) { // For custom post type archive
                              echo 'Projects'; // Or your desired title
                          } elseif ($is_blog_home) {
                              // If 'Resources' is your main blog page, this will be the final breadcrumb
                              // You can customize this or remove it if 'Resources' itself is the final breadcrumb for the blog home
                              echo 'What\'s new in Hanley Pepper'; // Or the actual title of your "Resources" page
                          }
                          echo '</span></li>';
                          ?>
                      </ol>
                  </nav>

                  <!-- Main Heading Section -->
                <header class="w-full max-md:max-w-full">
                      <?php
                        // dynamic heading tag
                        printf(
                          '<%1$s class="text-6xl font-bold leading-tight text-white max-md:max-w-full max-md:text-4xl">%2$s</%1$s>',
                          esc_attr($hero_tag),
                          esc_html($hero_text)
                        );
                      ?>

                      <?php if ($sub_text): ?>
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
                // before you output anything, grab the WP categories and current filter
                $all_cats      = get_terms([
                  'taxonomy'   => 'category',
                  'hide_empty' => true,
                ]);
                $current_slug  = $category_slug; // you already have this from get_queried_object()
              ?>
              <div class="flex-1 pb-2 text-base shrink min-w-60 max-md:max-w-full">
                <span class="mb-2 font-bold text-white"><?php echo esc_html($filter_title); ?></span>
                <div
                  role="radiogroup"
                  aria-label="Filter news by category"
                  class="flex flex-wrap gap-4 items-start mt-2 w-full font-medium max-md:max-w-full"
                >
                  <button
                    role="radio"
                    class="gap-2 px-6 py-2 whitespace-nowrap rounded-lg filter-btn bg-secondary hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 btn"
                    data-filter="all"
                    aria-checked="<?php echo $current_slug === 'all' ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $current_slug === 'all' ? '0' : '-1'; ?>"
                  >
                    All news
                  </button>

                  <?php foreach ( $all_cats as $cat ) :
                    $slug    = esc_attr( $cat->slug );
                    $name    = esc_html( $cat->name );
                    $checked = ( $slug === $current_slug ) ? 'true' : 'false';
                  ?>
                    <button
                      role="radio"
                      class="gap-2 px-6 py-2 whitespace-nowrap rounded-lg filter-btn bg-secondary hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-3black btn"
                      data-filter="<?php echo $slug; ?>"
                      aria-checked="<?php echo $checked; ?>"
                      tabindex="<?php echo $checked === 'true' ? '0' : '-1'; ?>"
                    >
                      <?php echo $name; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>


                  <!-- Search Section -->
                  <div class="flex items-center w-96 min-w-60">
                      <form class="flex w-full" role="search" aria-label="Search articles">
                          <div class="flex-1 my-auto text-base shrink min-h-14 min-w-60 text-slate-600">
                              <div class="flex-1 w-full">
                                  <div class="flex flex-1 justify-between items-center px-4 py-3 bg-white rounded-l size-full">
                                      <label for="article-search" class="sr-only">Search articles</label>
                                        <input
                                          type="search"
                                          id="article-search"
                                          placeholder="Search articles"
                                          class="flex-1 px-4 py-3 bg-white rounded-l border-none size-full text-slate-600 placeholder-slate-600"
                                          aria-label="Search articles"
                                        />
                                  </div>
                              </div>
                          </div>

                          <button type="submit" class="flex gap-2 justify-center items-center px-6 py-4  bg-orange-400 rounded-none min-h-14 w-[72px] max-md:px-5 search-btn btn" aria-label="Search">
                            <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M21 21.0408L16.65 16.6908M19 11.0408C19 15.459 15.4183 19.0408 11 19.0408C6.58172 19.0408 3 15.459 3 11.0408C3 6.62249 6.58172 3.04077 11 3.04077C15.4183 3.04077 19 6.62249 19 11.0408Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>

                          </button>
                      </form>
                  </div>
              </div>
          </div>
      </div>
  </section>

  <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Filter button functionality
          const filterButtons = document.querySelectorAll('[data-filter]');
          const filterButtonList = Array.from(filterButtons);

          function setActiveButton(nextButton) {
              filterButtons.forEach(btn => {
                  const isActive = btn === nextButton;
                  btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
                  btn.setAttribute('tabindex', isActive ? '0' : '-1');
              });
          }

          filterButtons.forEach(button => {
              button.addEventListener('click', function() {
                  setActiveButton(this);

                  // Trigger filter event (can be extended for actual filtering)
                  const filterValue = this.getAttribute('data-filter');
                  const filterEvent = new CustomEvent('newsFilter', {
                      detail: { filter: filterValue }
                  });
                  document.dispatchEvent(filterEvent);
              });

              button.addEventListener('keydown', function(e) {
                  const key = e.key;
                  if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(key)) {
                      return;
                  }

                  if (key === 'Enter' || key === ' ') {
                      e.preventDefault();
                      this.click();
                      return;
                  }

                  e.preventDefault();
                  const currentIndex = filterButtonList.indexOf(this);
                  let nextIndex = currentIndex;

                  if (key === 'ArrowRight' || key === 'ArrowDown') {
                      nextIndex = (currentIndex + 1) % filterButtonList.length;
                  } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
                      nextIndex = (currentIndex - 1 + filterButtonList.length) % filterButtonList.length;
                  } else if (key === 'Home') {
                      nextIndex = 0;
                  } else if (key === 'End') {
                      nextIndex = filterButtonList.length - 1;
                  }

                  const nextButton = filterButtonList[nextIndex];
                  if (!nextButton) return;
                  setActiveButton(nextButton);
                  nextButton.focus();

                  const filterEvent = new CustomEvent('newsFilter', {
                      detail: { filter: nextButton.getAttribute('data-filter') }
                  });
                  document.dispatchEvent(filterEvent);
              });
          });

          // Search form functionality
          const searchForm = document.querySelector('form[role="search"]');
          if (searchForm) {
              searchForm.addEventListener('submit', function(e) {
                  e.preventDefault();
                  const searchInput = this.querySelector('input[type="search"]');
                  const searchValue = searchInput.value.trim();

                  if (searchValue) {
                      // Trigger search event (can be extended for actual search)
                      const searchEvent = new CustomEvent('newsSearch', {
                          detail: { query: searchValue }
                      });
                      document.dispatchEvent(searchEvent);
                      console.log('Searching for:', searchValue);
                  }
              });
          }
      });
  </script>

</div>

<section class="flex overflow-hidden relative">
<div class="flex flex-col items-center pt-5 pb-5 mx-auto w-full max-w-container max-xl:px-5">
  <div class="flex flex-col gap-8 pt-12 pb-14 w-full bg-white max-md:p-8 max-sm:p-4">

    <!-- Heading: Total posts + Clear Filters Button -->
    <div class="flex justify-between items-center w-full">
      <span class="text-2xl font-bold leading-7 text-slate-600">
        <?php echo wp_count_posts()->publish; ?> posts
      </span>
      <button
          type="button"
          id="clear-filters"
          class="flex gap-2 items-center px-4 py-2 bg-[#F9FAFB] rounded cursor-pointer h-[42px] w-fit whitespace-nowrap hover:bg-hover hover:text-hover hidden btn"
          aria-label="Clear filters"
        >
        <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M12 4.04102L4 12.041M4 4.04102L12 12.041" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
        <span class="text-sm font-semibold leading-5 text- [#40BFF5]">Clear filters</span>
      </button>
    </div>

    <!-- Blog Posts Grid - Updated to 3 columns -->
    <main class="w-full" role="main" aria-label="Blog posts">
      <div class="flex flex-col">
        <div class="flex flex-col items-center w-full max-md:max-w-full">
          <?php
          $args = [
            'post_type'      => 'post',
            'posts_per_page' => 9, // Changed to 9 for 3x3 grid
            'paged'          => get_query_var('paged') ? get_query_var('paged') : 1,
          ];

          $query = new WP_Query($args);
          $post_count = 0;

          if ($query->have_posts()) :
            // Group posts into rows of 3
            $posts_per_row = 3;
            $row_count = 0;

            while ($query->have_posts()) : $query->the_post();
              $post_count++;

              // Start new row
              if (($post_count - 1) % $posts_per_row === 0) {
                $row_count++;
                $margin_class = $row_count === 1 ? '' : 'mt-12 max-md:mt-10';
                echo '<div class="flex flex-wrap gap-10 items-start' . $margin_class . ' max-md:max-w-full">';
              }

              // get an array of category slugs for this post
              $post_cats = array_map( function( $c ){ return $c->slug; }, get_the_category() );
              $data_attr = implode( ' ', $post_cats );
              ?>

              <article class="project-card" data-categories="<?php echo esc_attr( $data_attr ); ?>">
                <a
                  href="<?php the_permalink(); ?>"
                  class="block group"
                  aria-label="Read article: <?php the_title_attribute(); ?>"
                >
                  <?php if (has_post_thumbnail()) : ?>
                    <img
                      src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'large')); ?>"
                      alt="<?php the_title_attribute(); ?>"
                      class="object-contain w-[425px] min-w-60 max-md:max-w-full transition-transform duration-300 group-hover:scale-105"
                      style="aspect-ratio: 0.79;"
                      loading="lazy"
                    />
                  <?php else : ?>
                    <div
                      class="bg-[#F9FAFB] w-[425px] min-w-60 max-md:max-w-full flex items-center justify-center text-gray-500"
                      style="aspect-ratio: 0.79;"
                      aria-label="No image available for <?php the_title_attribute(); ?>"
                    >
                      <span class="text-sm">No image</span>
                    </div>
                  <?php endif; ?>

                  <div class="sr-only">
                    <h3><?php the_title(); ?></h3>
                    <time datetime="<?php echo get_the_date('c'); ?>">
                      Published on <?php echo get_the_date(); ?>
                    </time>
                  </div>
                </a>
              </article>

              <?php
              // Close row after 3 posts
              if ($post_count % $posts_per_row === 0 || !$query->have_posts()) {
                echo '</div>';
              }
            endwhile;

            wp_reset_postdata();
          else : ?>
            <div class="py-12 text-center">
              <p class="text-lg text-gray-600">No posts found.</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Pagination Component -->
        <?php if ($query->max_num_pages > 1) : ?>
          <nav class="self-center mt-8 max-w-full w-fit" aria-label="Blog pagination" role="navigation">
            <div class="flex gap-5 justify-between items-center">
              <?php
              $current_page = max(1, get_query_var('paged'));
              $prev_page = $current_page - 1;
              $next_page = $current_page + 1;
              $max_pages = $query->max_num_pages;
              ?>

              <!-- Previous Page Button -->
              <?php if ($prev_page >= 1) : ?>
                <a
                  href="<?php echo get_pagenum_link($prev_page); ?>"
                  class="block transition-opacity hover:opacity-70 focus:outline-2 focus:outline-blue-600 focus:outline-offset-2 btn"
                  aria-label="Go to previous page"
                >
                  <img
                    src="https://api.builder.io/api/v1/image/assets/f35586c581c84ecf82b6de32c55ed39e/21ccf810db8d7a2652471eed758820a757d774ce?placeholderIfAbsent=true"
                    alt=""
                    class="object-contain shrink-0 w-[22px]"
                    style="aspect-ratio: 0.96;"
                    aria-hidden="true"
                  />
                </a>
              <?php else : ?>
                <span
                  class="block opacity-30 cursor-not-allowed"
                  aria-label="Previous page not available"
                >
                  <img
                    src="https://api.builder.io/api/v1/image/assets/f35586c581c84ecf82b6de32c55ed39e/21ccf810db8d7a2652471eed758820a757d774ce?placeholderIfAbsent=true"
                    alt=""
                    class="object-contain shrink-0 w-[22px]"
                    style="aspect-ratio: 0.96;"
                    aria-hidden="true"
                  />
                </span>
              <?php endif; ?>

              <!-- Page Numbers -->
              <div class="flex gap-4 self-center text-base leading-3 text-center text-primary whitespace-nowrap w-[78px]">
                <?php
                // Show up to 4 page numbers
                $start_page = max(1, $current_page - 1);
                $end_page = min($max_pages, $start_page + 3);

                // Adjust start if we're near the end
                if ($end_page - $start_page < 3) {
                  $start_page = max(1, $end_page - 3);
                }

                for ($i = $start_page; $i <= $end_page && $i <= $max_pages; $i++) :
                  if ($i === $current_page) : ?>
                    <span
                      class="font-bold text-blue-950"
                      aria-current="page"
                      aria-label="Current page, page <?php echo $i; ?>"
                    >
                      <?php echo $i; ?>
                    </span>
                  <?php else : ?>
                    <a
                      href="<?php echo get_pagenum_link($i); ?>"
                      class="transition-colors hover:text-blue-950 focus:outline-2 focus:outline-blue-600 focus:outline-offset-2 btn"
                      aria-label="Go to page <?php echo $i; ?>"
                    >
                      <?php echo $i; ?>
                    </a>
                  <?php endif;
                endfor; ?>
              </div>

              <!-- Next Page Button -->
              <?php if ($next_page <= $max_pages) : ?>
                <a
                  href="<?php echo get_pagenum_link($next_page); ?>"
                  class="block transition-opacity hover:opacity-70 focus:outline-2 focus:outline-blue-600 focus:outline-offset-2 btn"
                  aria-label="Go to next page"
                >
                  <img
                    src="https://api.builder.io/api/v1/image/assets/f35586c581c84ecf82b6de32c55ed39e/49a4270b9457ce721eb7c28a1b885f36bb45f076?placeholderIfAbsent=true"
                    alt=""
                    class="object-contain shrink-0 w-[22px]"
                    style="aspect-ratio: 0.96;"
                    aria-hidden="true"
                  />
                </a>
              <?php else : ?>
                <span
                  class="block opacity-30 cursor-not-allowed"
                  aria-label="Next page not available"
                >
                  <img
                    src="https://api.builder.io/api/v1/image/assets/f35586c581c84ecf82b6de32c55ed39e/49a4270b9457ce721eb7c28a1b885f36bb45f076?placeholderIfAbsent=true"
                    alt=""
                    class="object-contain shrink-0 w-[22px]"
                    style="aspect-ratio: 0.96;"
                    aria-hidden="true"
                  />
                </span>
              <?php endif; ?>
            </div>
          </nav>
        <?php endif; ?>
      </div>
    </main>

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

// decide visibility of a card
function cardVisible(card) {
  const cats = card.getAttribute('data-categories').split(' ');
  const titleEl = card.querySelector('h3');
  const title   = titleEl ? titleEl.textContent.toLowerCase() : '';

  const matchesCategory = (activeFilter === 'all') || cats.includes(activeFilter);
  const matchesSearch   = (searchTerm === '') || (title.indexOf(searchTerm) !== -1);

  return matchesCategory && matchesSearch;
}
// apply to all cards, then toggle clear button
function applyFilter() {
  cards.forEach(card => {
    card.style.display = cardVisible(card) ? '' : 'none';
  });
  // Show clearFilters if we're not default
  const needsClear = (activeFilter !== 'all') || (searchTerm !== '');
  if (needsClear) {
    clearFilters.classList.remove('hidden');
  } else {
    clearFilters.classList.add('hidden');
  }
}

// CATEGORY BUTTONS
buttons.forEach(btn => {
  btn.addEventListener('click', () => {
    buttons.forEach(b => {
      const isActive = b === btn;
      b.setAttribute('aria-checked', isActive ? 'true' : 'false');
      b.setAttribute('tabindex', isActive ? '0' : '-1');
    });

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
    buttons.forEach((b) => {
      const isActive = b === nextBtn;
      b.setAttribute('aria-checked', isActive ? 'true' : 'false');
      b.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    nextBtn.focus();
    activeFilter = nextBtn.getAttribute('data-filter');
    applyFilter();
  });
});

// LIVE SEARCH
if (searchInput) {
  searchInput.addEventListener('input', () => {
    searchTerm = searchInput.value.trim().toLowerCase();
    applyFilter();
  });
}

// CLEAR FILTERS BUTTON
clearFilters.addEventListener('click', () => {
  // Reset category buttons
  activeFilter = 'all';
  buttons.forEach(b => {
    b.setAttribute('aria-checked', b.getAttribute('data-filter') === 'all' ? 'true' : 'false');
    b.setAttribute('tabindex', b.getAttribute('data-filter') === 'all' ? '0' : '-1');
  });

  // Reset search
  if (searchInput) {
    searchInput.value = '';
    searchTerm = '';
  }

  applyFilter();
});

// Keyboard navigation for pagination
const paginationLinks = document.querySelectorAll('nav[aria-label="Blog pagination"] a, nav[aria-label="Blog pagination"] button');
paginationLinks.forEach(link => {
  link.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.click();
    }
  });
});

// initial run (hide it by default)
applyFilter();
});
</script>
