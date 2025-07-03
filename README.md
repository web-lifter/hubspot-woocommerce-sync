# HubSpot WooCommerce Sync

**Version:** 1.0.0  
**Author:** [Weblifter](https://weblifter.com.au)  
**Plugin URI:** [GitHub Repository](https://github.com/weblifter/hubspot-woocommerce-sync)  
**License:** GPL-3.0  

## Overview

HubSpot WooCommerce Sync is a WordPress plugin that integrates WooCommerce with HubSpot, allowing store owners to **automatically sync orders as deals in HubSpot CRM**. This plugin uses a **public HubSpot app** to authenticate users and store their API tokens.

---

## Features

* **HubSpot OAuth Authentication**
  Securely connect your WooCommerce store to HubSpot using OAuth 2.0. Tokens are managed automatically and refreshed when needed. The connection is initiated via the admin settings panel and validated via the HubSpot API.

* **Automatic Deal Creation for Online Orders**
  When an online WooCommerce order is paid (via `woocommerce_payment_complete`), the plugin creates a corresponding **HubSpot Deal** in the selected pipeline. The deal includes:

  * Order total and shipping costs
  * Full billing and shipping details
  * Contact association (customer email, phone, name)
  * Optional company association
  * Line items as HubSpot products (with price, quantity, GST, SKU)
  * Deal notes from WooCommerce’s customer note field
  * PayWay transaction reference, if available
  * Pipeline and stage mappings from WooCommerce order status. Each status can
    be mapped separately for the **Online Orders Pipeline** and the
    **Manual Orders Pipeline** using the `hubspot-online-mapping` and
    `hubspot-manual-mapping` options.
* **Automatic Order Completion**
  Online orders can be marked **complete** automatically when enabled in the **Orders** tab.
* Order cleanup: automatically delete orders that remain in a chosen status (e.g., Pending Payment) for a specified number of days. These controls live in the **Orders** tab.

* **Manual Deal Creation**
  For unpaid or offline WooCommerce orders, admins can click **“Create Deal”** in the Order Management screen. These orders are pushed to the pipeline chosen for manual sales (e.g., a Quotes pipeline) and use the stage mappings defined under **Manual Orders Pipeline**.

* **Manual Order Sync from HubSpot Deal**
  Orders created manually in WooCommerce can be fully re-synced from their associated HubSpot deal using the **“Sync”** button. This updates:

  * Billing/shipping fields
  * Customer contact info
  * Line items
  * Deal stage and pipeline labels

* **Quote Email Workflow**

  * Admins can send a **quote email** directly from the Order Management UI.
  * The quote is tracked using `quote_status` and `quote_last_sent` order meta.
  * HubSpot logs a timeline event (“Quote Sent”) and updates the deal stage accordingly.
  * If the customer clicks **“Accept Quote”**, the deal stage is updated again and an invoice is sent automatically.

* **Invoice Email Workflow**

  * Admins can send invoices manually, or invoices are automatically triggered on quote acceptance.
  * The plugin tracks `invoice_status` and `invoice_last_sent`.
  * HubSpot logs an **invoice sent** event and updates the deal stage using mapped stage IDs (based on order type).
  * This works for both manual and online orders.

* **Deal Stage Updates**
  At key moments (invoice sent, quote accepted, etc.), the plugin updates the **HubSpot deal stage** using admin-defined mappings:

  * `hubspot_stage_quote_sent_manual`
  * `hubspot_stage_invoice_sent_online`, etc.

* **Email Activity Logging in HubSpot Timeline**
  Every quote, invoice, or acceptance action logs a **timeline event in HubSpot** associated with the Deal. The subject and body content are dynamically generated with order details and payment links.

* **WooCommerce Admin Order Management UI**
  A powerful admin screen at `WooCommerce → HubSpot Orders` allows you to:

  * Filter/search orders by status or customer name
  * View quote/invoice status
  * Trigger actions: **Send Quote**, **Send Invoice**, **Create Deal**, **Sync from HubSpot**
  * See whether an order is “Manual” or “Online”
  * Detect if a quote is **outdated** compared to the latest order modification

* **PayWay Integration Support**
  Automatically syncs `_payway_api_order_number` to the **HubSpot Deal** after payment. Ensures external transaction references are tracked.

* **Smart Order Typing (Manual vs Online)**

  * Admin-created, REST API, and CLI orders are marked as `manual`
  * Frontend WooCommerce checkout orders are marked as `online`
  * This distinction controls pipeline/stage logic and affects invoice/quote workflows

* **Stage & Pipeline Label Caching**
  Caches HubSpot pipeline and stage labels from the API to reduce repeated requests and provide readable names in the admin UI. Cached data lives in the `hubspot_cached_pipelines` option and can be refreshed from the **Pipelines** tab using the new **Sync** button.
* **Property Caching**
  Fetches and stores property definitions for HubSpot objects (deals, contacts, companies, products and line items). These are cached in options like `hubspot_properties_deals` and can be refreshed from the **Properties** tab.

* **Robust Error Handling and Logging**

  * Errors during sync or API requests are logged via `error_log()` and `hubwoo_log()`
  * Invalid configurations (e.g., missing pipeline) now trigger order notes or error returns
  * Admin is alerted if deals aren't created due to missing mapping

* **Custom Line Item Integration**
  Each WooCommerce product is added to the HubSpot deal as a **line item** with:

  * Product name, quantity, subtotal, SKU, and tax
  * Proper associations via HubSpot v4 batch API

* **Extensibility for Developers**
  Clean separation of concerns across files:

  * `create-object.php`: Deal, contact, and company creation
  * `fetch-object.php`: Pulling HubSpot data
  * `send-quote.php`, `manual-actions.php`: Action handlers
  * `manual-order-sync.php`, `online-order-sync.php`: Contextual sync flows

---

## Installation

### Manual Installation
1. Download the latest release from [GitHub](https://github.com/weblifter/hubspot-woocommerce-sync).
2. Upload the `.zip` file to WordPress via **Plugins → Add New → Upload Plugin**.
3. Activate the plugin.


### Using GitHub Updater
1. Install the [GitHub Updater](https://github.com/afragen/github-updater) plugin.
2. Add the repository URL in the GitHub Updater settings.
3. The plugin will now receive updates directly from GitHub.

## Creating a Private HubSpot App

1. Sign in to the [HubSpot Developer Portal](https://developers.hubspot.com/).
2. Create a **Private App** and note the generated **Client ID** and **Client Secret**.
3. Add your site's OAuth **Redirect URI** (usually `https://yoursite.com/wp-json/hubspot/v1/oauth/callback`).
4. Copy these values into the plugin's `variables.php` file.

## Setup & Authentication

1. **Go to WordPress Admin → HubSpot Sync**.
2. Ensure the plugin's `variables.php` file contains your HubSpot **Client ID**, **Client Secret**, and **Redirect URI** from your private app.
3. Click the **Connect HubSpot** button and follow the authorization flow.
4. Once authenticated, select a **pipeline for online orders** and a separate
   **pipeline for manual orders** under the **Pipelines** tab.
5. Map each WooCommerce order status to a HubSpot stage for both pipelines using
   the status table on that tab (stored in `hubspot-online-mapping` and
   `hubspot-manual-mapping`).
6. Enable **Automatic Deal Creation** to sync new orders.
7. In the **Orders** tab, enable **Auto-Complete Online Orders** to automatically mark paid orders complete.

### Properties & Field Mapping
Use the **Properties** tab to control how HubSpot data maps to WooCommerce.
Click **Refresh Properties** at the top of the tab whenever you add new custom properties in HubSpot to pull the latest lists.
For each object type (Deals, Contacts, Companies, Products and Line Items), choose the HubSpot property and the WooCommerce field (or custom meta key) that should sync.
Several common mappings are filled in by default, and you can add additional rows to map custom fields.
Any property left unmapped will be ignored during sync.
Product mapping relies on matching SKUs, so make sure each product uses the same SKU in WooCommerce and HubSpot.

### Admin-Created Orders
Orders created manually in WooCommerce are **not** synced automatically. After
creating an order in the admin, go to **HubSpot → Order Management** and click
the **Create Deal** button to push the order to HubSpot.

### HubSpot App Credentials
Your HubSpot app's **Client ID**, **Client Secret**, and **Redirect URI** are loaded from the `variables.php` file in the plugin directory.

## REST API Endpoints

The plugin provides the following API routes. All routes require an authenticated user with the `manage_options` capability. In practice, you must be logged in as an administrator when calling `/start-auth` and `/oauth/callback`. The **Connect HubSpot** link on the plugin settings page opens these routes for you so authentication is handled automatically:

### Start OAuth Authentication
`GET /wp-json/hubspot/v1/start-auth`

**Query Parameter:**
- `store_url` (required) - The WooCommerce store URL.

### OAuth Callback
`GET /wp-json/hubspot/v1/oauth/callback`

**Handles HubSpot's OAuth response and stores the API tokens.**

The previous connection status endpoint has been removed for security. Only
administrators can access the OAuth routes.


## Uninstallation
To remove the plugin:
1. Deactivate the plugin in WordPress.
2. Click "Delete" to remove all associated data.

## License
This plugin is licensed under **GPL-3.0**.

## Support & Contributions
For bug reports, feature requests, or contributions, visit the [GitHub Issues](https://github.com/weblifter/hubspot-woocommerce-sync/issues) page.
