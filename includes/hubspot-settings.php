<?php

class HubSpot_WC_Settings {

    public static function register_settings() {
        register_setting('hubspot_wc_settings', 'hubspot_client_id');
        register_setting('hubspot_wc_settings', 'hubspot_client_secret');
        register_setting('hubspot_wc_settings', 'hubspot_connected');
        register_setting('hubspot_wc_settings', 'hubspot_auto_create_deal');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_online');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_manual');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_sync_enabled');
        register_setting('hubspot_wc_settings', 'hubspot_status_stage_mapping');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_online');
    }

    public static function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'authentication';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HubSpot WooCommerce Sync', 'hub-woo-sync'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hubspot-woocommerce-sync&tab=authentication" class="nav-tab <?php echo self::get_active_tab('authentication'); ?>"><?php esc_html_e('HubSpot Setup', 'hub-woo-sync'); ?></a>
                <a href="?page=hubspot-woocommerce-sync&tab=woocommerce" class="nav-tab <?php echo self::get_active_tab('woocommerce'); ?>"><?php esc_html_e('Pipelines', 'hub-woo-sync'); ?></a>
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

    private static function render_authentication_settings() {
        $auth_url = get_site_url() . "/wp-json/hubspot/v1/start-auth";
        ?>
        <h3><?php esc_html_e('HubSpot Authentication & Setup', 'hub-woo-sync'); ?></h3>
        <p><?php esc_html_e('Status:', 'hub-woo-sync'); ?> <span id="hubspot-connection-status" style="color: red;"><?php esc_html_e('Checking...', 'hub-woo-sync'); ?></span></p>
        <div id="hubspot-account-info">
            <ul>
                <li><strong><?php esc_html_e('Portal ID:', 'hub-woo-sync'); ?></strong> <span id="portal-id">...</span></li>
                <li><strong><?php esc_html_e('Account Type:', 'hub-woo-sync'); ?></strong> <span id="account-type">...</span></li>
                <li><strong><?php esc_html_e('Time Zone:', 'hub-woo-sync'); ?></strong> <span id="time-zone">...</span></li>
                <li><strong><?php esc_html_e('Company Currency:', 'hub-woo-sync'); ?></strong> <span id="company-currency">...</span></li>
                <li><strong><?php esc_html_e('Data Hosting Location:', 'hub-woo-sync'); ?></strong> <span id="data-hosting">...</span></li>
                <li><strong><?php esc_html_e('Access Token:', 'hub-woo-sync'); ?></strong> <span id="access-token">...</span></li>
            </ul>
        </div>
        <a href="<?php echo esc_url($auth_url); ?>" class="button-primary" id="hubspot-auth-button"><?php esc_html_e('Connect HubSpot', 'hub-woo-sync'); ?></a>
        <script>
        jQuery(function($){
            function checkHubSpotConnection() {
                $.post(ajaxurl, { action: 'hubspot_check_connection' }, function(response) {
                    if (typeof response === 'string') {
                        try { response = JSON.parse(response); } catch (e) {}
                    }
                    if (response.connected === 'yes') {
                        $('#hubspot-connection-status').html('<span style="color: green;">Connected</span>');
                        $('#portal-id').text(response.account_info["Portal ID"]);
                        $('#account-type').text(response.account_info["Account Type"]);
                        $('#time-zone').text(response.account_info["Time Zone"]);
                        $('#company-currency').text(response.account_info["Company Currency"]);
                        $('#data-hosting').text(response.account_info["Data Hosting Location"]);
                        $('#access-token').text(response.account_info["Access Token (truncated)"]);
                    } else {
                        $('#hubspot-connection-status').text('Not Connected');
                    }
                });
            }
            checkHubSpotConnection();
        });
        </script>
        <?php
    }

    private static function render_woocommerce_settings() {
        echo '<h3>' . esc_html__('HubSpot Pipelines Settings', 'hub-woo-sync') . '</h3>';
        echo '<p>' . esc_html__('Pipelines and stage mapping settings will appear here.', 'hub-woo-sync') . '</p>';
        // TODO: implement real rendering logic if needed
    }

    public static function get_active_tab($tab) {
        return ($_GET['tab'] ?? 'authentication') === $tab ? 'nav-tab-active' : '';
    }

    public static function maybe_refresh_cache_on_save() {
        if (isset($_GET['page'], $_GET['settings-updated']) && $_GET['page'] === 'hubspot-woocommerce-sync') {
            self::refresh_pipeline_cache();
        }
    }

    private static function refresh_pipeline_cache() {
        $pipelines = self::get_hubspot_pipelines();
        if (!empty($pipelines)) {
            update_option('hubspot_cached_pipelines', $pipelines);
        }
    }

    private static function get_hubspot_pipelines() {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}hubspot_tokens LIMIT 1", ARRAY_A);
        if (!$row || empty($row['access_token'])) return [];

        $response = wp_remote_get('https://api.hubapi.com/crm/v3/pipelines/deals', [
            'headers' => [
                'Authorization' => 'Bearer ' . $row['access_token'],
                'Content-Type'  => 'application/json',
            ]
        ]);
        if (is_wp_error($response)) return [];

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $results = $body['results'] ?? [];

        $pipelines = [];
        foreach ($results as $pipeline) {
            if (!empty($pipeline['id']) && !empty($pipeline['label'])) {
                $stages_map = [];
                if (!empty($pipeline['stages'])) {
                    foreach ($pipeline['stages'] as $stage) {
                        if (!empty($stage['id']) && !empty($stage['label'])) {
                            $stages_map[$stage['id']] = $stage['label'];
                        }
                    }
                }
                $pipelines[$pipeline['id']] = [
                    'label'  => $pipeline['label'],
                    'stages' => $stages_map
                ];
            }
        }

        return $pipelines;
    }

    public static function hubspot_check_connection() {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}hubspot_tokens LIMIT 1", ARRAY_A);
        if (!$row || empty($row['access_token'])) {
            echo json_encode(['connected' => 'no', 'account_info' => []]);
            wp_die();
        }

        $response = wp_remote_get('https://api.hubapi.com/account-info/v3/details', [
            'headers' => [
                'Authorization' => 'Bearer ' . $row['access_token'],
                'Content-Type'  => 'application/json'
            ]
        ]);
        if (is_wp_error($response)) {
            echo json_encode(['connected' => 'no', 'account_info' => []]);
            wp_die();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        echo json_encode([
            'connected' => 'yes',
            'account_info' => [
                'Portal ID' => $row['portal_id'] ?? 'Unknown',
                'Account Type' => $body['accountType'] ?? 'Unknown',
                'Time Zone' => $body['timeZone'] ?? 'Unknown',
                'Company Currency' => $body['companyCurrency'] ?? 'Unknown',
                'Data Hosting Location' => $body['dataHostingLocation'] ?? 'Unknown',
                'Access Token (truncated)' => substr($row['access_token'], 0, 10) . '...'
            ]
        ]);
        wp_die();
    }

    /**
     * Hook for order status changes to auto-sync deal stage.
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status) {
        if (!get_option('hubspot_pipeline_sync_enabled')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) return;

        $is_manual = get_post_meta($order_id, 'order_type', true) === 'manual';
        $status_key = ($is_manual ? 'manual_wc' : 'online_wc') . '-' . $new_status;

        if (function_exists('sync_order_to_hubspot_deal_stage')) {
            sync_order_to_hubspot_deal_stage($order, $status_key);
        }
    }
}

HubSpot_WC_Settings::init();
