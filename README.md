=== HubSpot WooCommerce Sync ===
Contributors: Weblifter
Plugin Name: HubSpot WooCommerce Sync
Plugin URI: https://github.com/weblifter/hubspot-woocommerce-sync
Description: Sync WooCommerce orders with HubSpot deals using a public HubSpot app.
Version: 1.0.0
Requires at least: 5.6
Tested up to: 6.3
Requires PHP: 7.4
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: woocommerce, hubspot, ecommerce, crm

== Description ==
This plugin connects WooCommerce with HubSpot, allowing automatic order synchronization with HubSpot deals.

== Features ==
* Automatically create WooCommerce orders when a HubSpot deal is won.
* Update WooCommerce orders when a HubSpot deal is modified.
* Delete WooCommerce orders when a HubSpot deal is removed.
* Fetch WooCommerce order details from a HubSpot deal.

== Installation ==
1. Download the plugin ZIP file.
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**.
3. Upload and activate the plugin.

== Setup ==
1. Install the HubSpot public app.
2. Follow the OAuth authentication flow to authorize the app.
3. Orders will sync automatically.

== API Endpoints ==
| Route                  | Method | Description |
|------------------------|--------|-------------|
| `/hubspot/v1/create-order` | POST | Create a WooCommerce order from a HubSpot deal. |
| `/hubspot/v1/update-order` | POST | Update an existing WooCommerce order. |
| `/hubspot/v1/delete-order` | DELETE | Delete a WooCommerce order linked to a HubSpot deal. |
| `/hubspot/v1/get-wc-order` | GET | Retrieve a WooCommerce order using a HubSpot deal ID. |

== Uninstallation ==
If you uninstall the plugin, all stored OAuth tokens will be deleted.

== Support ==
For support, visit: [https://weblifter.com.au](https://weblifter.com.au)

== Changelog ==
= 1.0.0 =
* Initial release
