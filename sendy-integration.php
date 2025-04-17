<?php
/**
 * Plugin Name: Sendy Integration
 * Description: Plugin Subscribe dan Unsubscribe ke Sendy dengan AJAX, notifikasi, dan logging alasan unsubscribe.
 * Version: 1.0.5
 * Author: Gio Fandi
 * Author URI: https://giofandi.my.id
 */

if (!defined('ABSPATH')) exit;

// Muat fungsi tambahan dari folder includes
require_once plugin_dir_path(__FILE__) . 'includes/plugin-meta-links.php';


// Enqueue JS and CSS
function sendy_enqueue_scripts() {
    wp_enqueue_script('sendy-js', plugin_dir_url(__FILE__) . 'assets/sendy.min.js', ['jquery'], '1.5', true);
    wp_localize_script('sendy-js', 'sendy_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'subscribe_url' => 'https://merpati.drife.co.id/subscribe',
        'unsubscribe_url' => 'https://merpati.drife.co.id/unsubscribe',
        'list_id' => get_option('sendy_list_id'),
    ]);

    wp_enqueue_style('sendy-styles', plugin_dir_url(__FILE__) . 'assets/sendy-styles.css', [], '1.5');
}
add_action('wp_enqueue_scripts', 'sendy_enqueue_scripts');

// === SUBSCRIBE SHORTCODE ===
function custom_sendy_subscribe_form_shortcode() {
    ob_start(); ?>
    <form id="custom-sendy-form" class="sendy-form">
        <p><label for="custom_name"></label><br/>
        <input type="text" placeholder="Enter Name" name="name" id="custom_name" required></p>

        <p><label for="custom_email"></label><br/>
        <input type="email" placeholder="Enter Email" name="email" id="custom_email" required></p>

        <div style="display:none;">
            <label for="custom_hp">HP</label><br/>
            <input type="text" name="hp" id="custom_hp">
        </div>

        <input type="submit" value="Subscribe" class="sendy-button"><br/>
        <div id="custom-sendy-message" class="sendy-message"></div>
    </form>
    <?php return ob_get_clean();
}
add_shortcode('sendy_subscribe_form', 'custom_sendy_subscribe_form_shortcode');

function handle_custom_sendy_subscribe_ajax() {
    $email = sanitize_email($_POST['email']);
    $name  = sanitize_text_field($_POST['name']);
    $hp    = sanitize_text_field($_POST['hp']);
    $list_id = get_option('sendy_list_id');

    if (!is_email($email)) wp_send_json_error('Email tidak valid.');
    if (!empty($hp)) wp_send_json_error('Spam terdeteksi.');
    if (empty($list_id)) wp_send_json_error('List ID belum disetel di pengaturan Sendy.');

    $response = wp_remote_post('https://merpati.drife.co.id/subscribe', [
        'body' => [
            'name' => $name,
            'email' => $email,
            'hp' => '',
            'list' => $list_id,
            'subform' => 'yes',
            'boolean' => 'true'
        ],
    ]);

    if (is_wp_error($response)) wp_send_json_error('Tidak dapat menghubungi server.');

    $body = trim(wp_remote_retrieve_body($response));

    if ($body === '1' || $body === 'true') {
        // Send notification email to admin
        if (get_option('sendy_enable_admin_notifications', 'yes') === 'yes') {
            $admin_email = get_option('sendy_admin_email', get_option('admin_email'));
            $site_name = get_bloginfo('name');
            $subject = "[$site_name] New Newsletter Subscription";
            $message = "Hello,\n\nA new user has subscribed to your newsletter.\n\n";
            $message .= "Name: $name\n";
            $message .= "Email: $email\n";
            $message .= "Date: " . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . "\n\n";
            $message .= "Regards,\n$site_name Newsletter System";
            
            wp_mail($admin_email, $subject, $message);
        }
        
        // Send welcome email to subscriber if enabled
        if (get_option('sendy_send_welcome_email', 'no') === 'yes') {
            $subject = get_option('sendy_welcome_email_subject', 'Welcome to our Newsletter!');
            $message = get_option('sendy_welcome_email_content', "Hello $name,\n\nThank you for subscribing to our newsletter!\n\nRegards,\n" . get_bloginfo('name'));
            
            wp_mail($email, $subject, $message);
        }
        
        wp_send_json_success('Berhasil berlangganan!');
    } elseif (stripos($body, 'Already subscribed') !== false) {
        wp_send_json_error('Email sudah berlangganan.');
    } elseif (stripos($body, 'Invalid email address') !== false) {
        wp_send_json_error('Email tidak valid.');
    } elseif (stripos($body, 'Some fields are missing') !== false) {
        wp_send_json_error('Beberapa field tidak terisi dengan benar.');
    } else {
        wp_send_json_error('Gagal berlangganan: ' . $body);
    }
}
add_action('wp_ajax_custom_sendy_subscribe', 'handle_custom_sendy_subscribe_ajax');
add_action('wp_ajax_nopriv_custom_sendy_subscribe', 'handle_custom_sendy_subscribe_ajax');

// === UNSUBSCRIBE FORM SHORTCODE ===
function custom_sendy_unsubscribe_form_shortcode() {
    $reasons = get_option('sendy_unsub_reasons', []);
    $reasons = is_array($reasons) ? $reasons : explode("\n", $reasons);
    $reasons = array_filter(array_map('trim', $reasons));
    
    ob_start(); ?>
    <form id="custom-sendy-unsub-form" class="sendy-form">
        <p><label for="unsub_email">Email</label><br/>
        <input type="email" name="email" id="unsub_email" required></p>

        <p><label for="unsub_reason">Reason</label><br/>
        <select name="reason" id="unsub_reason">
            <?php foreach ($reasons as $r): ?>
                <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?></option>
            <?php endforeach; ?>
            <option value="Other">Other</option>
        </select></p>

        <p id="custom_unsub_other_reason_wrap" style="display:none;">
            <label for="other_reason">Other Reason</label><br/>
            <input type="text" name="other_reason" id="other_reason">
        </p>

        <div style="display:none;">
            <label for="unsub_hp">HP</label><br/>
            <input type="text" name="hp" id="unsub_hp">
        </div>

        <input type="submit" value="Unsubscribe" class="sendy-button">
        <div id="custom-sendy-unsub-message" class="sendy-message"></div>
    </form>
    <?php return ob_get_clean();
}
add_shortcode('custom_sendy_unsubscribe', 'custom_sendy_unsubscribe_form_shortcode');


function handle_custom_sendy_unsubscribe_ajax() {
    $email = sanitize_email($_POST['email']);
    $reason = sanitize_text_field($_POST['reason']);
    $other_reason = sanitize_text_field($_POST['other_reason']);
    $hp = sanitize_text_field($_POST['hp']);
    $list_id = get_option('sendy_list_id');
    $date = current_time('mysql');

    if (!is_email($email)) wp_send_json_error('Email tidak valid.');
    if (!empty($hp)) wp_send_json_error('Spam terdeteksi.');
    if (empty($list_id)) wp_send_json_error('List ID belum disetel di pengaturan Sendy.');

    // Check if email exists in the system before unsubscribing
    $check_response = wp_remote_post('https://merpati.drife.co.id/api/subscribers/subscription-status.php', [
        'body' => [
            'email' => $email,
            'list_id' => $list_id,
            'api_key' => get_option('sendy_api_key', '') // Make sure you have an API key setting
        ],
    ]);

    if (is_wp_error($check_response)) wp_send_json_error('Tidak dapat menghubungi server.');
    
    $check_body = trim(wp_remote_retrieve_body($check_response));
    
    // If email doesn't exist or is already unsubscribed
    if ($check_body == 'Email does not exist in list' || $check_body == 'Not subscribed') {
        wp_send_json_error('Email tidak terdaftar dalam sistem kami.');
        return;
    }

    $log_reason = $reason === 'Other' ? $other_reason : $reason;

    // Only log if there's valid data
    if (!empty($email) && !empty($log_reason)) {
        // Save to database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'sendy_unsubscribe_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'email' => $email,
                'reason' => $log_reason,
                'unsubscribe_date' => $date,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    $response = wp_remote_post('https://merpati.drife.co.id/unsubscribe', [
        'body' => [
            'email' => $email,
            'list' => $list_id,
            'boolean' => 'true'
        ],
    ]);

    if (is_wp_error($response)) wp_send_json_error('Tidak dapat menghubungi server.');

    $body = trim(wp_remote_retrieve_body($response));

    // Check for various success indicators
    if ($body === 'true' || $body === '1' || $body === 'success') {
        // Send notification email to admin
        if (get_option('sendy_enable_admin_notifications', 'yes') === 'yes') {
            $admin_email = get_option('sendy_admin_email', get_option('admin_email'));
            $site_name = get_bloginfo('name');
            $subject = "[$site_name] Newsletter Unsubscription";
            $message = "Hello,\n\nA user has unsubscribed from your newsletter.\n\n";
            $message .= "Email: $email\n";
            $message .= "Reason: $log_reason\n";
            $message .= "Date: " . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . "\n\n";
            $message .= "Regards,\n$site_name Newsletter System";
            
            wp_mail($admin_email, $subject, $message);
        }
        
        // Send unsubscribe confirmation email if enabled
        if (get_option('sendy_send_unsubscribe_confirmation', 'no') === 'yes') {
            $subject = get_option('sendy_unsubscribe_email_subject', 'Unsubscription Confirmation');
            $message = get_option('sendy_unsubscribe_email_content', "Hello,\n\nThis is to confirm that you have been successfully unsubscribed from our newsletter.\n\nRegards,\n" . get_bloginfo('name'));
            
            wp_mail($email, $subject, $message);
        }
        
        wp_send_json_success('Berhasil berhenti berlangganan.');
    } elseif (strpos($body, 'Email does not exist') !== false) {
        wp_send_json_error('Email tidak terdaftar dalam sistem kami.');
    } else {
        wp_send_json_error('Gagal berhenti berlangganan. Silakan coba lagi.');
    }
}
add_action('wp_ajax_custom_sendy_unsubscribe', 'handle_custom_sendy_unsubscribe_ajax');
add_action('wp_ajax_nopriv_custom_sendy_unsubscribe', 'handle_custom_sendy_unsubscribe_ajax');

// === Create DB Tables on Plugin Activation ===
function sendy_create_db_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sendy_unsubscribe_logs';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        reason text NOT NULL,
        unsubscribe_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        ip_address varchar(100) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'sendy_create_db_tables');

// === ADMIN SETTINGS ===
function sendy_register_settings_menu() {
    add_menu_page('Sendy', 'Sendy', 'manage_options', 'sendy-settings', 'sendy_settings_page_html', 'dashicons-email', 30);
    add_submenu_page('sendy-settings', 'Settings', 'Settings', 'manage_options', 'sendy-settings', 'sendy_settings_page_html');
    add_submenu_page('sendy-settings', 'Unsubscribe Logs', 'Unsubscribe Logs', 'manage_options', 'sendy-unsub-logs', 'sendy_unsubscribe_logs_page');
    add_submenu_page('sendy-settings', 'Email Templates', 'Email Templates', 'manage_options', 'sendy-email-templates', 'sendy_email_templates_page');
}
add_action('admin_menu', 'sendy_register_settings_menu');

function sendy_register_settings() {
    register_setting('sendy_settings_group', 'sendy_list_id');
    register_setting('sendy_settings_group', 'sendy_unsub_reasons');
    register_setting('sendy_settings_group', 'sendy_api_key');
    register_setting('sendy_settings_group', 'sendy_enable_admin_notifications');
    register_setting('sendy_settings_group', 'sendy_admin_email');
    
    register_setting('sendy_email_templates_group', 'sendy_send_welcome_email');
    register_setting('sendy_email_templates_group', 'sendy_welcome_email_subject');
    register_setting('sendy_email_templates_group', 'sendy_welcome_email_content');
    register_setting('sendy_email_templates_group', 'sendy_send_unsubscribe_confirmation');
    register_setting('sendy_email_templates_group', 'sendy_unsubscribe_email_subject');
    register_setting('sendy_email_templates_group', 'sendy_unsubscribe_email_content');
}
add_action('admin_init', 'sendy_register_settings');

function sendy_settings_page_html() {
    $reasons = get_option('sendy_unsub_reasons', []);
    $reasons = is_array($reasons) ? $reasons : explode("\n", $reasons);
    $reasons = array_filter(array_map('trim', $reasons));
    
    ?>
    <div class="wrap">
        <h1>Sendy Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sendy_settings_group');
            do_settings_sections('sendy_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Sendy List ID</th>
                    <td><input type="text" name="sendy_list_id" value="<?php echo esc_attr(get_option('sendy_list_id')); ?>" class="regular-text" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Unsubscribe Reasons</th>
                    <td>
                        <textarea name="sendy_unsub_reasons" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", (array) $reasons)); ?></textarea>
                        <p class="description">Pisahkan tiap alasan dengan baris baru. Opsi 'Other' akan otomatis tersedia.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sendy API Key</th>
                    <td><input type="text" name="sendy_api_key" value="<?php echo esc_attr(get_option('sendy_api_key')); ?>" class="regular-text" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Admin Notifications</th>
                    <td>
                        <select name="sendy_enable_admin_notifications">
                            <option value="yes" <?php selected(get_option('sendy_enable_admin_notifications', 'yes'), 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected(get_option('sendy_enable_admin_notifications', 'yes'), 'no'); ?>>No</option>
                        </select>
                        <p class="description">Kirim notifikasi email ke admin saat ada yang subscribe atau unsubscribe.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Admin Email for Notifications</th>
                    <td>
                        <input type="email" name="sendy_admin_email" value="<?php echo esc_attr(get_option('sendy_admin_email', get_option('admin_email'))); ?>" class="regular-text" />
                        <p class="description">Alamat email yang akan menerima notifikasi. Default ke email admin WordPress.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Email Templates Admin Page
function sendy_email_templates_page() {
    ?>
    <div class="wrap">
        <h1>Sendy Email Templates</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sendy_email_templates_group');
            do_settings_sections('sendy_email_templates_group');
            ?>
            
            <h2 class="title">Welcome Email</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Send Welcome Email</th>
                    <td>
                        <select name="sendy_send_welcome_email">
                            <option value="yes" <?php selected(get_option('sendy_send_welcome_email', 'no'), 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected(get_option('sendy_send_welcome_email', 'no'), 'no'); ?>>No</option>
                        </select>
                        <p class="description">Kirim email selamat datang ketika seseorang berlangganan.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Subject</th>
                    <td>
                        <input type="text" name="sendy_welcome_email_subject" value="<?php echo esc_attr(get_option('sendy_welcome_email_subject', 'Welcome to our Newsletter!')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Content</th>
                    <td>
                        <textarea name="sendy_welcome_email_content" rows="10" class="large-text"><?php echo esc_textarea(get_option('sendy_welcome_email_content', "Hello {name},\n\nThank you for subscribing to our newsletter!\n\nRegards,\n" . get_bloginfo('name'))); ?></textarea>
                        <p class="description">Gunakan {name} untuk nama pelanggan.</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">Unsubscribe Confirmation Email</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Send Unsubscribe Confirmation</th>
                    <td>
                        <select name="sendy_send_unsubscribe_confirmation">
                            <option value="yes" <?php selected(get_option('sendy_send_unsubscribe_confirmation', 'no'), 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected(get_option('sendy_send_unsubscribe_confirmation', 'no'), 'no'); ?>>No</option>
                        </select>
                        <p class="description">Kirim email konfirmasi ketika seseorang berhenti berlangganan.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Subject</th>
                    <td>
                        <input type="text" name="sendy_unsubscribe_email_subject" value="<?php echo esc_attr(get_option('sendy_unsubscribe_email_subject', 'Unsubscription Confirmation')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Content</th>
                    <td>
                        <textarea name="sendy_unsubscribe_email_content" rows="10" class="large-text"><?php echo esc_textarea(get_option('sendy_unsubscribe_email_content', "Hello,\n\nThis is to confirm that you have been successfully unsubscribed from our newsletter.\n\nRegards,\n" . get_bloginfo('name'))); ?></textarea>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
// Unsubscribe Logs Dashboard Page
// Unsubscribe Logs Dashboard Page
function sendy_unsubscribe_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sendy_unsubscribe_logs';
    
    // Process delete actions
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'sendy_delete_log_' . $_GET['id'])) {
            $id = intval($_GET['id']);
            $wpdb->delete($table_name, ['id' => $id], ['%d']);
            // Show success message
            echo '<div class="notice notice-success"><p>Log entry deleted successfully.</p></div>';
        } else {
            // Show error message
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    }
    
    // Process bulk delete action
    if (isset($_POST['sendy_bulk_action']) && $_POST['sendy_bulk_action'] == 'delete' && isset($_POST['log_ids']) && isset($_POST['_wpnonce'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'sendy_bulk_delete_logs')) {
            $ids = array_map('intval', $_POST['log_ids']);
            if (!empty($ids)) {
                $ids_format = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($ids_format)",
                    $ids
                ));
                $count = count($ids);
                echo '<div class="notice notice-success"><p>' . $count . ' log ' . ($count > 1 ? 'entries' : 'entry') . ' deleted successfully.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    }
    
    // Setup pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get total count for pagination
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $total_pages = ceil($total_items / $per_page);
    
    // Get records with pagination
    $logs = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY unsubscribe_date DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        )
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Sendy Unsubscribe Logs</h1>
        
        <?php 
        // Create a nonce for security
        $export_nonce = wp_create_nonce('sendy_export_logs');
        $export_url = admin_url('admin-post.php?action=sendy_export_logs&_wpnonce=' . $export_nonce);
        ?>
        <a href="<?php echo esc_url($export_url); ?>" class="page-title-action">Export to CSV</a>
        
        <hr class="wp-header-end">
        
        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p>No unsubscribe logs found.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('sendy_bulk_delete_logs'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                        <select name="sendy_bulk_action" id="bulk-action-selector-top">
                            <option value="-1">Bulk Actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Apply">
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th>Email</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>">
                                </th>
                                <td><?php echo esc_html($log->email); ?></td>
                                <td><?php echo esc_html($log->reason); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->unsubscribe_date))); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td>
                                    <?php 
                                    $delete_nonce = wp_create_nonce('sendy_delete_log_' . $log->id);
                                    $delete_url = add_query_arg([
                                        'page' => 'sendy-unsub-logs',
                                        'action' => 'delete',
                                        'id' => $log->id,
                                        '_wpnonce' => $delete_nonce
                                    ], admin_url('admin.php'));
                                    ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="sendy-delete-log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-2">
                            </td>
                            <th>Email</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </tfoot>
                </table>
            </form>
            
            <?php
            // Pagination links
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page,
            ]);
            echo '</div>';
            echo '</div>';
            ?>
        <?php endif; ?>
    </div>
    <?php
}
// Register CSV export action
function sendy_register_export_handler() {
    add_action('admin_post_sendy_export_logs', 'sendy_export_unsubscribe_logs_csv');
}
add_action('admin_init', 'sendy_register_export_handler');

// Improved CSV export function
function sendy_export_unsubscribe_logs_csv() {
    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to export data.');
    }
    
    // Verify nonce
    check_admin_referer('sendy_export_logs');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sendy_unsubscribe_logs';
    
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY unsubscribe_date DESC");
    
    if (empty($logs)) {
        wp_redirect(admin_url('admin.php?page=sendy-unsub-logs&error=no_data'));
        exit;
    }
    
    // Ensure no output has happened yet
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sendy-unsubscribe-logs-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 CSV in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Add CSV headers
    fputcsv($output, ['Email', 'Reason', 'Date', 'IP Address']);
    
    // Add data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log->email,
            $log->reason,
            $log->unsubscribe_date,
            $log->ip_address
        ]);
    }
    
    fclose($output);
    exit;
}

// Add script for unsubscribe form
function sendy_admin_scripts() {
    wp_enqueue_script('sendy-admin-js', plugin_dir_url(__FILE__) . 'assets/sendy-admin.js', ['jquery'], '1.5', true);
    wp_enqueue_style('sendy-admin-css', plugin_dir_url(__FILE__) . 'assets/sendy-admin.css', [], '1.5');
}
add_action('admin_enqueue_scripts', 'sendy_admin_scripts');
