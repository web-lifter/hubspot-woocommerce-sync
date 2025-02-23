<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HubSpot_WC_GitHub_Updater {
    private static $github_repo = "weblifter/hubspot-woocommerce-sync"; // GitHub repo owner/repo-name
    private static $api_url = "https://api.github.com/repos/weblifter/hubspot-woocommerce-sync/releases/latest";
    private static $plugin_file = "hubspot-woocommerce-sync/hubspot-woocommerce-sync.php"; // Plugin file path

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_update_info'], 10, 3);
        add_filter('upgrader_package_options', [__CLASS__, 'set_download_url']);
    }

    /**
     * Check GitHub for a new release.
     */
    public static function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . "/" . self::$plugin_file);
        $current_version = $plugin_data['Version'];

        $response = wp_remote_get(self::$api_url, ['headers' => ['Accept' => 'application/json']]);

        if (is_wp_error($response)) {
            return $transient;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        $latest_version = $release['tag_name'] ?? '';

        if (version_compare($current_version, $latest_version, '<')) {
            $transient->response[self::$plugin_file] = (object) [
                'slug'        => 'hubspot-woocommerce-sync',
                'plugin'      => self::$plugin_file,
                'new_version' => $latest_version,
                'package'     => $release['assets'][0]['browser_download_url'] ?? '', // Plugin ZIP file URL
                'url'         => "https://github.com/" . self::$github_repo
            ];
        }

        return $transient;
    }

    /**
     * Display update details inside the WordPress dashboard.
     */
    public static function plugin_update_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'hubspot-woocommerce-sync') {
            return $result;
        }

        $response = wp_remote_get(self::$api_url, ['headers' => ['Accept' => 'application/json']]);

        if (is_wp_error($response)) {
            return $result;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);

        return (object) [
            'name'          => 'HubSpot WooCommerce Sync',
            'slug'          => 'hubspot-woocommerce-sync',
            'version'       => $release['tag_name'] ?? '',
            'author'        => '<a href="https://weblifter.com.au">Weblifter</a>',
            'homepage'      => "https://github.com/" . self::$github_repo,
            'download_link' => $release['assets'][0]['browser_download_url'] ?? '',
            'sections'      => ['description' => $release['body'] ?? 'Sync WooCommerce orders with HubSpot.'],
        ];
    }

    /**
     * Set the download URL for the plugin update.
     */
    public static function set_download_url($options) {
        if (strpos($options['package'], 'github.com') !== false) {
            $options['package'] = str_replace('api.github.com/repos', 'github.com', $options['package']);
            $options['package'] = str_replace('/releases/assets/', '/releases/download/', $options['package']);
        }
        return $options;
    }
}

// Initialize GitHub Updater
HubSpot_WC_GitHub_Updater::init();

?>
