# Newsletter Signup Block

A custom Gutenberg block that renders a newsletter signup form and handles subscriptions via a custom REST API endpoint.  
Includes double opt-in confirmation emails and a lightweight honeypot for spam prevention.

## Features

- Gutenberg block for inserting signup forms anywhere on your site
- Submits to `/wp-json/newsletter/v1/subscribe` (custom REST route)
- **Secure database storage** - Custom table for persistent subscriber management
- **CSRF protection** - Nonce verification on REST endpoint
- **Cryptographically secure tokens** - 256-bit tokens for email confirmation
- Validates email addresses before sending confirmation
- Sends confirmation email with one-time token (double opt-in)
- Token expiration (24 hours) with automatic cleanup
- Honeypot field for basic bot protection
- Fully dynamic: form markup rendered server-side in PHP
- Works with Local, Mailpit, or any SMTP setup
- Action hook for integrations: `nsb_subscriber_confirmed`

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- Node 14+ (for building block assets, if editing source)

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins/` directory:

   ```bash
   git clone https://github.com/turnpiece/Newsletter-signup-block.git
   ```

2. Navigate to the plugin directory and install the dependencies:

   ```bash
   cd Newsletter-signup-block
   npm install
   ```

3. Build the block assets:

   ```bash
   npm run build
   ```

4. Activate the plugin through the 'Plugins' menu in WordPress.

## Database Schema

The plugin creates a custom table `wp_nsb_subscribers` (prefix may vary) with the following structure:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `email` | varchar(255) | Subscriber email (unique) |
| `status` | varchar(20) | Status: 'pending' or 'confirmed' |
| `token` | varchar(64) | Confirmation token (cleared after confirmation) |
| `token_expires` | datetime | Token expiration timestamp |
| `subscribed_at` | datetime | When user initially subscribed |
| `confirmed_at` | datetime | When user confirmed subscription |

The table is created automatically on plugin activation.

## Usage

1. Insert the 'Newsletter Signup' block into a post or page.
2. Publish or update the post/page to see the form.
3. Users will receive a confirmation email with a 24-hour expiration link.
4. Expired pending subscriptions are automatically cleaned up after 7 days.

## Developer Hooks

### Actions

**`nsb_subscriber_confirmed`** - Fires when a subscriber confirms their email

```php
add_action( 'nsb_subscriber_confirmed', function( $email, $subscriber_id ) {
    // Add to your email service provider
    // Example: mailchimp_api_add_subscriber( $email );
}, 10, 2 );
```

## Accessing Subscriber Data

Query subscribers directly from the database:

```php
global $wpdb;
$table_name = $wpdb->prefix . 'nsb_subscribers';

// Get all confirmed subscribers
$subscribers = $wpdb->get_results(
    "SELECT email, confirmed_at FROM $table_name WHERE status = 'confirmed' ORDER BY confirmed_at DESC"
);
```
