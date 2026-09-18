=== Quick Mailer for WooCommerce ===
Contributors: mirailit
Donate link: https://mirailit.com/
Tags: woocommerce, email, communication, orders, customer-support
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Quick, Easy Emails to Customers right from Woocommerce Dashboard.


== Description ==

The Quick Mailer for WooCommerce Plugin is a powerful and user-friendly tool designed to streamline the communication process between your online store's support team or shop managers and your customers. With this plugin, you can efficiently send updates and information directly from the WooCommerce Orders Dashboard, ensuring your customers are always informed and engaged. Using template you can save your frequently used emails.


== Features ==

- **Email Directly from Orders Dashboard:** Quickly send emails without leaving the WooCommerce Orders Dashboard.
- **Predefined Templates:** Save time with customizable templates for common updates and inquiries.
- **Dynamic Placeholders:** Automatically fill in details like order number, customer name, and to email address, along with custom meta fields as required.
- **Universal Email Provider Compatibility:** Set up with any email provider, ensuring all sent emails are stored in your sent box for easy tracking and follow-up.

== Installation ==

1. **Upload to WordPress:**
   - Navigate to your WordPress dashboard.
   - Go to `Plugins > Add New > Upload Plugin`.
   - Choose the downloaded zip file and click "Install Now".

2. **Activate the Plugin:**
   - Activate the plugin through the 'Plugins' menu in WordPress

== Configuration ==

1. **Set Up Email Provider:**
   - Set up the email provider information from the plugins setting page.
   - You will acccess the page 'Quick Mailer' from the left sidebar of the admin panel.

2. **Create and Manage Templates:**
   - Access the templates files. Section to create or modify your email templates.
   - Utilize placeholders and custom meta fields to personalize your messages.

3. **Sending Emails:**
   - Go to WooCommerce > Orders and open an order.
   - Scroll to the 'Quick Mailer for WooCommerce' box on the order edit screen.
   - Pick a predefined template or write a custom message, and send it directly to the customer.

== Usage Tips ==

- **Customize Templates:** Save common templates for various scenarios to save time in the future.
- **Monitor Sent Emails:** Regularly check your email provider's sent box to ensure all communications are being sent and received properly.


== Frequently asked questions ==

= What is Quick Mailer for WooCommerce? =

Quick Mailer for WooCommerce is a plugin designed to simplify communication between store owners/managers and customers. It enables you to send updates, queries and information directly from the WooCommerce Orders Dashboard, making the process efficient and hassle-free.

= How does Quick Mailer for WooCommerce benefit my online store? =

This plugin streamlines communication by allowing you to send important updates and information directly to customers from within the WooCommerce Orders Dashboard. It ensures that customers are always informed and engaged, leading to improved customer satisfaction and loyalty.

= Can I customize the emails sent through Quick Mailer for WooCommerce? =

Yes, absolutely! Quick Mailer for WooCommerce lets you write any subject and body, save them as reusable templates, and personalize them with placeholders such as the customer name, order number and delivery date, which are filled in automatically for the order you are viewing.

= Is Quick Mailer for WooCommerce easy to use? =

Yes, one of the key features of Quick Mailer for WooCommerce is its user-friendly interface. Sending emails is simple and intuitive, with no coding or technical expertise required. You can compose and send emails directly from the WooCommerce Orders Dashboard, saving you time and effort.

== Screenshots ==

1. Order Details Page view of Quick Mailer for WooCommerce
2. Setting Page View

== Changelog ==

= v1.0.3 =

* Security - Added capability checks to all AJAX handlers in addition to the existing nonce checks.
* Security - Added a sanitize callback for the settings option so every SMTP field is validated before saving.
* Security - All database queries now use prepared statements, including table identifiers.
* Fix - SMTP send failures are now reported instead of silently returning success.
* Fix - Saved templates now appear immediately after saving on sites with a persistent object cache.
* Fix - Declared compatibility with WooCommerce High-Performance Order Storage (HPOS) and read order meta via the order object.
* Fix - Admin scripts and styles are only loaded on the order edit screen and the plugin settings page.
* Fix - Inline scripts moved into the enqueued admin script file.
* Fix - Plugin no longer errors when WooCommerce is inactive; an admin notice is shown instead.
* Enhancement - Added an uninstall routine that removes the settings and templates table.
* Fix - SMTP port 465 now uses implicit TLS instead of STARTTLS, so providers that require SMTPS work.
* Fix - A failed template save now reports the error instead of a false success message.
* Fix - The duplicate order numbers placeholder no longer includes the order being viewed.
* Update - Tested with WordPress 7.1 and WooCommerce 11.1. Minimum WordPress raised to 6.2 and minimum PHP to 7.4.

= v1.0.2 =

* Fix - Improved sanitization, escaping, and validation of data throughout the plugin to enhance security.
* Fix - Updated generic function, class, define, namespace, and option names for better clarity and consistency.
* Fix - Ensured that direct file access to plugin files is restricted for improved security measures.
* Fix - Resolved any potential vulnerabilities identified in the codebase to enhance overall plugin security.
* Enhancement - Implemented additional error handling and validation checks to improve plugin robustness and stability.
* Update - Updated documentation and inline comments for better code readability and maintainability.


= v1.0.1 =

* Fix - ## Data Must be Sanitized, Escaped, and Validated
* Fix - ## Generic function/class/define/namespace/option names
* Fix - ## Allowing Direct File Access to plugin files

= v1.0.0 =

Original version of the Quick Mailer for Woocommerce, not a released version of the plugin, this changelog is here for historical purposes only.


== Initial Release ==

- **Email Directly from Orders Dashboard:** Quickly send emails without leaving the WooCommerce Orders Dashboard.
- **Predefined Templates:** Save time with customizable templates for common updates and inquiries.
- **Dynamic Placeholders:** Automatically fill in details like order number, customer name, and to email address, along with custom meta fields as required.
- **Universal Email Provider Compatibility:** Set up with any email provider, ensuring all sent emails are stored in your sent box for easy tracking and follow-up.


== Upgrade Notice ==

= 1.0.3 =
Security and compatibility release. Adds capability checks, settings sanitization, prepared SQL, HPOS compatibility and WordPress 7.1 support. Update recommended for all users.