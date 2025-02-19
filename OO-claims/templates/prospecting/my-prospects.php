<?php
/**
 * Template for the My Prospects page
 *
 * @package ClaimsManagement
 * @subpackage Prospecting
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-18
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_country = get_user_meta($current_user->ID, 'cm_user_country', true);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('My Prospects', 'claims-management'); ?></h1>
    
    <hr class="wp-header-end">

    <?php if (empty($prospects)): ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('No prospects are currently assigned to you.', 'claims-management'); ?></p>
        </div>
    <?php else: ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="bulk-action-selector-top">
                    <option value="-1"><?php esc_html_e('Bulk Actions', 'claims-management'); ?></option>
                    <option value="convert"><?php esc_html_e('Convert to Client', 'claims-management'); ?></option>
                    <option value="archive"><?php esc_html_e('Archive', 'claims-management'); ?></option>
                </select>
                <button class="button" id="doaction"><?php esc_html_e('Apply', 'claims-management'); ?></button>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        _n('%s prospect', '%s prospects', count($prospects), 'claims-management'),
                        number_format_i18n(count($prospects))
                    ); ?>
                </span>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped prospects-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th scope="col" class="manage-column column-business_name">
                        <?php esc_html_e('Business Name', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-contact">
                        <?php esc_html_e('Contact Info', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-country">
                        <?php esc_html_e('Country', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php esc_html_e('Status', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php esc_html_e('Actions', 'claims-management'); ?>
                    </th>
                </tr>
            </thead>

            <tbody id="the-list">
                <?php foreach ($prospects as $prospect): 
                    $prospect_id = $prospect->ID;
                    $business_name = get_post_meta($prospect_id, 'business_name', true);
                    $web_address = get_post_meta($prospect_id, 'web_address', true);
                    $phone = get_post_meta($prospect_id, 'phone', true);
                    $country = get_post_meta($prospect_id, 'country', true);
                    $status = get_post_meta($prospect_id, 'status', true) ?: 'new';
                    $last_contact = get_post_meta($prospect_id, 'last_contact', true);
                    ?>
                    <tr id="prospect-<?php echo esc_attr($prospect_id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="prospect[]" value="<?php echo esc_attr($prospect_id); ?>">
                        </th>
                        <td class="column-business_name">
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=prospect-profile&id=' . $prospect_id)); ?>">
                                    <?php echo esc_html($business_name); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=prospect-profile&id=' . $prospect_id)); ?>">
                                        <?php esc_html_e('Edit', 'claims-management'); ?>
                                    </a> |
                                </span>
                                <span class="convert">
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=convert_prospect&id=' . $prospect_id), 'convert_prospect_' . $prospect_id)); ?>">
                                        <?php esc_html_e('Convert to Client', 'claims-management'); ?>
                                    </a> |
                                </span>
                                <span class="trash">
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_prospect&id=' . $prospect_id), 'delete_prospect_' . $prospect_id)); ?>" 
                                       class="submitdelete" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this prospect?', 'claims-management'); ?>');">
                                        <?php esc_html_e('Delete', 'claims-management'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-contact">
                            <?php if ($web_address): ?>
                                <div class="prospect-web">
                                    <span class="dashicons dashicons-admin-site"></span>
                                    <a href="<?php echo esc_url($web_address); ?>" target="_blank">
                                        <?php echo esc_html(preg_replace('#^https?://#', '', $web_address)); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                                <div class="prospect-phone">
                                    <span class="dashicons dashicons-phone"></span>
                                    <a href="tel:<?php echo esc_attr($phone); ?>">
                                        <?php echo esc_html($phone); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-country">
                            <?php 
                            $country_code = cm_get_country_code($country);
                            if ($country_code) {
                                echo cm_get_flag_img($country_code, $country);
                            }
                            echo esc_html($country);
                            ?>
                        </td>
                        <td class="column-status">
                            <select class="prospect-status-select" 
                                    data-prospect-id="<?php echo esc_attr($prospect_id); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('update_prospect_status_' . $prospect_id)); ?>">
                                <option value="new" <?php selected($status, 'new'); ?>>
                                    <?php esc_html_e('New', 'claims-management'); ?>
                                </option>
                                <option value="contacted" <?php selected($status, 'contacted'); ?>>
                                    <?php esc_html_e('Contacted', 'claims-management'); ?>
                                </option>
                                <option value="interested" <?php selected($status, 'interested'); ?>>
                                    <?php esc_html_e('Interested', 'claims-management'); ?>
                                </option>
                                <option value="not-interested" <?php selected($status, 'not-interested'); ?>>
                                    <?php esc_html_e('Not Interested', 'claims-management'); ?>
                                </option>
                                <option value="converted" <?php selected($status, 'converted'); ?>>
                                    <?php esc_html_e('Converted', 'claims-management'); ?>
                                </option>
                            </select>
                            <?php if ($last_contact): ?>
                                <div class="row-actions">
                                    <span class="last-contact">
                                        <?php printf(
                                            esc_html__('Last contact: %s', 'claims-management'),
                                            date_i18n(get_option('date_format'), strtotime($last_contact))
                                        ); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <button type="button" 
                                    class="button add-note-button" 
                                    data-prospect-id="<?php echo esc_attr($prospect_id); ?>"
                                    title="<?php esc_attr_e('Add Note', 'claims-management'); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" 
                                    class="button schedule-followup-button" 
                                    data-prospect-id="<?php echo esc_attr($prospect_id); ?>"
                                    title="<?php esc_attr_e('Schedule Follow-up', 'claims-management'); ?>">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </td>
                    <th scope="col" class="manage-column column-business_name">
                        <?php esc_html_e('Business Name', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-contact">
                        <?php esc_html_e('Contact Info', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-country">
                        <?php esc_html_e('Country', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php esc_html_e('Status', 'claims-management'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php esc_html_e('Actions', 'claims-management'); ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<!-- Add Note Modal -->
<div id="add-note-modal" class="cm-modal" style="display: none;">
    <div class="cm-modal-content">
        <span class="cm-modal-close">&times;</span>
        <h2><?php esc_html_e('Add Note', 'claims-management'); ?></h2>
        <form id="add-note-form">
            <input type="hidden" name="prospect_id" id="note-prospect-id">
            <?php wp_nonce_field('add_prospect_note', 'note_nonce'); ?>
            <textarea name="note" id="prospect-note" rows="4" required></textarea>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Note', 'claims-management'); ?>
            </button>
        </form>
    </div>
</div>

<!-- Schedule Follow-up Modal -->
<div id="schedule-followup-modal" class="cm-modal" style="display: none;">
    <div class="cm-modal-content">
        <span class="cm-modal-close">&times;</span>
        <h2><?php esc_html_e('Schedule Follow-up', 'claims-management'); ?></h2>
        <form id="schedule-followup-form">
            <input type="hidden" name="prospect_id" id="followup-prospect-id">
            <?php wp_nonce_field('schedule_followup', 'followup_nonce'); ?>
            <p>
                <label for="followup-date"><?php esc_html_e('Date', 'claims-management'); ?></label>
                <input type="date" name="followup_date" id="followup-date" required>
            </p>
            <p>
                <label for="followup-time"><?php esc_html_e('Time', 'claims-management'); ?></label>
                <input type="time" name="followup_time" id="followup-time" required>
            </p>
            <p>
                <label for="followup-notes"><?php esc_html_e('Notes', 'claims-management'); ?></label>
                <textarea name="followup_notes" id="followup-notes" rows="3"></textarea>
            </p>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Schedule', 'claims-management'); ?>
            </button>
        </form>
    </div>
</div>

<style>
.cm-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.cm-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 50%;
    max-width: 500px;
    border-radius: 4px;
    position: relative;
}

.cm-modal-close {
    position: absolute;
    right: 10px;
    top: 5px;
    font-size: 20px;
    cursor: pointer;
}

.prospects-table .column-actions {
    width: 100px;
}

.prospects-table .column-status {
    width: 150px;
}

.prospects-table .column-country {
    width: 120px;
}

.prospect-web,
.prospect-phone {
    margin: 3px 0;
}

.dashicons {
    vertical-align: middle;
    color: #666;
}
</style>