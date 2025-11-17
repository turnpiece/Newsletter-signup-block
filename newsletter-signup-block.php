<?php
/**
 * Plugin Name: Newsletter Signup Block
 * Description: A custom Gutenberg block that renders a newsletter signup form and posts to a custom REST endpoint which validates the email and sends a confirmation email.
 * Version: 1.0.1
 * Author: Paul Jenkins
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Constants
const NSB_VERSION = '1.0.1';
const NSB_PLUGIN_DIR = __DIR__;
const NSB_DB_VERSION = '1.0';

/**
 * Create database table on plugin activation.
 */
function nsb_create_table() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'nsb_subscribers';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(255) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		token varchar(64) DEFAULT NULL,
		token_expires datetime DEFAULT NULL,
		subscribed_at datetime NOT NULL,
		confirmed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY email (email),
		KEY status (status),
		KEY token (token),
		KEY token_expires (token_expires)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'nsb_db_version', NSB_DB_VERSION );
}
register_activation_hook( __FILE__, 'nsb_create_table' );

/**
 * Ensure the database table exists (run on init as fallback).
 */
function nsb_maybe_create_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'nsb_subscribers';

	// Check if table exists
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

	if ( ! $table_exists ) {
		nsb_create_table();
	}
}
add_action( 'init', 'nsb_maybe_create_table' );

/**
 * Register the block (dynamic) and frontend assets.
 */
function nsb_register_block() {
	// Register frontend handling script (no dependencies, keep it tiny)
	wp_register_script(
		'nsb-frontend',
		plugin_dir_url( __FILE__ ) . 'assets/frontend.js',
		array(),
		NSB_VERSION,
		true
	);

	// Make REST details available to the frontend script.
	wp_localize_script( 'nsb-frontend', 'NSB', array(
		'restUrl' => esc_url_raw( rest_url( 'newsletter/v1/subscribe' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );

	// Register the block using block.json (sets editor script/css). Use render_callback for markup.
	register_block_type( __DIR__, array(
		'render_callback' => 'nsb_render_block',
	) );
}
add_action( 'init', 'nsb_register_block' );

/**
 * Server-side render: outputs the form markup and enqueues the frontend JS when the block appears on a page.
 */
function nsb_render_block( $attributes, $content, $block ) {
	// Ensure our JS runs where the form appears
	wp_enqueue_script( 'nsb-frontend' );

	$headline = isset( $attributes['headline'] ) ? wp_kses_post( $attributes['headline'] ) : __( 'Subscribe to our newsletter', 'nsb' );
	$button   = isset( $attributes['buttonLabel'] ) ? esc_html( $attributes['buttonLabel'] ) : __( 'Subscribe', 'nsb' );

	$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'nsb-signup' ) );

	ob_start();
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<form class="nsb-form" novalidate>
			<div class="nsb-field">
				<label class="nsb-label" for="nsb-email"><?php echo esc_html( $headline ); ?></label>
				<input id="nsb-email" class="nsb-input" type="email" name="email" placeholder="you@example.com" required />
			</div>
			<div class="nsb-actions">
				<button class="nsb-button" type="submit"><?php echo $button; ?></button>
			</div>
			<p class="nsb-msg" aria-live="polite"></p>
			<!-- Honeypot -->
			<input type="text" name="company" class="nsb-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Register the custom REST route: POST /wp-json/newsletter/v1/subscribe
 */
function nsb_register_rest_routes() {
	register_rest_route( 'newsletter/v1', '/subscribe', array(
		'methods'             => 'POST',
		'callback'            => 'nsb_rest_subscribe',
		'permission_callback' => 'nsb_verify_nonce',
	) );
}
add_action( 'rest_api_init', 'nsb_register_rest_routes' );

/**
 * Verify the nonce for CSRF protection.
 */
function nsb_verify_nonce() {
	$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
	return wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * REST callback: validate email and send confirmation email.
 */
function nsb_rest_subscribe( WP_REST_Request $request ) {
	global $wpdb;
	$params = $request->get_json_params();

	$email     = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
	$honeypot  = isset( $params['company'] ) ? trim( (string) $params['company'] ) : '';

	// Simple bot check
	if ( ! empty( $honeypot ) ) {
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Thanks! Please check your email.', 'nsb' ) ), 200 );
	}

	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_Error( 'nsb_invalid_email', __( 'Please enter a valid email address.', 'nsb' ), array( 'status' => 400 ) );
	}

	$table_name = $wpdb->prefix . 'nsb_subscribers';

	// Check if email already exists in database
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, status FROM $table_name WHERE email = %s",
		$email
	) );

	if ( $existing ) {
		// If already confirmed, inform user
		if ( $existing->status === 'confirmed' ) {
			return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'You\'re already subscribed.', 'nsb' ) ), 200 );
		}
		// If pending, send a friendly message (avoid revealing if email exists)
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Please check your email to confirm your subscription.', 'nsb' ) ), 200 );
	}

	// Create confirmation token
	$token         = bin2hex( random_bytes( 32 ) ); // Cryptographically secure 64-character token
	$token_expires = gmdate( 'Y-m-d H:i:s', time() + ( HOUR_IN_SECONDS * 24 ) );

	// Insert new subscriber into database
	$inserted = $wpdb->insert(
		$table_name,
		array(
			'email'         => $email,
			'status'        => 'pending',
			'token'         => $token,
			'token_expires' => $token_expires,
			'subscribed_at' => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		// Log the error for debugging
		error_log( 'NSB Database Error: ' . $wpdb->last_error );
		return new WP_Error( 'nsb_db_error', __( 'Sorry, we couldn\'t process your subscription. Please try again later.', 'nsb' ), array( 'status' => 500 ) );
	}

	// Send confirmation email to the user
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject   = sprintf( __( 'Confirm your subscription to %s', 'nsb' ), $site_name );
	$confirm   = add_query_arg( array( 'nsb_confirm' => $token ), home_url( '/' ) );

	$message  = sprintf( __( "Hello.\n\nPlease confirm your subscription to %s by clicking the link below:\n%s\n\nIf you didn\'t request this, you can ignore this email.\n\nThanks,\n%s", 'nsb' ), $site_name, $confirm, $site_name );
	$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );

	$sent = wp_mail( $email, $subject, $message, $headers );

	if ( ! $sent ) {
		// Optionally delete the pending subscriber if email fails
		// $wpdb->delete( $table_name, array( 'id' => $wpdb->insert_id ), array( '%d' ) );
		return new WP_Error( 'nsb_send_failed', __( 'Sorry, we couldn\'t send the confirmation email. Please try again later.', 'nsb' ), array( 'status' => 500 ) );
	}

	return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Thanks. Please check your email to confirm your subscription.', 'nsb' ) ), 200 );
}

/**
 * Handle confirmation link (?nsb_confirm=TOKEN) at the front end.
 * Mark as confirmed and show a message.
 */
function nsb_maybe_handle_confirmation() {
	global $wpdb;

	if ( empty( $_GET['nsb_confirm'] ) ) { return; }

	$token      = sanitize_text_field( wp_unslash( $_GET['nsb_confirm'] ) );
	$table_name = $wpdb->prefix . 'nsb_subscribers';

	// Find subscriber by token
	$subscriber = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, email, status, token_expires FROM $table_name WHERE token = %s AND status = 'pending'",
		$token
	) );

	if ( ! $subscriber ) { return; }

	// Check if token has expired
	if ( strtotime( $subscriber->token_expires ) < time() ) {
		add_action( 'the_content', function( $content ) {
			$msg = '<div class="nsb-confirm nsb-error">' . esc_html__( 'This confirmation link has expired. Please sign up again.', 'nsb' ) . '</div>';
			return $msg . $content;
		});
		return;
	}

	// Update subscriber status to confirmed
	$updated = $wpdb->update(
		$table_name,
		array(
			'status'       => 'confirmed',
			'confirmed_at' => current_time( 'mysql', true ),
			'token'        => null, // Clear token after use
			'token_expires' => null,
		),
		array( 'id' => $subscriber->id ),
		array( '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	if ( ! $updated ) { return; }

	// Hook to allow integrations (e.g., add to email service)
	do_action( 'nsb_subscriber_confirmed', $subscriber->email, $subscriber->id );

	add_action( 'wp_head', function() {
		echo '<meta name="nsb-confirmed" content="1" />';
	});
	add_action( 'the_content', function( $content ) use ( $subscriber ) {
		$msg = '<div class="nsb-confirm">' . sprintf( esc_html__( 'Thanks, %s has been confirmed.', 'nsb' ), esc_html( $subscriber->email ) ) . '</div>';
		return $msg . $content;
	});
}
add_action( 'template_redirect', 'nsb_maybe_handle_confirmation' );

/**
 * Clean up expired confirmation tokens (runs daily via WP Cron).
 */
function nsb_cleanup_expired_tokens() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'nsb_subscribers';

	// Delete pending subscribers with expired tokens older than 7 days
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $table_name WHERE status = 'pending' AND token_expires < %s",
		gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * 7 ) )
	) );
}

/**
 * Schedule daily cleanup on plugin activation.
 */
function nsb_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'nsb_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'nsb_daily_cleanup' );
	}
}
register_activation_hook( __FILE__, 'nsb_schedule_cleanup' );
add_action( 'nsb_daily_cleanup', 'nsb_cleanup_expired_tokens' );

/**
 * Clear scheduled cleanup on plugin deactivation.
 */
function nsb_clear_scheduled_cleanup() {
	$timestamp = wp_next_scheduled( 'nsb_daily_cleanup' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'nsb_daily_cleanup' );
	}
}
register_deactivation_hook( __FILE__, 'nsb_clear_scheduled_cleanup' );