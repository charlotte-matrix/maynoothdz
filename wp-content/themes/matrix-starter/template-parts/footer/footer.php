<?php
/**
 * Maynooth DZ site footer.
 */

$assets      = get_template_directory_uri() . '/assets/dz';
$home_url    = home_url('/');
$contact_email = 'climateaction@kildarecoco.ie';

$footer_groups = [
    [
        'title' => 'Map',
        'url'   => home_url('/map/'),
        'links' => [
            ['label' => 'Energy', 'url' => home_url('/map/')],
            ['label' => 'Retrofit', 'url' => home_url('/map/')],
            ['label' => 'Transport', 'url' => home_url('/map/')],
            ['label' => 'Biodiversity & resilience', 'url' => home_url('/map/')],
            ['label' => 'Community action', 'url' => home_url('/map/')],
            ['label' => 'Public realm', 'url' => home_url('/map/')],
            ['label' => 'Education awareness', 'url' => home_url('/map/')],
            ['label' => 'Sustainable practices', 'url' => home_url('/map/')],
            ['label' => 'Circular economy', 'url' => home_url('/map/')],
            ['label' => 'Water & nature-based solutions', 'url' => home_url('/map/')],
        ],
    ],
    [
        'title' => 'About',
        'url'   => home_url('/#about'),
        'links' => [
            ['label' => 'Resources', 'url' => home_url('/resources/')],
            ['label' => 'Why Maynooth', 'url' => home_url('/#about')],
            ['label' => 'What is the Maynooth DZ', 'url' => home_url('/#about')],
            ['label' => 'Climate action explained', 'url' => home_url('/#about')],
        ],
    ],
    [
        'title' => 'Projects',
        'url'   => home_url('/projects/'),
        'links' => [
            ['label' => 'Community garden harbour field', 'url' => home_url('/projects/')],
            ['label' => 'Climate champions', 'url' => home_url('/projects/')],
            ['label' => 'Together for sustainable future podcast', 'url' => home_url('/projects/')],
            ['label' => 'Kildare demohouse', 'url' => home_url('/projects/')],
            ['label' => 'Picnic in the park', 'url' => home_url('/projects/')],
            ['label' => 'Solar PV Meitheal Moyglare hall', 'url' => home_url('/projects/')],
        ],
    ],
    [
        'title' => 'Take Action',
        'url'   => home_url('/take-action/'),
        'links' => [
            ['label' => 'Retrofit your home', 'url' => home_url('/take-action/retrofit-your-home/')],
            ['label' => 'Travel sustainably', 'url' => home_url('/take-action/travel-sustainably/')],
            ['label' => 'Start a community project', 'url' => home_url('/take-action/start-a-community-project/')],
            ['label' => 'Business and school actions', 'url' => home_url('/take-action/business-and-school-actions/')],
        ],
    ],
    [
        'title' => 'Community',
        'url'   => home_url('/community/'),
        'links' => [
            ['label' => 'Events', 'url' => home_url('/community/#events')],
            ['label' => 'Updates', 'url' => home_url('/community/#updates')],
            ['label' => 'Add an event or update', 'url' => wp_registration_url()],
        ],
    ],
];

$partners = [
    [
        'src' => $assets . '/kildare.webp',
        'alt' => 'Kildare County Council',
        'w'   => 177,
        'h'   => 40,
    ],
    [
        'src' => $assets . '/climate.webp',
        'alt' => "We're taking climate action",
        'w'   => 81,
        'h'   => 40,
    ],
    [
        'src' => $assets . '/government.png',
        'alt' => 'Government of Ireland',
        'w'   => 115,
        'h'   => 40,
    ],
];
?>
<footer class="footer" id="contact">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a aria-label="<?php echo esc_attr__('Maynooth DZ home', 'matrix-starter'); ?>" href="<?php echo esc_url($home_url); ?>">
                    <img alt="<?php echo esc_attr__('DZ Action — Decarbonising Zone Maynooth', 'matrix-starter'); ?>" height="40" src="<?php echo esc_url($assets . '/logo-white.svg'); ?>" width="191">
                </a>
                <img alt="<?php echo esc_attr__('LinkedIn, Instagram and Facebook', 'matrix-starter'); ?>" class="social-icons" height="24" src="<?php echo esc_url($assets . '/social.svg'); ?>" width="85">
            </div>
            <div class="partners">
                <?php foreach ($partners as $partner) : ?>
                    <img
                        alt="<?php echo esc_attr($partner['alt']); ?>"
                        height="<?php echo esc_attr((string) $partner['h']); ?>"
                        src="<?php echo esc_url($partner['src']); ?>"
                        width="<?php echo esc_attr((string) $partner['w']); ?>"
                    >
                <?php endforeach; ?>
            </div>
        </div>

        <div aria-hidden="true" class="footer-rule"></div>

        <div class="footer-nav" id="sitemap">
            <?php foreach ($footer_groups as $group) : ?>
                <details class="footer-group" open>
                    <summary><?php echo esc_html($group['title']); ?></summary>
                    <ul>
                        <?php foreach ($group['links'] as $link) : ?>
                            <li>
                                <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endforeach; ?>
        </div>

        <p class="contact-line">
            <?php esc_html_e('Climate Action Office, Kildare County Council,', 'matrix-starter'); ?>
            <a href="<?php echo esc_url('mailto:' . $contact_email); ?>"><?php echo esc_html($contact_email); ?></a>
        </p>

        <div aria-hidden="true" class="footer-rule"></div>

        <div class="footer-bottom">
            <div>
                <button class="footer-text" data-info="accessibility" type="button"><?php esc_html_e('Accessibility statement', 'matrix-starter'); ?></button>
                <button class="footer-text" data-info="privacy" type="button"><?php esc_html_e('Privacy & cookie policy', 'matrix-starter'); ?></button>
                <button class="footer-text" data-info="cookies" type="button"><?php esc_html_e('Cookie settings', 'matrix-starter'); ?></button>
                <a href="#sitemap" id="sitemap-link"><?php esc_html_e('Sitemap', 'matrix-starter'); ?></a>
            </div>
            <p><?php esc_html_e('Designed and Developed by', 'matrix-starter'); ?> <span>Matrix Internet</span></p>
        </div>
    </div>
    <div aria-hidden="true" class="brand-bar"></div>
</footer>

<dialog aria-labelledby="info-title" class="info-dialog" id="info-dialog">
    <button aria-label="<?php echo esc_attr__('Close', 'matrix-starter'); ?>" class="close-dialog" type="button">×</button>
    <h2 id="info-title"></h2>
    <p id="info-copy"></p>
</dialog>
