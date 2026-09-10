<?php
/**
 * Default page body (block editor).
 *
 * @package Matrix_Starter
 */
?>
<article
    <?php post_class(matrix_content_article_classes()); ?>
    id="post-<?php the_ID(); ?>"
>
    <div class="entry-content">
        <?php the_content(); ?>
    </div>
</article>
