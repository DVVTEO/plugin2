<?php
/**
 * Helper Functions
 *
 * @package ClaimsManagement
 * @version 3.5
 * @author DVVTEO
 * @since 2025-02-19 00:00:49
 */

namespace ClaimsManagement;

if (!defined('ABSPATH')) {
    exit;
}

function cm_enqueue_frontend_scripts() {
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\cm_enqueue_frontend_scripts');

/**
 * Redirect clients away from the backend.
 */
function cm_redirect_clients_from_admin() {
    if (is_user_logged_in() && current_user_can('cm_client') && !defined('DOING_AJAX')) {
        wp_redirect(home_url('/client-portal/'));
        exit;
    }
}

/**
 * Hide the admin bar for clients.
 *
 * @param bool $show Whether to show the admin bar.
 * @return bool
 */
function cm_hide_admin_bar_for_clients($show) {
    if (current_user_can('cm_client')) {
        return false;
    }
    return $show;
}

/**
 * Generate a unique 5-digit numeric string to be used as a client username.
 *
 * @return string A unique 5-digit string.
 */
function generate_unique_slug() {
    do {
        $slug = strval(rand(10000, 99999));
        $user = get_user_by('login', $slug);
    } while ($user);
    return $slug;
}

/**
 * Return the default countries.
 *
 * @return array Default countries.
 */
function get_default_countries() {
    return [
        'Albania',
        'Andorra',
        'Austria',
        'Belarus',
        'Belgium',
        'Bosnia and Herzegovina',
        'Bulgaria',
        'Croatia',
        'Cyprus',
        'Czech Republic',
        'Denmark',
        'Estonia',
        'Finland',
        'France',
        'Germany',
        'Greece',
        'Hungary',
        'Iceland',
        'Ireland',
        'Italy',
        'Kosovo',
        'Latvia',
        'Liechtenstein',
        'Lithuania',
        'Luxembourg',
        'Malta',
        'Moldova',
        'Monaco',
        'Montenegro',
        'Netherlands',
        'North Macedonia',
        'Norway',
        'Poland',
        'Portugal',
        'Romania',
        'Russia',
        'San Marino',
        'Serbia',
        'Slovakia',
        'Slovenia',
        'Spain',
        'Sweden',
        'Switzerland',
        'Turkey',
        'Ukraine',
        'United Kingdom',
        'Vatican City',
    ];
}

/**
 * Sanitize the countries input.
 *
 * @param mixed $input The input from the textarea.
 * @return array Sanitized countries array.
 */
function sanitize_countries($input) {
    if (is_array($input)) {
        return array_map('sanitize_text_field', $input);
    } else {
        $lines = explode("\n", $input);
        $countries = array_map('trim', $lines);
        return array_filter(array_map('sanitize_text_field', $countries));
    }
}

/**
 * Add the country field to the user profile for Claims Managers and Claims Admins.
 *
 * @param WP_User $user The current user object.
 */
function cm_add_country_field_to_profile($user) {
    // Only show this field if the user is a Claims Manager or Claims Admin.
    if (!in_array('claims_manager', (array) $user->roles, true) && !in_array('claims_admin', (array) $user->roles, true)) {
        return;
    }
    
    // Retrieve the list of countries from the plugin settings.
    $countries = get_option('cm_countries', get_default_countries());
    
    // Get the currently assigned country (if any) from user meta.
    $user_country = get_user_meta($user->ID, 'cm_user_country', true);
    ?>
    <h3><?php esc_html_e('Claims Management Country Settings', 'claims-management'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="cm_user_country"><?php esc_html_e('Country', 'claims-management'); ?></label></th>
            <td>
                <select name="cm_user_country_display" id="cm_user_country" <?php if (in_array('claims_manager', (array) $user->roles, true) && !empty($user_country)) { echo 'disabled="disabled"'; } ?>>
                    <?php
                    if (is_array($countries)) {
                        foreach ($countries as $country) {
                            ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($user_country, $country); ?>>
                                <?php echo esc_html($country); ?>
                            </option>
                            <?php
                        }
                    }
                    ?>
                </select>
                <?php
                // For Claims Managers, output a hidden field to retain the value if the select is disabled.
                if (in_array('claims_manager', (array) $user->roles, true) && !empty($user_country)) {
                    ?>
                    <input type="hidden" name="cm_user_country" value="<?php echo esc_attr($user_country); ?>">
                    <?php
                } else {
                    // For users who can edit, use the normal field name.
                    ?>
                    <input type="hidden" name="cm_user_country" value="">
                    <?php
                }
                ?>
                <p class="description"><?php esc_html_e('Assign the country for this user.', 'claims-management'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', __NAMESPACE__ . '\\cm_add_country_field_to_profile');
add_action('edit_user_profile', __NAMESPACE__ . '\\cm_add_country_field_to_profile');

/**
 * Save the country field when the user profile is updated.
 *
 * @param int $user_id The ID of the user being saved.
 */
function cm_save_country_field_from_profile($user_id) {
    // Check current user's capability to edit the user.
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    // Only save if the field is set.
    if (isset($_POST['cm_user_country']) && !empty($_POST['cm_user_country'])) {
        update_user_meta($user_id, 'cm_user_country', sanitize_text_field(wp_unslash($_POST['cm_user_country'])));
    } elseif (isset($_POST['cm_user_country_display'])) {
        // For users allowed to edit, use the value from the editable select.
        update_user_meta($user_id, 'cm_user_country', sanitize_text_field(wp_unslash($_POST['cm_user_country_display'])));
    }
}
add_action('personal_options_update', __NAMESPACE__ . '\\cm_save_country_field_from_profile');
add_action('edit_user_profile_update', __NAMESPACE__ . '\\cm_save_country_field_from_profile');

function rename_dashboard_home_submenu() {
    global $submenu;
    if (isset($submenu['index.php']) && is_array($submenu['index.php'])) {
        // Change the first submenu item (usually "Home")
        $submenu['index.php'][0][0] = 'Claims';
    }
}
add_action('admin_menu', __NAMESPACE__ . '\\rename_dashboard_home_submenu', 999);

function remove_dashboard_updates_submenu() {
    remove_submenu_page('index.php', 'update-core.php');
}
add_action('admin_menu', __NAMESPACE__ . '\\remove_dashboard_updates_submenu', 999);

/**
 * Get country code from country name
 *
 * @param string $country_name Full country name
 * @return string|false ISO 3166-1 alpha-2 country code or false if not found
 */
function cm_get_country_code($country_name) {
    $countries = [
        'Albania' => 'AL',
        'Andorra' => 'AD',
        'Austria' => 'AT',
        'Belarus' => 'BY',
        'Belgium' => 'BE',
        'Bosnia and Herzegovina' => 'BA',
        'Bulgaria' => 'BG',
        'Croatia' => 'HR',
        'Cyprus' => 'CY',
        'Czech Republic' => 'CZ',
        'Denmark' => 'DK',
        'Estonia' => 'EE',
        'Finland' => 'FI',
        'France' => 'FR',
        'Germany' => 'DE',
        'Greece' => 'GR',
        'Hungary' => 'HU',
        'Iceland' => 'IS',
        'Ireland' => 'IE',
        'Italy' => 'IT',
        'Kosovo' => 'XK',
        'Latvia' => 'LV',
        'Liechtenstein' => 'LI',
        'Lithuania' => 'LT',
        'Luxembourg' => 'LU',
        'Malta' => 'MT',
        'Moldova' => 'MD',
        'Monaco' => 'MC',
        'Montenegro' => 'ME',
        'Netherlands' => 'NL',
        'North Macedonia' => 'MK',
        'Norway' => 'NO',
        'Poland' => 'PL',
        'Portugal' => 'PT',
        'Romania' => 'RO',
        'Russia' => 'RU',
        'San Marino' => 'SM',
        'Serbia' => 'RS',
        'Slovakia' => 'SK',
        'Slovenia' => 'SI',
        'Spain' => 'ES',
        'Sweden' => 'SE',
        'Switzerland' => 'CH',
        'Turkey' => 'TR',
        'Ukraine' => 'UA',
        'United Kingdom' => 'GB',
        'Vatican City' => 'VA'
    ];

    // Try direct match
    if (isset($countries[$country_name])) {
        return $countries[$country_name];
    }

    // Try case-insensitive match
    $lower_name = strtolower($country_name);
    foreach ($countries as $name => $code) {
        if (strtolower($name) === $lower_name) {
            return $code;
        }
    }

    return false;
}

/**
 * Get flag image HTML for a country
 *
 * @param string $country_code ISO 3166-1 alpha-2 country code
 * @param string $country_name Country name for alt text
 * @return string HTML img tag with flag
 */
function cm_get_flag_img($country_code, $country_name) {
    $flag_url = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/images/flags/' . strtolower($country_code) . '.png';
    
    return sprintf(
        '<img src="%s" alt="%s" title="%s" class="country-flag" width="16" height="11" />',
        esc_url($flag_url),
        esc_attr($country_name),
        esc_attr($country_name)
    );
}

/**
 * Clean and standardize a phone number.
 *
 * If the phone number already begins with the same numeric prefix as the
 * country's dialing code (but without a plus), only a plus is prepended.
 * Otherwise, the full dialing code is prepended.
 *
 * @param string $phone The input phone number.
 * @param string $country The country name (to look up its dialing code).
 * @return string The cleaned phone number.
 */
function clean_phone_number($phone, $country) {
    // Remove leading/trailing whitespace.
    $phone = trim($phone);

    // If the phone number already starts with a '+' then assume it's good.
    if (strpos($phone, '+') === 0) {
        return '+' . preg_replace('/[^\d]/', '', substr($phone, 1));
    }

    // Get the dialing code
    $dialCode = cm_get_country_dialing_code($country);

    // Remove any non-digit characters from the phone number.
    $digits = preg_replace('/\D/', '', $phone);
    // Also remove non-digits from the dialing code (e.g. "+44" becomes "44").
    $dialNumeric = preg_replace('/\D/', '', $dialCode);

    // If the dialing code is more than one digit, compare the first two digits.
    if (strlen($dialNumeric) > 1) {
        if (substr($digits, 0, 2) === substr($dialNumeric, 0, 2)) {
            return '+' . $digits;
        }
    }
    // If the dialing code is one digit, compare the first digit.
    elseif (strlen($dialNumeric) === 1) {
        if (substr($digits, 0, 1) === $dialNumeric) {
            return '+' . $digits;
        }
    }

    // Otherwise, prepend the full dialing code.
    return $dialCode . $digits;
}

/**
 * Clean a URL to include only the scheme, host, and port (if any).
 *
 * Strips any paths, queries, or fragments from the URL.
 *
 * @param string $url The original URL.
 * @return string The cleaned URL.
 */
function clean_root_domain_url($url) {
    $url = trim($url);
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'http://' . $url;
    }
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'])) {
        return $url;
    }
    $clean_url = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $clean_url .= ':' . $parts['port'];
    }
    return $clean_url;
}

/**
 * Get country dialing code
 *
 * @param string $country_name Country name
 * @return string Dialing code with plus prefix
 */
function cm_get_country_dialing_code($country_name) {
    $dialing_codes = [
        'Albania' => '+355',
        'Andorra' => '+376',
        'Austria' => '+43',
        'Belarus' => '+375',
        'Belgium' => '+32',
        'Bosnia and Herzegovina' => '+387',
        'Bulgaria' => '+359',
        'Croatia' => '+385',
        'Cyprus' => '+357',
        'Czech Republic' => '+420',
        'Denmark' => '+45',
        'Estonia' => '+372',
        'Finland' => '+358',
        'France' => '+33',
        'Germany' => '+49',
        'Greece' => '+30',
        'Hungary' => '+36',
        'Iceland' => '+354',
        'Ireland' => '+353',
        'Italy' => '+39',
        'Kosovo' => '+383',
        'Latvia' => '+371',
        'Liechtenstein' => '+423',
        'Lithuania' => '+370',
        'Luxembourg' => '+352',
        'Malta' => '+356',
        'Malta' => '+356',
        'Moldova' => '+373',
        'Monaco' => '+377',
        'Montenegro' => '+382',
        'Netherlands' => '+31',
        'North Macedonia' => '+389',
        'Norway' => '+47',
        'Poland' => '+48',
        'Portugal' => '+351',
        'Romania' => '+40',
        'Russia' => '+7',
        'San Marino' => '+378',
        'Serbia' => '+381',
        'Slovakia' => '+421',
        'Slovenia' => '+386',
        'Spain' => '+34',
        'Sweden' => '+46',
        'Switzerland' => '+41',
        'Turkey' => '+90',
        'Ukraine' => '+380',
        'United Kingdom' => '+44',
        'Vatican City' => '+379'
    ];

    return $dialing_codes[$country_name] ?? '+1'; // Default to +1 if not found
}

/**
 * Initialize plugin settings
 *
 * @since 2025-02-19 00:14:30
 * @author DVVTEO
 */
function cm_initialize_settings() {
    // Add default settings if they don't exist
    if (false === get_option('cm_prospects')) {
        update_option('cm_prospects', []);
    }
    
    if (false === get_option('cm_import_history')) {
        update_option('cm_import_history', []);
    }
}
add_action('admin_init', __NAMESPACE__ . '\\cm_initialize_settings');

/**
 * Format phone number for display
 *
 * @param string $phone Phone number
 * @param string $country_code Country code
 * @return string Formatted phone number
 */
function format_phone_number($phone, $country_code) {
    // Remove everything except digits and plus sign
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Format based on country
    switch ($country_code) {
        case 'GB': // United Kingdom
            if (preg_match('/^\+44(\d{10})$/', $phone, $matches)) {
                return '+44 ' . substr($matches[1], 0, 4) . ' ' . substr($matches[1], 4, 3) . ' ' . substr($matches[1], 7);
            }
            break;
            
        case 'DE': // Germany
            if (preg_match('/^\+49(\d{11})$/', $phone, $matches)) {
                return '+49 ' . substr($matches[1], 0, 4) . ' ' . substr($matches[1], 4, 4) . ' ' . substr($matches[1], 8);
            }
            break;
            
        case 'FR': // France
            if (preg_match('/^\+33(\d{9})$/', $phone, $matches)) {
                return '+33 ' . substr($matches[1], 0, 3) . ' ' . substr($matches[1], 3, 2) . ' ' . 
                       substr($matches[1], 5, 2) . ' ' . substr($matches[1], 7);
            }
            break;
            
        case 'ES': // Spain
            if (preg_match('/^\+34(\d{9})$/', $phone, $matches)) {
                return '+34 ' . substr($matches[1], 0, 3) . ' ' . substr($matches[1], 3, 3) . ' ' . substr($matches[1], 6);
            }
            break;
    }
    
    // Return original number if no formatting applied
    return $phone;
}

/**
 * Get users by country assignment
 *
 * @param string $country Country name
 * @param string|array $role Optional role(s) to filter by
 * @return array Array of WP_User objects
 */
function get_users_by_country($country, $role = '') {
    $args = [
        'meta_key' => 'cm_user_country',
        'meta_value' => $country,
        'number' => -1
    ];
    
    if (!empty($role)) {
        $args['role__in'] = (array) $role;
    }
    
    return get_users($args);
}

/**
 * Log an import event
 *
 * @param array $data Import data including results
 * @return bool Success status
 */
function log_import_event($data) {
    $defaults = [
        'date' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'imported' => 0,
        'rejected' => 0,
        'countries' => [],
        'errors' => []
    ];
    
    $data = wp_parse_args($data, $defaults);
    
    $history = get_option('cm_import_history', []);
    array_unshift($history, $data);
    
    // Keep only last 100 entries
    $history = array_slice($history, 0, 100);
    
    return update_option('cm_import_history', $history);
}

/**
 * Get prospect status label
 *
 * @param string $status Status key
 * @return string Translated status label
 */
function get_prospect_status_label($status) {
    $statuses = [
        'new' => __('New', 'claims-management'),
        'contacted' => __('Contacted', 'claims-management'),
        'interested' => __('Interested', 'claims-management'),
        'not-interested' => __('Not Interested', 'claims-management'),
        'converted' => __('Converted', 'claims-management'),
        'archived' => __('Archived', 'claims-management')
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * Get prospect status color class
 *
 * @param string $status Status key
 * @return string CSS class name
 */
function get_prospect_status_class($status) {
    $classes = [
        'new' => 'status-new',
        'contacted' => 'status-contacted',
        'interested' => 'status-interested',
        'not-interested' => 'status-not-interested',
        'converted' => 'status-converted',
        'archived' => 'status-archived'
    ];
    
    return $classes[$status] ?? 'status-default';
}

/**
 * Check if a prospect can be converted to client
 *
 * @param int $prospect_id Prospect ID
 * @return bool|WP_Error True if can be converted, WP_Error if not
 */
function can_convert_prospect($prospect_id) {
    $status = get_post_meta($prospect_id, 'status', true);
    
    if ($status === 'converted') {
        return new \WP_Error('already_converted', __('This prospect has already been converted to a client.', 'claims-management'));
    }
    
    if ($status === 'archived') {
        return new \WP_Error('archived', __('Cannot convert an archived prospect.', 'claims-management'));
    }
    
    if ($status === 'not-interested') {
        return new \WP_Error('not_interested', __('Cannot convert a prospect marked as not interested.', 'claims-management'));
    }
    
    return true;
}

/**
 * Generate a unique prospect reference number
 *
 * @return string Unique reference number
 */
function generate_prospect_reference() {
    $prefix = 'PR';
    $year = date('y');
    $month = date('m');
    
    $count = wp_count_posts('cm_prospect');
    $sequence = str_pad($count->publish + 1, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $year . $month . $sequence;
}