<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HubSpot_WC_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_hubspot_check_connection', [__CLASS__, 'hubspot_check_connection']);
    }

    /**
     * Register HubSpot settings page in WordPress Admin
     */
    public static function register_menu() {
        add_menu_page(
            'HubSpot Settings',
            'HubSpot Sync',
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic',
            56
        );
    }

    /**
     * Register settings for HubSpot authentication & WooCommerce sync options
     */
    public static function register_settings() {
        register_setting('hubspot_wc_settings', 'hubspot_connected');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_id');
        register_setting('hubspot_wc_settings', 'hubspot_auto_create_deal');
    }

    /**
     * Render the settings page
     */
    public static function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'authentication';
        ?>
        <div class="wrap">
            <h1>HubSpot WooCommerce Sync</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hubspot-woocommerce-sync&tab=authentication" class="nav-tab <?php echo self::get_active_tab('authentication'); ?>">HubSpot Setup</a>
                <a href="?page=hubspot-woocommerce-sync&tab=woocommerce" class="nav-tab <?php echo self::get_active_tab('woocommerce'); ?>">WooCommerce Settings</a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('hubspot_wc_settings');
                do_settings_sections('hubspot_wc_settings');

                if ($active_tab === 'authentication') {
                    self::render_authentication_settings();
                } elseif ($active_tab === 'woocommerce') {
                    self::render_woocommerce_settings();
                }

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get active tab class
     */
    private static function get_active_tab($tab) {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'authentication';
        return ($active_tab === $tab) ? 'nav-tab-active' : '';
    }

    /**
     * Render HubSpot Authentication Tab
     */
    private static function render_authentication_settings() {
        $auth_url = "https://weblifter.com.au/wp-json/hubspot/v1/start-auth?store_url=" . urlencode(get_site_url());
        ?>
        <h3>HubSpot Authentication & Setup</h3>
        <p>Status: <span id="hubspot-connection-status" style="color: red;">Checking...</span></p>
        <a href="<?php echo esc_url($auth_url); ?>" class="button-primary" id="hubspot-auth-button">
            Connect HubSpot
        </a>

        <script>
        jQuery(document).ready(function($) {
            function checkHubSpotConnection() {
                $.post(ajaxurl, { action: 'hubspot_check_connection' }, function(response) {
                    if (response === 'yes') {
                        $('#hubspot-connection-status').html('<span style="color: green;">Connected</span>');
                        $('#hubspot-auth-button').text('Reconnect HubSpot');
                    } else {
                        $('#hubspot-connection-status').html('<span style="color: red;">Not Connected</span>');
                    }
                });
            }

            checkHubSpotConnection();
            setInterval(checkHubSpotConnection, 5000);
        });
        </script>
        <?php
    }

    /**
     * Fetch available HubSpot pipelines dynamically
     */
    private static function get_hubspot_pipelines() {
        $store_url = get_site_url();
        $access_token = HubSpot_WC_Auth::get_access_token($store_url);

        if (!$access_token) {
            return ['error' => 'HubSpot not authenticated'];
        }

        $response = wp_remote_get('https://api.hubapi.com/crm/v3/pipelines/deals', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch pipelines: ' . $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['results']) || !is_array($body['results'])) {
            return ['error' => 'Invalid API response'];
        }

        $pipelines = [];
        foreach ($body['results'] as $pipeline) {
            if (!isset($pipeline['id'], $pipeline['label'])) continue;
            $pipelines[$pipeline['id']] = $pipeline['label'];
        }

        return $pipelines;
    }

    /**
     * Render WooCommerce Settings Tab
     */
    private static function render_woocommerce_settings() {
        $pipelines = self::get_hubspot_pipelines();
        ?>
        <h3>WooCommerce Integration</h3>
        <table class="form-table">
            <tr>
                <th><label for="hubspot_auto_create_deal">Automatically Create Deals</label></th>
                <td>
                    <input type="checkbox" name="hubspot_auto_create_deal" value="yes" <?php checked(get_option('hubspot_auto_create_deal'), 'yes'); ?>>
                    <span>Enable automatic HubSpot deal creation for new orders</span>
                </td>
            </tr>
            <tr>
                <th><label for="hubspot_pipeline_id">Select HubSpot Pipeline</label></th>
                <td>
                    <select name="hubspot_pipeline_id">
                        <?php if (isset($pipelines['error'])): ?>
                            <option value=""><?php echo esc_html($pipelines['error']); ?></option>
                        <?php else: ?>
                            <?php foreach ($pipelines as $id => $label): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected(get_option('hubspot_pipeline_id'), $id); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p>Select the pipeline where new deals will be created.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * AJAX Handler: Check Connection Status
     */
    public static function hubspot_check_connection() {
        echo get_option('hubspot_connected', 'no');
        wp_die();
    }
}

// Initialize settings page
HubSpot_WC_Settings::init();
