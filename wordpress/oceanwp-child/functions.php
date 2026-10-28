<?php
/**
 * Moudrost pochopení – OceanWP child theme.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/rozcestnik.php';

/**
 * Theme mods are stored per theme, so without this the header, menu and logo
 * settings from the parent Customizer would vanish when the child is activated.
 */
add_action(
	'after_switch_theme',
	static function () {
		if ( get_option( 'theme_mods_' . get_stylesheet() ) ) {
			return;
		}

		$parent_mods = get_option( 'theme_mods_' . get_template() );
		if ( $parent_mods ) {
			update_option( 'theme_mods_' . get_stylesheet(), $parent_mods );
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$dir     = get_stylesheet_directory_uri() . '/assets/css/';
		$version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style( 'moudrost-fonts', $dir . 'fonts.css', array(), $version );
		wp_enqueue_style( 'moudrost-theme', $dir . 'theme.css', array( 'oceanwp-style' ), $version );
	},
	20
);

/**
 * Preload the two font files used above the fold.
 */
add_action(
	'wp_head',
	static function () {
		$fonts = get_stylesheet_directory_uri() . '/assets/fonts/';
		foreach ( array( 'newsreader-normal-latin.woff2', 'dm-sans-normal-latin.woff2' ) as $file ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts . $file ) );
		}
	},
	1
);

// Fonts are self-hosted (GDPR), Elementor must not load them from Google.
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
