<?php

/**
 * GeneratePress child theme functions and definitions.
 *
 * Add your custom PHP in this file.
 * Only edit this file if you have direct access to it on your server (to fix errors if they happen).
 */

/* ==================================================
   ENQUEUE STYLES
================================================== */

function my_child_theme_enqueue_styles()
{
    // Parent theme stylesheet
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');

    // Overrides stylesheet with cache busting
    wp_enqueue_style(
        'overrides-style',
        get_stylesheet_directory_uri() . '/overrides.css',
        array(),
        filemtime(get_stylesheet_directory() . '/overrides.css')
    );

    // Child theme main stylesheet
    wp_enqueue_style('child-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

// Add styles to block editor
add_filter('generate_editor_styles', 'tct_editor_styles', 50);
function tct_editor_styles($editor_styles)
{
    $editor_styles[] = '/style.css';
    $editor_styles[] = '/overrides.css';
    return $editor_styles;
}

// Inject Customizer "Additional CSS" into block editor
add_filter('block_editor_settings_all', function ($editor_settings) {
    $custom_css_post = wp_get_custom_css_post();
    if ($custom_css_post && !empty($custom_css_post->post_content)) {
        $editor_settings['styles'][] = array(
            'css' => $custom_css_post->post_content,
        );
    }
    return $editor_settings;
});


function breadcrumb_shortcode()
{
    if (function_exists('bcn_display') && !is_front_page()) {
        ob_start();
        bcn_display();
        return '<div class="hero-breadcrumb">' . ob_get_clean() . '</div>';
    }
    return '';
}
add_shortcode('hero_breadcrumb', 'breadcrumb_shortcode');

/* ==================================================
   EDITOR EXPERIENCE
================================================== */

// Limit post editor content width
function custom_admin_styles()
{
    echo '<style>
        .post-type-post .block-editor-block-list__layout,
        .post-type-post .edit-post-visual-editor__post-title-wrapper .editor-post-title {
            max-width: 728px;
            margin-inline: auto;
        }
    </style>';
}
add_action('admin_head', 'custom_admin_styles');


/* ==================================================
   COMMENTS: Disable Everywhere
================================================== */

// Remove comments admin menu
add_action('admin_menu', 'djw_remove_admin_menus');
function djw_remove_admin_menus()
{
    remove_menu_page('edit-comments.php');
}

// Remove comment support from post types
add_action('init', 'djw_remove_comment_support', 100);
function djw_remove_comment_support()
{
    remove_post_type_support('post', 'comments');
    remove_post_type_support('page', 'comments');
}

// Remove comments from admin bar
add_action('wp_before_admin_bar_render', 'djw_admin_bar_render');
function djw_admin_bar_render()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
}

/* ==================================================
   BLOCK EDITOR CLEANUP
================================================== */

// Remove default WordPress block patterns
add_action('after_setup_theme', 'my_remove_patterns');
function my_remove_patterns()
{
    remove_theme_support('core-block-patterns');
}


/* ==================================================
   CONTENT REDIRECTS & CLEANUP
================================================== */

// Redirect attachment pages to parent or home
add_action('template_redirect', 'redirect_attachment_to_homepage');
function redirect_attachment_to_homepage()
{
    if (is_attachment()) {
        global $post;
        $redirect = ($post->post_parent) ? get_permalink($post->post_parent) : home_url();
        wp_redirect($redirect, 301);
        exit;
    }
}

// Redirect date archive pages to home
add_action('template_redirect', 'redirect_date_archives_to_homepage');
function redirect_date_archives_to_homepage()
{
    if (is_date()) {
        wp_redirect(home_url(), 301);
        exit;
    }
}

// Remove post format support
add_action('after_setup_theme', 'remove_post_formats', 100);
function remove_post_formats()
{
    remove_theme_support('post-formats');
}


/* ==================================================
   DASHBOARD CLEANUP
================================================== */

// Remove default dashboard widgets
add_action('admin_menu', 'disable_default_dashboard_widgets');
function disable_default_dashboard_widgets()
{
    $widgets = [
        'dashboard_activity',
        'dashboard_right_now',
        'dashboard_recent_comments',
        'dashboard_incoming_links',
        'dashboard_plugins',
        'dashboard_quick_press',
        'dashboard_recent_drafts',
        'dashboard_primary',
        'dashboard_secondary'
    ];
    foreach ($widgets as $widget) {
        remove_meta_box($widget, 'dashboard', strpos($widget, 'side') !== false ? 'side' : 'normal');
    }
}

// Hide empty dashboard containers
add_action('admin_head', 'hide_empty_dashboard_boxes');
function hide_empty_dashboard_boxes()
{
    echo '<style>#dashboard-widgets .empty-container { display: none; }</style>';
}

/* ==================================================
   ADMIN MENU VISIBILITY
================================================== */

// Hide admin menu/sidebar for non-admin users
add_action('admin_head', 'hide_admin_sidebar_for_non_admins');
function hide_admin_sidebar_for_non_admins()
{
    if (!current_user_can('activate_plugins')) {
        echo '<style>
            #adminmenu, #adminmenuback { width: 0 !important; display: none !important; }
            #wpcontent, #wpfooter { margin-left: 0 !important; }
        </style>';
    }
}


/* ==================================================
   ARCHIVE & SEARCH TITLE CLEANUP
================================================== */

// Remove archive page title
add_action('wp', 'lh_remove_archive_title');
function lh_remove_archive_title()
{
    remove_action('generate_archive_title', 'generate_archive_title');
}

// Remove default search title output
add_filter('generate_search_title_output', '__return_empty_string');

// Custom search title shortcode
function custom_search_title_shortcode()
{
    if (is_search()) {
        return '<h1 class="custom-search-title">Search Results for: ' . esc_html(get_search_query()) . '</h1>';
    }
    return '';
}
add_shortcode('search_title', 'custom_search_title_shortcode');


/* ==================================================
   LAST LOGIN COLUMN
================================================== */

// Track user login
add_action('wp_login', 'record_last_login_time', 10, 2);
function record_last_login_time($user_login, $user)
{
    update_user_meta($user->ID, 'last_login_time', current_time('mysql'));
}

// Add column to Users screen
add_filter('manage_users_columns', 'add_last_login_column');
function add_last_login_column($columns)
{
    $columns['last_login_time'] = 'Last Login';
    return $columns;
}

// Display last login time
add_filter('manage_users_custom_column', 'add_last_login_column_content', 10, 3);
function add_last_login_column_content($value, $column_name, $user_id)
{
    if ($column_name === 'last_login_time') {
        $last_login = get_user_meta($user_id, 'last_login_time', true);
        return $last_login ?: 'Never';
    }
    return $value;
}

/* ==================================================
   LOGIN PAGE CUSTOMIZATION
================================================== */

// Custom login styles
function wpb_login_logo()
{ ?>
    <style type="text/css">
        :root {
            --login-brand-color: #06352c;
            --login-brand-color-hover: #06352c;
            --login-background-color: #06352c;
        }

        body.login {
            background: var(--background-color);
            display: flex;
        }

        #login h1 a,
        .login h1 a {
            background-image: url(http://starter.local/wp-content/uploads/2025/08/logo-jt.svg);
            height: 80px;
            max-width: 263px;
            background-size: contain;
            background-repeat: no-repeat;
            width: 100%;
        }

        body.login div#login {
            padding: clamp(2rem, 0.714rem + 1.429vw, 3rem);
            margin: auto;
            border-radius: 1.5em;
            box-shadow:
                0px 2.8px 2.2px rgba(0, 0, 0, 0.006),
                0px 6.7px 5.3px rgba(0, 0, 0, 0.008),
                0px 12.5px 10px rgba(0, 0, 0, 0.01),
                0px 22.3px 17.9px rgba(0, 0, 0, 0.012),
                0px 41.8px 33.4px rgba(0, 0, 0, 0.014),
                0px 100px 80px rgba(0, 0, 0, 0.02);
            background-color: white;
            border: 1px solid lightgray;
        }

        body.login div#login form {
            border: none;
            box-shadow: none;
            padding: 1rem 1rem 2rem;
        }

        body.login input:focus {
            outline: 2px solid var(--login-brand-color);
            border: 0;
        }

        body.login div#login #wp-submit {
            background-color: var(--login-brand-color);
            border: 0;
        }

        body.login div#login #wp-submit:hover {
            background-color: var(--login-brand-color-hover);
        }

        body.login div#login p#nav a:hover,
        body.login div#login p#backtoblog a:hover {
            color: var(--login-brand-color-hover);
        }

        body.login div#login p#nav,
        body.login div#login p#backtoblog {
            display: flex;
            justify-content: center;
            margin-top: 0.5rem;
        }

        body.login .message {
            border-left: 4px solid var(--login-brand-color-hover);
        }
    </style>
<?php }
add_action('login_enqueue_scripts', 'wpb_login_logo');

// Set logo link URL
function wpb_login_logo_url()
{
    return 'https://www.jeremyteurterie.com';
}
add_filter('login_headerurl', 'wpb_login_logo_url');

// Désactive la génération automatique de tailles
add_filter('intermediate_image_sizes_advanced', function ($sizes) {
    // Désactive toutes les tailles sauf celles que tu veux garder
    unset($sizes['thumbnail']);    // 150x150
    unset($sizes['medium']);       // 300x300
    unset($sizes['medium_large']); // 768x0
    unset($sizes['large']);        // 1024x1024
    // unset($sizes['1536x1536']);
    // unset($sizes['2048x2048']);
    return $sizes;
});

// Désactive le scaling automatique à 2560px (WordPress 5.3+)
add_filter('big_image_size_threshold', '__return_false');
