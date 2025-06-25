Recommended Improvements & Best Practices

Secure the HubSpot OAuth Integration: The highest priority is to lock down the REST API endpoints. The get-token endpoint should be removed entirely from production code – it’s meant for debugging but exposes your tokens. If you need to keep it, restrict access by adding a permission_callback that checks for an administrator capability (e.g. current_user_can('manage_options')) and perhaps an extra nonce. Similarly, the OAuth start (/start-auth) and callback routes should require an admin user. Typically, you’d initiate OAuth from the WP admin (where the user is already logged in as admin), so you can afford to require is_user_logged_in() and capability checks in those callbacks. This will prevent unauthorized hijacking of the connection. In summary: update the register_rest_route calls to include proper permission_callback functions instead of __return_true, and remove or guard any debug endpoints that return sensitive info.

Protect Tokens and Credentials: Consider moving the client ID and secret out of the theme and into a safer location. For example, during plugin conversion (see below), you might put these in constants in the plugin’s main file or use the database (with update_option) to store them after an admin enters them. If you keep using a file, place it in the plugin folder and load it similarly, but ensure it’s not web-accessible (a PHP file with no direct output is fine). Also, once the REST endpoint is secured, the tokens in the database are less exposed. But you might still want to encrypt them or at least ensure no other part of the site can accidentally leak them. At minimum, do not log full tokens in any circumstances. You already truncate the token in some logs, which is good. Continue that practice or remove those logs in production.

Use Nonces and Capability Checks Everywhere: For your admin AJAX actions, you are already generating and checking nonces (e.g. check_ajax_referer('send_invoice_email_nonce', 'security') in send-quote.php and similar in other functions). This is good. To strengthen it, add an explicit user capability check in each AJAX handler. For example, at the top of send_invoice_email_ajax() in manual-actions.php, do: if (! current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized', 403);. This ensures the user is a shop manager or admin. It’s a small hardening step. Also ensure that any form that triggers these actions (like the Import form on the Order Management page) is only visible to authorized users (it already is, due to the admin menu capability). Going forward, stick to using WordPress’s nonce and user capability system for any new admin actions.

Improve Order Type Handling: To avoid confusion between manual and online orders, the code should consistently tag orders with an order_type. Currently, front-end orders get tagged as 'online' automatically. We recommend that any order created via the admin dashboard or via external APIs be explicitly tagged as 'manual'. You can achieve this by modifying the set_order_type_for_online_orders function (in online-order-sync.php) or adding a new hook for admin order creation. For example, remove or adjust the is_admin() check so that instead of skipping admin orders, it tags them:

if (is_admin() || defined('REST_REQUEST') || php_sapi_name() === 'cli') {
    // For admin or external creations, mark as manual (if not already set)
    if (strtolower($existing_order_type) !== 'manual') {
        $order->update_meta_data('order_type', 'manual');
        $order->save_meta_data();
    }
    return;
}

This way, any order not created by a normal customer checkout will be labeled 'manual'. This will make the pipeline selection logic clearer – is_order_manual() will yield true for all admin orders, ensuring they use the manual pipeline settings. It also helps your team see at a glance in the order meta what type it is.

Fix Pipeline Stage Mapping Logic: Ensure that deal creation uses the correct stage mapping for both online and manual orders. For online orders, the timing issue should be addressed: ideally, create the HubSpot deal after payment is complete (so the order status is accurate). The simplest fix is to use a different WooCommerce hook. Instead of woocommerce_checkout_order_processed, you could hook into woocommerce_payment_complete or woocommerce_order_status_processing (which fire when an order transitions from pending to processing after successful payment). This way, when the code runs to create the deal, the order status will be “processing” (or even “completed” for instant payment methods), and your mapping online_wc-processing -> (your stage) will be applied. That would prevent deals from defaulting to the “pending payment” stage. Another approach is to keep the current hook but add logic: if the order status is “pending” at checkout, perhaps delay deal creation slightly or update it immediately when the status changes. However, using the proper hook is cleaner. We recommend hooking deal creation to the payment success event. This ensures online deals start in the correct stage. (Don’t worry – for orders that never get to processing because payment fails or is COD, you can still create deals on the “pending” stage if desired. But since your pipeline mapping suggests you treat pending as a failed stage, it may be acceptable not to create a deal until payment is made.)

For manual deals, correct the hard-coded status key. In manual-actions.php, find where $status_key = "online_wc-processing"; is set in create_hubspot_deal_manual. This should be changed to use the actual order’s status and the manual prefix. For example:

$pipeline_id = get_option('hubspot_pipeline_manual');
$status = $order->get_status();  
$status_key = "manual_wc-{$status}";
$mapping = get_option('hubspot_status_stage_mapping', []);
$deal_stage = $mapping[$status_key] ?? hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);

This way, if the manual order is in status “pending” (which it likely is if you just created it to send an invoice), it will use your mapping for manual_wc-pending (which in your saved options is set to a specific stage ID). If that mapping is blank, it will fall back to the first stage of the manual pipeline (which might be okay for your process). This change will ensure manual deals start at a logical stage.

After making these fixes, test both an online checkout and a manual order:

Online paid order should create a HubSpot deal in the correct pipeline (online pipeline) and in the stage mapped to “Processing” (rather than the first stage).

Manual order: when using “Create Deal”, it should go to the manual pipeline at the stage mapped for its current status (e.g., Pending Payment → whatever you mapped, possibly a “Quote sent” or similar stage for manual).

Additionally, with manual orders properly labeled, the pipeline sync on status changes will also behave correctly. The sync_order_status_to_hubspot_pipeline function already checks the order type and uses the appropriate mapping key. By fixing the initial stage and the order_type, subsequent status transitions (e.g., manual order from pending to completed) will update the HubSpot deal to the right stage (your mapping for manual_wc-completed, which you set to the ID 634097, presumably “Closed Won”). Make sure all desired status→stage pairs are mapped in settings so the sync doesn’t log a warning for an unmapped status.

Ensure All Deal Stages Are Updated on Workflow Events: You’ll want to patch the missing stage update for invoice sent. The solution is to call update_hubspot_deal_stage for the invoice stage, similar to how quotes are handled. In manual-actions.php send_invoice_email_ajax(), after triggering the WooCommerce invoice email and updating the meta, add a call to update the deal stage. For example:

if ($invoice_stage_id) {
    update_hubspot_deal_stage($order_id, $invoice_stage_id);
}

This should be done before or in place of the log_email_in_hubspot call. In fact, log_email_in_hubspot doesn’t accept a stage ID parameter in a way that updates the deal – it’s only using that parameter to potentially override the email subject (which is not what we want here). So, the clean approach is:

Remove passing $invoice_stage_id into log_email_in_hubspot. Instead, call update_hubspot_deal_stage($order_id, $invoice_stage_id) to actually move the deal to “Invoice Sent” stage.

Then call log_email_in_hubspot($order_id, 'invoice') without the override, so it logs the event with the standard subject (“Invoice Sent for Order #X”).

This way the HubSpot deal will progress to the stage you configured (e.g., the GUID or ID you have for "Invoice Sent" in the manual pipeline), and an email timeline event will still be logged on the deal. After implementing, test by sending an invoice from the Order Management page – the deal in HubSpot should move to the “Invoice Sent” stage (you can verify in HubSpot UI).

Similarly, you might review the Quote Accepted flow: currently it updates the status to pending payment and moves the deal to the “Quote Accepted” stage. That’s fine. The minor improvement there is to remove the duplicate stage update. You can delete one of the two update_hubspot_deal_stage($order_id, $accepted_stage_id) calls to avoid redundant API calls (either the one within the if ($quote_accepted_stage_id) or the one right after it, since they do the same thing). Also, you can simplify by using is_order_manual($order) to decide which option key to use, instead of the undefined order_type() function. These tweaks will clean up the code but won’t change functionality.

Reduce Logging Noise and Protect Data: In production, it’s best to minimize extensive logging. Currently, the plugin liberally uses error_log() to record debug information (e.g., dumping pipeline data, API responses, etc.). While this is useful during development, it can slow down execution and clutter your logs. It can also inadvertently expose customer or deal information in log files. We recommend wrapping these logs in checks so they only run when debugging is enabled. For example, you can do:

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log("[HubSpot Sync] ... debug info ...");
}

around large print_r dumps or remove them entirely once the integration is stable. At a minimum, remove any logging of access tokens or sensitive personal data. Keep logs for important errors (like “failed to refresh token” or “API request failed”) but consider using error_log only for errors, not routine operation. This follows the principle of failing quietly in production unless an admin needs to be alerted.

Code Organization and Prefixes: As you convert this into a plugin, take the opportunity to namespace or prefix functions to avoid collisions. One approach is to wrap most of this functionality into a class (or a few classes) within the plugin. For example, you might have a main class Hubspot_Woo_Sync that inits all the hooks, and perhaps separate classes for “DealsSync”, “AdminUI”, etc. If refactoring into classes is too time-consuming, at least prefix global function names with a unique string (e.g., hubwoo_ or similar). For instance, rename send_invoice_email_ajax to hubwoo_send_invoice_email_ajax. Update the add_action('wp_ajax_...') accordingly. Do this for all custom functions not part of a class. This will prevent any naming clashes with other plugins or themes. It also makes it clearer that these functions are part of your integration.

Follow WordPress Plugin Best Practices: Ensure all output is escaped (you did well in the settings and table HTML – continue that). Use WordPress APIs when available (for example, use update_option/get_option which you are doing, use wp_nonce_field/check_admin_referer for form submissions, etc.). Also, handle return values from API calls carefully – some of your functions assume the remote call succeeded and immediately decode JSON. It might be good to check the HTTP status or is_wp_error in every call (you do in most, but double-check all, e.g., hubspot_create_deal_from_order does check $response status code, which is good). Little consistency fixes like that will make the code more robust if HubSpot’s API has a hiccup.

Finally, consider adding error handling for failed syncs that surfaces to the WP Admin UI. Right now, if a deal fails to create (e.g., network error or invalid pipeline ID), the error is logged but a user in WP might not know. You could add an admin notice or a log entry in a custom post meta so that you can easily see in the Order Management page which orders failed to sync. This is an enhancement for user experience.
