Thank you for confirming. I will now perform a full audit of your HubSpot-WooCommerce integration code (excluding the abandoned carts folder). This will include:

1. A security review covering sanitization, validation, and escaping best practices.
2. A thorough check for functional issues and data inconsistencies, including the syncing problem you described.
3. A comprehensive list of improvements with detailed instructions.
4. Specific development instructions to convert the code into a standalone WordPress plugin using best practices.

I'll update you once the full report is ready.

# HubSpot WooCommerce Sync Code Audit

## Identified Security Vulnerabilities

* **Unrestricted REST API Endpoints:** The integration registers custom REST routes with `permission_callback => __return_true`, making them publicly accessible. In particular, the `/hubspot/v1/get-token` endpoint returns the entire HubSpot OAuth token record (including access and refresh tokens) to any caller. This is a serious security risk – an unauthenticated user can retrieve your HubSpot API tokens and potentially hijack the integration. Likewise, the OAuth start and callback endpoints (`/start-auth` and `/oauth/callback`) have no access control. This means a malicious party could initiate or complete the OAuth flow without authentication, possibly linking your site to an attacker’s HubSpot account or causing unintended token refreshes.

* **Exposure of Credentials and Tokens:** Currently, the HubSpot API client ID and secret are loaded from a `variables.php` file in the theme, and tokens are stored in the database without encryption. While storing tokens in the DB is common, exposing them via the open REST endpoint is the primary vulnerability (as noted above). Additionally, writing the access token (even if truncated) and other sensitive info to error logs is not ideal in production – logs can sometimes be accessed or leave traces. Best practice is to restrict access to such data and avoid logging sensitive tokens.

* **Lack of Capability Checks on Actions:** Most WooCommerce AJAX actions in this code rely on nonce checks (which is good), but they don’t explicitly check user capabilities. Since these actions (e.g. sending quotes/invoices, creating deals) are only exposed in the admin UI (which requires login) this is less severe. However, for defense in depth, adding an explicit `current_user_can('manage_woocommerce')` or similar check on these `wp_ajax_...` handlers would ensure only authorized staff trigger them. The main gap is the REST API routes above, which have no such checks at all.

* **Potential Function Name Collisions:** Many functions are declared in the global namespace without a unique prefix (e.g. `send_invoice_email_ajax`, `manual_sync_hubspot_order`, etc. in **manual-actions.php**). This isn’t a direct security hole, but it’s a maintainability hazard – if another plugin or theme defines a function with the same name, it will cause a fatal error or override behavior. Using a unique prefix or wrapping them in a class would prevent accidental conflicts that could be exploited or cause instability.

## Identified Functional Issues & Inconsistencies

1. **Online Orders Sync to Wrong Deal Stage:** Online WooCommerce orders are being created in HubSpot under the wrong deal stage. Specifically, orders that should be in the “Processing/Completed” stage of the sales pipeline are initially logged in HubSpot as “Failed/Pending Payment”. This was observed when an online order (with status “Processing”) ended up in the HubSpot stage mapped for a failed/pending order. The cause is a logic timing issue: the code triggers deal creation *before* payment is fully completed. In the `hubspot_auto_sync_online_order` function, it uses the order’s status at checkout time – which can be “pending” prior to payment capture – to pick a deal stage. If no mapping is found for that status or if the status is still “pending”, it falls back to the first stage of the pipeline (which in your case is the “WC Order Failed” stage mapped to pending). Thus, even though you mapped “processing” to the correct HubSpot stage, the deal was created too early (when the order was pending) and went to the wrong stage.

2. **Manual Deal Creation Uses Incorrect Stage Mapping:** When creating a HubSpot deal for a manually entered order (via the “Create Deal” button), the plugin does not respect the manual pipeline’s stage mappings. The code in `create_hubspot_deal_manual` mistakenly uses the **online** order key `"online_wc-processing"` when looking up the stage. This is clearly a bug – it means manual deals will always use whatever stage is mapped for online “Processing” orders (or fall back to the first stage) instead of the correct stage for manual orders. In your configuration, this likely causes manual deals to start at an incorrect stage or pipeline. (For example, if no mapping existed for online processing, it would default manual deals to the first stage of the manual pipeline, which might not be what you expect.)

3. **Manual Orders Not Marked Properly:** Related to the above, there is ambiguity in how the code distinguishes “manual” vs “online” orders. The `order_type` meta is used for this – online orders are tagged with `order_type = online` during checkout, and the code treats any order without that as potentially manual. However, when an admin creates an order in WooCommerce, the code currently **does not** set `order_type = manual`. In fact, the function `set_order_type_for_online_orders` explicitly returns early if the order is created in admin or via REST, leaving `order_type` blank. This means many manually created orders have no explicit type. As a result, other logic (like the pipeline sync on status change) might misidentify a manual order as “online” (because `is_order_manual` will return false if the meta is blank). This inconsistency can lead to deals being created in the wrong pipeline or not at all. In practice, it appears the intention was that manually created orders should only be pushed to HubSpot via the manual “Import/Create Deal” actions – but the code doesn’t robustly enforce or indicate this, which can confuse the sync behavior.

4. **Deal Stage Not Updated on Invoice Sent:** The workflow includes custom “Quote Sent”, “Quote Accepted”, and “Invoice Sent” stages. You have fields in settings for these, and the expectation is that when a quote is accepted or an invoice is sent, the HubSpot deal moves to the corresponding stage. The code for quotes is handled (when a quote is sent, it calls `update_hubspot_deal_stage` with the mapped stage, and similarly on quote acceptance). However, for **invoices**, the stage update is missing. In `send_invoice_email_ajax`, after sending the WooCommerce invoice email, the code updates the order meta and attempts to log the event to HubSpot. It fetches the configured invoice stage ID (`hubspot_stage_invoice_sent_online/manual`), but instead of updating the deal stage, it just calls `log_email_in_hubspot` with that ID as a parameter. This likely does **not** update the deal’s stage in HubSpot – it only logs an email event. In other words, when an invoice is sent, the deal remains in the prior stage (e.g. “Quote Accepted”) and doesn’t progress to “Invoice Sent” stage as expected.

5. **Redundant or Inefficient Stage Updates:** There are a few minor logic issues in stage syncing that, while not breaking, should be cleaned up. For example, in `handle_quote_acceptance`, the code calls `update_hubspot_deal_stage` twice for the “Quote Accepted” stage (there are two blocks updating the stage to the same value). This redundancy isn’t user-facing but could be simplified to avoid two API calls. Another example: the `order_type` detection during quote acceptance tries to use an undefined function `order_type($order)` and falls back to assuming manual. This appears to be a leftover from an earlier approach – since you have `is_order_manual`, the extra function check is unnecessary. These inconsistencies won’t show up as errors (because `order_type` isn’t defined, the code just assumes manual) but they indicate places for improvement.

6. **General Code Quality Observations:** The core functionality is in place, but there are opportunities to follow WordPress coding best practices more closely. For instance, direct SQL queries (`$wpdb->get_row(...)`) could use `$wpdb->prepare` (though in our case inputs are static or safe). Output in the admin HTML is mostly escaped, which is good; just ensure any future outputs of dynamic data use `esc_html`/`esc_attr`. The heavy use of `error_log` for debugging (e.g. logging full API responses and data structures) can clutter logs and potentially expose data – ideally these would be removed or switched to use `WP_DEBUG_LOG` and only active in dev environments. None of these are breaking issues, but addressing them will make the plugin more robust.

## Recommended Improvements & Best Practices

**Secure the HubSpot OAuth Integration:** The highest priority is to lock down the REST API endpoints. The `get-token` endpoint should be removed entirely from production code – it’s meant for debugging but exposes your tokens. If you need to keep it, restrict access by adding a `permission_callback` that checks for an administrator capability (e.g. `current_user_can('manage_options')`) and perhaps an extra nonce. Similarly, the OAuth start (`/start-auth`) and callback routes should require an admin user. Typically, you’d initiate OAuth from the WP admin (where the user is already logged in as admin), so you can afford to require `is_user_logged_in()` and capability checks in those callbacks. This will prevent unauthorized hijacking of the connection. **In summary:** update the `register_rest_route` calls to include proper `permission_callback` functions instead of `__return_true`, and remove or guard any debug endpoints that return sensitive info.

**Protect Tokens and Credentials:** Consider moving the client ID and secret out of the theme and into a safer location. For example, during plugin conversion (see below), you might put these in constants in the plugin’s main file or use the database (with `update_option`) to store them after an admin enters them. If you keep using a file, place it in the plugin folder and load it similarly, but ensure it’s not web-accessible (a PHP file with no direct output is fine). Also, once the REST endpoint is secured, the tokens in the database are less exposed. But you might still want to encrypt them or at least ensure no other part of the site can accidentally leak them. At minimum, **do not log full tokens** in any circumstances. You already truncate the token in some logs, which is good. Continue that practice or remove those logs in production.

**Use Nonces and Capability Checks Everywhere:** For your admin AJAX actions, you are already generating and checking nonces (e.g. `check_ajax_referer('send_invoice_email_nonce', 'security')` in **send-quote.php** and similar in other functions). This is good. To strengthen it, add an explicit user capability check in each AJAX handler. For example, at the top of `send_invoice_email_ajax()` in **manual-actions.php**, do: `if (! current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized', 403);`. This ensures the user is a shop manager or admin. It’s a small hardening step. Also ensure that any form that triggers these actions (like the Import form on the Order Management page) is only visible to authorized users (it already is, due to the admin menu capability). Going forward, stick to using WordPress’s nonce and user capability system for any new admin actions.

**Improve Order Type Handling:** To avoid confusion between manual and online orders, the code should consistently tag orders with an `order_type`. Currently, front-end orders get tagged as 'online' automatically. We recommend that **any** order created via the admin dashboard or via external APIs be explicitly tagged as 'manual'. You can achieve this by modifying the `set_order_type_for_online_orders` function (in **online-order-sync.php**) or adding a new hook for admin order creation. For example, remove or adjust the `is_admin()` check so that instead of *skipping* admin orders, it tags them:

```php
if (is_admin() || defined('REST_REQUEST') || php_sapi_name() === 'cli') {
    // For admin or external creations, mark as manual (if not already set)
    if (strtolower($existing_order_type) !== 'manual') {
        $order->update_meta_data('order_type', 'manual');
        $order->save_meta_data();
    }
    return;
}
```

This way, any order not created by a normal customer checkout will be labeled 'manual'. This will make the pipeline selection logic clearer – `is_order_manual()` will yield true for all admin orders, ensuring they use the manual pipeline settings. It also helps your team see at a glance in the order meta what type it is.

**Fix Pipeline Stage Mapping Logic:** Ensure that deal creation uses the correct stage mapping for both online and manual orders. For **online orders**, the timing issue should be addressed: ideally, create the HubSpot deal *after* payment is complete (so the order status is accurate). The simplest fix is to use a different WooCommerce hook. Instead of `woocommerce_checkout_order_processed`, you could hook into `woocommerce_payment_complete` or `woocommerce_order_status_processing` (which fire when an order transitions from pending to processing after successful payment). This way, when the code runs to create the deal, the order status will be “processing” (or even “completed” for instant payment methods), and your mapping `online_wc-processing -> (your stage)` will be applied. That would prevent deals from defaulting to the “pending payment” stage. Another approach is to keep the current hook but add logic: if the order status is “pending” at checkout, perhaps delay deal creation slightly or update it immediately when the status changes. However, using the proper hook is cleaner. **We recommend hooking deal creation to the payment success event**. This ensures online deals start in the correct stage. (Don’t worry – for orders that never get to processing because payment fails or is COD, you can still create deals on the “pending” stage if desired. But since your pipeline mapping suggests you treat pending as a failed stage, it may be acceptable not to create a deal until payment is made.)

For **manual deals**, correct the hard-coded status key. In **manual-actions.php**, find where `$status_key = "online_wc-processing";` is set in `create_hubspot_deal_manual`. This should be changed to use the actual order’s status and the manual prefix. For example:

```php
$pipeline_id = get_option('hubspot_pipeline_manual');
$status = $order->get_status();  
$status_key = "manual_wc-{$status}";
$mapping = get_option('hubspot_status_stage_mapping', []);
$deal_stage = $mapping[$status_key] ?? hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);
```

This way, if the manual order is in status “pending” (which it likely is if you just created it to send an invoice), it will use your mapping for `manual_wc-pending` (which in your saved options is set to a specific stage ID). If that mapping is blank, it will fall back to the first stage of the manual pipeline (which might be okay for your process). This change will ensure manual deals start at a logical stage.

After making these fixes, test both an online checkout and a manual order:

* Online paid order should create a HubSpot deal in the correct pipeline (online pipeline) and in the stage mapped to “Processing” (rather than the first stage).
* Manual order: when using “Create Deal”, it should go to the manual pipeline at the stage mapped for its current status (e.g., Pending Payment → whatever you mapped, possibly a “Quote sent” or similar stage for manual).

Additionally, with manual orders properly labeled, the **pipeline sync on status changes** will also behave correctly. The `sync_order_status_to_hubspot_pipeline` function already checks the order type and uses the appropriate mapping key. By fixing the initial stage and the `order_type`, subsequent status transitions (e.g., manual order from pending to completed) will update the HubSpot deal to the right stage (your mapping for `manual_wc-completed`, which you set to the ID `634097`, presumably “Closed Won”). Make sure all desired status→stage pairs are mapped in settings so the sync doesn’t log a warning for an unmapped status.

**Ensure All Deal Stages Are Updated on Workflow Events:** You’ll want to patch the missing stage update for invoice sent. The solution is to call `update_hubspot_deal_stage` for the invoice stage, similar to how quotes are handled. In **manual-actions.php** `send_invoice_email_ajax()`, after triggering the WooCommerce invoice email and updating the meta, add a call to update the deal stage. For example:

```php
if ($invoice_stage_id) {
    update_hubspot_deal_stage($order_id, $invoice_stage_id);
}
```

This should be done **before** or in place of the `log_email_in_hubspot` call. In fact, `log_email_in_hubspot` doesn’t accept a stage ID parameter in a way that updates the deal – it’s only using that parameter to potentially override the email subject (which is not what we want here). So, the clean approach is:

* Remove passing `$invoice_stage_id` into `log_email_in_hubspot`. Instead, call `update_hubspot_deal_stage($order_id, $invoice_stage_id)` to actually move the deal to “Invoice Sent” stage.
* Then call `log_email_in_hubspot($order_id, 'invoice')` without the override, so it logs the event with the standard subject (“Invoice Sent for Order #X”).

This way the HubSpot deal will progress to the stage you configured (e.g., the GUID or ID you have for "Invoice Sent" in the manual pipeline), and an email timeline event will still be logged on the deal. After implementing, test by sending an invoice from the Order Management page – the deal in HubSpot should move to the “Invoice Sent” stage (you can verify in HubSpot UI).

Similarly, you might review the **Quote Accepted** flow: currently it updates the status to pending payment and moves the deal to the “Quote Accepted” stage. That’s fine. The minor improvement there is to remove the duplicate stage update. You can delete one of the two `update_hubspot_deal_stage($order_id, $accepted_stage_id)` calls to avoid redundant API calls (either the one within the `if ($quote_accepted_stage_id)` or the one right after it, since they do the same thing). Also, you can simplify by using `is_order_manual($order)` to decide which option key to use, instead of the undefined `order_type()` function. These tweaks will clean up the code but won’t change functionality.

**Reduce Logging Noise and Protect Data:** In production, it’s best to minimize extensive logging. Currently, the plugin liberally uses `error_log()` to record debug information (e.g., dumping pipeline data, API responses, etc.). While this is useful during development, it can slow down execution and clutter your logs. It can also inadvertently expose customer or deal information in log files. We recommend wrapping these logs in checks so they only run when debugging is enabled. For example, you can do:

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log("[HubSpot Sync] ... debug info ...");
}
```

around large `print_r` dumps or remove them entirely once the integration is stable. At a minimum, remove any logging of access tokens or sensitive personal data. Keep logs for important errors (like “failed to refresh token” or “API request failed”) but consider using `error_log` only for errors, not routine operation. This follows the principle of failing quietly in production unless an admin needs to be alerted.

**Code Organization and Prefixes:** As you convert this into a plugin, take the opportunity to namespace or prefix functions to avoid collisions. One approach is to wrap most of this functionality into a class (or a few classes) within the plugin. For example, you might have a main class `Hubspot_Woo_Sync` that inits all the hooks, and perhaps separate classes for “DealsSync”, “AdminUI”, etc. If refactoring into classes is too time-consuming, at least prefix global function names with a unique string (e.g., `hubwoo_` or similar). For instance, rename `send_invoice_email_ajax` to `hubwoo_send_invoice_email_ajax`. Update the `add_action('wp_ajax_...')` accordingly. Do this for all custom functions not part of a class. This will prevent any naming clashes with other plugins or themes. It also makes it clearer that these functions are part of your integration.

**Follow WordPress Plugin Best Practices:** Ensure all output is escaped (you did well in the settings and table HTML – continue that). Use WordPress APIs when available (for example, use `update_option`/`get_option` which you are doing, use `wp_nonce_field`/`check_admin_referer` for form submissions, etc.). Also, handle return values from API calls carefully – some of your functions assume the remote call succeeded and immediately decode JSON. It might be good to check the HTTP status or `is_wp_error` in every call (you do in most, but double-check all, e.g., `hubspot_create_deal_from_order` does check `$response` status code, which is good). Little consistency fixes like that will make the code more robust if HubSpot’s API has a hiccup.

Finally, consider adding **error handling for failed syncs** that surfaces to the WP Admin UI. Right now, if a deal fails to create (e.g., network error or invalid pipeline ID), the error is logged but a user in WP might not know. You could add an admin notice or a log entry in a custom post meta so that you can easily see in the Order Management page which orders failed to sync. This is an enhancement for user experience.

## Issue Resolution & Development Instructions

Below are specific fix instructions for each major issue identified. Each set of steps can be handed off to a developer to implement in the code. (After making these changes, it’s important to thoroughly test the integration – create a few test orders of each type and verify the behavior in HubSpot.)

### Development Instructions: Secure HubSpot API Endpoints

1. **Restrict the `/get-token` endpoint:** In **hubspot-auth.php**, remove or protect the `register_rest_route('hubspot/v1', '/get-token', ...)` call. This is around line 22 in the file. Ideally, delete this route entirely since it’s not needed in production. If you want to keep a token inspection endpoint, use a secure callback:

   ```php
   'permission_callback' => function() { return current_user_can('manage_options'); }
   ```

   This will only allow administrators to use it (when logged in and with a valid nonce or cookie). But again, our recommendation is to remove it to eliminate any risk.

2. **Secure the OAuth endpoints:** For the `/start-auth` and `/oauth/callback` routes (registered in the same place), add a similar `permission_callback` that ensures only logged-in admins can hit them. For example:

   ```php
   register_rest_route('hubspot/v1', '/start-auth', [
       'methods' => 'GET',
       'callback' => 'steelmark_start_hubspot_auth',
       'permission_callback' => function() { return current_user_can('manage_options'); }
   ]);
   ```

   Do the same for `/oauth/callback`. This way, a random visitor cannot initiate the OAuth flow. Only an admin (who would be clicking the “Connect HubSpot” button in WP Admin) will trigger it. The callback endpoint needs to be open for HubSpot’s server to redirect to – but we can require that an admin initiated the process. One way to enforce this is to store a transient or option when the admin clicks “Connect HubSpot” and validate it in the callback. That might be overkill; simply requiring an admin session for the callback is usually enough (the admin will be logged in when they complete the OAuth flow).

3. **Remove token output in response:** Ensure that even with the above protections, you never return the raw tokens in any API response. In **steelmark\_handle\_oauth\_callback**, after saving tokens to the database, you redirect the user to a success page (that’s fine). In `steelmark_get_stored_token`, if you keep it, consider returning only a sanitized status (e.g., “Connected” or the portal ID) instead of the entire token array. But with the route off or protected, this is less of an issue. Also review the admin AJAX actions and confirm none of them echo sensitive data without permission (they generally return success messages or data, which is okay).

4. **Test the security changes:** After implementing, try accessing the endpoints via a browser or curl **when not logged in**. For example, `/wp-json/hubspot/v1/get-token` should now return a 401/403 or be nonexistent. The same for `/start-auth` (it should likely say “forbidden” if not admin). Then test the OAuth flow as admin to ensure you can still connect to HubSpot. Once connected, verify normal functionality (fetching pipelines, syncing deals) still works with the new restrictions in place.

### Development Instructions: Fix Online Order Stage Mapping Timing

1. **Change the hook for auto deal creation:** In **online-order-sync.php**, you currently have `add_action('woocommerce_checkout_order_processed', 'hubspot_auto_sync_online_order', 20, 1)`. We want to use a later hook in the order lifecycle. A good choice is `woocommerce_payment_complete`. This action fires after an order’s payment is completed (for gateways that use it) – in other words, after the order status changes to “processing” or “completed”. It passes the order ID as an argument. Modify or add:

   ```php
   add_action('woocommerce_payment_complete', 'hubspot_auto_sync_online_order', 10, 1);
   ```

   You might remove the `woocommerce_checkout_order_processed` hook for this function to avoid double-triggering. If you anticipate some orders might not go through the payment complete (e.g., COD or bank transfer that remain pending), we can handle those separately (perhaps create deals for them when they move to a paid status manually, or even leave them until an admin triggers something). The key is to avoid creating the deal at the “pending” stage unintentionally. Using `woocommerce_payment_complete` ensures only successful payments trigger the sync.

2. **Update the function if needed:** The `hubspot_auto_sync_online_order($order_id)` function can largely remain the same, but now `$order->get_status()` will likely return “processing” (or “completed” for payment methods that auto-complete) instead of “pending”. This means the mapping look-up will find `online_wc-processing` and get the correct HubSpot stage ID (e.g., your “WC Order Paid – Waiting for Freight” stage). It will then create the deal with that stage. No further changes inside the function are needed for this part, but do remove any logic that might skip if the order is manual – that check can stay (`if (is_order_manual($order)) return;`) since we only call this on payment complete for online orders.

3. **Test the online order sync:** Place a test order on the frontend using a payment method that completes immediately (e.g., credit card, test gateway). After payment, the action should fire and create the deal. Verify in HubSpot that the deal is under the correct pipeline (online pipeline) and in the correct stage (“Processing” stage, not the “Failed” stage). Also test an order that fails or remains pending (e.g., using a check payment or simulating a card decline) – in those cases, `woocommerce_payment_complete` won’t fire, and no deal should be created (which is probably desirable; you likely don’t want deals for failed orders). If you do want deals for those, you could hook into `woocommerce_order_status_failed` or `pending` to create them in the “Failed” stage, but that’s an edge case depending on your sales process.

4. **Keep using status-change sync:** With this change, remember that the general status syncing (`woocommerce_order_status_changed` in **hubspot-pipelines.php**) will continue to work. So if an order later moves from processing to completed, it will update the HubSpot deal stage accordingly. Make sure you have mappings for `online_wc-completed` if you want that (in your provided options, `online_wc-completed` was empty – consider setting that to your “Closed Won” stage if not already). This way, the deal will move to Closed Won when the WooCommerce order is marked completed (perhaps when you finish fulfillment).

### Development Instructions: Fix Manual Order Deal Creation & Identification

1. **Correct the mapping key in manual deal creation:** Open **manual-actions.php** and find the `create_hubspot_deal_manual` function (around line 170). Inside it, locate the section where `$pipeline_id` and `$deal_stage` are determined (Step 3 in the comments). Change the hard-coded `$status_key = "online_wc-processing";` to use the actual order status with the manual prefix:

   ```php
   $status = $order->get_status();
   $status_key = "manual_wc-{$status}";
   ```

   Then `$deal_stage = $mapping[$status_key] ?? hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);` as discussed earlier. Save this change.

2. **Ensure manual orders have `order_type = manual`:** As discussed, implement a hook so that when an admin creates an order, it gets tagged. One approach is to adjust the existing `set_order_type_for_online_orders` function. Currently it skips admin orders entirely. You can modify that to tag admin orders as manual. For example:

   ```php
   if (is_admin() || defined('REST_REQUEST') || php_sapi_name() === 'cli') {
       if (strtolower($existing_order_type) !== 'manual') {
           $order->update_meta_data('order_type', 'manual');
           $order->save_meta_data();
       }
       return;
   }
   ```

   Place this at the top of the function (replacing the current early return for admin). This means when an order is created in the backend, it will now explicitly be marked manual. Alternatively, you can use a separate hook: `add_action('woocommerce_new_order', 'mark_admin_order_manual', 5, 2)` that checks `if (is_admin()) { update_meta(order_type=manual) }`. Either method is fine. The goal is that by the time `create_hubspot_deal_manual` runs (which likely is triggered by an AJAX action from the Order Management page), the order already has `order_type = manual` meta.

   *Important:* Also ensure that your Order Management page (HubSpot Order list) isn’t misidentifying manual orders. That page uses `get_hubspot_pipeline_and_stage_labels()` and displays each order’s `order_type` as a column. If `order_type` was blank before, it showed “—” in the UI. After this change, it will show “Manual” for those orders, which is a nice clarity improvement for your team.

3. **Use the manual pipeline ID consistently:** Double-check that when creating a manual deal, you use the manual pipeline from settings. In the code, `$pipeline_id = get_option('hubspot_pipeline_manual')` is already being used, which is correct. Just ensure that value is set (in your case it is the GUID `1f495427-...`). After the changes, the deal creation API call will use the manual pipeline ID and the stage computed from the manual mapping array.

4. **Test manual deal creation:** Go to WooCommerce, create a new order in the admin (simulate a phone order or inquiry conversion). Set its status to “Pending Payment” (default for new manual orders) and save. Now go to the **HubSpot Order Management** page and use the “Create Deal” button for that order. After running, it should associate a new deal in HubSpot. Verify that deal is in the **manual pipeline** (ID matching `1f495427-...`) and at the stage you mapped for a manual pending order. From your `hubspot_status_stage_mapping`, `manual_wc-pending` was mapped to a GUID (d573c11e-07fa-493e-a9a2-f0571ff6fb19) which I suspect is your “Quote Sent” or initial stage for manual deals. Confirm the deal appears at the correct stage name in HubSpot. Also check that the WP order now has the HubSpot Deal ID, Pipeline, and Deal Stage meta filled (the code updates these on success).

   Next, advance the order in WooCommerce: e.g., mark it “Completed” (to simulate closing the deal). The `woocommerce_order_status_changed` hook should pick that up and move the HubSpot deal to the stage you mapped for `manual_wc-completed` (you had set `634097`, presumably a “Closed Won” stage). Ensure that happens – the deal in HubSpot should move to Closed Won. If it doesn’t, check the debug log for any “\[HubSpot Sync] ⚠️ HubSpot stage is empty for key 'manual\_wc-completed'” messages. If you see that, it means the mapping for that status wasn’t saved or retrieved correctly. In that case, update the mapping in the plugin settings (Pipelines tab) to include Completed for manual pipeline.

5. **Handle manual edge cases:** If there are other manual statuses you use (like a “Manual order Processing” or On-Hold), consider mapping those in settings as well. The pipeline sync will skip any status that isn’t mapped (to avoid mistakes), logging a warning. It’s okay to leave some blank if you intentionally don’t want to move the deal for those statuses. Just be aware of what’s configured so you’re not surprised by a lack of movement.

### Development Instructions: Update HubSpot Deal Stage on Invoice Sent

1. **Modify the invoice AJAX handler:** In **manual-actions.php**, locate the `send_invoice_email_ajax` function (search for `wp_ajax_send_invoice_email`). After the lines that update the order meta to “Invoice Sent”, insert a call to update the HubSpot deal stage. You will use the same `$invoice_stage_id` that was retrieved from settings. For example:

   ```php
   if ($invoice_stage_id) {
       update_hubspot_deal_stage($order_id, $invoice_stage_id);
   }
   ```

   Make sure this occurs before sending the JSON success response. You might also remove the conditional logging that was there. The `log_email_in_hubspot` call can remain to log the email event, but pass only `'invoice'` as the type (omit the stage id parameter as we discussed). So, change:

   ```php
   if ($invoice_stage_id) {
       log_email_in_hubspot($order_id, 'invoice', $invoice_stage_id);
   } else {
       log_email_in_hubspot($order_id, 'invoice');
   }
   ```

   to simply:

   ```php
   log_email_in_hubspot($order_id, 'invoice');
   ```

   (after you’ve updated the stage via `update_hubspot_deal_stage`). This ensures the deal moves stage and the email is logged with a proper subject/body.

2. **Verify function availability:** Ensure the function `update_hubspot_deal_stage($order_id, $stage_id)` is loaded before this AJAX call is used. It’s defined in **send-quote.php**. In your current setup, it likely is loaded (since you include send-quote.php early or in functions.php). Just double-check that `manual-actions.php` can call `update_hubspot_deal_stage` (if not, you may need to include or require the file where it’s defined at the top of manual-actions.php). Given the code structure, it’s probably already included via your combined plugin loading.

3. **Test the invoice stage sync:** Create or use an existing WooCommerce order that’s synced to a HubSpot deal. For example, use the Order Management page to send a quote, accept it (to put the deal in Quote Accepted stage), then click “Send Invoice” for that order. After triggering the invoice send AJAX, check HubSpot: the deal should have moved to the “Invoice Sent” stage (whichever stage ID you configured, e.g., `44436bec-31cb-481d-a835-c9bc3cddebb3` from your options). Also ensure the timeline entry “Invoice Sent for Order #XYZ” appears on the deal’s timeline (from the `log_email_in_hubspot` call). If the stage did not change, recheck that the `update_hubspot_deal_stage` line ran – you might watch your PHP error log for any errors when that AJAX runs. If there’s an error like “Undefined function update\_hubspot\_deal\_stage”, it means the function wasn’t loaded – in that case, include send-quote.php at the top of manual-actions.php or load all these in your main plugin file (which you will do during plugin conversion).

4. **Quote/Invoice stage settings usage:** With both Quote and Invoice stage updates in place, your configured option fields (`hubspot_stage_quote_sent_*`, `hubspot_stage_quote_accepted_*`, `hubspot_stage_invoice_sent_*`) are now fully utilized:

   * Quote Sent – when you click “Send Quote”, the deal moves to this stage (already implemented in send-quote.php).
   * Quote Accepted – when the customer accepts (via the link), the deal moves to this stage (already implemented in handle\_quote\_acceptance).
   * Invoice Sent – now the deal will move to this stage when you send the invoice.

   Just ensure those options are filled in your settings for both pipelines (manual/online) as needed. In your case, you had values for manual pipeline but “online” ones were null (likely because you might only use quotes/invoices for manual orders). That’s fine – the code checks the correct one based on order type. Just maintain these through the WP Admin settings UI as your process evolves.

### Development Instructions: General Code Improvements & Plugin Hardening

1. **Prefix or Namespace Functions:** Go through each custom function that’s in the global scope and add a unique prefix. For instance, `function send_invoice_email_ajax` becomes `function hubwoo_send_invoice_email_ajax`. Do this for: `manual_sync_hubspot_order`, `create_hubspot_deal_manual`, `send_invoice_email_ajax`, `handle_quote_acceptance`, `send_invoice` (the helper inside send-quote.php), etc. Also prefix the JavaScript `ajaxurl` calls if needed (though those use `action` names which you might also change to include a prefix, e.g., `action: 'hubwoo_send_invoice_email'` – not strictly necessary but could be done for consistency). After renaming, update the `add_action('wp_ajax_...')` hooks to the new function names. This will prevent any collisions with similarly named functions from other plugins. It will also make it clearer in logs and stack traces that these functions belong to the HubSpot sync plugin.

2. **Remove Redundant Code:** Clean up the double call in quote acceptance:

   * In **send-quote.php**, inside `handle_quote_acceptance`, remove the first block that calls `update_hubspot_deal_stage` (lines 115-121) or the second one (125-129). One is enough. We suggest keeping the one after `$manual = is_order_manual($order)` for clarity and removing the earlier one. Also replace the `function_exists('order_type')` logic with a direct check:

   ```php
   $manual = is_order_manual($order);
   $quote_accepted_stage_id = $manual 
       ? get_option('hubspot_stage_quote_accepted_manual') 
       : get_option('hubspot_stage_quote_accepted_online');
   if ($quote_accepted_stage_id) {
       update_hubspot_deal_stage($order_id, $quote_accepted_stage_id);
   }
   ```

   This is more straightforward. (This aligns with what you have, just minus the duplicate.)

3. **Validate API call results:** Add checks for API responses where missing. For example, in `hubspot_create_deal_from_order` (in **create-object.php**), after the `wp_remote_post`, you do check the status code and decode the body. That’s fine. In functions like `hubspot_get_or_create_contact` and `..._company`, you already check for errors and existing results. This is generally good. One improvement: if an API call fails (returns WP\_Error or no `id` in response), you might want to log a specific error and halt the process gracefully. For instance, if creating a deal returns null, you currently `return` without notifying the user (for auto-sync) or do `wp_send_json_error` (for manual create, which is good). For auto-sync, since it’s silent, consider logging an error to WooCommerce order notes too, something like `$order->add_order_note("❌ HubSpot deal creation failed: $reason")`. This way an admin looking at the order can see there was an issue. It’s optional, but helps debugging in the future.

4. **Optimize HubSpot API usage:** You have caching for pipelines and stages (`hubspot_cached_pipelines` option) which is great. Make sure to call `HubSpot_WC_Settings::refresh_pipeline_cache()` whenever needed (it’s hooked on settings save, which is good). Also note that `get_hubspot_pipeline_and_stage_labels()` compiles a flat list of stage labels each time it’s called by reading the cached option. This is fine given the small number of pipelines. If performance ever became an issue, you could cache that in a static variable. Not a big concern now.

5. **Deactivate and Cron cleanup:** If you convert this to a plugin, implement an activation and deactivation hook. On activation, you may want to create the DB table for tokens if it doesn’t exist (currently the code assumes it’s there). You can do this with `dbDelta` or a simple SQL CREATE TABLE if not exists. On deactivation, clear the cron event for token refresh (`hubspot_token_refresh_event`) by calling `wp_clear_scheduled_hook('hubspot_token_refresh_event')`. This prevents orphaned cron jobs if the plugin is deactivated.

6. **Documentation and Settings:** After all these changes, update any inline comments or README (if exists) to reflect new behavior (e.g., “Deals are now created on payment completion instead of order creation”). Also, educate the team that **admin orders now need to click “Create Deal”** (which you’re already doing) and they’ll sync at the correct stages. The improved messaging in the UI (order notes on success/failure, visible manual tag) will help here.

## Development Instructions for Plugin Conversion

Turning this integration code into a well-structured standalone plugin will make it easier to maintain and deploy. Here’s how to proceed:

1. **Organize Files:** Create a new folder in `wp-content/plugins/`, for example `hubspot-woocommerce-sync` (or a name of your choosing). Inside, create a main plugin file, e.g. `hubspot-woocommerce-sync.php`. At the top of this file, add the plugin header comment:

   ```php
   <?php
   /**
    * Plugin Name: HubSpot WooCommerce Sync
    * Description: Integrates WooCommerce with HubSpot (deals, contacts, and pipelines sync).
    * Version: 1.0.0
    * Author: Your Name/Company
    */
   if (!defined('ABSPATH')) exit;
   ```

   This marks the plugin so WordPress recognizes it.

2. **Merge or Include Existing Code:** You have several PHP files (settings, auth, sync logic, order management UI, etc.). You can keep them as separate files for clarity and include them, or merge into the main file. A good structure is:

   * `hubspot-woocommerce-sync.php` (main file that sets up classes and includes others)
   * `includes/class-hubspot-wc-settings.php` (from your HubSpot\_WC\_Settings class)
   * Other functionality as either classes or included files: e.g. `includes/hubspot-auth.php`, `includes/order-sync.php`, `includes/manual-sync.php`, etc.

   In the main plugin file, after the header, do something like:

   ```php
   require_once plugin_dir_path(__FILE__) . 'includes/class-hubspot-wc-settings.php';
   require_once plugin_dir_path(__FILE__) . 'includes/hubspot-auth.php';
   require_once plugin_dir_path(__FILE__) . 'includes/online-order-sync.php';
   require_once plugin_dir_path(__FILE__) . 'includes/manual-actions.php';
   // ...include any other files (order management UI, etc.)
   ```

   This will load all your code when the plugin activates.

3. **Remove Theme Dependencies:** In `hubspot-auth.php`, you load `variables.php` from the theme directory. Move the contents of `variables.php` into a safe place. Ideally, convert those values into plugin settings or define them as constants in the main plugin file. For example, you could add fields in the HubSpot Settings page for “Client ID” and “Client Secret”, and store them in options (similar to how you store pipeline info). If you want a quicker solution, define them in the plugin file:

   ```php
   define('HUBSPOT_CLIENT_ID', 'your-client-id');
   define('HUBSPOT_CLIENT_SECRET', 'your-client-secret');
   define('HUBSPOT_REDIRECT_URI', site_url('/wp-json/hubspot/v1/oauth/callback'));
   ```

   Then in `hubspot-auth.php`, remove the code that includes `variables.php` and instead use those constants. This keeps everything within the plugin. Be sure to treat the secret carefully (don’t accidentally commit it to public repos, etc., since it’s sensitive).

4. **Adjust Paths:** Wherever the code uses `get_template_directory()` or `get_stylesheet_directory()` for plugin assets, change it to use `plugin_dir_path(__FILE__)` or similar. For example, if you had email templates in the theme, you might leave that – but since this is a plugin, perhaps move any email templates into the WooCommerce `email` overrides directory in the theme if they aren’t already. The main one was using `get_stylesheet_directory() . '/woocommerce/'` to load custom email templates for quotes/invoices. If those templates are part of your theme, that’s okay; just ensure they remain accessible. No change needed there unless you plan to ship templates with the plugin (which is more complex). The key path changes will be for includes and assets within the plugin.

5. **Initialize Hooks in the Plugin:** The HubSpot\_WC\_Settings class has an `init()` method hooking admin\_menu, etc.. In your main plugin file, after including everything, call:

   ```php
   HubSpot_WC_Settings::init();
   ```

   This will register the admin menu and settings. Also consider hooking your token refresh cron schedule on activation. You might move the `schedule_hubspot_token_refresh()` call into an activation hook:

   ```php
   register_activation_hook(__FILE__, 'hubwoo_activation');
   function hubwoo_activation() {
       // Create hubspot_tokens table if not exists (you can use $wpdb for this).
       // Schedule cron for token refresh:
       if (!wp_next_scheduled('hubspot_token_refresh_event')) {
           wp_schedule_event(time(), 'thirty_minutes', 'hubspot_token_refresh_event');
       }
   }
   register_deactivation_hook(__FILE__, 'hubwoo_deactivation');
   function hubwoo_deactivation() {
       wp_clear_scheduled_hook('hubspot_token_refresh_event');
   }
   ```

   You’ve already defined the custom interval (though note: it’s labeled ten\_minutes but set to 30 minutes – you may want to rename that to “thirty\_minutes” for clarity). Ensure that filter runs on plugin load (you can call it in main file or keep it in auth include as it is). With these activation hooks, when the plugin is activated, it will set up the DB table and cron job; on deactivation, it’ll clean the cron.

6. **Deploy and Test as Plugin:** Once all files are in place and referenced correctly, go to WP Admin -> Plugins and activate “HubSpot WooCommerce Sync”. Confirm that the “HubSpot” menu appears in WP Admin (under WooCommerce or as a top-level per your code). Test the full functionality:

   * Connect to HubSpot (if not already connected) via the OAuth button. Ensure the redirect URI is correct (with the plugin, `site_url/wp-json/hubspot/v1/oauth/callback` remains valid).
   * Verify that pipelines load in the settings page (the cached pipelines function should fetch and store them – check the `hubspot_cached_pipelines` option gets populated).
   * Create an online order, see that it creates a deal (with all the fixes above) in the right stage.
   * Create a manual order, use “Create Deal”, ensure that works and stages update on status changes.
   * Use the Abandoned Carts pages (if you plan to keep that feature) to ensure nothing broke there (we didn’t modify it, but since we moved to a plugin, just verify those submenu pages still render if needed).
   * Check that all admin pages (Order Management table, etc.) load without PHP errors. Because we included files in a different order, if any function was used before being declared, you’d get an error. Adjust the include order if needed (for example, if manual-actions calls a function in send-quote, include send-quote.php first).

   Address any undefined function errors by reordering or by wrapping code in `function_exists` checks (though reordering includes is preferable so everything is available).

7. **Apply WordPress Coding Standards:** As a final polish, run the code through a linter or PHP\_CodeSniffer with the WP ruleset. This will catch any missing escaping or improper formatting. For example, ensure all translatable strings (if any) are wrapped in `__()` or `_e()`, though I suspect this plugin is not localization-focused. Ensure your file and class naming is consistent (Class names in StudlyCase, etc., which you have). None of this changes function, but it makes the plugin meet WP repository standards if you ever distribute it.

8. **Version Control and Deployment:** Put this plugin code under version control (if not already). When deploying to your site, remove or deactivate any old code that was in the theme (to avoid double execution). Since you mentioned “directory from my website”, presumably you will delete that old directory or at least ensure it’s not included anymore. Activate the new plugin and monitor logs for any unexpected issues.

By following these steps, you’ll transform the custom code into a maintainable plugin structure. This will simplify future updates – for instance, if HubSpot changes an API, you can update the plugin files in one place. It also makes it easier to install this integration on another site if needed, just by activating the plugin and configuring the settings.

---

By implementing all the above fixes and improvements, you will have:

* Closed the security holes (no more token leakage or unauthorized access).
* Ensured deals sync to HubSpot in the correct pipelines and stages for both online and manual orders (fixing the mapping issues you faced).
* Improved reliability (with proper checks and error handling) and maintainability (with a plugin format, cleaner code, and best practices).

Each set of changes should be tested in a staging environment if possible before going live. Once confirmed, you can confidently use this integration knowing it follows WordPress standards and your sales workflow accurately. Good luck, and feel free to iterate further on this foundation as your business needs grow!

– *Example of insecure REST route exposing HubSpot tokens. This has been tightened with proper permission callbacks in the solution above.*
– *Online order deal creation logic, which fell back to the first stage if no mapping. The solution ensures this code runs at the right time (after payment) so the correct stage mapping is found.*
– *Manual deal creation code using the wrong mapping key (“online\_wc-processing”). The updated code uses the manual order’s status for stage mapping.*
– *Invoice send logic where deal stage wasn’t updated. The fix adds an update call using the retrieved `invoice_stage_id`.*
