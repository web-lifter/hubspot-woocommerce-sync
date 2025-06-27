# Plugin Testing Checklist

Follow the steps below on your staging or development site to ensure **HubSpot WooCommerce Sync** functions correctly.

- [ ] Activate the plugin and verify the **HubSpot** menu appears in the WordPress admin.
- [ ] Open **HubSpot → HubSpot Settings** and connect your HubSpot account via OAuth.
- [ ] Ensure available pipelines load on the settings page after connecting.
- [ ] Create a new WooCommerce order through the storefront and confirm a corresponding deal is created in HubSpot with the correct stage.
- [ ] Create a manual order in the admin, then use **HubSpot → Order Management** to create a deal for it. Verify the deal stage and pipeline match your settings.
- [ ] Test the **Import Order** page by entering a HubSpot deal ID and confirm the order is created or updated in WooCommerce.
- [ ] Send a quote and an invoice from an order and confirm the stages update in HubSpot.
- [ ] Review the Abandoned Carts screens (if enabled) to ensure pages load without errors.
- [ ] Deactivate and delete the plugin to confirm that scheduled events and database tables are cleaned up.

Run through these checks after updates or configuration changes to verify the integration remains operational.
