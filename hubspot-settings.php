        add_menu_page(
            __( 'HubSpot Settings', 'hub-woo-sync' ),
            __( 'HubSpot', 'hub-woo-sync' ),
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic',
            56
        <div class="wrap">
            <h1><?php esc_html_e( 'HubSpot WooCommerce Sync', 'hub-woo-sync' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hubspot-woocommerce-sync&tab=authentication" class="nav-tab <?php echo self::get_active_tab('authentication'); ?>"><?php esc_html_e( 'HubSpot Setup', 'hub-woo-sync' ); ?></a>
                <a href="?page=hubspot-woocommerce-sync&tab=woocommerce" class="nav-tab <?php echo self::get_active_tab('woocommerce'); ?>"><?php esc_html_e( 'Pipelines', 'hub-woo-sync' ); ?></a>
            </h2>
        <h3><?php esc_html_e( 'HubSpot Authentication & Setup', 'hub-woo-sync' ); ?></h3>
        <p><?php esc_html_e( 'Status', 'hub-woo-sync' ); ?>: <span id="hubspot-connection-status" style="color: red;"><?php esc_html_e( 'Checking...', 'hub-woo-sync' ); ?></span></p>
        <div id="hubspot-account-info">
            <p><strong><?php esc_html_e( 'HubSpot Account Details:', 'hub-woo-sync' ); ?></strong></p>
            <ul>
                <li><strong><?php esc_html_e( 'Portal ID:', 'hub-woo-sync' ); ?></strong> <span id="portal-id"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
                <li><strong><?php esc_html_e( 'Account Type:', 'hub-woo-sync' ); ?></strong> <span id="account-type"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
                <li><strong><?php esc_html_e( 'Time Zone:', 'hub-woo-sync' ); ?></strong> <span id="time-zone"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
                <li><strong><?php esc_html_e( 'Company Currency:', 'hub-woo-sync' ); ?></strong> <span id="company-currency"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
                <li><strong><?php esc_html_e( 'Data Hosting Location:', 'hub-woo-sync' ); ?></strong> <span id="data-hosting"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
                <li><strong><?php esc_html_e( 'Access Token:', 'hub-woo-sync' ); ?></strong> <span id="access-token"><?php esc_html_e( 'Fetching...', 'hub-woo-sync' ); ?></span></li>
            </ul>
        </div>
        <a href="<?php echo esc_url($auth_url); ?>" class="button-primary" id="hubspot-auth-button">
            <?php esc_html_e( 'Connect HubSpot', 'hub-woo-sync' ); ?>
        </a>
    }

    /**
    * Register HubSpot settings page in WordPress Admin
    */
    public static function register_menu() {
        add_menu_page(
            'HubSpot Settings',
            'HubSpot',
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
        <h3>HubSpot Authentication & Setup</h3>
        <p>Status: <span id="hubspot-connection-status" style="color: red;">Checking...</span></p>
        <table class="form-table">
            <tr>
                <th><label for="hubspot_client_id">Client ID</label></th>
                <td><input type="text" id="hubspot_client_id" name="hubspot_client_id" value="<?php echo esc_attr( get_option('hubspot_client_id') ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="hubspot_client_secret">Client Secret</label></th>
                <td><input type="text" id="hubspot_client_secret" name="hubspot_client_secret" value="<?php echo esc_attr( get_option('hubspot_client_secret') ); ?>" class="regular-text" /></td>
            </tr>
        </table>
        register_setting('hubspot_wc_settings', 'hubspot_auto_create_deal');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_online');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_manual');
        register_setting('hubspot_wc_settings', 'hubspot_status_stage_mapping');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_sync_enabled');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_online');

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
                <a href="?page=hubspot-woocommerce-sync&tab=woocommerce" class="nav-tab <?php echo self::get_active_tab('woocommerce'); ?>">Pipelines</a>
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
        $auth_url = get_site_url() . "/wp-json/hubspot/v1/start-auth";
        ?>
        <h3>HubSpot Authentication & Setup</h3>
        <p>Status: <span id="hubspot-connection-status" style="color: red;">Checking...</span></p>
        <div id="hubspot-account-info">
            <p><strong>HubSpot Account Details:</strong></p>
            <ul>
                <li><strong>Portal ID:</strong> <span id="portal-id">Fetching...</span></li>
                <li><strong>Account Type:</strong> <span id="account-type">Fetching...</span></li>
                <li><strong>Time Zone:</strong> <span id="time-zone">Fetching...</span></li>
                <li><strong>Company Currency:</strong> <span id="company-currency">Fetching...</span></li>
                <li><strong>Data Hosting Location:</strong> <span id="data-hosting">Fetching...</span></li>
                <li><strong>Access Token:</strong> <span id="access-token">Fetching...</span></li>
            </ul>
        </div>
        <a href="<?php echo esc_url($auth_url); ?>" class="button-primary" id="hubspot-auth-button">
            Connect HubSpot
        </a>

        <script>
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

        jQuery(document).ready(function($) {
            function checkHubSpotConnection() {
                console.log("🔍 Checking HubSpot Connection...");

                $.post(ajaxurl, { action: 'hubspot_check_connection' }, function(response) {
                    console.log("✅ AJAX Response:", response);

                    try {
                        response = JSON.parse(response);

                        if (response.connected === 'yes') {
                            $('#hubspot-connection-status').html('<span style="color: green;">Connected</span>');
                            $('#hubspot-auth-button').text('Reconnect HubSpot');

                            let accountInfo = response.account_info;

                            $('#portal-id').text(accountInfo["Portal ID"]);
                            $('#account-type').text(accountInfo["Account Type"]);
                            $('#time-zone').text(accountInfo["Time Zone"]);
                            $('#company-currency').text(accountInfo["Company Currency"]);
                            $('#data-hosting').text(accountInfo["Data Hosting Location"]);
                            $('#access-token').text(accountInfo["Access Token (truncated)"]);
                        } else {
                            $('#hubspot-connection-status').html('<span style="color: red;">Not Connected</span>');
                            $('#hubspot-account-info ul').html('<li>No account linked</li>');        <h3><?php esc_html_e( 'Hubspot Pipelines', 'hub-woo-sync' ); ?></h3>
                <th><label for="hubspot_pipeline_sync_enabled"><?php esc_html_e( 'Enable Pipeline Sync', 'hub-woo-sync' ); ?></label></th>
                    <span><?php esc_html_e( 'Enable automatic syncing of WooCommerce orders to HubSpot pipeline stages', 'hub-woo-sync' ); ?></span>
                <th><label for="hubspot_pipeline_online"><?php esc_html_e( 'Online Orders Pipeline', 'hub-woo-sync' ); ?></label></th>
                    <p><?php esc_html_e( 'Pipeline for customer-initiated online orders.', 'hub-woo-sync' ); ?></p>
                <th><label for="hubspot_pipeline_manual"><?php esc_html_e( 'Manual Orders Pipeline', 'hub-woo-sync' ); ?></label></th>
                    <p><?php esc_html_e( 'Pipeline for admin-created manual orders (e.g., after a customer enquiry).', 'hub-woo-sync' ); ?></p>
                                <option value=""><?php esc_html_e( '— Select Stage —', 'hub-woo-sync' ); ?></option>
        <h4><?php esc_html_e( 'Quote & Invoice Stage Mapping', 'hub-woo-sync' ); ?></h4>
        $quote_invoice_stage_fields = [
            'hubspot_stage_quote_sent'     => __( 'Quote Sent Stage', 'hub-woo-sync' ),
            'hubspot_stage_quote_accepted' => __( 'Quote Accepted Stage', 'hub-woo-sync' ),
            'hubspot_stage_invoice_sent'   => __( 'Invoice Sent Stage', 'hub-woo-sync' ),
        ];
            <h5><?php echo esc_html( ucfirst( $type ) ); ?> <?php esc_html_e( 'Orders', 'hub-woo-sync' ); ?></h5>
                                <option value=""><?php esc_html_e( '— Select Stage —', 'hub-woo-sync' ); ?></option>

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
        global $wpdb;
        $table_name = $wpdb->prefix . "hubspot_tokens";

        // Ensure token is valid before making API requests
        check_and_refresh_hubspot_token();

        $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
        if (!$token_data || empty($token_data['access_token'])) {
            error_log("[HubSpot OAuth] ❌ No valid access token available.");
            return ['error' => 'HubSpot not authenticated'];
        }

        $access_token = $token_data['access_token'];

        error_log("[HubSpot OAuth] 🔍 Fetching pipelines with token: " . substr($access_token, 0, 10) . "...");

        // Make API request to fetch pipelines
        $response = wp_remote_get('https://api.hubapi.com/crm/v3/pipelines/deals', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ]
        ]);

        $http_code = wp_remote_retrieve_response_code($response);
        error_log("[HubSpot OAuth] 🔍 Pipelines API HTTP Status Code: " . $http_code);

        if (is_wp_error($response)) {
            error_log("[HubSpot OAuth] ❌ API request failed: " . $response->get_error_message());
            return ['error' => 'Failed to fetch pipelines: ' . $response->get_error_message()];
        }    public static function hubspot_check_connection() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . "hubspot_tokens";
        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log("[HubSpot OAuth] 🔍 Pipelines API Response: " . print_r($body, true));

        if (!isset($body['results']) || !is_array($body['results'])) {
            error_log("[HubSpot OAuth] ❌ Invalid API response: 'results' field missing.");
            return ['error' => 'Invalid API response'];
        }

        $pipelines = [];
        foreach ($body['results'] as $pipeline) {
            if (!isset($pipeline['id'], $pipeline['label'])) continue;
            $pipelines[$pipeline['id']] = $pipeline['label'];
        }

        error_log("[HubSpot OAuth] ✅ Pipelines fetched successfully: " . print_r($pipelines, true));

        return $pipelines;
    }

    private static function get_pipeline_stages($pipeline_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "hubspot_tokens";

        // Ensure token is valid before making API requests
        check_and_refresh_hubspot_token();

        $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
        if (!$token_data || empty($token_data['access_token'])) {
            error_log("[HubSpot OAuth] ❌ No valid access token available.");
            return [];
        }

        $access_token = $token_data['access_token'];
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("[HubSpot OAuth] ❌ API request failed: " . $response->get_error_message());
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['stages']) || !is_array($body['stages'])) {
            error_log("[HubSpot OAuth] ❌ Invalid API response: 'stages' field missing.");
            return [];
        }

        $stages = [];
        foreach ($body['stages'] as $stage) {
            if (!isset($stage['id'], $stage['label'])) continue;
            $stages[$stage['id']] = $stage['label'];
        }

        return $stages;
    }

    /**
     * Fetch pipelines and stages and cache them in an option
     */
    public static function refresh_pipeline_cache() {
        $pipelines = self::get_hubspot_pipelines();

        if (!is_array($pipelines) || isset($pipelines['error'])) {
            return;
        }

        $cache = [];
        foreach ($pipelines as $id => $label) {
            $cache[$id] = [
                'id'     => $id,
                'label'  => $label,
                'stages' => self::get_pipeline_stages($id)
            ];
        }

        update_option('hubspot_cached_pipelines', $cache);
    }

    /**
     * Refresh cache after settings are saved
     */
    public static function maybe_refresh_cache_on_save() {
        if (isset($_GET['page'], $_GET['settings-updated']) &&
            $_GET['page'] === 'hubspot-woocommerce-sync' &&
            current_user_can('manage_options')) {
            self::refresh_pipeline_cache();
        }
    }


    private static function render_woocommerce_settings() {
        $pipelines = self::get_hubspot_pipelines();
        $selected_online = get_option('hubspot_pipeline_online');
        $selected_manual = get_option('hubspot_pipeline_manual');
        $status_stage_mapping = get_option('hubspot_status_stage_mapping', []);
        $wc_statuses = wc_get_order_statuses();
        $sync_enabled = get_option('hubspot_pipeline_sync_enabled') === 'yes';

        $online_stages = self::get_pipeline_stages($selected_online);
        $manual_stages = self::get_pipeline_stages($selected_manual);
        ?>

        <h3>Hubspot Pipelines</h3>

        <table class="form-table">
            <tr>
                <th><label for="hubspot_pipeline_sync_enabled">Enable Pipeline Sync</label></th>
                <td>
                    <input type="checkbox" name="hubspot_pipeline_sync_enabled" value="yes" <?php checked($sync_enabled, true); ?>>
                    <span>Enable automatic syncing of WooCommerce orders to HubSpot pipeline stages</span>
                </td>
            </tr>
            <tr>
                <th><label for="hubspot_pipeline_online">Online Orders Pipeline</label></th>
                <td>
                    <select name="hubspot_pipeline_online">
                        <?php foreach ($pipelines as $id => $label): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_online, $id); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p>Pipeline for customer-initiated online orders.</p>
                </td>
            </tr>
            <tr>
                <th><label for="hubspot_pipeline_manual">Manual Orders Pipeline</label></th>
                <td>
                    <select name="hubspot_pipeline_manual">
                        <?php foreach ($pipelines as $id => $label): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_manual, $id); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p>Pipeline for admin-created manual orders (e.g., after a customer enquiry).</p>
                </td>
            </tr>
        </table>

        <h4>Order Status to Pipeline Stage Mapping</h4>
        <p>Configure how WooCommerce order statuses correspond to HubSpot pipeline stages. Separate mappings are supported for online and manual orders.</p>

        <?php
        $order_types = [
            'online' => ['label' => 'Online Orders', 'stages' => $online_stages],
            'manual' => ['label' => 'Manual Orders', 'stages' => $manual_stages]
        ];

        foreach ($order_types as $type_key => $data): ?>
            <h5><?php echo esc_html($data['label']); ?></h5>
            <table class="form-table">
                <?php foreach ($wc_statuses as $slug => $label):
                    $map_key = "{$type_key}_{$slug}";
                    $mapped_value = $status_stage_mapping[$map_key] ?? ''; ?>
                    <tr>
                        <th><label><?php echo esc_html($label); ?></label></th>
                        <td>
                            <select name="hubspot_status_stage_mapping[<?php echo esc_attr($map_key); ?>]">
                                <option value="">— Select Stage —</option>
                                <?php foreach ($data['stages'] as $stage_id => $stage_label): ?>
                                    <option value="<?php echo esc_attr($stage_id); ?>" <?php selected($mapped_value, $stage_id); ?>>
                                        <?php echo esc_html($stage_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>

        <h4>Quote & Invoice Stage Mapping</h4>

        <?php
        $quote_invoice_stage_fields = [
            'hubspot_stage_quote_sent'      => 'Quote Sent Stage',
            'hubspot_stage_quote_accepted'  => 'Quote Accepted Stage',
            'hubspot_stage_invoice_sent'    => 'Invoice Sent Stage',
        ];

        foreach (['manual' => $manual_stages, 'online' => $online_stages] as $type => $stages): ?>
            <h5><?php echo ucfirst($type); ?> Orders</h5>
            <table class="form-table">
                <?php foreach ($quote_invoice_stage_fields as $field_key => $label):
                    $option_key = "{$field_key}_{$type}";
                    $selected_value = get_option($option_key); ?>
                    <tr>
                        <th><label for="<?php echo esc_attr($option_key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <select name="<?php echo esc_attr($option_key); ?>">
                                <option value="">— Select Stage —</option>
                                <?php foreach ($stages as $stage_id => $stage_label): ?>
                                    <option value="<?php echo esc_attr($stage_id); ?>" <?php selected($selected_value, $stage_id); ?>>
                                        <?php echo esc_html($stage_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach;
    }





    /**
    * AJAX Handler: Check Connection Status and Get Account Info
    */
    public static function hubspot_check_connection() {
        global $wpdb;
        $table_name = $wpdb->prefix . "hubspot_tokens";

        error_log("[HubSpot OAuth] 🔍 Checking connection...");

        // Get stored access token
        $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
        
        if (!$token_data || empty($token_data['access_token'])) {
            error_log("[HubSpot OAuth] ❌ No token found in database.");
            echo json_encode(['connected' => 'no', 'portal_id' => '', 'account_info' => 'No data available']);
            wp_die();
        }

        $access_token = $token_data['access_token'];
        $portal_id = $token_data['portal_id'] ?? 'Unknown';

        error_log("[HubSpot OAuth] ✅ Token found: " . substr($access_token, 0, 10) . "...");

        // Fetch HubSpot account details
        $response = wp_remote_get("https://api.hubapi.com/account-info/v3/details", [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ]
        ]);

        // Log HTTP status code
        $http_code = wp_remote_retrieve_response_code($response);
        error_log("[HubSpot OAuth] 🔍 HTTP Status Code: " . $http_code);

        if (is_wp_error($response)) {
            error_log("[HubSpot OAuth] ❌ API request failed: " . $response->get_error_message());
            echo json_encode([
                'connected' => 'yes',
                'portal_id' => $portal_id,
                'account_info' => 'API request failed'
            ]);
            wp_die();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Log full API response for debugging
        error_log("[HubSpot OAuth] 🔍 HubSpot API Response: " . print_r($body, true));

        // Extract available account information
        $account_info = [
            "Portal ID" => $portal_id,
            "Account Type" => $body['accountType'] ?? 'Unknown',
            "Time Zone" => $body['timeZone'] ?? 'Unknown',
            "Company Currency" => $body['companyCurrency'] ?? 'Unknown',
            "Access Token (truncated)" => substr($access_token, 0, 10) . "...",
            "Data Hosting Location" => $body['dataHostingLocation'] ?? 'Unknown'
        ];

        error_log("[HubSpot OAuth] ✅ Account Information Retrieved: " . print_r($account_info, true));

        echo json_encode([
            'connected' => 'yes',
            'portal_id' => $portal_id,
            'account_info' => $account_info
        ]);
        wp_die();
    }

}

// Ensure AJAX action is registered
add_action('wp_ajax_hubspot_check_connection', ['HubSpot_WC_Settings', 'hubspot_check_connection']);

// Initialize settings page
HubSpot_WC_Settings::init();
