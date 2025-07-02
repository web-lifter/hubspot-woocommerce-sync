<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
return [
    // Insert your HubSpot app credentials here. See README for details.
    'client_id'     => '',
    'client_secret' => '',
    'redirect_uri'  => '',
    'scopes'        => 'crm.objects.line_items.read crm.objects.line_items.write oauth conversations.read conversations.write crm.objects.contacts.write e-commerce sales-email-read crm.objects.companies.write crm.objects.companies.read crm.objects.deals.read crm.objects.deals.write crm.objects.contacts.read',
];
?>
