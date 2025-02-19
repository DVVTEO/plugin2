<?php
/**
 * Template for the main prospecting page
 *
 * @package ClaimsManagement
 * @subpackage Prospecting
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-19 00:23:30
 */

use function ClaimsManagement\cm_get_country_code;
use function ClaimsManagement\cm_get_flag_img;

if (!defined('ABSPATH')) {
    exit;
}


$current_user = wp_get_current_user();
$user_country = get_user_meta($current_user->ID, 'cm_user_country', true);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Prospecting Dashboard', 'claims-management'); ?></h1>
    
    <?php if (!empty($import_results)): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    esc_html__('Successfully imported %d prospects. %d prospects were rejected.', 'claims-management'),
                    intval($import_results['imported']),
                    !empty($import_results['rejected']) && is_array($import_results['rejected']) ? count($import_results['rejected']) : 0
                );
                ?>
            </p>
        </div>

        <?php if (!empty($import_results['errors']) && is_array($import_results['errors'])): ?>
            <div class="notice notice-error is-dismissible">
                <?php foreach ($import_results['errors'] as $error): ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Import Section -->
    <div class="card">
        <h2 class="title"><?php esc_html_e('Import Prospects', 'claims-management'); ?></h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="prospect-import-form">
            <?php wp_nonce_field('cm_import_prospects', 'cm_import_nonce'); ?>
            <input type="hidden" name="action" value="cm_import_prospects">
            
            <div class="form-field">
                <label for="prospects_csv">
                    <?php esc_html_e('Select CSV File', 'claims-management'); ?>
                    <span class="required">*</span>
                </label>
                <input type="file" 
                       name="prospects_csv" 
                       id="prospects_csv" 
                       accept=".csv" 
                       required>
                <p class="description">
                    <?php esc_html_e('CSV must include: Business Name, Web Address, Phone Number, Country', 'claims-management'); ?>
                </p>
            </div>

            <?php submit_button(__('Import Prospects', 'claims-management')); ?>
        </form>
    </div>

    <!-- Sample CSV Template -->
    <div class="card">
        <h2 class="title"><?php esc_html_e('CSV Template', 'claims-management'); ?></h2>
        <p>
            <?php esc_html_e('Download our CSV template to ensure your data is formatted correctly:', 'claims-management'); ?>
        </p>
        <a href="<?php echo esc_url(CM_PLUGIN_URL . 'templates/prospects-template.csv'); ?>" 
           class="button button-secondary">
            <?php esc_html_e('Download Template', 'claims-management'); ?>
        </a>
    </div>

    <!-- Rejected Prospects Section -->
    <?php if (!empty($import_results['rejected']) && is_array($import_results['rejected'])): ?>
        <div class="card">
            <h2 class="title">
                <?php esc_html_e('Rejected Prospects', 'claims-management'); ?>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=download_rejected_prospects'), 'download_rejected_prospects')); ?>" 
                   class="page-title-action">
                    <?php esc_html_e('Download Rejected List', 'claims-management'); ?>
                </a>
            </h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Business Name', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Web Address', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Phone Number', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Country', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Rejection Reason', 'claims-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($import_results['rejected'] as $rejected): ?>
                        <tr>
                            <td><?php echo esc_html($rejected['Business Name'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($rejected['Web Address'])): ?>
                                    <a href="<?php echo esc_url($rejected['Web Address']); ?>" 
                                       target="_blank">
                                        <?php echo esc_html($rejected['Web Address']); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($rejected['Phone Number'] ?? ''); ?></td>
                            <td>
                                <?php 
                                if (!empty($rejected['Country'])) {
                                    $country_code = cm_get_country_code($rejected['Country']);
                                    if ($country_code) {
                                        echo cm_get_flag_img($country_code, $rejected['Country']) . ' ';
                                    }
                                    echo esc_html($rejected['Country']);
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($rejected['rejection_reason'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Import History Section -->
    <div class="card">
        <h2 class="title"><?php esc_html_e('Import History', 'claims-management'); ?></h2>
        <?php
        $import_history = get_option('cm_import_history', []);
        if (empty($import_history)): ?>
            <p><?php esc_html_e('No import history available.', 'claims-management'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'claims-management'); ?></th>
                        <th><?php esc_html_e('User', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Imported', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Rejected', 'claims-management'); ?></th>
                        <th><?php esc_html_e('Countries', 'claims-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($import_history as $history): ?>
                        <tr>
                            <td>
                                <?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($history['date'])
                                    )
                                ); ?>
                            </td>
                            <td>
                                <?php 
                                $user_info = get_userdata($history['user_id']);
                                echo esc_html($user_info ? $user_info->display_name : __('Unknown', 'claims-management'));
                                ?>
                            </td>
                            <td><?php echo esc_html($history['imported']); ?></td>
                            <td><?php echo esc_html($history['rejected']); ?></td>
                            <td><?php 
                                if (!empty($history['countries']) && is_array($history['countries'])) {
                                    echo esc_html(implode(', ', array_filter($history['countries'])));
                                }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.prospect-import-form {
    max-width: 600px;
    margin: 20px 0;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-field .required {
    color: #dc3232;
}

.form-field input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-field select {
    width: 100%;
    max-width: 300px;
}

.card {
    padding: 20px;
    margin-top: 20px;
    background: #fff;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    border: 1px solid #ccd0d4;
}

.card .title {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.page-title-action {
    margin-left: 10px;
    font-size: 13px;
}

table img {
    vertical-align: middle;
    margin-right: 5px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // File input validation
    $('#prospects_csv').on('change', function() {
        var file = this.files[0];
        if (file) {
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                alert('<?php echo esc_js(__('Please select a valid CSV file.', 'claims-management')); ?>');
                this.value = '';
            }
        }
    });

    // Form submission validation
    $('.prospect-import-form').on('submit', function(e) {
        var fileInput = $('#prospects_csv')[0];
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please select a CSV file to import.', 'claims-management')); ?>');
            return false;
        }
    });

    // Notice dismissal
    $('.notice-dismiss').on('click', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    /**
 * Enqueue admin assets
 */
public function enqueue_admin_assets($hook) {
    if (strpos($hook, $this->parent_slug) === false) {
        return;
    }

    wp_enqueue_style(
        'cm-prospecting',
        CM_PLUGIN_URL . 'assets/css/prospecting.css',
        [],
        CM_VERSION
    );

    wp_enqueue_script(
        'cm-prospecting',
        CM_PLUGIN_URL . 'assets/js/prospecting.js',
        ['jquery'],
        CM_VERSION,
        true
    );

    wp_localize_script('cm-prospecting', 'cmProspecting', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cm_prospecting_nonce'),
        'i18n' => [
            'invalidFileType' => __('Please select a valid CSV file.', 'claims-management'),
            'noFileSelected' => __('Please select a CSV file to import.', 'claims-management'),
            'confirmDelete' => __('Are you sure you want to delete these prospects?', 'claims-management'),
            'confirmConvert' => __('Are you sure you want to convert these prospects?', 'claims-management'),
            'confirmStatusChange' => __('Are you sure you want to change the status?', 'claims-management')
        ]
    ]);
}
});
</script>

