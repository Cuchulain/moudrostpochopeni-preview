<?php
/**
 * Plugin Name: Local development safeguards
 * Description: Keeps the local copy of the site from e-mailing real people or being indexed.
 */

if ( wp_get_environment_type() !== 'local' ) {
	return;
}

// Pretend every e-mail was sent so forms behave normally, but never deliver it.
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		error_log( 'local-dev: blocked e-mail "' . $atts['subject'] . '"' );
		return true;
	},
	10,
	2
);

add_filter( 'pre_option_blog_public', '__return_zero' );

// Complianz derives the cookie path from the site URL and keeps the port (":8080/"),
// so the consent cookie is never sent back and the banner reappears on every page.
add_filter( 'cmplz_cookie_path', static fn() => '/' );
