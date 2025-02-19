<?php
/**
 * Core functionality for the Claims Management plugin
 *
 * @package ClaimsManagement
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-18
 */

namespace ClaimsManagement;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main core class for Claims Management functionality
 */
class CM_Core {
    /**
     * Singleton instance
     *
     * @var CM_Core
     */
    private static $instance = null;

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Error logger
     *
     * @var object
     */
    private $logger;

    /**
     * Cache expiration (in seconds)
     *
     * @var int
     */
    private $cache_expiration = 3600; // 1 hour

    /**
     * Get singleton instance
     *
     * @return CM_Core
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_logger();
        $this->load_settings();
        $this->init_hooks();
        $this->init_cache();
    }

    /**
     * Initialize logger
     */
    private function init_logger() {
        $log_dir = CM_PLUGIN_DIR . 'logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $this->logger = new \WP_Error();
    }

    /**
     * Initialize cache
     */
    private function init_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cm_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            PRIMARY KEY  (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = [
            'version' => CM_VERSION,
            'db_version' => get_option('cm_db_version', '1.0'),
            'items_per_page' => get_option('cm_items_per_page', 25),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'countries' => get_option('cm_countries', get_default_countries()),
            'last_updated' => current_time('mysql'),
            'security' => get_option('cm_security', [
                'max_login_attempts' => 5,
                'lockout_duration' => 900,
                'password_expiry' => 90,
                'require_strong_password' => true,
                'session_expiry' => 3600
            ])
        ];
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Post types and taxonomies
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_notices', [$this, 'admin_notices']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        }

        // AJAX handlers
        add_action('wp_ajax_cm_get_claim_data', [$this, 'ajax_get_claim_data']);
        add_action('wp_ajax_cm_update_claim', [$this, 'ajax_update_claim']);
        add_action('wp_ajax_cm_delete_claim', [$this, 'ajax_delete_claim']);
        add_action('wp_ajax_cm_get_statistics', [$this, 'ajax_get_statistics']);

        // Custom actions
        add_action('cm_after_claim_update', [$this, 'notify_claim_update'], 10, 2);
        add_action('cm_daily_maintenance', [$this, 'perform_maintenance']);
        
        // Filters
        add_filter('post_updated_messages', [$this, 'claim_updated_messages']);
        add_filter('bulk_post_updated_messages', [$this, 'claim_bulk_updated_messages'], 10, 2);
    }

    /**
     * Register admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (!in_array($hook, ['claims_page_claims-dashboard', 'claims_page_claims-settings'])) {
            return;
        }

        wp_enqueue_style(
            'cm-admin',
            CM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CM_VERSION
        );

        wp_enqueue_script(
            'cm-admin',
            CM_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            CM_VERSION,
            true
        );

        wp_localize_script('cm-admin', 'cmConfig', [
            'nonce' => wp_create_nonce('claims_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this claim?', 'claims-management'),
                'deletingClaim' => __('Deleting claim...', 'claims-management'),
                'claimDeleted' => __('Claim deleted successfully.', 'claims-management'),
                'error' => __('An error occurred.', 'claims-management')
            ]
        ]);
    }

    /**
     * Register custom post types
     */
    public function register_post_types() {
        register_post_type('cm_claim', [
            'labels' => [
                'name' => __('Claims', 'claims-management'),
                'singular_name' => __('Claim', 'claims-management'),
                'add_new' => __('Add New', 'claims-management'),
                'add_new_item' => __('Add New Claim', 'claims-management'),
                'edit_item' => __('Edit Claim', 'claims-management'),
                'new_item' => __('New Claim', 'claims-management'),
                'view_item' => __('View Claim', 'claims-management'),
                'search_items' => __('Search Claims', 'claims-management'),
                'not_found' => __('No claims found', 'claims-management'),
                'not_found_in_trash' => __('No claims found in trash', 'claims-management'),
                'all_items' => __('All Claims', 'claims-management'),
                'menu_name' => __('Claims', 'claims-management')
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'claims-dashboard',
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'capabilities' => [
                'create_posts' => 'create_claims',
                'edit_post' => 'edit_claims',
                'edit_posts' => 'edit_claims',
                'edit_others_posts' => 'edit_others_claims',
                'publish_posts' => 'publish_claims',
                'read_post' => 'read_claim',
                'read_private_posts' => 'read_private_claims',
                'delete_post' => 'delete_claims'
            ],
            'map_meta_cap' => true,
            'hierarchical' => false,
            'has_archive' => false,
            'menu_position' => 30,
            'menu_icon' => 'dashicons-clipboard',
            'show_in_rest' => true,
            'rest_base' => 'claims',
            'rest_controller_class' => 'WP_REST_Posts_Controller'
        ]);
    }

    /**
     * Register custom taxonomies
     */
    public function register_taxonomies() {
        register_taxonomy('cm_claim_status', ['cm_claim'], [
            'labels' => [
                'name' => __('Claim Statuses', 'claims-management'),
                'singular_name' => __('Claim Status', 'claims-management'),
                'search_items' => __('Search Statuses', 'claims-management'),
                'popular_items' => __('Popular Statuses', 'claims-management'),
                'all_items' => __('All Statuses', 'claims-management'),
                'edit_item' => __('Edit Status', 'claims-management'),
                'update_item' => __('Update Status', 'claims-management'),
                'add_new_item' => __('Add New Status', 'claims-management'),
                'new_item_name' => __('New Status Name', 'claims-management'),
                'menu_name' => __('Statuses', 'claims-management')
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'claim-status'],
            'show_in_rest' => true,
            'rest_base' => 'claim-statuses',
            'rest_controller_class' => 'WP_REST_Terms_Controller'
        ]);

        // Register default statuses
        $default_statuses = [
            'pending' => __('Pending', 'claims-management'),
            'in-progress' => __('In Progress', 'claims-management'),
            'under-review' => __('Under Review', 'claims-management'),
            'approved' => __('Approved', 'claims-management'),
            'rejected' => __('Rejected', 'claims-management'),
            'closed' => __('Closed', 'claims-management')
        ];

        foreach ($default_statuses as $slug => $name) {
            if (!term_exists($slug, 'cm_claim_status')) {
                wp_insert_term($name, 'cm_claim_status', ['slug' => $slug]);
            }
        }
    }

    /**
     * Initialize admin functionality
     */
    public function admin_init() {
        // Register settings
        register_setting('cm_settings', 'cm_items_per_page', [
            'type' => 'integer',
            'default' => 25,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('cm_settings', 'cm_countries', [
            'type' => 'array',
            'sanitize_callback' => 'sanitize_countries'
        ]);

        register_setting('cm_settings', 'cm_security', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_security_settings']
        ]);

        // Add settings sections
        add_settings_section(
            'cm_general_settings',
            __('General Settings', 'claims-management'),
            [$this, 'render_general_settings_section'],
            'claims-settings'
        );

        add_settings_section(
            'cm_security_settings',
            __('Security Settings', 'claims-management'),
            [$this, 'render_security_settings_section'],
            'claims-settings'
        );

        // Add settings fields
        add_settings_field(
            'cm_items_per_page',
            __('Items Per Page', 'claims-management'),
            [$this, 'render_items_per_page_field'],
            'claims-settings',
            'cm_general_settings'
        );

        add_settings_field(
            'cm_countries',
            __('Available Countries', 'claims-management'),
            [$this, 'render_countries_field'],
            'claims-settings',
            'cm_general_settings'
        );
    }

    /**
     * Add admin menu items
     */
    public function admin_menu() {
        add_menu_page(
            __('Claims Dashboard', 'claims-management'),
            __('Claims', 'claims-management'),
            'manage_claims',
            'claims-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'claims-dashboard',
            __('Settings', 'claims-management'),
            __('Settings', 'claims-management'),
            'manage_options',
            'claims-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'claims-dashboard',
            __('Statistics', 'claims-management'),
            __('Statistics', 'claims-management'),
            'manage_claims',
            'claims-statistics',
            [$this, 'render_statistics_page']
        );
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if ($this->logger && $this->logger->has_errors()) {
            foreach ($this->logger->get_error_messages() as $message) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html($message)
                );
            }
        }
    }

    /**
     * AJAX handler for getting claim data
     */
    public function ajax_get_claim_data() {
        check_ajax_referer('claims_nonce', 'nonce');

        if (!current_user_can('view_claims')) {
            wp_send_json_error(['message' => __('Permission denied', 'claims-management')]);
        }

        $claim_id = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;
        if (!$claim_id) {
            wp_send_json_error(['message' => __('Invalid claim ID', 'claims-management')]);
        }

        $claim = get_post($claim_id);
        if (!$claim || $claim->post_type !== 'cm_claim') {
            wp_send_json_error(['message' => __('Claim not found', 'claims-management')]);
        }

        $data = [
            'id' => $claim->ID,
            'title' => $claim->post_title,
            'content' => $claim->post_content,
            'status' => wp_get_post_terms($claim->ID, 'cm_claim_status', ['fields' => 'names']),
            'meta' => get_post_meta($claim->ID),
            'modified' => get_post_modified_time('Y-m-d H:i:s', true, $claim->ID),
            'author' => get_post_field('post_author', $claim->ID),
            'assigned_to' => get_post_meta($claim->ID, '_assigned_to', true)
        ];

        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for updating claims
     */
    public function ajax_update_claim() {
        check_ajax_referer('claims_nonce', 'nonce');

        if (!current_user_can('edit_claims')) {
            wp_send_json_error(['message' => __('Permission denied', 'claims-management')]);
        }

        $claim_id = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;
        $claim_data = isset($_POST['claim_data']) ? $_POST['claim_data'] : [];

        if (!$claim_id || empty($claim_data)) {
            wp_send_json_error(['message' => __('Invalid data', 'claims-management')]);
        }

        // Sanitize and update the claim
        $updated = wp_update_post([
            'ID' => $claim_id,
            'post_title' => sanitize_text_field($claim_data['title']),
            'post_content' => wp_kses_post($claim_data['content'])
        ]);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => $updated->get_error_message()]);
        }

        // Update claim status
        if (isset($claim_data['status'])) {
            wp_set_object_terms($claim_id, $claim_data['status'], 'cm_claim_status');
        }

        // Update claim metadata
        if (isset($claim_data['meta']) && is_array($claim_data['meta'])) {
            foreach ($claim_data['meta'] as $key => $value) {
                update_post_meta($claim_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        // Update assigned user
        if (isset($claim_data['assigned_to'])) {
            update_post_meta($claim_id, '_assigned_to', absint($claim_data['assigned_to']));
        }

        do_action('cm_after_claim_update', $claim_id, $claim_data);

        // Clear cache for this claim
        $this->delete_cache('claim_' . $claim_id);

        wp_send_json_success([
            'message' => __('Claim updated successfully', 'claims-management'),
            'claim_id' => $claim_id
        ]);
    }

    /**
     * AJAX handler for deleting claims
     */
    public function ajax_delete_claim() {
        check_ajax_referer('claims_nonce', 'nonce');

        if (!current_user_can('delete_claims')) {
            wp_send_json_error(['message' => __('Permission denied', 'claims-management')]);
        }

        $claim_id = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;
        if (!$claim_id) {
            wp_send_json_error(['message' => __('Invalid claim ID', 'claims-management')]);
        }

        // Check if the claim exists and is of the correct type
        $claim = get_post($claim_id);
        if (!$claim || $claim->post_type !== 'cm_claim') {
            wp_send_json_error(['message' => __('Claim not found', 'claims-management')]);
        }

        // Delete the claim
        $deleted = wp_delete_post($claim_id, true);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Failed to delete claim', 'claims-management')]);
        }

        // Clear cache for this claim
        $this->delete_cache('claim_' . $claim_id);

        wp_send_json_success([
            'message' => __('Claim deleted successfully', 'claims-management'),
            'claim_id' => $claim_id
        ]);
    }

    /**
     * Handle claim update notifications
     *
     * @param int $claim_id
     * @param array $claim_data
     */
    public function notify_claim_update($claim_id, $claim_data) {
        $claim = get_post($claim_id);
        if (!$claim) {
            return;
        }

        $admin_email = get_option('admin_email');
        $subject = sprintf(__('Claim Update: %s', 'claims-management'), $claim->post_title);
        
        $message = sprintf(
            __('Claim "%s" has been updated by %s.', 'claims-management'),
            $claim->post_title,
            wp_get_current_user()->display_name
        );

        if (isset($claim_data['status'])) {
            $message .= "\n\n" . sprintf(
                __('New Status: %s', 'claims-management'),
                is_array($claim_data['status']) ? implode(', ', $claim_data['status']) : $claim_data['status']
            );
        }

        $message .= "\n\n" . admin_url('post.php?post=' . $claim_id . '&action=edit');

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Perform daily maintenance tasks
     */
    public function perform_maintenance() {
        // Clean up old logs
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

        // Clear expired cache entries
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cm_cache WHERE expiration < %s",
                current_time('mysql')
            )
        );
    }

    /**
     * Cache management methods
     */
    private function set_cache($key, $value, $expiration = null) {
        global $wpdb;
        
        if (null === $expiration) {
            $expiration = $this->cache_expiration;
        }

        $table_name = $wpdb->prefix . 'cm_cache';
        $expiration_date = date('Y-m-d H:i:s', time() + $expiration);

        return $wpdb->replace(
            $table_name,
            [
                'cache_key' => $key,
                'cache_value' => maybe_serialize($value),
                'expiration' => $expiration_date
            ],
            ['%s', '%s', '%s']
        );
    }

    private function get_cache($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cm_cache';
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT cache_value, expiration FROM {$table_name} WHERE cache_key = %s AND expiration > %s",
                $key,
                current_time('mysql')
            )
        );

        return $result ? maybe_unserialize($result->cache_value) : false;
    }

    private function delete_cache($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cm_cache';
        return $wpdb->delete(
            $table_name,
            ['cache_key' => $key],
            ['%s']
        );
    }

    /**
     * Get claim statistics
     *
     * @return array
     */
    public function get_claim_statistics() {
        // Try to get cached statistics
        $cache_key = 'claim_statistics';
        $cached_stats = $this->get_cache($cache_key);
        
        if (false !== $cached_stats) {
            return $cached_stats;
        }

        global $wpdb;

        $stats = [
            'total' => 0,
            'by_status' => [],
            'by_country' => [],
            'recent_activity' => [],
            'by_manager' => [],
            'monthly_trends' => []
        ];

        // Get total claims
        $stats['total'] = wp_count_posts('cm_claim')->publish;

        // Get claims by status
        $status_terms = get_terms([
            'taxonomy' => 'cm_claim_status',
            'hide_empty' => true
        ]);

        foreach ($status_terms as $term) {
            $stats['by_status'][$term->slug] = $term->count;
        }

        // Get claims by country
        $countries = $wpdb->get_results(
            "SELECT meta_value as country, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_claim_country' 
             GROUP BY meta_value 
             ORDER BY count DESC 
             LIMIT 10"
        );

        foreach ($countries as $country) {
            $stats['by_country'][$country->country] = (int)$country->count;
        }

        // Get claims by manager
        $managers = get_users(['role' => 'claims_manager']);
        foreach ($managers as $manager) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_assigned_to' 
                     AND meta_value = %d",
                    $manager->ID
                )
            );
            $stats['by_manager'][$manager->display_name] = (int)$count;
        }

        // Get monthly trends for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $year_month = date('Y-m', strtotime("-$i months"));
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$wpdb->posts} 
                     WHERE post_type = 'cm_claim' 
                     AND post_status = 'publish' 
                     AND DATE_FORMAT(post_date, '%%Y-%%m') = %s",
                    $year_month
                )
            );
            $stats['monthly_trends'][$year_month] = (int)$count;
        }

        // Get recent activity
        $recent = get_posts([
            'post_type' => 'cm_claim',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        foreach ($recent as $claim) {
            $stats['recent_activity'][] = [
                'id' => $claim->ID,
                'title' => $claim->post_title,
                'modified' => get_post_modified_time('Y-m-d H:i:s', true, $claim->ID),
                'status' => wp_get_post_terms($claim->ID, 'cm_claim_status', ['fields' => 'names']),
                'assigned_to' => get_post_meta($claim->ID, '_assigned_to', true)
            ];
        }

        // Cache the statistics for 1 hour
        $this->set_cache($cache_key, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    /**
     * Get a plugin setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update a plugin setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        return update_option('cm_' . $key, $value);
    }

    /**
     * Sanitize security settings
     *
     * @param array $input The unsanitized settings
     * @return array The sanitized settings
     */
    public function sanitize_security_settings($input) {
        $sanitized = [];
        
        $sanitized['max_login_attempts'] = absint($input['max_login_attempts']);
        $sanitized['lockout_duration'] = absint($input['lockout_duration']);
        $sanitized['password_expiry'] = absint($input['password_expiry']);
        $sanitized['require_strong_password'] = (bool)$input['require_strong_password'];
        $sanitized['session_expiry'] = absint($input['session_expiry']);

        return $sanitized;
    }

    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    public function log_error($message, $context = []) {
        if ($this->logger) {
            $formatted_message = sprintf(
                '[%s] %s | Context: %s',
                current_time('mysql'),
                $message,
                json_encode($context)
            );

            $log_file = CM_PLUGIN_DIR . 'logs/error.log';
            error_log($formatted_message . PHP_EOL, 3, $log_file);

            $this->logger->add('error', $message, $context);
        }
    }
}