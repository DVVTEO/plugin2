<?php
/**
 * Plugin Name: Claims Management
 * Plugin URI: https://github.com/DVVTEO/claimsangel
 * Description: Create unique, password-protected client portals and manage claims. This version creates custom roles for Claims Manager (who sees only their own clients) and Claims Admin.
 * Version: 3.5
 * Author: DVVTEO
 * Author URI: https://github.com/DVVTEO
 * Text Domain: claims-management
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CM_VERSION', '3.5');
define('CM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load required files
require_once CM_PLUGIN_DIR . 'includes/functions.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-plugin.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-admin.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-settings.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-public.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-ajax.php';
require_once CM_PLUGIN_DIR . 'includes/class-cm-pages.php';
require_once CM_PLUGIN_DIR . 'includes/country-mapping.php';
require_once CM_PLUGIN_DIR . 'includes/prospecting/upload-prospects.php';
require_once CM_PLUGIN_DIR . 'includes/prospecting/my-prospects.php';
require_once CM_PLUGIN_DIR . 'includes/prospecting/prospect-profile-page.php';
require_once CM_PLUGIN_DIR . 'includes/dashboards/prospecting-dashboard.php';
require_once CM_PLUGIN_DIR . 'includes/dashboards/claims-dashboard.php';
require_once CM_PLUGIN_DIR . 'includes/core/class-cm-core.php';

/**
 * Initialize the plugin
 */
function cm_init() {
    try {
        // Initialize legacy plugin
        \ClaimsManagement\Plugin::get_instance();
        
        // Initialize core functionality
        \ClaimsManagement\CM_Core::get_instance();
        
    } catch (Exception $e) {
        error_log('Claims Management Plugin Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html('Claims Management Plugin Error: ' . $e->getMessage())
            );
        });
    }
}
add_action('plugins_loaded', 'cm_init');

/**
 * Enqueue scripts and styles for admin
 */
function cm_enqueue_admin_scripts() {
    $screen = get_current_screen();
    
    // Always enqueue jQuery
    wp_enqueue_script('jquery');
    
    // Add thickbox support
    add_thickbox();
    
    // Only load our admin scripts on claims-related pages
    if (isset($screen->id) && (
        strpos($screen->id, 'claims') !== false ||
        strpos($screen->id, 'prospect') !== false ||
        $screen->id === 'toplevel_page_claims-dashboard'
    )) {
        wp_enqueue_script(
            'claims-dashboard',
            CM_PLUGIN_URL . 'assets/js/claims-dashboard.js',
            ['jquery', 'wp-api'],
            CM_VERSION,
            true
        );
        
        wp_localize_script('claims-dashboard', 'cmConfig', [
            'nonce' => wp_create_nonce('claims_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'userCan' => [
                'delete_claims' => current_user_can('delete_claims'),
                'edit_claims' => current_user_can('edit_claims'),
                'view_claims' => current_user_can('view_claims'),
                'manage_claims' => current_user_can('manage_claims'),
                'assign_claims' => current_user_can('assign_claims')
            ],
            'itemsPerPage' => absint(get_option('cm_items_per_page', 25)),
            'dateFormat' => get_option('date_format'),
            'timeFormat' => get_option('time_format')
        ]);
        
        wp_enqueue_style(
            'claims-dashboard',
            CM_PLUGIN_URL . 'assets/css/claims-dashboard.css',
            [],
            CM_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'cm_enqueue_admin_scripts');

/**
 * Create custom roles on plugin activation
 */
function cm_add_custom_roles() {
    // Claims Manager role
    add_role(
        'claims_manager',
        __('Claims Manager', 'claims-management'),
        [
            'read' => true,
            'edit_claims' => true,
            'view_claims' => true,
            'manage_claims' => true,
            'upload_files' => true,
            'manage_categories' => false
        ]
    );
    
    // Claims Admin role
    add_role(
        'claims_admin',
        __('Claims Admin', 'claims-management'),
        [
            'read' => true,
            'manage_options' => true,
            'edit_claims' => true,
            'delete_claims' => true,
            'view_claims' => true,
            'manage_claims' => true,
            'assign_claims' => true,
            'upload_files' => true,
            'manage_categories' => true
        ]
    );
    
    // Add capabilities to administrator
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('edit_claims');
        $admin_role->add_cap('delete_claims');
        $admin_role->add_cap('view_claims');
        $admin_role->add_cap('manage_claims');
        $admin_role->add_cap('assign_claims');
    }
}

/**
 * Plugin activation hook
 */
function cm_activate_plugin() {
    // Create custom roles
    cm_add_custom_roles();
    
    // Create required pages
    \ClaimsManagement\Pages_Creator::create_pages();
    
    // Create database tables
    cm_create_tables();
    
    // Set default options
    cm_set_default_options();
    
    // Clear the permalinks
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'cm_activate_plugin');

/**
 * Create required database tables
 */
function cm_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $claims_table = $wpdb->prefix . 'cm_claims';
    $claims_meta_table = $wpdb->prefix . 'cm_claimsmeta';
    $cache_table = $wpdb->prefix . 'cm_cache';
    
    $claims_sql = "CREATE TABLE IF NOT EXISTS $claims_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modified_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        country varchar(100) NOT NULL,
        assigned_to bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY country (country),
        KEY assigned_to (assigned_to)
    ) $charset_collate;";
    
    $claims_meta_sql = "CREATE TABLE IF NOT EXISTS $claims_meta_table (
        meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        claim_id bigint(20) unsigned NOT NULL,
        meta_key varchar(255) DEFAULT NULL,
        meta_value longtext,
        PRIMARY KEY (meta_id),
        KEY claim_id (claim_id),
        KEY meta_key (meta_key(191))
    ) $charset_collate;";
    
    $cache_sql = "CREATE TABLE IF NOT EXISTS $cache_table (
        cache_key varchar(255) NOT NULL,
        cache_value longtext NOT NULL,
        expiration datetime NOT NULL,
        PRIMARY KEY (cache_key),
        KEY expiration (expiration)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($claims_sql);
    dbDelta($claims_meta_sql);
    dbDelta($cache_sql);
}

/**
 * Set default plugin options
 */
function cm_set_default_options() {
    $defaults = [
        'cm_version' => CM_VERSION,
        'cm_items_per_page' => 25,
        'cm_enable_client_portal' => true,
        'cm_notification_emails' => get_option('admin_email'),
        'cm_date_format' => get_option('date_format'),
        'cm_countries' => \ClaimsManagement\get_default_countries(),
        'cm_security' => [
            'max_login_attempts' => 5,
            'login_lockout_duration' => 900,
            'password_expiry_days' => 90,
            'require_strong_passwords' => true
        ]
    ];
    
    foreach ($defaults as $key => $value) {
        if (is_array($value)) {
            update_option($key, $value, false);
        } else {
            add_option($key, $value, '', false);
        }
    }
}

/**
 * Plugin deactivation hook
 */
function cm_deactivate_plugin() {
    // Clear any scheduled events
    wp_clear_scheduled_hook('cm_hourly_cleanup');
    wp_clear_scheduled_hook('cm_daily_maintenance');
    
    // Clear the permalinks
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cm_deactivate_plugin');

/**
 * Remove default roles that are not needed
 */
function cm_remove_default_roles() {
    if (get_role('claims_manager') || get_role('claims_admin')) {
        remove_role('subscriber');
        remove_role('contributor');
        remove_role('author');
    }
}
register_activation_hook(__FILE__, 'cm_remove_default_roles');

/**
 * Remove unwanted admin menu items for Claims Manager and Claims Admin
 */
function cm_remove_admin_menu_items_for_claims_roles() {
    if (current_user_can('claims_manager') || current_user_can('claims_admin')) {
        $remove_menus = [
            'index.php',          // Dashboard
            'jetpack',            // Jetpack
            'edit.php',           // Posts
            'upload.php',         // Media
            'edit.php?post_type=page', // Pages
            'edit-comments.php',  // Comments
            'themes.php',         // Appearance
            'plugins.php',        // Plugins
            'tools.php',          // Tools
            'options-general.php' // Settings
        ];
        
        foreach ($remove_menus as $menu) {
            remove_menu_page($menu);
        }
    }
}
add_action('admin_menu', 'cm_remove_admin_menu_items_for_claims_roles', 999);

/**
 * Add plugin action links
 */
function cm_add_plugin_action_links($links) {
    $plugin_links = [
        '<a href="' . admin_url('admin.php?page=claims-settings') . '">' . __('Settings', 'claims-management') . '</a>',
        '<a href="' . admin_url('admin.php?page=claims-dashboard') . '">' . __('Dashboard', 'claims-management') . '</a>'
    ];
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cm_add_plugin_action_links');

/**
 * Schedule cleanup tasks
 */
function cm_schedule_tasks() {
    if (!wp_next_scheduled('cm_hourly_cleanup')) {
        wp_schedule_event(time(), 'hourly', 'cm_hourly_cleanup');
    }
    if (!wp_next_scheduled('cm_daily_maintenance')) {
        wp_schedule_event(time(), 'daily', 'cm_daily_maintenance');
    }
}
add_action('wp', 'cm_schedule_tasks');

/**
 * Hourly cleanup task
 */
function cm_do_hourly_cleanup() {
    global $wpdb;
    
    // Clean expired cache
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cm_cache WHERE expiration < %s",
            current_time('mysql')
        )
    );
}
add_action('cm_hourly_cleanup', 'cm_do_hourly_cleanup');

/**
 * Daily maintenance task
 */
function cm_do_daily_maintenance() {
    // Cleanup old logs
    $log_dir = CM_PLUGIN_DIR . 'logs';
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*.log');
        $max_age = 30 * DAY_IN_SECONDS;
        
        foreach ($files as $file) {
            if (filemtime($file) < (time() - $max_age)) {
                @unlink($file);
            }
        }
    }
}
add_action('cm_daily_maintenance', 'cm_do_daily_maintenance');

// Load text domain
function cm_load_textdomain() {
    load_plugin_textdomain(
        'claims-management',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'cm_load_textdomain');