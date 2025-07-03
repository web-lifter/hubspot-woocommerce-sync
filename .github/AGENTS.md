Great, I’ll now perform a comprehensive code review of the current plugin and develop a detailed plan to implement and enhance the following features:

1. A new HubSpot property import and WooCommerce mapping system for Contacts, Companies, Deals, Line Items, and Products.
2. Debugging and expanding the existing order cleanup logic to support multiple statuses and ensure it functions as expected.
3. Implementing a webhook endpoint that catches all HubSpot event subscriptions and updates WooCommerce orders/products according to user mapping settings.

I’ll review the relevant code including `order-cleanup.php`, verify integration points, and provide a clear, structured implementation plan for each feature.

# HubSpot WooCommerce Sync New Feature Development Plan

## 1. HubSpot Property Import & Field Mapping

**Goal:** Introduce a flexible mapping system for HubSpot object properties to WooCommerce fields (and vice versa). This will allow administrators to choose which HubSpot fields (from Deals, Contacts, Companies, Line Items, and Products) should sync to which WooCommerce fields, instead of using the current hard-coded mappings. When creating or updating WooCommerce orders from HubSpot deals (and when creating or updating HubSpot deals from Woo orders), the plugin will use these mappings to transfer data accordingly.

**Proposed Implementation Overview:** We will add a new **“Properties”** tab in the plugin’s settings page where users can import available HubSpot properties for the relevant objects and define field mappings. Under the hood, this involves fetching property definitions via the HubSpot API, storing them in WordPress (as cached options), providing a UI for mapping, and modifying the sync logic to use these mappings.

**Step 1: Fetch and Store HubSpot Properties.** We will use HubSpot’s API to retrieve the list of properties for each object type we care about: Deals, Contacts, Companies, Products, and Line Items. HubSpot provides endpoints like `GET /crm/v3/properties/{objectType}` (for example, `/crm/v3/properties/deals` for deal properties). Using the stored OAuth access token, we can call these endpoints to get all property names, labels, and types. We don’t want to do this on every page load (as it’s an expensive API call), so a good approach is to fetch and **cache** the results when the admin opens the Properties tab (or on plugin setup). For instance, when the Properties tab is first loaded, if we don’t yet have cached property lists, call each endpoint and save the results into WordPress options such as:

* `hubspot_properties_deals` (array of deal property definitions),
* `hubspot_properties_contacts`,
* `hubspot_properties_companies`,
* `hubspot_properties_products`,
* `hubspot_properties_line_items`.

Each option could store an associative array mapping property name → label (and possibly type). Storing just name and label is likely sufficient for building the UI. This way, the plugin knows what fields are available on the HubSpot side. (We will also provide a “Refresh Properties” button to manually re-fetch in case the user adds new custom properties in HubSpot later.)

**Step 2: Extend the Settings UI with a “Properties” Tab.** In `HubSpot_WC_Settings::render_settings_page()`, we’ll add another nav-tab for “Properties” alongside Authentication, Pipelines, and Orders. When this tab is active, we will output a form interface to define mappings. The UI needs to allow the user to map any HubSpot property to a WooCommerce field. We should organize this by object type for clarity. For example, we can have sub-sections for **Deal Fields**, **Contact Fields**, **Company Fields**, **Product Fields**, and **Line Item Fields**. Under each, the admin can select which HubSpot property maps to which WooCommerce field.

* **HubSpot Fields:** For each object type, provide a dropdown (or list of checkboxes) of HubSpot properties (populated from the cached property list fetched in Step 1). Because there could be many fields, we may want to allow searching in the dropdown. Simpler implementations might list all fields, but a search or filtering UI (perhaps using select2 or similar) would improve usability for large field sets.
* **WooCommerce Fields:** For deals (orders), WooCommerce fields include standard order fields (billing\_first\_name, billing\_last\_name, billing\_email, billing\_phone, billing\_address\_1, billing\_address\_2, billing\_city, billing\_state, billing\_postcode, billing\_country, shipping equivalents, billing\_company, order notes, etc.), as well as custom meta fields. We should provide a dropdown of common order fields. We also want to allow mapping to custom meta keys – one way is to have an option in the dropdown like “Custom field…” which, when selected, reveals an input box for the meta key name. This way, an admin could map a HubSpot property to a specific order meta field by name.
* We need to capture **bidirectional intent**: generally, if a field is mapped, we will use it in both directions (HubSpot → Woo and Woo → HubSpot), but we may document that the WooCommerce field will be overwritten by HubSpot when importing a deal, and sent to HubSpot when exporting a deal. (If needed, we could allow one-way mapping, but that adds complexity; for now assume symmetrical sync for the chosen fields.)
* The UI might be a table or list of mapping pairs. For example, under “Deal to Order Field Mappings”, we could present multiple rows: each row has a HubSpot Deal property select and a WooCommerce Order field select. The admin can add as many mappings as needed. We can include a few rows by default (pre-populated with the plugin’s current defaults for convenience, e.g., map `address_line_1` → Billing Address 1, `city` → Billing City, `deal_notes` → Order Customer Note, etc., to illustrate usage). We’ll also include properties like `amount` (deal amount) if needed – though typically order total is calculated from line items, but we might allow mapping deal `amount` to some field or use it for validation.
* Similarly, for Contact mappings: allow mapping HubSpot contact properties (e.g., contact email, phone, address) to WooCommerce order fields. For example, map HubSpot `email` → Order Billing Email, `phone` → Order Billing Phone, etc. (The plugin already does email and phone by default in code; we’ll formalize that through mapping). We may pre-populate those as default mappings.
* Company mappings: likely just Company Name → Order Billing Company by default, but users might also want Company phone or address if they have those fields – we can allow it if WooCommerce had a place (Woo doesn’t have a company address separate from billing, but one could map Company address to Billing address fields if desired, though that could conflict with contact address mapping; this might be an edge case to note).
* Product mappings: If the user wants to sync product information, we need to allow mapping HubSpot **Product** properties to WooCommerce **Product** fields. This is separate from orders – it’s a mapping for product sync. We can include a section for “Product Field Mappings” where HubSpot product fields (name, price, description, SKU, etc.) map to WooCommerce product fields (product title, regular price, description, etc.). This mapping will be used for handling product update webhooks (see Webhooks section below). We will certainly want to map something like HubSpot product `price` → Woo product price, and perhaps product `name` → Woo product name, by default. **Important:** The SKU is the key that links products, so by design we will assume the SKU field on both sides identifies the same product. We likely will **not** let the user change the SKU mapping since it’s fundamental (the plugin already uses SKU to match line items). We may document that the SKU must match; optionally, if HubSpot’s product object doesn’t have a default SKU property, we’ll rely on a custom property (the plugin uses `hs_sku` for line items, we can assume a similar `hs_sku` on product if configured). In the mapping UI, we might display SKU as a mapped field but not allow editing it (or at least inform the user that matching SKUs are required for product sync).

After designing the UI, we’ll use `register_setting()` to save the mappings. We can store mappings in one or multiple options. One approach is to use **one option per object type** for clarity: e.g., `hubspot_deal_field_map`, `hubspot_contact_field_map`, `hubspot_company_field_map`, `hubspot_product_field_map`, `hubspot_line_item_field_map`. Each option can hold an array (or JSON string) that maps HubSpot property names to WooCommerce field identifiers. For instance, `hubspot_deal_field_map` might be an associative array like `['address_line_1' => 'billing_address_1', 'city' => 'billing_city', 'deal_notes' => '_customer_note']`. We’ll define a sanitize callback to ensure the data is stored in a safe format. (Alternatively, we could use a single combined option, but separate options make it easier to handle each mapping group.)

**Step 3: Modify Woo → HubSpot Sync (Deal Creation) to use Mappings.** Currently, when creating a deal from a WooCommerce order, the plugin directly sets certain properties (deal name, amount, pipeline, stage, plus after creation patches shipping, address, etc.). We will refactor this process to reference the new mappings:

* When creating the initial deal payload (in `hubspot_create_deal_from_order()`), beyond the required fields (`dealname, amount, pipeline, dealstage`), we should include any **deal properties** that have a mapping from WooCommerce. For each mapping in `hubspot_deal_field_map` (Woo → HubSpot direction), retrieve the corresponding WooCommerce order field and add it to the `properties` in the API request. For example, if the mapping contains `'deal_notes' => '_customer_note'`, we would get the Woo order’s customer note (`$order->get_customer_note()`) and include it as `properties["deal_notes"]` in the JSON. If `'address_line_1' => 'billing_address_1'`, include `properties["address_line_1"]` from the order’s billing address, and so on. Essentially, we replace the hard-coded `$fields` array in `hubspot_patch_deal_optional_fields()` with a loop over the mapping definitions. (We must be careful to format data appropriately – e.g., combine first and last name if mapping to a single field, or format phone numbers, etc., if needed.)
* For Contact and Company: Currently the plugin creates a HubSpot contact with email, first name, last name, phone only. We can extend this. If the user has mapped additional contact fields (say, Woo billing city -> HubSpot contact city), then when creating a new contact in HubSpot we should include those. We will fetch the mapping for contact (`hubspot_contact_field_map`) and populate the contact creation payload accordingly. For example, if `'address' => 'billing_address_1'` is mapped for contact, include that in `properties` when creating the contact. (Note: HubSpot contact properties might have different naming, e.g., `address` is a single field or they might have separate fields; the mapping should reflect the exact property names as provided by HubSpot API, which we have from the property fetch.)
* Similar logic for companies: if additional company fields are mapped (besides name), use them in the company creation payload in `hubspot_get_or_create_company()`. By default, we at least map company name. If, for instance, a user maps HubSpot company “website” property to some Woo field (not obvious in an order, unless we have customer data), we could include it if available.
* **Line Items and Products:** When creating line items in HubSpot, currently the plugin uses WooCommerce product data (name, price, quantity, tax) to create line item objects. If we plan to leverage the HubSpot **Product** object mapping, a more advanced approach is possible: rather than always creating custom line items, we could check if the WooCommerce product exists in HubSpot’s Product library (by SKU) and then create an association. However, implementing that fully might be beyond scope here unless the user explicitly wants it. For now, we can maintain the current approach (create line items with SKU embedded). The mapping feature doesn’t directly affect line item creation, since line items are built from the WooCommerce order line items. However, we *will* use the product mapping when responding to product updates from HubSpot (in the webhook section).
* After creating the deal, the plugin currently patches optional deal fields (addresses, etc.). With mappings, if we’ve already included those in the initial creation payload, the patch may be partly redundant. However, we might still need a second round for certain fields, especially if some data (like shipping address) wasn’t available at creation time. We can refactor such that **all mapped deal fields** are updated: either include them in creation or patch immediately after. A safe route: include what we can in creation, then patch the rest (or patch everything) according to mapping. (HubSpot’s API allows deal creation with custom properties, so one call may suffice if we prepare it right.)
* We should also update the logic that adds the “PayWay order number” (`_payway_api_order_number` meta) to the deal property `payway_order_number` using the mapping system. For instance, if the plugin user has a custom deal field for PayWay reference, that can be mapped and we populate it accordingly.

**Step 4: Modify HubSpot → Woo Sync (Deal Import) to use Mappings.** When importing a HubSpot deal into a WooCommerce order (either via manual sync or the upcoming webhook), we will reverse the process: use the mapping definitions to know which WooCommerce fields to set from the HubSpot data.

* In `hubwoo_ajax_import_order()` (manual import) and in our webhook handler (to be written), after fetching the HubSpot deal data, we should not assume a fixed set of properties. Instead, iterate over `hubspot_deal_field_map`. For each mapped deal property, take the value from the fetched deal data and set the corresponding field in the Woo order. For example, if there's a mapping for `address_line_1 -> billing_address_1`, we do `$order->set_billing_address_1($deal['address_line_1'])`. The current code already does this for a known subset of fields; we will generalize it. We will have to handle data types properly (e.g., if mapping to an order meta key, use `$order->update_meta_data()`).
* For contact and company associations: the current code fetches the first associated contact and sets billing name/email/phone, and fetches the first associated company to set the billing company. With mapping, we can extend this:

  * Use `hubspot_contact_field_map` to populate **any** mapped fields from the contact. For instance, if contact has a property “mobile phone” mapped to a custom order meta, or “city” mapped to billing city (though that might overlap with deal’s own address), we can set those. Essentially, once we fetch `contact = fetch_hubspot_contact($id)`, we have an array of contact properties; then for each mapping (HubSpot contact property -> Woo field) we set the Woo field. We should be cautious not to override something already set by the deal’s properties if it’s the same field (e.g., if both deal and contact have an address mapping to billing address, the admin should only map one of these to avoid confusion – we might note this in documentation).
  * Similarly, use `hubspot_company_field_map` after fetching the company. For example, if HubSpot company’s `name` is mapped to billing company (default), set it (already done). If company’s address or phone is mapped and the order doesn’t have that info yet, we can set perhaps a custom meta or ignore if there’s no equivalent standard field.
  * Line items: if there are custom properties on line items that the user wants to capture, this is tricky because WooCommerce order line items have limited standard fields (name, quantity, total, tax) and custom meta. If someone wanted, for example, a custom field “delivery date” on each HubSpot line item to transfer to the WooCommerce line item, we could store it as item meta. This could be possible if we allow mapping line item properties to item meta. However, this goes quite deep and may be overkill for now (the user didn’t explicitly request mapping of line item custom fields). We can hold off on detailed line-item property mapping in this phase. The main data for line items (name, price, quantity, SKU) is already handled in code without a custom mapping, but if needed, we could incorporate the mapping by e.g. if a user adds a mapping for a line item property like “hs\_note -> item meta Note”, we apply it per item.
  * Products: The plugin currently doesn’t import product objects from HubSpot in any way; it only deals with line items. If we decide to support creating a WooCommerce Product from a HubSpot deal’s line item (if the product wasn’t found by SKU), that could be an option: using the product mappings, we could instantiate a new WooCommerce product. However, automatically creating products on-the-fly might not be desired unless explicitly needed. More likely, we’ll assume products should exist in WooCommerce already, and if not, the line item is treated as a one-off (as done now). The mapping for products will primarily be used for syncing product updates via webhook (below).
* After applying all mappings, we save the order. We must ensure this works in combination with the plugin’s existing logic. For example, after import, the plugin sets the pipeline and stage meta for the order (to align it with the manual pipeline) and also potentially updates the HubSpot deal’s stage. We will retain this behavior. The mapping system doesn’t change how pipeline/stage are handled (since stage mapping is a separate mechanism). However, if the user had custom deal properties for pipeline or stage name, they could map those to some order meta if they wanted, but that would be informational only – the actual stage syncing uses the mapping options under the Pipelines tab.

**Step 5: Testing and Compatibility:** We will test common scenarios to ensure the mapping works:

* Creating a new deal from a Woo order: verify that all mapped fields appear in HubSpot under the correct properties. For example, if we map “Customer Note” to a HubSpot deal property, create an order with a customer note and see that after sync, the HubSpot deal’s property is set.
* Importing a deal from HubSpot: create a deal in HubSpot with various property values (or update an existing deal), then use the import feature or webhook to bring it into WooCommerce. Verify all mapped fields populate the order correctly (addresses, contact info, etc.).
* We should ensure that if a field is left unmapped, it stays as previously implemented. For instance, if the user doesn’t map anything for address, the plugin might either use default behavior (which we could define as none or some fallback). We likely will rely entirely on mappings for non-critical fields. We will document that to sync a given piece of data, it must be mapped. The plugin’s prior defaults can serve as initial mappings so that out-of-the-box behavior remains the same (to avoid losing existing functionality). For backward compatibility, on upgrade we can pre-populate the mapping options with the current set of fields the plugin was using: e.g., automatically add mapping for each of the fields in `hubspot_patch_deal_optional_fields` and the contact fields (email, first name, etc.) it used. This ensures that after updating the plugin, it continues to sync those fields unless the admin changes it.
* One more thing: we will need to guard against mapping conflicts or impossible mappings (e.g., mapping a text HubSpot field to an integer Woo field). Since WooCommerce order fields are mostly text, and HubSpot properties have types, most will be strings and should map OK. We should be careful with date/datetime fields or numeric fields (if any) – maybe convert formats if necessary. For example, if someone maps a HubSpot date property to a WooCommerce custom field, we might need to format the timestamp to a human date or store timestamp. These details can be handled if relevant based on property type (we can get the type info from the property definitions if needed).

**Summary of Benefits:** This new system will **empower users to sync custom data** between HubSpot and WooCommerce without additional coding. Any custom HubSpot property (for deals, contacts, etc.) can be tied to a WooCommerce order field or meta. For instance, a HubSpot deal’s custom property “Delivery Date” could be mapped to a custom order meta “\_delivery\_date”, and it will be imported into the order and/or exported to HubSpot when the order is created. The mapping covers Products too: if the user has custom fields on HubSpot products (or wants to update price/name), the mapping will guide the update logic (as we will implement in the webhook section). Overall, this change shifts the plugin from a hard-coded integration to a **configurable platform**, accommodating various use cases. It introduces complexity in setup (the user must configure mappings), but by providing sensible defaults we can make the transition smooth.

## 2. Enhanced Order Cleanup (Multiple Statuses & Fixes)

**Goal:** Fix the existing order cleanup cron job (so it actually deletes the intended orders), and extend it to support multiple order statuses and retention periods instead of just one. This will allow the admin to automatically clean up old orders in various statuses (e.g., Pending Payment, Failed, Cancelled) after a configurable number of days.

**Issue Fix (Pending orders not deleting):** As identified in the code review, the current implementation likely fails because of the status format mismatch. We will resolve this by ensuring the status filter in `wc_get_orders()` is correctly specified. WooCommerce’s `wc_get_orders` expects statuses without the `wc-` prefix (for example, `'pending'` instead of `'wc-pending'`). In `hubwoosync_cleanup_orders()`, we retrieve the option `hubspot_order_cleanup_status` (which currently might hold a value like "wc-pending"). We should convert this to the proper format. A simple fix: check if the stored status starts with "wc-", and if so, remove the prefix before querying. For example:

```php
$status = get_option('hubspot_order_cleanup_status');
if (strpos($status, 'wc-') === 0) {
    $status = substr($status, 3); // remove "wc-" prefix
}
```

Then use `$status` in the `wc_get_orders()` call. This should make the query return the intended orders. We will test this fix by creating some pending orders older than the cutoff and running the cron function manually to ensure they get deleted.

**Support Multiple Statuses:** The user wants to clean up multiple statuses (e.g., Pending Payment, Failed, Cancelled, etc.). We have a couple of design options:

* **Single Retention Period for All Selected Statuses:** Simpler to implement – the admin could select multiple statuses (via checkboxes or a multi-select) and specify one “Cleanup After (days)” value that applies to all. For example, if set to 35 days, then pending, failed, and cancelled orders older than 35 days will all be deleted by the cron. This is likely acceptable and easier to understand.
* **Different Retention Periods per Status:** More flexible, but the UI becomes more complex (we’d need to allow specifying a days value for each status or each group of statuses). This could be done with a repeater list of rules (Status + Days). If this level of granularity is desired, it can be implemented, but unless explicitly needed, we might choose the simpler route for now.

Given the request phrasing, it sounds like they mostly want to apply the same rule to multiple statuses (the example: “delete orders in Pending Payment, Failed Payment, Cancelled etc. after X days”). We’ll proceed with one global age and multiple statuses for now.

**Changes in Settings UI:** In the **Orders** tab of settings, the “Cleanup Order Status” field currently is a `<select>` allowing one choice. We will change this to allow multiple selections. A user-friendly way is to list the statuses with checkboxes (e.g.:

```html
<label><input type="checkbox" name="hubspot_order_cleanup_statuses[]" value="wc-pending"> Pending Payment</label>
```

for each status). Or we could use a multi-select box. Checkboxes are straightforward. We’ll list all available statuses (from `wc_get_order_statuses()` which gives `['wc-pending' => 'Pending payment', ...]`) and check those that are in the saved option array. We will introduce a **new option** `hubspot_order_cleanup_statuses` (note: plural) to store an array of selected statuses. This avoids confusion with the old single-selection option. We will mark the old `hubspot_order_cleanup_status` as deprecated but still read it if `hubspot_order_cleanup_statuses` is empty (for backward compatibility).

We will keep the single “Cleanup After (days)” field as is (renaming it maybe to make clear it applies to all selected statuses). If in future we want per-status days, we can extend the UI then.

We’ll update `register_setting('hubspot_wc_orders', ...)` to register the new `hubspot_order_cleanup_statuses` option. WP options can store arrays, but to be safe, we might set `'type' => 'array'` or use a sanitize callback that ensures the value is an array of strings (statuses). On form submission, since we’ll use `name="hubspot_order_cleanup_statuses[]"`, WordPress will likely handle it as an array (the Settings API might serialize it automatically). We need to test that, but typically, it will store as a serialized array in the `wp_options` table.

**Cron Logic Changes:** In `hubwoosync_cleanup_orders()` (in `order-cleanup.php`), instead of a single status, we will get the array of statuses. Example:

```php
$statuses = get_option('hubspot_order_cleanup_statuses', []);
$days = absint(get_option('hubspot_order_cleanup_days'));
if (!$statuses || $days <= 0) return;
$cutoff = strtotime("-{$days} days");
```

We have two ways to proceed from here:

* Use one combined query for all statuses. The WooCommerce `wc_get_orders()` function does allow specifying an array of statuses (e.g. `['pending','failed','cancelled']`). If we pass `'status' => ['pending','failed']`, it should translate to a `post_status IN (...)` query. We would need to remove the `wc-` prefix for each status in the array (like we do for one). We should confirm this works; assuming it does, we can do:

```php
$statuses = array_map(function($s){ return strpos($s,'wc-') === 0 ? substr($s,3) : $s; }, $statuses);
$orders = wc_get_orders([
    'status'        => $statuses,
    'date_created'  => '<' . gmdate('Y-m-d H:i:s', $cutoff),
    'limit'         => -1,
    'return'        => 'ids',
]);
```

Then loop through and delete as before.

* Alternatively, loop through each status and run separate queries per status. This is less efficient but still fine if the number of statuses is small. Something like:

```php
foreach ($statuses as $status) {
    $status = (strpos($status, 'wc-') === 0) ? substr($status, 3) : $status;
    $orders = wc_get_orders([...]);
    foreach ($orders as $id) wc_delete_order($id);
}
```

Either approach works; the single query with multiple statuses is cleaner if supported, so we’ll likely do that.

We will also extend deletion to cover **multiple statuses concurrently**. For example, if an order has status “failed” and older than X days, or status “pending” older than X days, both should be caught. The combined query approach handles that.

**Expand to Multiple Periods?** If needed, we could allow per-status days (e.g., delete pending after 7 days, canceled after 30 days). This would require a more complex UI (perhaps a small table listing each status with a days input). Since the request did not explicitly require different days for each, we will not implement this complexity now. However, we will mention in documentation or comments that currently one retention period applies to all selected statuses.

**Testing:** We will test the cron by manually triggering it (or shortening the schedule for testing). We’ll create dummy orders in various statuses, modify their dates to simulate age, and run `hubwoosync_cleanup_orders()` to ensure they get deleted. We also need to ensure that if the option array is empty or days is 0, the function exits gracefully (which we have in the code with the guard conditions).

We should verify that `wc_delete_order()` fully deletes the order (including removing it from WooCommerce tables). WooCommerce’s `wc_delete_order` will trash or delete the order post and clean associated data (with `wp_delete_post` internally). By default, `wc_delete_order` actually **permanently deletes** the order (it passes `force_delete=true` by default as of Woo 6+ for shop\_order posts). That should be fine. If needed, in documentation we might clarify that this is permanent deletion (the plugin description already calls it "delete", not just trash, so it's expected).

**Cron Scheduling:** The cron event is already scheduled daily on activation. Because we changed the option name (to an array), we should ensure the event continues to run. The event hook name (`hubspot_order_cleanup_event`) stays the same, so the schedule remains. If the user updates the statuses or days, we don’t actually need to reschedule anything – the event runs daily regardless and picks up new settings dynamically each run. That’s fine. We might consider offering a way to disable the cleanup (if no status selected or days blank, it effectively is disabled). Right now, if no status is selected, our function returns early (so nothing happens, which is fine). The scheduled event will still call it, but it exits immediately. That's fine overhead-wise.

**UI/UX:** In the Orders settings tab, we will adjust the wording to plural if needed. For example, change “Cleanup Order Status” label to “Cleanup Order Statuses” and clarify “Select all statuses to automatically delete after the specified number of days.” The rest of the tab (auto-complete orders) remains unchanged.

**Backwards Compatibility:** If a user had already set a status and days in previous version, we don’t want to lose that. We can do a one-time migration in the settings display: if `hubspot_order_cleanup_statuses` is empty but `hubspot_order_cleanup_status` (old single value) is set, we populate the checkboxes with that single value. Also, if we keep the code reading the old option for a while, we could modify `hubwoosync_cleanup_orders()` to check the old option as a fallback. Alternatively, simply instruct users to reconfigure the setting after updating. But a smooth approach: in the function, if `hubspot_order_cleanup_statuses` is empty and old `hubspot_order_cleanup_status` is not, we could treat the old value as an array of one. This way the functionality continues until they update settings (at which point saving will populate the new option).

**Multiple Status Execution:** If multiple statuses are selected, we will be deleting more orders in one go. That’s fine, but we should consider performance if there are many old orders. A single query pulling potentially hundreds or thousands of orders (some stores accumulate many pending orders from bots, etc.). We should consider if this should be rate-limited. Since it runs daily, deleting even a few thousand posts in one run might be okay on a moderately powered server, but on a lower-tier host it could time out if run via WP Cron web request. If necessary, we might implement batched deletion (e.g., delete max 100 orders per run). However, this might overcomplicate for now. We assume that truly large numbers of stale orders would be unusual or the admin can run it multiple days to clear gradually. We can mention in release notes that if there are a huge number of orders to delete, it might not finish in one cron execution due to timeouts – as a mitigation, one could run it manually or increase WP cron timeout, etc. But until proven problematic, we’ll keep it straightforward.

In summary, after this improvement, the **Orders** tab will allow selecting *multiple* statuses to clean up. The cron job will then remove orders in any of those statuses older than X days. This covers the example given (Pending Payment older than 35 days, plus maybe Failed after 35 days as well, etc.).

We will also explicitly verify that statuses like "Failed Payment" or "Cancelled" are properly recognized by `wc_get_orders` when passed (ensuring we use the correct keys: likely 'failed' and 'cancelled' without prefix). According to WooCommerce, order statuses are stored as `wc-{status}` in `post_status`, but `wc_get_orders` expects them without `wc-`. For instance, `'cancelled'` (with two 'l') for Cancelled. We will double-check the key from `wc_get_order_statuses()` (which returns `'wc-cancelled' => 'Canceled'`) and ensure we strip "wc-" and use "cancelled" as query. The code fix covers that.

**Testing Example:** After implementation: If the user checks “Pending Payment” and “Failed” and sets 30 days, the SQL query essentially will fetch all orders with `post_status IN ('wc-pending','wc-failed')` and `post_date < today-30days`. Those will be deleted. The user can include or exclude statuses as needed (e.g., maybe they never want to delete cancelled orders for record-keeping, then they'd not check "Cancelled").

This improvement addresses the immediate bug and extends functionality as requested, giving the admin more control over automatic data retention.

## 3. Webhook System for Real-Time Sync

**Goal:** Implement a webhook endpoint to receive real-time updates from HubSpot (Deals, Contacts, Companies, Line Items, and Products events) and handle them accordingly. This will enable automatic creation of WooCommerce orders when new deals are created in HubSpot, automatic updates to orders when associated records change (contact/company associations, line items added), and automatic updating of WooCommerce products when HubSpot products are modified. Essentially, it closes the loop for two-way sync: changes in HubSpot will immediately reflect on the website without manual intervention.

**HubSpot Webhook Setup:** The user has already configured their HubSpot app to send events to `https://steelmark.com.au/wp-json/hubspot/v1/webhook`. We need to create a REST API route to catch POST requests at `/wp-json/hubspot/v1/webhook`. In `hubspot-auth.php` (or a new file like `hubspot-webhook.php`), we will add:

```php
add_action('rest_api_init', function() {
    register_rest_route('hubspot/v1', '/webhook', [
        'methods'  => 'POST',
        'callback' => 'hubspot_webhook_handler',
        'permission_callback' => '__return_true',  // publicly accessible
    ]);
});
```

This ensures HubSpot can POST to the endpoint without authentication (HubSpot cannot provide WP credentials; instead, we’ll implement our own validation as needed).

We then write the callback `hubspot_webhook_handler($request)`. This function will parse the incoming data and trigger the appropriate actions. HubSpot typically sends a JSON payload. The exact format can vary, but generally, for each subscribed event you might get an array of event objects. For example, HubSpot webhooks often POST a JSON body like:

```json
[
  {
    "eventId": "12345",
    "subscriptionId": 67890,
    "portalId": 111222,
    "occurredAt": 1670000000000,
    "subscriptionType": "deal.creation",
    "objectId": 98765,
    "propertyName": null,
    "propertyValue": null
  },
  {
    "subscriptionType": "deal.propertyChange",
    "objectId": 98765,
    "propertyName": "amount",
    "propertyValue": "1500",
    ... 
  },
  ...
]
```

HubSpot might batch multiple events in one call if they occur close together. Our handler should be ready to handle either a single event object or an array of them.

So in code:

```php
$body = $request->get_body();
$data = json_decode($body, true);
if (!$data) {
    return new WP_REST_Response('No data', 200); // respond OK even if no data, to avoid retries
}
if (isset($data['objectId'])) {
    // Single event (HubSpot sometimes sends one object not wrapped in array)
    $events = [ $data ];
} else {
    $events = $data;
}
```

Then iterate over `$events`.

For each event, we examine:

* The **object type** (could be derived from `subscriptionType` or sometimes given as `objectType` field depending on HubSpot’s format).
* The **event type** (creation, propertyChange, etc).
* The **object ID** (e.g., deal ID, contact ID).
* Possibly associated object IDs if it’s an association event (HubSpot might include details when a contact is associated with a deal, but if not, we might have to query).

Since the user specifically mentioned:

* **Deal creation**: "When a deal is created in HubSpot, automatically create a WooCommerce order."
* **Associations (contact, company, line items) added to deal**: update the Woo order with that info.
* **Product update**: if a product is updated in HubSpot, update the matching product on the site (by SKU).

We will focus on those. We should also consider **deal updates** (e.g., if a deal’s stage changes, do we want to update order status? The user didn’t mention it, but it might be a logical extension. Possibly out of scope for now unless needed for consistency – we can note it but perhaps not implement immediately).

**Deal Creation Event:** On receiving a deal creation webhook (likely subscriptionType `"deal.creation"` or similar):

* **Avoid Duplicates:** First, ensure this deal isn’t already in WooCommerce. It could already exist if it was created from Woo in the first place (for online orders, our plugin would have created the deal and saved `hubspot_deal_id` on the order). If a HubSpot deal was created via our plugin, the webhook will still fire. We should detect that and skip creating a duplicate order. We can do this by searching for an order with meta `hubspot_deal_id` equal to the new deal’s ID. For example:

  ```php
  $existing_orders = wc_get_orders([
      'meta_key'   => 'hubspot_deal_id',
      'meta_value' => $dealId,
      'limit'      => 1,
      'return'     => 'ids',
  ]);
  if ($existing_orders) {
      // Already have this deal in Woo (probably from our own sync), skip
      continue;
  }
  ```

  This check is similar to what we do in manual import. If found, we can break out or just continue to next event. (If an order exists, it means the deal came from Woo originally, so no further action required; we might update it if we wanted to sync changes, but the initial creation is done.)

* **Fetch Deal Data:** Use our `fetch_hubspot_deal($dealId)` helper to retrieve the deal’s properties and associations. We have to be mindful: if the webhook is for creation, it might fire immediately when the deal is created, potentially before all associations (contact, line items) are in place. In many cases, the contact is probably associated at creation (HubSpot requires an associated contact for a deal unless it’s allowed to be blank), but the line items might be added afterwards (depending on HubSpot UI workflow). However, our safest route is to fetch whatever is there. The `fetch_hubspot_deal` function requests associations for contacts, companies, and line\_items. It will return arrays of associated IDs (which could be empty if none yet) and all the deal’s properties it requested (we might want to ensure it requests all relevant properties including amount, shipping, our custom fields, etc. It already requests many custom fields, but if we introduced new custom fields via mapping, we may need to adjust the properties list or fetch generically all properties. Possibly, since we have the properties list from earlier mapping fetch, we could request all or just use the needed ones. For immediate implementation, using the existing `fetch_hubspot_deal` which covers addresses, notes, amount, etc., is fine.)

* **Create WooCommerce Order:** Once we have the deal data, we perform essentially the same steps as the manual import function did:

  * If no existing order, call `wc_create_order()` to make a new order. We should decide what order status to give it initially. Perhaps “pending” by default (as it's an order that likely needs action). We could mark it as “on-hold” or a custom status if representing a quote. The manual import code doesn’t explicitly set status; `wc_create_order()` will create a draft/pending order. It then adds items and saves, which usually ends up as a “pending payment” order unless changed. We can leave it as pending for now, and perhaps the admin will process it or mark it accordingly. (Alternatively, if the HubSpot deal has a stage that corresponds to a certain Woo status via mapping, we could set the order’s status to that. But that might be too detailed for initial implementation. The plugin’s design currently is one-way for status: it sets the HubSpot stage based on Woo status on import, not vice versa.)
  * Populate billing and shipping addresses from the deal properties (using either the new mapping system or the same fields as manual import). The manual import did: billing address fields from deal custom properties and shipping fields from either deal shipping properties or fallback to billing. We will do the same, but now we can use our mapping: effectively, it will map those same fields by default. So either we call the same block of code or write a function to apply mappings (e.g., `apply_deal_mapping_to_order($dealData, $order)` that loops through and sets fields).
  * If the deal had an associated contact (the `contacts` array from fetch), fetch that contact’s details and set the order’s billing name, email, phone, etc. The manual import does this for the first contact. We will do similarly: use `fetch_hubspot_contact($contactId)` to get properties, then apply contact mappings (by default, first/last name, email, phone). This ensures the order has a customer name and email (crucial for WooCommerce orders).

    * **Customer Account**: A consideration is whether to link this order to a WooCommerce customer account if the email matches an existing user or create a new user. The current plugin doesn’t explicitly create WP users for contacts; it just assigns billing email (which means the order will be a guest order unless that email corresponds to a user). We might leave it that way. If needed, we could add logic: if billing email from HubSpot contact matches an existing WP user, assign the order to that user. Or if not, optionally create a new user. This could be a nice feature, but the user didn’t ask for it. We can note it as a possible future improvement. For now, orders created via webhook will likely be guest orders with billing details filled.
  * If the deal had an associated company (companies array), fetch the company and set billing company name (and any other mapped company fields).
  * Add line items: This is a crucial step. If the deal has associated line items already (perhaps the sales rep added products to the deal), `fetch_hubspot_deal` will give us a list of `line_items` IDs. We then loop through them, fetch each line item via `fetch_hubspot_line_item($id)`, and add to the order:

    * Use the SKU from the line item to find a WooCommerce product: `wc_get_product_id_by_sku($sku)`.
    * If found, create a `WC_Order_Item_Product` and set the product, quantity, total, etc..
    * If not found, create a `WC_Order_Item_Product` without an associated product (or a `WC_Order_Item` with type “line\_item”) and set name, quantity, total manually. The manual import code handles this by setting product\_id to 0 and adding meta 'Cost' and 'SKU' for reference. We should replicate that so the order item at least shows something and the admin knows what was ordered. (We might also consider automatically creating a new WooCommerce product in this case, using the data from HubSpot, but that might not be desired if it's a one-off line item or a custom quote item. Likely better to keep as custom line on order.)
    * Add each item to the order.
  * If the deal has no line items at creation time (possible scenario: a deal could be created first, then products added later – in which case this initial import would create an order with no items or amount). Our code should handle that: if `line_items` array is empty, we might leave the order with no items initially and perhaps a note that line items will sync when available. The manual import logs a warning if no line items. We could do similar: add an order note “No line items imported (deal may not have products yet).” And we should be prepared to later handle the line item association events (addressed below) to fill in the order.
  * Set the order totals: After adding items (if any) and shipping (we handle shipping next), call `$order->calculate_totals()`. But note, if the deal had a known total amount (`deal['amount']`), we might want to ensure the Woo order total matches it. The manual import code computed a discount if needed. We can replicate that:

    * Compute `$expected_total = $deal['amount']` (HubSpot deal amount).
    * Compute `$current_total = sum(order line items + shipping)`.
    * If `expected_total > 0` and differs from current, add a fee or discount line item to adjust. In manual import, they added a negative fee named "Manual Discount" if current > expected (meaning HubSpot deal likely had a discount applied). We should do the same to keep totals consistent with HubSpot, especially for cases where the sales rep manually adjusted the deal amount or applied a discount in HubSpot.
  * Shipping and fees: If the HubSpot deal has a shipping amount property (the plugin uses a custom property `shipping` on deals), after items we add a shipping line to the order. We should do that here as well. Use `$deal['shipping']` (if available) to create a `WC_Order_Item_Shipping` with that amount. Similarly, if there’s any other fees field (the plugin code checks `$deal['fees']` in manual import, though it's unclear if HubSpot deals have a fees property unless they added one), we add a fee line item.
  * Mark order meta: Once the order is composed, update the meta fields to link it to HubSpot:

    * `hubspot_deal_id` = the HubSpot deal ID (so we know this order is synced).
    * `hubspot_pipeline_id` and `hubspot_dealstage_id` if we want to store those (the manual import sets these to manual pipeline/stage). We may do the same: likely the HubSpot deal was created in a particular pipeline (maybe the one designated for manual deals). The manual import code actually **overrides** the pipeline to the configured Manual pipeline in Woo meta and picks a stage based on the Woo order status mapping. This might be done to ensure that if the admin later updates the order status, the plugin knows how to update HubSpot (using the manual pipeline mapping). It’s a bit opinionated because it assumes we always treat imported deals as part of the manual pipeline on the WooCommerce side. We can follow that approach: set the order’s `order_type` to 'manual' and assign the configured `hubspot_pipeline_manual` as its pipeline meta. This might differ from the actual pipeline the deal is currently in on HubSpot, but presumably the configured manual pipeline *is* the one the deal is in (the user likely set the app to use a specific pipeline for these manually created deals). If not, we might consider not overriding pipeline, but then stage mapping logic might not find a match if using online mapping by mistake. For consistency, we’ll do what manual import does: treat it as manual pipeline.
    * The stage: manual import picks a stage corresponding to the current order status (which on creation is probably “pending”). It finds `manual_stage_id` for that status and sets meta, then PATCHes the HubSpot deal to set it to that stage. This effectively moves the deal in HubSpot to whatever stage is mapped for “pending” in the manual pipeline. This could be a bit unexpected – it assumes the HubSpot user just created the deal, and we immediately change its stage. But maybe they intended that the act of creating the order in Woo indicates the deal is now in the “order created” stage. We should follow the established logic to maintain uniform behavior. So:

      * Determine WooCommerce order status (likely 'pending' by default for a new order).
      * Use `hubspot-manual-mapping` option to find the corresponding HubSpot stage ID (like the first stage “New” maybe). If found, prepare to update the deal’s stage.
    * We will definitely update the deal’s `online_order_id` property to store the Woo order number (for reference in HubSpot).
  * Save the order and add an order note (e.g. "Order created from HubSpot deal #12345").

* **Update HubSpot deal (optional):** After creating the Woo order, we should consider whether to send any confirmation back to HubSpot via API. The manual import does a PATCH to update dealstage and online\_order\_id. We will do the same:

  * Set `online_order_id` = WooCommerce order number (as a string). This property should have been defined in the HubSpot app's schema (likely it was, since the plugin uses it to store Woo order IDs in deals).
  * Set `dealstage` = \$new\_stage (if we decided to change stage). If we use the mapping logic as above, \$new\_stage might be e.g. “appointmentscheduled” (HubSpot stage ID) for pending – whatever the admin set in the mapping table for manual pipeline.
  * Call HubSpot PATCH: `PATCH /crm/v3/objects/deals/{dealId}` with properties in body.
  * If this fails, log an error. If it succeeds, great. This will move the deal to the correct stage and note the Woo order ID in HubSpot for the sales team’s reference.

This covers deal creation handling.

**Contact Association Event:** This likely corresponds to an event where a contact is associated with a deal after the deal was created. HubSpot might send a specific event for this (possibly a `deal.propertyChange` event on the `associations.contacts` or something similar). If not, it might come through as a contact event (e.g., contact property "associatedDeals" changed). Assuming we can capture it:

* Identify the deal and contact involved. HubSpot’s payload might not directly say "dealId" on a contact event. Alternatively, maybe subscribe to "deal.contactAssociationChange" if HubSpot offers that. Let’s assume we get something that gives us the deal ID and the contact ID.

* When we receive it, we find the Woo order corresponding to that HubSpot deal (same method: search by meta `hubspot_deal_id`).

* Fetch the contact’s details (if we don’t have it already). Then update the order’s billing contact info. Specifically, if the order was missing name/email (which could happen if the deal had no contact at initial creation), we fill those in now:

  * `$order->set_billing_first_name($contact['firstname'])`, last name, billing email, phone, etc. (or use mapping for any contact fields needed).
  * Save the order and maybe add an order note "Contact associated in HubSpot – billing info updated."

* If a user account exists matching that email and one wanted to link the order to the user, we could do that here as well (setting \$order->set\_customer\_id). But again, that’s optional – not in initial spec.

* This ensures that if the deal was created first (maybe as a placeholder) and later a contact was attached, the order now gets the customer info.

**Company Association Event:** Similar to contact:

* On receiving a company association, fetch the company, find the order by deal ID, and update the order’s billing company field (and any other mapped fields like company address if we allowed that).
* Save order, add note "Company associated – updated billing company."

**Line Item Association Event:** If the sales rep adds products to the deal after initial creation, HubSpot will fire events for each line item association (likely something like `dealLineItem.creation` or a generic deal change event).

* For each new line item event, we:

  * Identify the deal and the line item ID.
  * Find the Woo order by deal ID.
  * Fetch the line item from HubSpot (`fetch_hubspot_line_item`).
  * Add the line item to the Woo order (just like we do during initial creation, but now as an update):

    * Find product by SKU, add item with qty and price; or add custom item if product not found.
  * Recalculate totals. Because adding a new item will change the order total, we should update it. We might also want to check if the HubSpot deal’s amount property changed accordingly, but if we trust HubSpot’s deal amount to reflect the latest total, we could use it. Perhaps simpler: after adding the item, call `$order->calculate_totals()` and then consider adjusting for discount if necessary:

    * If HubSpot deal amount (we can fetch it or perhaps it was included in the event payload if updated) is less than the new sum of items, it means a discount was in place. Since previously we might have added a discount line item, we should update it. This gets complicated if doing incrementally. One strategy: after each line item addition, fetch the deal’s current `amount` and use the same logic as manual import: compare to order subtotal+shipping. Then adjust the existing discount line or add one if needed. This is a bit involved. To simplify, we might choose to always recompute from scratch:

      * Re-fetch the entire deal (with all line items) and re-sync the order completely (clear existing items and rebuild). But that might interfere with manual adjustments or cause duplication if events overlap.
      * Alternatively, accumulate changes: if the deal had no items and now has one, just add it. If a discount exists in HubSpot (deal amount lower), we could add a discount item then.
    * For initial version, it might be acceptable to not handle partial discounts perfectly on incremental additions. As a simpler approach, we could decide: whenever we detect new line items were added (especially if multiple events come in a burst), maybe wait a short delay and then do a full sync of line items. However, implementing a delay in a webhook handler is not trivial (unless we schedule a follow-up WP Cron a minute later to sync).
    * Given complexity, a pragmatic approach: handle each line item event by adding the item. If after adding, the order total exceeds HubSpot deal amount (we should fetch the deal or maybe the line item event payload includes a new total?), then adjust discount. If we can’t easily get the deal’s target total from the event, we might do a quick API fetch of the deal’s `amount` property here.

      * Use `fetch_hubspot_deal($dealId)` or a lighter query for just amount.
      * Compare and adjust the discount line (if one exists from before, update its amount; if none and needed, add a new fee with negative amount).
    * This ensures the order total always mirrors HubSpot.
  * Save the order, add an order note like "Added product XYZ (via HubSpot) to order."

* **Line item removal** events: If a line item is removed from a deal, HubSpot could send a deletion event. We should consider if we want to handle that (removing an item from the Woo order). Possibly yes for completeness:

  * If a `dealLineItem.deletion` event (if exists) comes with a line item ID, find the Woo order, find the order item with that SKU or some identifier, and remove it (`$order->remove_item($item_id)`). Woo doesn’t store HubSpot line item IDs, but we do store SKUs and names. If the line item had a unique SKU, we can find that item in the order. If multiple of same product, maybe match by SKU and quantity? This is ambiguous if multiple identical items.
  * This is tricky to implement reliably, but if needed, a safer way is to do a full resync: just fetch all current line items from HubSpot and replace the order’s items with that set (the manual import basically does that on re-import by clearing items and re-adding).
  * For initial implementation, we might skip handling deletions (or require manual sync if items are removed). We will note this as a limitation: the automation will add items when they appear, but not automatically remove if they’re deleted in HubSpot (to avoid accidental data loss if triggered wrongly). The admin could always re-sync manually if needed.

**Product Update Event:** When a HubSpot Product is updated (e.g., price or name changed in HubSpot’s product library), we need to update the corresponding WooCommerce product (matching by SKU). Steps:

* Extract the HubSpot product ID from the event (objectId).
* Fetch the product details from HubSpot. We will implement a helper similar to others, e.g.:

  ```php
  function fetch_hubspot_product($id) {
      $token = manage_hubspot_access_token();
      $url = "https://api.hubapi.com/crm/v3/objects/products/{$id}?properties=hs_sku,name,price,description";
      $res = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"]]);
      if (is_wp_error($res)) { return null; }
      $data = json_decode(wp_remote_retrieve_body($res), true);
      return $data['properties'] ?? null;
  }
  ```

  (We’ll include all relevant properties. `hs_sku` custom property for SKU – if the user’s HubSpot products use a field for SKU, perhaps they set up hs\_sku. If not, maybe the default “Product SKU” property in HubSpot’s product library might just be `sku` – we should confirm which property to use. The plugin’s line item logic suggests `hs_sku` was used on line items, and possibly on products as well. We’ll try hs\_sku then fallback to sku, similar to line items.)
* After fetching, we will have something like: `['hs_sku' => 'ABC123', 'name' => 'New Product Name', 'price' => '79.99', 'description' => '...']` (depending on what was changed, HubSpot might only send the changed property in the event, but our fetch gets all).
* Find the WooCommerce product by SKU. Use `wc_get_product_id_by_sku($sku)`. If it returns a valid product ID:

  * Load the product (`$product = wc_get_product($id)`).
  * Update fields that were changed. Ideally, we should only update fields that correspond to changed HubSpot properties (to avoid unnecessary overwriting). The webhook event might indicate which property changed (`propertyName` and `propertyValue`). For instance, if it was just price, we could update price only. But doing a full fetch anyway, we can update multiple if needed. At minimum:

    * If name changed: call `$product->set_name($newName)`.
    * If price changed: since WooCommerce products have `regular_price`, `sale_price`, etc., and HubSpot likely has just one price field (assuming standard price), we will update the WooCommerce regular price. If the product is simple, just do `$product->set_regular_price($newPrice)` (and maybe `$product->set_sale_price('')` if we want to clear any sale, or leave sale price as is if one was set manually – tricky decision. Possibly assume HubSpot's price corresponds to the current base price).
    * If description changed: map it accordingly. WooCommerce has a long description and a short description. If the user wants them in sync, they might have mapped either. Perhaps use mapping here too: we’ll consult `hubspot_product_field_map`. For instance, if HubSpot `description` is mapped to WooCommerce product long description, we update `$product->set_description($desc)`. Or if mapped to short, do that. We should abide by whatever mapping the admin set for product fields.
    * Other fields: if HubSpot has more (like a custom field they wanted to map to some product meta), in theory we could update those meta too if mapping exists.
  * Save the product (`$product->save()`).
  * We may want to log or note that product was updated. Possibly add an admin order note somewhere is not applicable because it's not an order. We could maybe add a small entry in a custom log or simply rely on site admins noticing the product update on their own. We could send an email or dashboard notice if needed, but that might be too much. Logging via `error_log` that "Product SKU X updated from HubSpot." is at least useful for debugging.
* If product not found by SKU:

  * Possibly skip (nothing to update). We could optionally create a new WooCommerce product if we interpret this as a new product created in HubSpot. The user specifically said “when a product is updated in HubSpot update the matching product (same SKU) on the website.” This implies they expect the product to exist (match by SKU). So if we don’t find it, perhaps the user hasn’t created it in Woo, or SKU mismatch. We will likely just log a warning: no matching product for SKU X – cannot update.
  * If we wanted to be fancy, we could create it: use name, price, description from HubSpot to create a new WooCommerce product. But automatically creating products could have unintended consequences (like incomplete product data, missing categories, etc.). Since it wasn’t requested explicitly, we won’t do that unless needed in the future.

**Other Events Considerations:**

* **Deal stage change:** The user didn’t mention it, but a common sync might be if a deal moves to a certain stage in HubSpot (e.g., “Closed Won”), we mark the Woo order as completed or such. This is a potential addition. We have stage mapping info, so it’s feasible: On a `deal.propertyChange` for `dealstage`, find the order and update its status according to a reverse mapping. Possibly, if stage mapping is one-to-one, we could invert the `hubspot_manual_mapping` to find which Woo status corresponds to that stage. However, note that originally the mapping was designed one-way (Woo status -> stage). Reversing it might be okay if mapping is bijective, but multiple Woo statuses might map to one stage (not likely in their usage). We could implement this if desired: e.g., if a deal was moved to the stage that corresponds to “Completed” in our mapping, we auto-mark the order completed. This would truly complete two-way sync for order status. We should confirm if the user wants that; since it’s not explicitly mentioned, we can leave it as a possible future improvement. (We will at least be capturing deal propertyChange events generally, but unless we explicitly handle `dealstage`, we’d ignore them for now).
* **Contact or Company updates:** If a contact’s details change (like they updated an email or phone in HubSpot), should we update those on existing WooCommerce orders or user accounts? This might be out of scope because historically an order’s billing info is a snapshot at time of order. Updating a past order’s info because the CRM changed might not be desired (except for maybe open quotes). Perhaps skip contact updates for now (or only apply to an associated order if it’s not yet completed? That’s complicated logic and not requested).
* **Line item updates (quantity or price changes on a deal):** HubSpot might allow editing a line item (e.g., change quantity or give a discount on that line). If an event comes through for a line item property change, we could reflect that in the Woo order item. However, WooCommerce doesn’t support per-item discount easily unless using coupons or meta. Changing price on an order item can be done (set new total), and adjust discount line accordingly. This becomes complex and likely not necessary unless the user specifically needs it. We might skip fine-grained line updates.

**Security & Verification:** We should verify that requests to the webhook are genuinely from HubSpot to avoid unauthorized triggers. HubSpot webhooks do include a signature (in header `X-HubSpot-Signature`) that can be used along with the client secret to validate payloads. We will implement this if possible:

* Retrieve the signature header.
* HubSpot’s signature algorithm (for private apps) typically is HMAC SHA-256 using the client secret, applied to the request payload (or something similar – we’ll consult their documentation).
* Compute it and compare. If mismatch, we can respond with 403 or just ignore.
* If correct, proceed.
* If we find this too involved right now (time constraints), at minimum we ensure `permission_callback => __return_true` allows entry but perhaps include a simple auth layer, like expecting HubSpot to include a certain secret token (HubSpot allows adding a secret to the webhook URL as a query param if configured). The user’s provided URL doesn’t show any secret param, so likely not. We’ll rely on signature then. Implementation example:

  ```php
  $sig = $_SERVER['HTTP_X_HUBSPOT_SIGNATURE'] ?? '';
  $payload = $request->get_body();
  $clientSecret = HUBSPOT_CLIENT_SECRET; // from our config
  $hash = hash_hmac('sha256', $payload, $clientSecret);
  if (!$sig || $hash !== $sig) {
      return new WP_REST_Response('Signature mismatch', 403);
  }
  ```

  This ensures only HubSpot (who knows the secret) could have generated the signature.

**Performance and Response:** The webhook handler should return a 200 OK as quickly as possible so HubSpot doesn’t retry or mark as failed. If our processing might take long (creating orders, etc.), we might consider offloading to asynchronous jobs. However, typically, a few API calls and order creation should be quick (maybe a second or two). HubSpot’s webhook guidelines likely allow a short time (maybe up to 5 or 10 seconds) before timing out. We should be fine doing it inline for now, given the moderate complexity. If performance issues arise, we can optimize (like using `wp_schedule_single_event` to process events asynchronously and immediately responding 200 to HubSpot). For now, handle inline for simplicity and reliability.

We will ensure to catch any exceptions or errors so that we always return 200 OK to HubSpot (unless we intentionally want a retry on error). Possibly better: if something fails (like token expired unexpectedly – though our manage\_hubspot\_access\_token() should refresh it), we could return a 500 to ask HubSpot to retry later. But often, it’s safer to accept and log the failure rather than repeated retries. We'll likely always return 200 and handle errors internally.

**Testing:** We will test the webhook by simulating HTTP POSTs to the endpoint with sample payloads. We can use a tool like Postman or WP-CLI to POST to `/wp-json/hubspot/v1/webhook` with JSON resembling HubSpot’s events. For example:

* Test deal creation: Craft a fake event JSON for deal creation with a known HubSpot deal ID that we create manually via API or test account, and see if an order is created.
* Test adding a contact: simulate that after creation, send a contact association event and see if the order updates.
* Test adding a line item: simulate a line item creation event and verify the order item is added.
* Test product update: simulate a product propertyChange event (with known SKU and changed name or price) and check the Woo product changes.

If direct testing with actual HubSpot is possible (maybe using a developer test account), that’s ideal. If not, carefully validate logic with dry runs.

**Summary:** With the webhook system in place, the integration becomes truly bi-directional:

* **Deals:** As soon as a sales rep creates a Deal in HubSpot (in the configured pipeline for Woo integration), an order is generated on the site (including all available details at that moment). As more data is added to the deal (associating a contact/company or adding products), the corresponding Woo order is enriched with customer info and line items automatically. This means the WooCommerce site always has a current copy of the deal for the admin to review or process (e.g., send an invoice or take payment).
* **Products:** If product info is maintained in HubSpot (prices, names, etc.), any changes there will propagate to the WooCommerce catalog, keeping prices and details in sync. For example, updating the price of “Product A” in HubSpot will adjust the WooCommerce price, so that future orders on the site use the new price. This prevents discrepancies between quotes in HubSpot and prices on the website.
* The webhook approach significantly reduces manual work: no need to click “Sync” for deals or manually update products in two places. It also reduces the reliance on scheduled sync (except for the cleanup and token refresh tasks).

We will document these changes for the user and ensure they are aware of the new real-time behaviors.

Lastly, we’ll monitor error logs after deploying to catch any edge cases (like network issues on webhook calls, etc.). The implementation will be made robust by using existing proven functions where possible (reusing the `import_hubspot_deal` logic inside the webhook handler to avoid duplication, perhaps by refactoring that logic into a helper function that both AJAX and webhook can call).

---

By following the above plans, we will significantly enhance the plugin: **(1)** a flexible field mapping system to handle custom data syncing, **(2)** a more powerful and correct order cleanup mechanism for better data hygiene, and **(3)** a webhook-driven sync for near-instant updates between HubSpot and WooCommerce. These improvements will make the integration more customizable and responsive, addressing the user’s needs and resolving current issues. Each feature will be implemented with careful consideration of existing architecture and backward compatibility, resulting in a more robust plugin overall.

**Sources:**

* Plugin code – HubSpot deal creation and field mappings
* Plugin code – Order cleanup implementation
* Plugin code – Manual import of HubSpot deal (used as reference for webhook logic)
