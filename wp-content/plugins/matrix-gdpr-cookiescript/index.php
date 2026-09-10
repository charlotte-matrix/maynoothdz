<?php /**
 * Plugin Name: Matrix GDPR CookieScript
 * Description: Creates a shortcode for CookieScript and insert the cookie banner
 * Author: Matrix Internet
 * Author URI: https://www.matrixinternet.ie
 * Version: 2.4
 */


/**
 * Register the "book" custom post type
 */
function pluginprefix_setup_post_type() {
    // register_post_type( 'book', ['public' => true ] ); 
} 
add_action( 'init', 'pluginprefix_setup_post_type' );
 
 
/**
 * Activate the plugin.
 */
function pluginprefix_activate() { 
    // Trigger our function that registers the custom post type plugin.
    // pluginprefix_setup_post_type(); 
    // Clear the permalinks after the post type has been registered.
    // flush_rewrite_rules(); 
}
register_activation_hook( __FILE__, 'pluginprefix_activate' );


/**
 * Deactivation hook.
 */
function pluginprefix_deactivate() {
    // Unregister the post type, so the rules are no longer in memory.
    // unregister_post_type( 'book' );
    // Clear the permalinks to remove our post type's rules from the database.
    // flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pluginprefix_deactivate' );

class MySettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Matrix GDPR', 
            'Matrix GDPR', 
            'manage_options', 
            'matrix-gdpr', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'matrix_gdpr_option' );
        ?>
        <div class="wrap">
            <h1>Matrix GDPR Cookie Script</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'matrix_gdpr_group' );
                do_settings_sections( 'my-setting-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'matrix_gdpr_group', // Option group
            'matrix_gdpr_option', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
       
        add_settings_field(
            'csid', 
            'Cookie Script ID', 
            array( $this, 'title_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        ); 

        add_settings_field(
            'cs_has_GTM_v2', 
            'GTM Consent Mode validated <br /> <small style="font-weight: normal; font-size: 11px;">(Only check "Yes" if the website is validated under Google Consent Mode in Cookie Script)</small>
            ', 
            array( $this, 'cs_has_GTM_v2_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        ); 

        add_settings_field(
            'csloggedin', // ID
            'Show only for logged in users', // Title 
            array( $this, 'csloggedin_callback' ), // Callback
            'my-setting-admin', // Page
            'setting_section_id' // Section           
        );

        /*
        add_settings_field(
            'cswhere', // ID
            'Where should we insert the script', // Title 
            array( $this, 'cswhere_callback' ), // Callback
            'my-setting-admin', // Page
            'setting_section_id' // Section           
        );
        */

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'my-setting-admin' // Page
        );  
    }


    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['csloggedin'] ) )
            $new_input['csloggedin'] = absint( $input['csloggedin'] );

        // if( isset( $input['cswhere'] ) )
            // $new_input['cswhere'] = sanitize_text_field( $input['cswhere'] );

        if( isset( $input['csid'] ) )
            $new_input['csid'] = sanitize_text_field( $input['csid'] );

        if( isset( $input['cs_has_GTM_v2'] ) )
            $new_input['cs_has_GTM_v2'] = absint( $input['cs_has_GTM_v2'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print '<br />You can use the shortcode <code>[matrix_gdpr_cookiescript]</code> in any page you want to display the cookie policy';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function csloggedin_callback()
    { 
        $csloggedin = isset( $this->options['csloggedin'] ) ? esc_attr( $this->options['csloggedin']) : '1';
        ?>
        <input type="radio" name="matrix_gdpr_option[csloggedin]" id="csloggedin_no" value="0" <?php checked(0, $csloggedin, true); ?>>
        <label for="csloggedin_no">No</label>
        <input type="radio" name="matrix_gdpr_option[csloggedin]" id="csloggedin_yes" value="1" <?php checked(1, $csloggedin, true); ?> style="margin-left: 30px;">
        <label for="csloggedin_yes">Yes</label>
    <?php }

    public function cswhere_callback()
    { 
        $cswhere = isset( $this->options['cswhere'] ) ? esc_attr( $this->options['cswhere']) : 'header';
        ?>
        <input type="radio" name="matrix_gdpr_option[cswhere]" id="cswhere_no" value="header" <?php checked('header', $cswhere, true); ?>>
        <label for="cswhere_no">Header</label>
        <input type="radio" name="matrix_gdpr_option[cswhere]" id="cswhere_yes" value="footer" <?php checked('footer', $cswhere, true); ?> style="margin-left: 30px;">
        <label for="cswhere_yes">Footer</label>
    <?php }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="csid" name="matrix_gdpr_option[csid]" value="%s" />',
            isset( $this->options['csid'] ) ? esc_attr( $this->options['csid']) : ''
        );
    }

    public function cs_has_GTM_v2_callback()
    {
        $cs_has_GTM_v2 = isset( $this->options['cs_has_GTM_v2'] ) ? esc_attr( $this->options['cs_has_GTM_v2']) : '0'; ?>
        
        <input type="radio" name="matrix_gdpr_option[cs_has_GTM_v2]" id="cs_has_GTM_v2_no" value="0" <?php checked(0, $cs_has_GTM_v2, true); ?>>
        <label for="cs_has_GTM_v2_no">No</label>
        <input type="radio" name="matrix_gdpr_option[cs_has_GTM_v2]" id="cs_has_GTM_v2_yes" value="1" <?php checked(1, $cs_has_GTM_v2, true); ?> style="margin-left: 30px;">
        <label for="cs_has_GTM_v2_yes">Yes</label>
    <?php }



}

if( is_admin() )
    $my_settings_page = new MySettingsPage();



add_action('wp_head', 'matrix_gdpr_script_early', 1); // Priority 1 = very early

function matrix_gdpr_script_early() {
    $matrix_gdpr_option = get_option('matrix_gdpr_option');

    if (isset($matrix_gdpr_option['csid']) && !empty($matrix_gdpr_option['csid'])) {
        if (
            ($matrix_gdpr_option['csloggedin'] == 1 && is_user_logged_in()) ||
            $matrix_gdpr_option['csloggedin'] == 0
        ) {
            $csid = esc_attr($matrix_gdpr_option['csid']);
            $rand = rand(1000, 9999); // or use time() if you prefer
            echo '<script src="//cdn.cookie-script.com/s/' . $csid . '.js?v=' . $rand . '"></script>' . "\n";
        }
    }

    if(isset($matrix_gdpr_option['cs_has_GTM_v2']) && $matrix_gdpr_option['cs_has_GTM_v2'] == 0) {
        if($matrix_gdpr_option['csloggedin'] == 1 && is_user_logged_in() || $matrix_gdpr_option['csloggedin'] == 0) { ?>
            <!-- GTM Cookie Script Fix start -->
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() {
                    dataLayer.push(arguments);
                }
                gtag("consent", "default", {
                    ad_storage: "denied",
                    analytics_storage: "denied",
                    personalization_storage: "denied",
                    analytics_storage: "denied",
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    wait_for_update: 500
                });
                gtag("set", "ads_data_redaction", true);
            </script>
            <!-- GTM Cookie Script Fix end -->
        <?php }
    }
}


function matrix_gdpr_script() {

    wp_enqueue_style('matrix_gdpr_style',  plugin_dir_url( __FILE__ ).'css/matrix-gdpr-cookiescript.css');

}
add_action('wp_enqueue_scripts', 'matrix_gdpr_script');

function shortcode_matrix_gdpr($atts) {

    $matrix_gdpr_option = get_option('matrix_gdpr_option');
    if(isset($matrix_gdpr_option['csid']) && !empty($matrix_gdpr_option['csid'])) {
        if($matrix_gdpr_option['csloggedin'] == 1 && is_user_logged_in() || $matrix_gdpr_option['csloggedin'] == 0) {   
            return '<script type="text/javascript" charset="UTF-8" data-cookiescriptreport="report" src="//report.cookie-script.com/r/'.$matrix_gdpr_option['csid'].'.js"></script>';
        }
    }
}
add_shortcode('matrix_gdpr_cookiescript', 'shortcode_matrix_gdpr');

function matrix_gdpr_remove_css_js_version( $src ) {
    if( strpos( $src, '?ver=' ) )
        $src = remove_query_arg( 'ver', $src );
    return $src;
}
add_filter('style_loader_src', 'matrix_gdpr_remove_css_js_version', 9999);
add_filter('script_loader_src', 'matrix_gdpr_remove_css_js_version', 9999);

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://plugins.matrixinternet.ie/matrix-gdpr-cookiescript/matrix-gdpr-cookiescript.json',
    __FILE__, //Full path to the main plugin file or functions.php.
    'matrix-gdpr-cookiescript'
);