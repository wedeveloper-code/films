<?php
/**
 * Generic Page Template
 *
 * @package FastWP
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
        <article class="page-article">
            <h1 class="page-title"><?php the_title(); ?></h1>
            <div class="page-content"><?php the_content(); ?></div>
        </article>
        <?php endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
