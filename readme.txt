=== Newsletter Signup Block ===
Contributors: Paul Jenkins
Tags: newsletter, signup, subscribe, email, block, gutenberg
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom Gutenberg block that renders a newsletter sign-up form and posts to a custom REST API endpoint with double opt-in.

== Description ==

This plugin provides a dynamic block that renders a simple newsletter sign-up form and a REST endpoint:
`POST /wp-json/newsletter/v1/subscribe`

- Validates the submitted email
- Sends a confirmation email with a one-time token (double opt-in)
- Honeypot field for basic spam protection

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the ‘Plugins’ screen.
3. (Optional) If editing the block source, run `npm install && npm run build`.

== Frequently Asked Questions ==

= Where do emails go in development? =
Use a mail catcher such as Mailpit/Mailhog, or install WP Mail Logging to verify `wp_mail()` was called.

= How do I integrate with a mailing list provider? =
Hook into the confirmation step inside `nsb_rest_subscribe()` and call your provider’s API to add confirmed subscribers.

== Changelog ==

= 1.0.0 =

- Initial release.

= 1.0.1 = 

- Tightened security

= 1.0.2 =

- Store subscriptions in database table

== License ==

This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 (or later).
