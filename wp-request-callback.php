<?php
/**
 * Plugin Name:       WP Request Callback
 * Plugin URI:        https://wprequestcallback.com
 * Description:       Capture callback requests from potential clients on your site. Use our built in forms or create your own. Simple, customisable, and easy to use.
 * Version:           0.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            KiteFrame
 * Author URI:        https://www.kiteframe.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-request-callback
 */

define( 'WPRC_POST_TYPE', 'wprc_cb_request' );
define( 'WPRC_DEFAULT_THEME_COLOR', '#0073aa' );

/**
 * Register post type
 */
function wprc_custom_post_type(): void {
	register_post_type( WPRC_POST_TYPE,
		array(
			'capabilities' => array(
				'create_posts' => 'do_not_allow',
			),
			'labels'       => array(
				'name'          => __( 'Callback Requests' ),
				'singular_name' => __( 'Callback Request' ),
			),
			'map_meta_cap' => true,
			'rewrite'      => false,
			'show_ui'      => true,
			'supports'     => false,
		)
	);
}

add_action( 'init', 'wprc_custom_post_type' );
/**
 * End register post type
 */


/**
 * Disable editing from list screen
 */
add_filter( 'bulk_actions-edit-' . WPRC_POST_TYPE, function ( array $actions ): array {
	unset( $actions['edit'] );

	return $actions;
} );

add_filter( 'post_row_actions', function ( array $actions ): array {
	if ( get_post_type() === WPRC_POST_TYPE ) {
		unset( $actions['edit'] );
		unset( $actions['inline hide-if-no-js'] ); // Quick Edit
	}

	return $actions;
} );
/**
 * End disable editing from list screen
 */


/**
 * Customise list screen columns
 */
function set_wprc_cb_request_columns( array $columns ): array {
	return array(
		'cb'    => '<input type="checkbox" />',
		'name'  => __( 'Name' ),
		'phone' => __( 'Phone' ),
		'date'  => __( 'Date' ),
	);
}

function custom_wprc_cb_request_column( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'name' :
		case 'phone' :
			echo get_post_meta( $post_id, $column, true );
			break;
		case 'date':
			var_dump( $column );
			echo 'foo';
			break;
	}
}

add_filter( 'manage_wprc_cb_request_posts_columns', 'set_wprc_cb_request_columns' );

add_filter( 'post_date_column_status', function ( string $status, WP_Post $post ): string {
	if ( $post->post_type === WPRC_POST_TYPE ) {
		return '';
	}

	return $status;
}, 10, 2 );

add_filter( 'post_date_column_time', function ( string $time, WP_Post $post ): string {
	if ( $post->post_type === WPRC_POST_TYPE ) {
		return get_the_date( '', $post->ID ) . ' ' . get_the_time( '', $post->ID );
	}

	return $time;
}, 10, 2 );

add_action( 'manage_wprc_cb_request_posts_custom_column', 'custom_wprc_cb_request_column', 10, 2 );
/**
 * End customise list screen columns
 */


/**
 * Customising list screen search
 */
function wprc_search_query( WP_Query $query ): void {
	if ( $query->is_search && $query->query_vars['post_type'] === WPRC_POST_TYPE ) {
		$meta_query_args = array(
			'relation' => 'OR',
			array(
				'key'     => 'name',
				'value'   => $query->query_vars['s'],
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'phone',
				'value'   => $query->query_vars['s'],
				'compare' => 'LIKE',
			),
		);
		$query->set( 'meta_query', $meta_query_args );
		add_filter( 'get_meta_sql', 'wprc_replace_and_with_or' );
	};
}

function wprc_replace_and_with_or( string $sql ): string {
	if ( 1 === strpos( $sql['where'], 'AND' ) ) {
		$sql['where'] = substr( $sql['where'], 4 );
		$sql['where'] = ' OR ' . $sql['where'];
	}

	//make sure that this filter will fire only once for the meta query
	remove_filter( 'get_meta_sql', 'wprc_replace_and_with_or' );

	return $sql;
}

add_filter( 'pre_get_posts', 'wprc_search_query' );
/**
 * End customise list screen search
 */


/**
 * Add API endpoint
 */
add_action( 'rest_api_init', function (): void {
	register_rest_route( 'wprc/v1', '/callback-requests', array(
		'methods'  => 'POST',
		'callback' => 'wprc_create_callback_request',
	) );
} );

function wprc_create_callback_request( WP_REST_Request $request ): WP_REST_Response {
	$errors = wprc_validate_request( $request );

	if ( ! empty( $errors ) ) {
		return new WP_REST_Response( $errors, 422 );
	}

	$name  = sanitize_text_field( $request['name'] );
	$phone = sanitize_text_field( $request['phone'] );

	$id = wp_insert_post( array(
		'post_type'   => WPRC_POST_TYPE,
		'post_status' => 'private',
	) );

	add_post_meta( $id, 'name', $name );
	add_post_meta( $id, 'phone', $phone );

	if ( $emailAddress = trim( get_option( 'wprc' )['email_address'] ) ) {
		wp_mail(
			$emailAddress,
			'New Callback Request',
			"You have a new callback request!<br/><br/><b>Name</b> $name<br/><b>Phone</b> $phone",
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: "WP Request Callback" <callbackrequest@wp-request-callback.test>'
			)
		);
	}

	if ( $slackUrl = trim( get_option( 'wprc' )['slack_url'] ) ) {
		wp_remote_post(
			$slackUrl,
			array( 'body' => "payload={\"text\": \"You have a new callback request!\n*Name* $name\n*Phone* $phone\"}" )
		);
	}

	return new WP_REST_Response( 'Success', 201 );
}

function wprc_validate_request( WP_REST_Request $request ): array {
	$errors = array();

	if ( ! isset( $request['name'] ) || empty( $request['name'] ) ) {
		$errors = wprc_add_error( $errors, 'name', 'Name is required.' );
	}

	if ( ! isset( $request['phone'] ) || empty( $request['phone'] ) ) {
		$errors = wprc_add_error( $errors, 'phone', 'Phone number is required.' );
	}

	if ( ! preg_match( '/^[0-9 ]+$/', $request['phone'] ) ) {
		$errors = wprc_add_error( $errors, 'phone', 'Phone number must be numbers or spaces.' );
	}

	return $errors;
}

function wprc_add_error( array $errors, string $key, string $error ): array {
	return array_merge_recursive( $errors, array( 'errors' => [ $key => [ $error ] ] ) );
}

/**
 * End add API endpoint
 */


/**
 * Add shortcode
 */
function wprc_shortcodes_init(): void {
	function wprc_shortcode( $atts ): string {
		$color = isset( get_option( 'wprc' )['theme_color'] ) ? get_option( 'wprc' )['theme_color'] : WPRC_DEFAULT_THEME_COLOR;

		$atts = shortcode_atts( array(
			'success_message' => 'Thanks for submitting your callback request.',
			'error_message'   => 'Something went wrong. Please try again.',
			'color'           => $color,
		), $atts, 'wprc' );

		return
			'
<style>
input[type="text"].wprc-input:focus,
input[type="tel"].wprc-input:focus {
    border-color: ' . $atts['color'] . ';
}

button.wprc-button {
    background-color: ' . $atts['color'] . ';
}

button.wprc-button:hover {
    background-color: ' . $atts['color'] . ';
}

button.wprc-button:focus {
    background-color: ' . $atts['color'] . ';
}
</style>
<div class="wprc-wrapper">
	<form class="wprc-form">
		<label class="wprc-label wprc-label-name">
		    <span class="wprc-label-text wprc-label-text-name">Name</span>
			<input class="wprc-input wprc-input-phone" name="name" type="text" required/>
		</label>
        <div class="wprc-validation-errors"></div>
        
		<label class="wprc-label wprc-label-phone">
		    <span class="wprc-label-text wprc-label-text-phone">Phone</span>
			<input class="wprc-input wprc-input-phone" name="phone" type="tel" required/>
		</label>
        <div class="wprc-validation-errors"></div>
        
		<div class="wprc-button-wrapper">
		    <button class="wprc-button" type="submit">Submit</button>
        </div>
	</form>
	<div class="wprc-message wprc-success-message" style="display: none">' . $atts['success_message'] . '</div>
	<div class="wprc-message wprc-error-message" style="display: none">' . $atts['error_message'] . '</div>
</div>
';
	}

	add_shortcode( 'wprc', 'wprc_shortcode' );
}

add_action( 'init', 'wprc_shortcodes_init' );

add_action( 'wp_enqueue_scripts', function (): void {
	wp_enqueue_style( 'wprc-style', plugins_url( 'main.css', __FILE__ ) );
	wp_enqueue_script( 'wprc-script', plugins_url( 'main.js', __FILE__ ), array(), false, true );
	wp_localize_script( 'wprc-script', 'wprcSettings', array( 'route' => rest_url( 'wprc/v1/callback-requests' ) ) );
} );
/**
 * End add shortcode
 */


/**
 * Add settings page
 */
function wprc_options_page(): void {
	add_options_page(
		'WP Request Callback',
		'WP Request Callback',
		'manage_options',
		'wprc',
		'wprc_options_page_html'
	);
}

add_action( 'admin_menu', 'wprc_options_page' );

function wprc_options_page_html() {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
    <div class="wrap">
        <h1><?= esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
			<?php
			// output security fields for the registered setting "wprc"
			settings_fields( 'wprc' );
			// output setting sections and their fields
			// (sections are registered for "wprc", each field is registered to a specific section)
			do_settings_sections( 'wprc' );
			// output save settings button
			submit_button( 'Save Settings' );
			?>
        </form>
    </div>
	<?php
}

function wprc_settings_init(): void {
	register_setting( 'wprc', 'wprc', array( 'sanitize_callback' => 'wprc_sanitize_callback' ) );

	add_settings_section(
		'wprc_notification',
		'Notification Settings',
		'wprc_notification_settings_section_html',
		'wprc'
	);

	add_settings_field(
		'wprc_email_address',
		'Email Address',
		'wprc_email_address_html',
		'wprc',
		'wprc_notification',
		array( 'label_for' => 'wprc_email_address' )
	);

	add_settings_field(
		'wprc_slack_url',
		'Slack Webhook URL',
		'wprc_slack_url_html',
		'wprc',
		'wprc_notification',
		array( 'label_for' => 'wprc_slack_url' )
	);

	add_settings_section(
		'wprc_styling',
		'Shortcode Styling',
		'wprc_styling_settings_section_html',
		'wprc'
	);

	add_settings_field(
		'wprc_slack_url',
		'Theme Color',
		'wprc_theme_color_html',
		'wprc',
		'wprc_styling',
		array( 'label_for' => 'wprc_theme_color' )
	);
}

function wprc_add_color_picker(): void {
	if ( is_admin() ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wprc-color-picker', plugins_url( 'color-picker.js', __FILE__ ), array( 'wp-color-picker' ), '', true );
	}
}

add_action( 'admin_enqueue_scripts', 'wprc_add_color_picker' );

add_action( 'admin_init', 'wprc_settings_init' );

function wprc_sanitize_callback( array $input ) {
	$newInput = get_option( 'wprc' );

	$sanitizedEmail      = sanitize_email( trim( $input['email_address'] ) );
	$sanitizedThemeColor = strip_tags( stripslashes( trim( $input['theme_color'] ) ) );

	if ( empty( $sanitizedEmail ) && ! empty( trim( $input['email_address'] ) ) ) {
		add_settings_error( 'wprc', 'wprc_email_address', 'Invalid email address' );
	} else {
		$newInput['email_address'] = $sanitizedEmail;
	}

	if ( ! preg_match( '/^#[a-f0-9]{6}$/i', $sanitizedThemeColor ) ) {
		add_settings_error( 'wprc', 'wprc_theme_color', 'Invalid theme color.' );
	} else {
		$newInput['theme_color'] = $sanitizedThemeColor;
	}


	$newInput['slack_url'] = esc_url( trim( $input['slack_url'] ) );

	return $newInput;
}

function wprc_notification_settings_section_html(): void {
	?>
    <p>Leave an input blank to disable that notification channel.</p>
	<?php
}

function wprc_email_address_html(): void {
	$emailAddress = isset( get_option( 'wprc' )['email_address'] ) ? get_option( 'wprc' )['email_address'] : '';
	?>
    <input id="wprc_email_address"
           type="email"
           size="50"
           value="<?= $emailAddress ?>"
           name="wprc[email_address]">
	<?php
}

function wprc_slack_url_html(): void {
	$slackUrl = isset( get_option( 'wprc' )['slack_url'] ) ? get_option( 'wprc' )['slack_url'] : '';
	?>
    <input id="wprc_slack_url"
           type="text"
           size="100"
           value="<?= $slackUrl ?>"
           name="wprc[slack_url]">
	<?php
}

function wprc_styling_settings_section_html(): void {
	?>
    <p>When using the built in shortcode, this color will be used by default for the background color of the button and
        border color of focussed inputs.</p>
    <p>The color can be overridden on specific shortcodes by using the 'color' option.</p>
    <p>Be aware that the button will have white text and the form will be displayed on a white background.</p>
	<?php
}

function wprc_theme_color_html(): void {
	$color = isset( get_option( 'wprc' )['theme_color'] ) ? get_option( 'wprc' )['theme_color'] : WPRC_DEFAULT_THEME_COLOR;
	?>
    <input id="wprc_theme_color"
           class="wprc-color-picker"
           type="text"
           value="<?= $color ?>"
           name="wprc[theme_color]">
	<?php
}
/**
 * End add settings page
 */
