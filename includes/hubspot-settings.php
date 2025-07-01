<?php

class HubSpot_WC_Settings {

    /**
     * Initialize hooks for settings and admin pages.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_refresh_cache_on_save']);

        add_action('wp_ajax_hubspot_check_connection', [__CLASS__, 'hubspot_check_connection']);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 10, 3);
    }

    public static function register_settings() {
        // Authentication related settings.
        register_setting('hubspot_wc_auth', 'hubspot_connected');
        register_setting('hubspot_wc_auth', 'hubspot_auto_create_deal', [
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);

        // Pipeline and stage mapping settings.
        register_setting(
            'hubspot_wc_pipelines',
            'hubspot_pipeline_online',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_pipeline_online'],
            ]
        );
        register_setting(
            'hubspot_wc_pipelines',
            'hubspot_pipeline_manual',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_pipeline_manual'],
            ]
        );
        register_setting('hubspot_wc_pipelines', 'hubspot_pipeline_sync_enabled', [
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
        register_setting('hubspot_wc_pipelines','hubspot-online-deal-stages');
        register_setting('hubspot_wc_pipelines','hubspot-manual-deal-stages');
        register_setting('hubspot_wc_pipelines','hubspot-online-mapping', [
            'sanitize_callback' => [__CLASS__, 'sanitize_online_mapping'],
        ]);
        register_setting('hubspot_wc_pipelines','hubspot-manual-mapping', [
            'sanitize_callback' => [__CLASS__, 'sanitize_manual_mapping'],
        ]);
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_quote_sent_manual');
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_quote_sent_online');
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_quote_accepted_manual');
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_quote_accepted_online');
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_invoice_sent_manual');
        register_setting('hubspot_wc_pipelines', 'hubspot_stage_invoice_sent_online');

        // Order settings.
        register_setting('hubspot_wc_orders', 'hubspot_autocomplete_online_order', [
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
        register_setting('hubspot_wc_orders', 'hubspot_order_cleanup_status');
        register_setting('hubspot_wc_orders', 'hubspot_order_cleanup_days');
    }

    /**
     * Register admin menu pages for the plugin.
     */
    public static function register_menu() {
        add_menu_page(
            __('HubSpot Settings', 'hubspot-woocommerce-sync'),
            __('HubSpot', 'hubspot-woocommerce-sync'),
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic',
            56
        );

        add_submenu_page(
            'hubspot-woocommerce-sync',
            __('Settings', 'hubspot-woocommerce-sync'),
            __('Settings', 'hubspot-woocommerce-sync'),
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page']
        );

        add_submenu_page(
            'hubspot-woocommerce-sync',
            __('Order Management', 'hubspot-woocommerce-sync'),
            __('Order Management', 'hubspot-woocommerce-sync'),
            'manage_woocommerce',
            'hubspot-order-management',
            [__CLASS__, 'render_order_management_page']
        );
    }

    /**
     * Wrapper to render the order management page.
     */
    public static function render_order_management_page() {
        if (function_exists('render_hubspot_orders_page_table_only')) {
            render_hubspot_orders_page_table_only();
        }
    }

    public static function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'authentication';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HubSpot WooCommerce Sync', 'hub-woo-sync'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hubspot-woocommerce-sync&tab=authentication" class="nav-tab <?php echo self::get_active_tab('authentication'); ?>"><?php esc_html_e('HubSpot Setup', 'hub-woo-sync'); ?></a>
                <a href="?page=hubspot-woocommerce-sync&tab=woocommerce" class="nav-tab <?php echo self::get_active_tab('woocommerce'); ?>"><?php esc_html_e('Pipelines', 'hub-woo-sync'); ?></a>
                <a href="?page=hubspot-woocommerce-sync&tab=orders" class="nav-tab <?php echo self::get_active_tab('orders'); ?>"><?php esc_html_e('Orders', 'hub-woo-sync'); ?></a>
            </h2>

            <?php
            $option_group = 'hubspot_wc_auth';
            if ($active_tab === 'woocommerce') {
                $option_group = 'hubspot_wc_pipelines';
            } elseif ($active_tab === 'orders') {
                $option_group = 'hubspot_wc_orders';
            }
            ?>

            <form method="post" action="options.php">
                <?php
                settings_fields($option_group);
                do_settings_sections($option_group);

                if ($active_tab === 'authentication') {
                    self::render_authentication_settings();
                } elseif ($active_tab === 'woocommerce') {
                    self::render_woocommerce_settings();
                } elseif ($active_tab === 'orders') {
                    self::render_orders_settings();
                }
                submit_button();
                if ($active_tab === 'woocommerce') {
                    echo '<button class="button" name="hubspot_refresh_pipelines" value="1">' . esc_html__('Sync', 'hub-woo-sync') . '</button>';
                }
                ?>
            </form>
        </div>
        <?php
    }

    private static function render_authentication_settings() {
        $nonce    = wp_create_nonce( 'wp_rest' );
        $auth_url = add_query_arg( '_wpnonce', $nonce, get_site_url() . '/wp-json/hubspot/v1/start-auth' );
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
        <a href="#" data-auth-url="<?php echo esc_url($auth_url); ?>" class="button-primary" id="hubspot-auth-button"><?php esc_html_e('Connect HubSpot', 'hub-woo-sync'); ?></a>
        <script>
        jQuery(function($){
            $('#hubspot-auth-button').on('click', function(e){
                e.preventDefault();
                window.location.href = $(this).data('auth-url');
            });

            function checkHubSpotConnection() {
                $.post(ajaxurl, { action: 'hubspot_check_connection' }, function(response) {
                    if (typeof response === 'string') {
                        try { response = JSON.parse(response); } catch (e) {}
                    }
                    if (response.connected === 'yes') {
                        $('#hubspot-connection-status').html('<span style="color: green;">Connected</span>');
                        $('#hubspot-auth-button').text('Reauthorize HubSpot');
                        $('#portal-id').text(response.account_info["Portal ID"]);
                        $('#account-type').text(response.account_info["Account Type"]);
                        $('#time-zone').text(response.account_info["Time Zone"]);
                        $('#company-currency').text(response.account_info["Company Currency"]);
                        $('#data-hosting').text(response.account_info["Data Hosting Location"]);
                        $('#access-token').text(response.account_info["Access Token (truncated)"]); 
                    } else {
                        $('#hubspot-connection-status').text('Not Connected');
                        $('#hubspot-auth-button').text('Connect HubSpot');
                    }
                });
            }
            checkHubSpotConnection();
        });
        </script>
        <?php
    }

    private static function render_woocommerce_settings() {
        $pipelines        = get_option('hubspot_cached_pipelines', []);
        $online_pipeline  = get_option('hubspot_pipeline_online');
        $manual_pipeline  = get_option('hubspot_pipeline_manual');
        $sync_enabled     = get_option('hubspot_pipeline_sync_enabled');
        $online_map     = get_option('hubspot-online-mapping', []);
        $manual_map     = get_option('hubspot-manual-mapping', []);

        $online_stages = get_option('hubspot-online-deal-stages', $pipelines[$online_pipeline]['stages'] ?? []);
        $manual_stages = get_option('hubspot-manual-deal-stages', $pipelines[$manual_pipeline]['stages'] ?? []);

        echo '<h3>' . esc_html__('HubSpot Pipelines Settings', 'hub-woo-sync') . '</h3>';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="hubspot_pipeline_online">' . esc_html__('Online Orders Pipeline', 'hub-woo-sync') . '</label></th>';
        echo '<td><select id="hubspot_pipeline_online" name="hubspot_pipeline_online">';
        echo '<option value="">' . esc_html__('Select Pipeline', 'hub-woo-sync') . '</option>';
        foreach ($pipelines as $pid => $pipeline) {
            echo '<option value="' . esc_attr($pid) . '"' . selected($online_pipeline, $pid, false) . '>' . esc_html($pipeline['label']) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="hubspot_pipeline_manual">' . esc_html__('Manual Orders Pipeline', 'hub-woo-sync') . '</label></th>';
        echo '<td><select id="hubspot_pipeline_manual" name="hubspot_pipeline_manual">';
        echo '<option value="">' . esc_html__('Select Pipeline', 'hub-woo-sync') . '</option>';
        foreach ($pipelines as $pid => $pipeline) {
            echo '<option value="' . esc_attr($pid) . '"' . selected($manual_pipeline, $pid, false) . '>' . esc_html($pipeline['label']) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Sync Order Status Changes', 'hub-woo-sync') . '</th>';
        echo '<td><input type="hidden" name="hubspot_pipeline_sync_enabled" value="0" />';
        echo '<label><input type="checkbox" name="hubspot_pipeline_sync_enabled" value="1"' . checked(1, $sync_enabled, false) . ' /> ' . esc_html__('Update HubSpot deal stage when order status changes', 'hub-woo-sync') . '</label></td></tr>';
        echo '</tbody></table>';

        echo '<h4>' . esc_html__('WooCommerce Status → HubSpot Stage Mapping', 'hub-woo-sync') . '</h4>';
        $statuses = wc_get_order_statuses();
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Status', 'hub-woo-sync') . '</th><th>' . esc_html__('Online Stage', 'hub-woo-sync') . '</th><th>' . esc_html__('Manual Stage', 'hub-woo-sync') . '</th></tr></thead><tbody>';
        foreach ($statuses as $slug => $label) {
            $status = substr($slug, 3); // remove wc-
            $online_val = $online_map[$status] ?? '';
            $manual_val = $manual_map[$status] ?? '';
            echo '<tr><td>' . esc_html($label) . '</td>';
            echo '<td><select name="hubspot-online-mapping[' . esc_attr($status) . ']">';
            echo '<option value="">' . esc_html__('Select Stage', 'hub-woo-sync') . '</option>';
            foreach ($online_stages as $sid => $slabel) {
                echo '<option value="' . esc_attr($sid) . '"' . selected($online_val, $sid, false) . '>' . esc_html($slabel) . '</option>';
            }
            echo '</select></td>';
            echo '<td><select name="hubspot-manual-mapping[' . esc_attr($status) . ']">';
            echo '<option value="">' . esc_html__('Select Stage', 'hub-woo-sync') . '</option>';
            foreach ($manual_stages as $sid => $slabel) {
                echo '<option value="' . esc_attr($sid) . '"' . selected($manual_val, $sid, false) . '>' . esc_html($slabel) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h4>' . esc_html__('Workflow Stage Mapping', 'hub-woo-sync') . '</h4>';
        $workflow_fields = [
            'hubspot_stage_quote_sent_online'     => [$online_stages, __('Quote Sent Stage (Online)', 'hub-woo-sync')],
            'hubspot_stage_quote_sent_manual'     => [$manual_stages, __('Quote Sent Stage (Manual)', 'hub-woo-sync')],
            'hubspot_stage_quote_accepted_online' => [$online_stages, __('Quote Accepted Stage (Online)', 'hub-woo-sync')],
            'hubspot_stage_quote_accepted_manual' => [$manual_stages, __('Quote Accepted Stage (Manual)', 'hub-woo-sync')],
            'hubspot_stage_invoice_sent_online'   => [$online_stages, __('Invoice Sent Stage (Online)', 'hub-woo-sync')],
            'hubspot_stage_invoice_sent_manual'   => [$manual_stages, __('Invoice Sent Stage (Manual)', 'hub-woo-sync')],
        ];

        echo '<table class="form-table"><tbody>';
        foreach ($workflow_fields as $option => $data) {
            list($stage_list, $label) = $data;
            $current = get_option($option);
            echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td><select id="' . esc_attr($option) . '" name="' . esc_attr($option) . '">';
            echo '<option value="">' . esc_html__('Select Stage', 'hub-woo-sync') . '</option>';
            foreach ($stage_list as $sid => $slabel) {
                echo '<option value="' . esc_attr($sid) . '"' . selected($current, $sid, false) . '>' . esc_html($slabel) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_orders_settings() {
        $auto_val      = get_option('hubspot_autocomplete_online_order');
        $cleanup_stat  = get_option('hubspot_order_cleanup_status');
        $cleanup_days  = get_option('hubspot_order_cleanup_days');

        echo '<h3>' . esc_html__('Order Settings', 'hub-woo-sync') . '</h3>';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Autocomplete Online Order', 'hub-woo-sync') . '</th>';
        echo '<td><input type="hidden" name="hubspot_autocomplete_online_order" value="0" />';
        echo '<label><input type="checkbox" name="hubspot_autocomplete_online_order" value="1"' . checked(1, $auto_val, false) . ' /> ' . esc_html__('Automatically mark online orders complete after payment', 'hub-woo-sync') . '</label></td></tr>';

        $statuses = wc_get_order_statuses();
        echo '<tr><th scope="row"><label for="hubspot_order_cleanup_status">' . esc_html__('Cleanup Order Status', 'hub-woo-sync') . '</label></th>';
        echo '<td><select id="hubspot_order_cleanup_status" name="hubspot_order_cleanup_status">';
        foreach ($statuses as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($cleanup_stat, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="hubspot_order_cleanup_days">' . esc_html__('Cleanup After (days)', 'hub-woo-sync') . '</label></th>';
        echo '<td><input type="number" min="1" id="hubspot_order_cleanup_days" name="hubspot_order_cleanup_days" value="' . esc_attr($cleanup_days) . '" /></td></tr>';

        echo '</tbody></table>';
    }

    public static function get_active_tab($tab) {
        return ($_GET['tab'] ?? 'authentication') === $tab ? 'nav-tab-active' : '';
    }

    public static function maybe_refresh_cache_on_save() {
        if (!empty($_POST['hubspot_refresh_pipelines'])) {
            self::refresh_pipeline_cache();
            set_transient('hubspot_pipelines_synced', 1, 30);
        } elseif (isset($_GET['page'], $_GET['settings-updated']) && $_GET['page'] === 'hubspot-woocommerce-sync') {
            self::refresh_pipeline_cache();
        }

        add_action('admin_notices', [__CLASS__, 'maybe_display_sync_notice']);
    }

    public static function maybe_display_sync_notice() {
        if (get_transient('hubspot_pipelines_synced')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('HubSpot pipelines refreshed.', 'hub-woo-sync') . '</p></div>';
            delete_transient('hubspot_pipelines_synced');
        }
    }

    private static function refresh_pipeline_cache() {
        $pipelines = self::get_hubspot_pipelines();
        if (!empty($pipelines)) {
            update_option('hubspot_cached_pipelines', $pipelines);

            $online = get_option('hubspot_pipeline_online');
            $manual = get_option('hubspot_pipeline_manual');

            if ($online && isset($pipelines[$online]['stages'])) {
                update_option('hubspot-online-deal-stages', array_keys($pipelines[$online]['stages']));
            }
            if ($manual && isset($pipelines[$manual]['stages'])) {
                update_option('hubspot-manual-deal-stages', array_keys($pipelines[$manual]['stages']));
            }
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

    /**
     * Sanitize stage mapping ensuring stages belong to selected pipelines.
     */
    public static function sanitize_stage_mapping($mapping) {
        if (!is_array($mapping)) {
            $mapping = [];
        }

        $online_pipeline = $_POST['hubspot_pipeline_online'] ?? get_option('hubspot_pipeline_online');
        $manual_pipeline = $_POST['hubspot_pipeline_manual'] ?? get_option('hubspot_pipeline_manual');

        $pipelines     = get_option('hubspot_cached_pipelines', []);
        $online_stages = $pipelines[$online_pipeline]['stages'] ?? [];
        $manual_stages = $pipelines[$manual_pipeline]['stages'] ?? [];

        foreach ($mapping as $key => $stage_id) {
            $stage_id = sanitize_text_field($stage_id);

            if (strpos($key, 'online_wc-') === 0) {
                if ($stage_id && !isset($online_stages[$stage_id])) {
                    $mapping[$key] = '';
                    add_settings_error(
                        'hubspot_status_stage_mapping',
                        'invalid_stage_' . $key,
                        sprintf(__('Invalid stage selected for %s; value cleared.', 'hub-woo-sync'), esc_html($key)),
                        'error'
                    );
                } else {
                    $mapping[$key] = $stage_id;
                }
            } elseif (strpos($key, 'manual_wc-') === 0) {
                if ($stage_id && !isset($manual_stages[$stage_id])) {
                    $mapping[$key] = '';
                    add_settings_error(
                        'hubspot_status_stage_mapping',
                        'invalid_stage_' . $key,
                        sprintf(__('Invalid stage selected for %s; value cleared.', 'hub-woo-sync'), esc_html($key)),
                        'error'
                    );
                } else {
                    $mapping[$key] = $stage_id;
                }
            } else {
                unset($mapping[$key]);
            }
        }

        return $mapping;
    }

    /**
     * Sanitize online mapping ensuring stages exist in hubspot-online-deal-stages.
     */
    public static function sanitize_online_mapping($mapping) {
        if (!is_array($mapping)) {
            $mapping = [];
        }

        $available = get_option('hubspot-online-deal-stages', []);

        foreach ($mapping as $status => $stage_id) {
            $stage_id = sanitize_text_field($stage_id);
            if ($stage_id && !isset($available[$stage_id])) {
                $mapping[$status] = '';
                add_settings_error(
                    'hubspot-online-mapping',
                    'invalid_stage_' . $status,
                    sprintf(__('Invalid stage selected for %s; value cleared.', 'hub-woo-sync'), esc_html($status)),
                    'error'
                );
            } else {
                $mapping[$status] = $stage_id;
            }
        }

        return $mapping;
    }

    /**
     * Sanitize manual mapping ensuring stages exist in hubspot-manual-deal-stages.
     */
    public static function sanitize_manual_mapping($mapping) {
        if (!is_array($mapping)) {
            $mapping = [];
        }

        $available = get_option('hubspot-manual-deal-stages', []);

        foreach ($mapping as $status => $stage_id) {
            $stage_id = sanitize_text_field($stage_id);
            if ($stage_id && !isset($available[$stage_id])) {
                $mapping[$status] = '';
                add_settings_error(
                    'hubspot-manual-mapping',
                    'invalid_stage_' . $status,
                    sprintf(__('Invalid stage selected for %s; value cleared.', 'hub-woo-sync'), esc_html($status)),
                    'error'
                );
            } else {
                $mapping[$status] = $stage_id;
            }
        }

        return $mapping;
    }

    /**
     * Sanitize the online pipeline option and refresh cache when changed.
     */
    public static function sanitize_pipeline_online($value) {
        $value = sanitize_text_field($value);
        if ($value !== get_option('hubspot_pipeline_online')) {
            self::refresh_pipeline_cache();
        }
        return $value;
    }

    /**
     * Sanitize the manual pipeline option and refresh cache when changed.
     */
    public static function sanitize_pipeline_manual($value) {
        $value = sanitize_text_field($value);
        if ($value !== get_option('hubspot_pipeline_manual')) {
            self::refresh_pipeline_cache();
        }
        return $value;
    }

    /**
     * Sanitize checkbox values to be strictly 0 or 1.
     */
    public static function sanitize_checkbox($value) {
        return empty($value) ? 0 : 1;
    }
}

