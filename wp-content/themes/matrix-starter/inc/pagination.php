<?php
function my_custom_pagination()
{
  global $wp_query;

  $total_pages  = $wp_query->max_num_pages;
  $current_page = max(1, get_query_var('paged'));

  // Only show pagination if more than one page.
  if ($total_pages <= 1) {
    return;
  }

  // Build array of page links.
  $links_array = paginate_links([
    'total'     => $total_pages,
    'current'   => $current_page,
    'type'      => 'array',
    'prev_next' => false, // <--- IMPORTANT: disable WP’s own prev/next
    'end_size'  => 1,
    'mid_size'  => 0,
  ]);
?>
  <nav aria-label="Pagination navigation">
    <ul class="flex flex-wrap gap-8 justify-center items-center p-0 text-base font-medium leading-none list-none text-sky-900 whitespace-nowrap">

      <!-- BACK link -->
      <li>
        <?php if (get_previous_posts_link()): ?>
          <a href="<?php echo esc_url(get_pagenum_link($current_page - 1)); ?>"
            class="flex items-center my-auto text-[#0A1119]"
            aria-label="Go to previous page">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
              <path d="M20 24L12 16L20 8" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span class="self-stretch my-auto">Back</span>
          </a>
        <?php else: ?>
          <!-- Disabled version if no previous page -->
          <span class="flex items-center self-stretch my-auto opacity-50 cursor-not-allowed text-slate-300"
            aria-disabled="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
              <path d="M20 24L12 16L20 8" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span class="self-stretch my-auto">Back</span>
          </span>
        <?php endif; ?>
      </li>

      <!-- Numbered pages + dots -->
      <li>
        <ul class="flex gap-x-2 justify-center items-center p-0 my-auto list-none">
          <?php foreach ($links_array as $link): ?>
            <?php
            // Check for "dots" link: <span class="dots">…</span>.
            if (strpos($link, 'dots') !== false) {
              echo '<li><span class="self-stretch py-1 my-auto w-6" aria-hidden="true">…</span></li>';
              continue;
            }

            // Check if it's the current page. Usually <span class="page-numbers current">X</span>.
            if (strpos($link, 'current') !== false) {
              // If it's a <span>.
              if (strpos($link, '<span') !== false) {
                $link = str_replace(
                  '<span',
                  '<span class="flex justify-center items-center my-auto w-12 h-12 border border-black border-solid min-h-[48px] rounded-[100px] text-black"',
                  $link
                );
              } else {
                // Fallback if WP ever uses <a class="current"> (less common).
                $link = str_replace(
                  '<a',
                  '<a class="flex justify-center items-center my-auto w-12 h-12 border hover:bg-blue border-black border-solid min-h-[48px] rounded-[100px] text-black"',
                  $link
                );
              }
              echo '<li>' . $link . '</li>';
              continue;
            }

            // Otherwise, a normal page link (not current, not dots).
            $link = str_replace(
              '<a',
              '<a class="flex justify-center items-center hover:bg-blue hover:text-white gap-4  my-auto w-12 min-h-[48px] rounded-[100px]"',
              $link
            );
            echo '<li>' . $link . '</li>';
            ?>
          <?php endforeach; ?>
        </ul>
      </li>

      <!-- NEXT link -->
      <li>
        <?php if (get_next_posts_link()): ?>
          <a href="<?php echo esc_url(get_pagenum_link($current_page + 1)); ?>"
            class="flex items-center my-auto"
            aria-label="Go to next page">
            <span class="self-stretch my-auto">Next</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
              <path d="M12 24L20 16L12 8" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </a>
        <?php else: ?>
          <!-- Disabled version if no next page -->
          <span class="flex gap-1 items-center self-stretch py-1 pr-1 pl-2.5 my-auto opacity-50 cursor-not-allowed"
            aria-disabled="true">
            <span class="self-stretch my-auto">Next</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
              <path d="M12 24L20 16L12 8" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </span>
        <?php endif; ?>
      </li>
    </ul>
  </nav>
<?php
}
?>