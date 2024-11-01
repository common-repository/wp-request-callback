=== WP Request Callback ===
Contributors: kiteframe
Tags: request,callback,callback request,phone back,phone,form
Requires at least: 5.0
Tested up to: 5.2
Stable tag: 0.1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture callback requests from potential clients on your site. Use our built in forms or create your own. Simple, customisable, and easy to use.

== Description ==

Easily add a form to any page on your site to allow visitors to leave their name and number to request a callback.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-request-callback` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Use the Settings->WP Request Callback screen to add an email address and/or Slack Webhook URL to enable notifications.
1. Add a built in form to your page using our shortcode, or if you're feeling adventurous, write your own using the API. See instructions for both below.

== Settings ==

The settings page can be reached by clicking on Settings in the WordPress admin menu, and then clicking on WP Request Callback.

Here you can add an email address to receive email notifications, as well as a Slack Webhook URL to enable notifications straight into a Slack channel.

Also on this page you can choose the default theme color. This will be used by the shortcode as the background color of the submit button and the border color of the focussed inputs.

The color can be overridden on a per form basis by using the shortcode settings as documented below.

== Configuring the Shortcode ==

The shortcode usage is as follows: `[wprc success_message="This is my custom success message." error_message="Something went wrong." color="#9f7aea"]`

* success_message is the text displayed to the user after the form is submitted successfully. It is optional and if omitted the default message is 'Thanks for submitting your callback request.'
* error_message is the text displayed to the user if an unexpected error occurs with the submission. It is optional and if omitted the default message is 'Something went wrong. Please try again.'
* color is a hex color code that is used to style the button and inputs of the form. It is optional, and if omitted, the color set on the settings page is used, or blue by default (#9f7aea).

== API Documentation ==

The plugin makes use of the WordPress [REST API](https://developer.wordpress.org/rest-api/).

= Endpoint =
POST /wp-json/wprc/v1/callback-requests

= Request Body =
name: Required, string.
phone: Required, string, numbers or spaces.

Example:
{ name: 'Name', phone: '01234567890' }

= Responses =
*Success*
Status: 201
Response data: 'Success'

*Validation error*
Status: 422
Response data: { errors: { name: ['Example validation error'], phone: ['Example validation error'] } }

== Frequently Asked Questions ==

= Help - something went wrong! =

If you're having any problems at all with this plugin, don't hesitate to get in touch on the support forum and we'll respond as soon as we can.

== Screenshots ==

1. The settings screen.
2. The built in form.
3. The built in form as part of the WordPress Twenty Nineteen theme.
4. Adding the shortcode to a page.
5. A success message after submission.
6. Customising the theme colour.
7. Email notifications.
8. Slack notifications.
9. List of callback requests in the admin screen.

== Changelog ==

= 0.1.0 =
* First release!
