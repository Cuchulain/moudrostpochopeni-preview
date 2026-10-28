<?php
/**
 * Headings: larger sizes (Newsreader is much narrower than the old Shrikhand) and the
 * Starorůžová global color, so all headings can be recolored from Elementor Site Settings.
 *
 * Run: docker compose run --rm cli wp eval-file /opt/moudrost/migrations/headings.php
 * Runs only once – sizes would otherwise grow on every run.
 */

defined( 'ABSPATH' ) || exit;

const MP_HEADINGS_VERSION = 'headings-1';
const MP_HEADING_SCALE    = 1.3;
const MP_HEADING_COLOR    = '#96576A';
const MP_HEADING_GLOBAL   = 'globals/colors?id=mp0rose';

// Dark heading colors that become the global color; light ones sit on dark backgrounds and stay.
const MP_RECOLOR = array( '#6C283F', '#302B2D', '#8C4057' );
const MP_RECOLOR_GLOBALS = array( 'globals/colors?id=primary', 'globals/colors?id=secondary', 'globals/colors?id=text' );

if ( get_option( 'mp_headings_version' ) === MP_HEADINGS_VERSION ) {
	WP_CLI::success( 'Headings already updated.' );
	return;
}

/**
 * Small and medium sizes grow; large display headings are already big enough.
 */
function mp_scale_size( array $size ): array {
	$limit = array( 'px' => 40, 'rem' => 2.5, 'em' => 2.5 )[ $size['unit'] ?? 'px' ] ?? 0;
	if ( isset( $size['size'] ) && is_numeric( $size['size'] ) && $size['size'] > 0 && $size['size'] <= $limit ) {
		$scaled       = $size['size'] * MP_HEADING_SCALE;
		$size['size'] = 'px' === $size['unit'] ? (int) round( $scaled ) : round( $scaled, 2 );
	}
	return $size;
}

function mp_update_heading( array $settings ): array {
	foreach ( array( 'typography_font_size', 'typography_font_size_tablet', 'typography_font_size_mobile' ) as $key ) {
		if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
			$settings[ $key ] = mp_scale_size( $settings[ $key ] );
		}
	}

	$color  = isset( $settings['title_color'] ) ? strtoupper( $settings['title_color'] ) : null;
	$global = $settings['__globals__']['title_color'] ?? null;
	if ( ( null === $color && ( null === $global || '' === $global ) ) || in_array( $color, MP_RECOLOR, true ) || in_array( $global, MP_RECOLOR_GLOBALS, true ) ) {
		unset( $settings['title_color'] );
		$settings['__globals__']                = (array) ( $settings['__globals__'] ?? array() );
		$settings['__globals__']['title_color'] = MP_HEADING_GLOBAL;
	}
	return $settings;
}

function mp_walk_headings( array $node ): array {
	if ( ( $node['widgetType'] ?? '' ) === 'heading' ) {
		$node['settings'] = mp_update_heading( (array) $node['settings'] );
	}
	if ( isset( $node['elements'] ) ) {
		$node['elements'] = array_map( 'mp_walk_headings', $node['elements'] );
	}
	return $node;
}

// --- Elementor content -------------------------------------------------------------

global $wpdb;
$rows = $wpdb->get_results(
	"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m
	 JOIN {$wpdb->posts} p ON p.ID = m.post_id
	 WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision'"
);
$changed = 0;
foreach ( $rows as $row ) {
	$data = json_decode( $row->meta_value, true );
	if ( ! is_array( $data ) ) {
		continue;
	}
	$new = array_map( 'mp_walk_headings', $data );
	if ( $new !== $data ) {
		update_metadata( 'post', $row->post_id, '_elementor_data', wp_slash( wp_json_encode( $new ) ) );
		++$changed;
	}
}
WP_CLI::log( "Updated headings in $changed Elementor documents" );

// --- Elementor Site Settings ---------------------------------------------------------

$kit_id   = (int) get_option( 'elementor_active_kit' );
$settings = get_post_meta( $kit_id, '_elementor_page_settings', true ) ?: array();
foreach ( array(
	'h1' => array( 40, 34, 30 ),
	'h2' => array( 34, 30, 27 ),
	'h3' => array( 27, 24, 22 ),
) as $tag => [ $desktop, $tablet, $mobile ] ) {
	$settings[ "{$tag}_color" ]                         = MP_HEADING_COLOR;
	$settings['__globals__'][ "{$tag}_color" ]          = MP_HEADING_GLOBAL;
	$settings[ "{$tag}_typography_typography" ]         = 'custom';
	$settings[ "{$tag}_typography_font_size" ]          = array( 'unit' => 'px', 'size' => $desktop, 'sizes' => array() );
	$settings[ "{$tag}_typography_font_size_tablet" ]   = array( 'unit' => 'px', 'size' => $tablet, 'sizes' => array() );
	$settings[ "{$tag}_typography_font_size_mobile" ]   = array( 'unit' => 'px', 'size' => $mobile, 'sizes' => array() );
	$settings[ "{$tag}_typography_line_height" ]        = array( 'unit' => 'em', 'size' => 1.15, 'sizes' => array() );
}
update_post_meta( $kit_id, '_elementor_page_settings', $settings );
WP_CLI::log( 'Updated heading defaults in Elementor Site Settings' );

// --- Customizer (blog posts and pages without Elementor) -----------------------------

$mods = get_option( 'theme_mods_' . get_stylesheet(), array() );
foreach ( array(
	'headings_typography'   => null,
	'heading_h1_typography' => '40px',
	'heading_h2_typography' => '34px',
	'heading_h3_typography' => '27px',
) as $key => $size ) {
	$mods[ $key ]          = (array) ( $mods[ $key ] ?? array() );
	$mods[ $key ]['color'] = MP_HEADING_COLOR;
	if ( $size ) {
		$mods[ $key ]['font-size'] = $size;
	}
}
update_option( 'theme_mods_' . get_stylesheet(), $mods );
WP_CLI::log( 'Updated heading colors and sizes in the Customizer' );

update_option( 'mp_headings_version', MP_HEADINGS_VERSION );
\Elementor\Plugin::$instance->files_manager->clear_cache();
WP_CLI::success( 'Headings updated.' );
