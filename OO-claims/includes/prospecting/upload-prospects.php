<?php
/**
 * Prospecting Management
 *
 * @package ClaimsManagement
 * @subpackage Prospecting
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-18 23:36:06
 */

namespace ClaimsManagement;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prospecting class handles all prospect-related functionality
 */
class Prospecting {
    /**
     * @var string The parent menu slug
     */
    private $parent_slug = 'upload-prospects';

    /**
     * @var string The templates directory path
     */
    private $templates_dir;

    /**
     * @var array Required CSV headers
     */
    private $required_headers = [
        'Business Name',
        'Web Address',
        'Phone Number',
        'Country'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->templates_dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/prospecting/';
        $this->ensure_template_directory();
        
        // Register admin post action for CSV import
        add_action('admin_post_cm_import_prospects', [$this, 'handle_csv_import']);
        
        $this->init_hooks();
    }

    /**
     * Ensure template directory exists
     */
    private function ensure_template_directory() {
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Menu Registration
        add_action('admin_menu', [$this, 'register_menus']);

        // Form Handlers
        add_action('admin_post_download_rejected_prospects', [$this, 'handle_download_rejected']);
        add_action('admin_post_assign_sales_manager', [$this, 'handle_assign_sales_manager']);
        add_action('admin_post_assign_all_equally', [$this, 'handle_assign_all_equally']);
        add_action('admin_post_delete_prospect', [$this, 'handle_delete_prospect']);
        add_action('admin_post_assign_claims_manager', [$this, 'handle_assign_claims_manager']);
        add_action('admin_post_convert_assigned_prospects', [$this, 'handle_convert_assigned']);

        // AJAX Handlers
        add_action('wp_ajax_cm_assign_claims_manager_ajax', [$this, 'handle_ajax_assign']);

        // Admin Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Register admin menus
     */
    public function register_menus() {
        // Main Prospecting Menu
        add_menu_page(
            __('Prospecting', 'claims-management'),
            __('Prospecting', 'claims-management'),
            'manage_options',
            $this->parent_slug,
            [$this, 'render_main_page'],
            'dashicons-phone',
            25
        );

        // Upload Prospects Submenu
        add_submenu_page(
            $this->parent_slug,
            __('Upload Prospects', 'claims-management'),
            __('Upload Prospects', 'claims-management'),
            'manage_options',
            $this->parent_slug,
            [$this, 'render_main_page']
        );

        // My Prospects Submenu
        add_submenu_page(
            $this->parent_slug,
            __('My Prospects', 'claims-management'),
            __('My Prospects', 'claims-management'),
            'manage_options',
            'my-prospects',
            [$this, 'render_my_prospects_page']
        );
    }

/**
 * Enqueue admin assets
 *
 * @since 2025-02-19 00:39:23
 * @author DVVTEO
 */
public function enqueue_admin_assets($hook) {
    if (strpos($hook, $this->parent_slug) === false) {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'cm-prospecting',
        CM_PLUGIN_URL . 'assets/css/prospecting.css',
        [],
        CM_VERSION
    );

    // Enqueue JavaScript
    wp_enqueue_script(
        'cm-prospecting',
        CM_PLUGIN_URL . 'assets/js/prospecting.js',
        ['jquery'],
        CM_VERSION,
        true
    );

    // Localize script
    wp_localize_script(
        'cm-prospecting',
        'cmProspecting',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cm_prospecting_nonce'),
            'i18n' => [
                'invalidFileType' => __('Please select a valid CSV file.', 'claims-management'),
                'noFileSelected' => __('Please select a CSV file to import.', 'claims-management'),
                'confirmDelete' => __('Are you sure you want to delete these prospects?', 'claims-management'),
                'confirmConvert' => __('Are you sure you want to convert these prospects?', 'claims-management'),
                'confirmStatusChange' => __('Are you sure you want to change the status?', 'claims-management')
            ]
        ]
    );
}

    /**
     * Get template path
     */
    private function get_template_path($template) {
        return $this->templates_dir . $template;
    }

    /**
     * Load a template
     */
    private function load_template($template, $data = []) {
        $template_path = $this->get_template_path($template);
        
        if (file_exists($template_path)) {
            if (!empty($data)) {
                extract($data);
            }
            include $template_path;
        } else {
            error_log(sprintf(
                '[ClaimsManagement] Template not found: %s (User: %s)',
                $template_path,
                wp_get_current_user()->user_login
            ));
            $this->display_template_error($template);
        }
    }

    /**
     * Display template error message
     */
    private function display_template_error($template) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    esc_html__('Template file not found: %s', 'claims-management'),
                    esc_html($template)
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render main prospecting page
     */
    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'claims-management'));
        }

        $import_results = get_transient('cm_import_results_' . get_current_user_id());
        delete_transient('cm_import_results_' . get_current_user_id());

        $this->load_template('main-page.php', [
            'import_results' => $import_results
        ]);
    }

    /**
     * Render my prospects page
     */
    public function render_my_prospects_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'claims-management'));
        }

        $prospects = $this->get_my_prospects();
        
        $this->load_template('my-prospects.php', [
            'prospects' => $prospects
        ]);
    }

    /**
     * Handle CSV import process
     */
    public function handle_csv_import() {
        if (!isset($_POST['cm_import_nonce']) || !wp_verify_nonce($_POST['cm_import_nonce'], 'cm_import_prospects')) {
            wp_die(__('Security check failed', 'claims-management'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        if (!isset($_FILES['prospects_csv']) || $_FILES['prospects_csv']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('No file uploaded or upload error occurred.', 'claims-management'));
        }

        $file = $_FILES['prospects_csv']['tmp_name'];
        $results = $this->process_csv_import($file);

        // Store results in transient
        set_transient(
            'cm_import_results_' . get_current_user_id(),
            $results,
            5 * MINUTE_IN_SECONDS
        );

        // Log the import
        $this->log_import($results);

        wp_redirect(admin_url('admin.php?page=' . $this->parent_slug . '&imported=1'));
        exit;
    }

    /**
     * Process CSV import
     */
    private function process_csv_import($file) {
        $results = [
            'imported' => 0,
            'rejected' => [],
            'errors' => []
        ];

        if (!file_exists($file)) {
            $results['errors'][] = __('File not found', 'claims-management');
            return $results;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $results['errors'][] = __('Could not open file', 'claims-management');
            return $results;
        }

        // Get and validate headers
        $headers = fgetcsv($handle);
        if (!$this->validate_headers($headers)) {
            $results['errors'][] = __('Invalid CSV headers. Required headers are: ', 'claims-management') 
                . implode(', ', $this->required_headers);
            fclose($handle);
            return $results;
        }

        $header_map = array_flip($headers);

        // Process each row
        while (($data = fgetcsv($handle)) !== false) {
            $prospect = $this->prepare_prospect_data($data, $header_map);
            
            if (empty(array_filter($prospect))) {
                continue; // Skip empty rows
            }

            if ($error = $this->validate_prospect($prospect)) {
                $prospect['rejection_reason'] = $error;
                $results['rejected'][] = $prospect;
                continue;
            }

            if ($this->save_prospect($prospect)) {
                $results['imported']++;
            } else {
                $prospect['rejection_reason'] = __('Failed to save prospect', 'claims-management');
                $results['rejected'][] = $prospect;
            }
        }

        fclose($handle);
        return $results;
    }

    /**
     * Validate CSV headers
     */
    private function validate_headers($headers) {
        if (!is_array($headers)) {
            return false;
        }
        return empty(array_diff($this->required_headers, $headers));
    }

    /**
     * Prepare prospect data from CSV row
     */
    private function prepare_prospect_data($data, $header_map) {
        $prospect = [];
        foreach ($this->required_headers as $header) {
            $index = $header_map[$header] ?? false;
            $prospect[$header] = ($index !== false && isset($data[$index])) 
                ? sanitize_text_field($data[$index]) 
                : '';
        }
        return $prospect;
    }

    /**
     * Validate prospect data
     */
    private function validate_prospect($prospect) {
        if (empty($prospect['Business Name'])) {
            return __('Missing business name', 'claims-management');
        }

        if (empty($prospect['Web Address'])) {
            return __('Missing web address', 'claims-management');
        }

        if (empty($prospect['Country'])) {
            return __('Missing country', 'claims-management');
        }

        if ($this->is_duplicate($prospect)) {
            return __('Duplicate prospect', 'claims-management');
        }

        return false;
    }

    /**
     * Check if prospect is a duplicate
     */
    private function is_duplicate($prospect) {
        // Check in temporary prospects
        $existing = get_option('cm_prospects', []);
        foreach ($existing as $existing_prospect) {
            if ($this->prospects_match($prospect, $existing_prospect)) {
                return true;
            }
        }

        // Check in permanent prospects
        $permanent = get_posts([
            'post_type' => 'cm_prospect',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'business_name',
                    'value' => $prospect['Business Name'],
                    'compare' => '='
                ],
                [
                    'key' => 'web_address',
                    'value' => $prospect['Web Address'],
                    'compare' => '='
                ]
            ]
        ]);

        return !empty($permanent);
    }

    /**
     * Compare two prospects for matching
     */
    private function prospects_match($prospect1, $prospect2) {
        return (
            $prospect1['Business Name'] === $prospect2['Business Name'] ||
            $prospect1['Web Address'] === $prospect2['Web Address']
        );
    }

    /**
     * Save prospect
     */
    private function save_prospect($prospect) {
        $existing = get_option('cm_prospects', []);
        $existing[] = $prospect;
        return update_option('cm_prospects', $existing);
    }

    /**
     * Get prospects for current user
     */
    private function get_my_prospects() {
        return get_posts([
            'post_type' => 'cm_prospect',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'claims_manager',
                    'value' => get_current_user_id(),
                    'compare' => '='
                ]
            ]
        ]);
    }

    /**
     * Log import results
     */
    private function log_import($results) {
        $history = get_option('cm_import_history', []);
        $history[] = [
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'imported' => $results['imported'],
            'rejected' => count($results['rejected']),
            'errors' => $results['errors']
        ];
        
        // Keep only last 100 imports
        $history = array_slice($history, -100);
        
        update_option('cm_import_history', $history);
    }

    /**
     * Handle prospect download
     */
    public function handle_download_rejected() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        $rejected = get_transient('cm_rejected_' . get_current_user_id());
        if (!$rejected) {
            wp_die(__('No rejected prospects found.', 'claims-management'));
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rejected-prospects-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_merge($this->required_headers, ['Rejection Reason']));

        foreach ($rejected as $prospect) {
            $row = [];
            foreach ($this->required_headers as $header) {
                $row[] = $prospect[$header] ?? '';
            }
            $row[] = $prospect['rejection_reason'] ?? '';
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Handle AJAX assignment
     */
    public function handle_ajax_assign() {
        check_ajax_referer('cm_prospecting_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'claims-management'));
        }

        $prospect_id = isset($_POST['prospect_id']) ? intval($_POST['prospect_id']) : 0;
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;

        if (!$prospect_id || !$manager_id) {
            wp_send_json_error(__('Invalid data provided', 'claims-management'));
        }

        $success = update_post_meta($prospect_id, 'claims_manager', $manager_id);
        
        if ($success) {
            wp_send_json_success(__('Prospect assigned successfully', 'claims-management'));
        } else {
            wp_send_json_error(__('Failed to assign prospect', 'claims-management'));
        }
    }

    /**
     * Handle sales manager assignment
     */
    public function handle_assign_sales_manager() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        check_admin_referer('assign_sales_manager');

        $prospect_id = isset($_POST['prospect_id']) ? intval($_POST['prospect_id']) : 0;
        $sales_manager_id = isset($_POST['sales_manager_id']) ? intval($_POST['sales_manager_id']) : 0;

        if (!$prospect_id || !$sales_manager_id) {
            wp_die(__('Invalid data provided', 'claims-management'));
        }

        update_post_meta($prospect_id, 'sales_manager', $sales_manager_id);

        wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=' . $this->parent_slug));
        exit;
    }

    /**
     * Handle bulk assignment
     */
    public function handle_assign_all_equally() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        check_admin_referer('assign_all_equally');

        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        if (empty($country)) {
            wp_die(__('No country specified', 'claims-management'));
        }

        // Get all unassigned prospects for the country
        $prospects = get_option('cm_prospects', []);
        $unassigned = array_filter($prospects, function($prospect) use ($country) {
            return $prospect['Country'] === $country && empty($prospect['claims_manager']);
        });

        // Get available claims managers for the country
        $claims_managers = get_users([
            'role' => 'claims_manager',
            'meta_key' => 'cm_user_country',
            'meta_value' => $country
        ]);

        if (empty($claims_managers)) {
            wp_die(__('No claims managers found for this country', 'claims-management'));
        }

        // Distribute prospects equally
        $manager_count = count($claims_managers);
        $prospects_per_manager = ceil(count($unassigned) / $manager_count);

        $manager_index = 0;
        foreach ($unassigned as $index => $prospect) {
            $manager_id = $claims_managers[$manager_index % $manager_count]->ID;
            $prospects[$index]['claims_manager'] = $manager_id;

            if (($manager_index + 1) % $prospects_per_manager === 0) {
                $manager_index++;
            }
        }

        update_option('cm_prospects', $prospects);

        wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=' . $this->parent_slug));
        exit;
    }

    /**
     * Handle prospect deletion
     */
    public function handle_delete_prospect() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        check_admin_referer('delete_prospect');

        $prospect_id = isset($_POST['prospect_id']) ? intval($_POST['prospect_id']) : 0;
        if (!$prospect_id) {
            wp_die(__('Invalid prospect ID', 'claims-management'));
        }

        if (get_post_type($prospect_id) !== 'cm_prospect') {
            wp_die(__('Invalid prospect', 'claims-management'));
        }

        wp_delete_post($prospect_id, true);

        wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=' . $this->parent_slug));
        exit;
    }

    /**
     * Handle prospect conversion
     */
    public function handle_convert_assigned() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'claims-management'));
        }

        check_admin_referer('convert_assigned_prospects');

        $prospects = get_option('cm_prospects', []);
        $converted = 0;

        foreach ($prospects as $index => $prospect) {
            if (empty($prospect['claims_manager'])) {
                continue;
            }

            $post_id = wp_insert_post([
                'post_type' => 'cm_prospect',
                'post_title' => $prospect['Business Name'],
                'post_status' => 'publish',
                'post_author' => $prospect['claims_manager']
            ]);

            if ($post_id) {
                update_post_meta($post_id, 'business_name', $prospect['Business Name']);
                update_post_meta($post_id, 'web_address', esc_url_raw($prospect['Web Address']));
                update_post_meta($post_id, 'phone_number', $prospect['Phone Number']);
                update_post_meta($post_id, 'country', $prospect['Country']);
                update_post_meta($post_id, 'claims_manager', $prospect['claims_manager']);
                
                if (!empty($prospect['sales_manager'])) {
                    update_post_meta($post_id, 'sales_manager', $prospect['sales_manager']);
                }

                unset($prospects[$index]);
                $converted++;
            }
        }

        $prospects = array_values($prospects);
        update_option('cm_prospects', $prospects);

        wp_redirect(add_query_arg(
            ['converted' => $converted],
            admin_url('admin.php?page=' . $this->parent_slug)
        ));
        exit;
    }
}

// Initialize the Prospecting class
new Prospecting();