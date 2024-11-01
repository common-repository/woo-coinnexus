=== Bitcoin CoinNexus accept fiat and get bitcoin payment plugin ===

 - Contributors: CoinNexus Oy
 - Tags: woocommerce, payment gateway, gateway, manual payment, bitcoin
 - Requires at least: 3.8
 - Tested up to: 5.1
 - Requires WooCommerce at least: 2.1
 - Tested WooCommerce up to: 3.5.4
 - Stable Tag: 1.0.0
 - License: GPLv3
 - License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

> **Requires: WooCommerce 2.1+**

This plugin clones the Cheque gateway to create a bitcoin payment method. It allows the user to integrate the CoinNexus api into his woocommerce store.
The CoinNexus API allows merchants to be paid in bitcoin, while the customer pays directly to us in Euro, USD or CHF. The fee for the conversion is just 2%.

= More Details =
 - See the (https://www.lamium.fi/) for full details and documentation

== Installation ==

1. Be sure you're running WooCommerce 2.1+ in your shop.
2. You can: (1) upload the entire `woocommerce-gateway-fiat-to-bitcoin-coinnexus-api` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Checkout** and select "Fiat to bitcoin coinnexus api " to configure

NOTE :Make sure Hold stock (for unpaid orders) for x minutes is set to null, otherwise by default woocommerce marks the pendging payment orders as cancelled after one hour"
You can change it here 
yourdomain/wp-admin/admin.php?page=wc-settings&tab=products&section=inventory

== Frequently Asked Questions ==

**Can I fork this?**
Please do! This is meant to be a simple starter offline gateway, and can be modified easily.

== Changelog ==

= 2015.07.27 - version 1.0.1 =
 * Misc: WooCommerce 2.4 Compatibility

= 2015.05.04 - version 1.0.0 =
 * Initial Release
