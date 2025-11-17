<?php
/**
 * Plugin Name: Newsletter Signup Block
 * Description: A custom Gutenberg block that renders a newsletter signup form and posts to a custom REST endpoint which validates the email and sends a confirmation email.
 * Version: 1.0.0
 * Author: Paul Jenkins
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Constants
const NSB_VERSION = '1.0.0';
const NSB_PLUGIN_DIR = __DIR__;

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

	// Prevent duplicates by transient
    // Can be replaced with real storage/CRM integration later
	$key = 'nsb_' . md5( strtolower( $email ) );
	if ( get_transient( $key ) ) {
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'You\'re already on the list — check your inbox.', 'nsb' ) ), 200 );
	}
	set_transient( $key, 1, HOUR_IN_SECONDS * 12 );

	// Send confirmation email to the user
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject   = sprintf( __( 'Confirm your subscription to %s', 'nsb' ), $site_name );

	// Create a one-time confirmation link (token stored in transient). In production, use user meta or a custom table.
	$token   = wp_generate_password( 20, false );
	set_transient( 'nsb_token_' . $token, $email, HOUR_IN_SECONDS * 24 );
	$confirm = add_query_arg( array( 'nsb_confirm' => $token ), home_url( '/' ) );

	$message  = sprintf( __( "Hello.\n\nPlease confirm your subscription to %s by clicking the link below:\n%s\n\nIf you didn\'t request this, you can ignore this email.\n\nThanks,\n%s", 'nsb' ), $site_name, $confirm, $site_name );
	$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );

	$sent = wp_mail( $email, $subject, $message, $headers );

	if ( ! $sent ) {
		return new WP_Error( 'nsb_send_failed', __( 'Sorry, we couldn\'t send the confirmation email. Please try again later.', 'nsb' ), array( 'status' => 500 ) );
	}

	return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Thanks. Please check your email to confirm your subscription.', 'nsb' ) ), 200 );
}

/**
 * Handle confirmation link (?nsb_confirm=TOKEN) at the front end.
 * Mark as confirmed and show a message.
 */
function nsb_maybe_handle_confirmation() {
	if ( empty( $_GET['nsb_confirm'] ) ) { return; }
	$token  = sanitize_text_field( wp_unslash( $_GET['nsb_confirm'] ) );
	$email  = get_transient( 'nsb_token_' . $token );
	if ( ! $email ) { return; }

	delete_transient( 'nsb_token_' . $token );

	// In a real project, store the confirmed subscriber in a list/CRM here.
	add_action( 'wp_head', function() use ( $email ) {
		echo '<meta name="nsb-confirmed" content="1" />';
	});
	add_action( 'the_content', function( $content ) use ( $email ) {
		$msg = '<div class="nsb-confirm">' . sprintf( esc_html__( 'Thanks, %s has been confirmed.', 'nsb' ), esc_html( $email ) ) . '</div>';
		return $msg . $content;
	});
}
add_action( 'template_redirect', 'nsb_maybe_handle_confirmation' );