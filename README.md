# HubSpot WooCommerce Sync

**Version:** 1.0.0  
**Author:** [Weblifter](https://weblifter.com.au)  
**Plugin URI:** [GitHub Repository](https://github.com/weblifter/hubspot-woocommerce-sync)  
**License:** GPL-3.0  

## Overview

HubSpot WooCommerce Sync is a WordPress plugin that integrates WooCommerce with HubSpot, allowing store owners to **automatically sync orders as deals in HubSpot CRM**. This plugin uses a **public HubSpot app** to authenticate users and store their API tokens.

## Features
- **HubSpot OAuth Authentication**: Securely connects your WooCommerce store to HubSpot.
- **Order Syncing**: Automatically creates deals in HubSpot for new WooCommerce orders.
- **Pipeline Selection**: Choose which HubSpot pipeline to use for deal management.
- **Settings Page**: Configure HubSpot authentication and syncing options from the WordPress admin panel.
- **GitHub Auto-Updates**: Plugin updates itself via GitHub.

## Installation

### Manual Installation
1. Download the latest release from [GitHub](https://github.com/weblifter/hubspot-woocommerce-sync).
2. Upload the `.zip` file to WordPress via **Plugins → Add New → Upload Plugin**.
3. Activate the plugin.

### Using GitHub Updater
1. Install the [GitHub Updater](https://github.com/afragen/github-updater) plugin.
2. Add the repository URL in the GitHub Updater settings.
3. The plugin will now receive updates directly from GitHub.

## Setup & Authentication

1. **Go to WordPress Admin → HubSpot Sync**.
2. Enter your HubSpot **Client ID** and **Client Secret** in the settings form.
3. Click the **Connect HubSpot** button and follow the authorization flow.
4. Once authenticated, choose your **HubSpot pipeline** for WooCommerce orders.
5. Enable **Automatic Deal Creation** to sync new orders.

### HubSpot App Credentials
Your HubSpot app's **Client ID** and **Client Secret** are stored as plugin options. They can be updated anytime from the HubSpot Sync settings page.

## REST API Endpoints

The plugin provides the following API routes. All routes require an authenticated user with the `manage_options` capability:

### Start OAuth Authentication
`GET /wp-json/hubspot/v1/start-auth`

**Query Parameter:**
- `store_url` (required) - The WooCommerce store URL.

### OAuth Callback
`GET /wp-json/hubspot/v1/oauth/callback`

**Handles HubSpot's OAuth response and stores the API tokens.**

### Check Connection Status
`GET /wp-json/hubspot/v1/get-token`

**Returns `Connected` or `Not connected` and the HubSpot portal ID if available. Tokens are never exposed.**

## Uninstallation
To remove the plugin:
1. Deactivate the plugin in WordPress.
2. Click "Delete" to remove all associated data.

## License
This plugin is licensed under **GPL-3.0**.

## Support & Contributions
For bug reports, feature requests, or contributions, visit the [GitHub Issues](https://github.com/weblifter/hubspot-woocommerce-sync/issues) page.
