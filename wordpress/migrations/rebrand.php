<?php
/**
 * Rebrand: activates the child theme and moves colors, fonts and logo to the new identity.
 *
 * Run: docker compose run --rm cli wp eval-file /opt/moudrost/migrations/rebrand.php
 * Safe to run repeatedly – it only rewrites old values.
 */

defined( 'ABSPATH' ) || exit;

const MP_CHILD_THEME = 'oceanwp-child';

// Old palette (burgundy, yellow, cream, purples) → new palette from the Astro preview.
const MP_COLOR_MAP = array(
	'7B0838' => '6C283F',
	'800000' => '6C283F',
	'5E0481' => '6C283F',
	'8A07BD' => '6C283F',
	'49027C' => '6C283F',
	'7A0660' => '8C4057',
	'BD1096' => '8C4057',
	'7E1370' => '8C4057',
	'73055B' => '8C4057',
	'993366' => '8C4057',
	'993300' => '8C4057',
	'9F2F33' => '8C4057',
	'61CE70' => '8C4057',
	'CB29CE' => '96576A',
	'FF2DE4' => '96576A',
	'F4D676' => 'EED6DF',
	'F9E1DB' => 'EADDE1',
	'FFF9E6' => 'F7F2ED',
	'000000' => '302B2D',
	'020201' => '302B2D',
	'060000' => '302B2D',
	'070000' => '302B2D',
	'0A0000' => '302B2D',
	'0A0A0A' => '302B2D',
	'49444C' => '62565B',
);

const MP_SERIF = 'Newsreader';
const MP_SANS  = 'DM Sans';

// Display fonts become the serif, everything else the sans. Heading widgets are always serif.
const MP_SERIF_SOURCES = array( 'Shrikhand', 'Quando', 'Montserrat' );
const MP_KEEP_FONTS    = array( '', 'Roboto Mono', MP_SERIF, MP_SANS );

function mp_map_color( string $value ): string {
	// Inline fonts pasted into text widgets would override the theme fonts.
	if ( str_contains( $value, '<' ) ) {
		$value = preg_replace( '/\s*font-family:\s*[^;"]+;?/i', '', $value );
	}

	return preg_replace_callback(
		'/#([0-9a-f]{6})([0-9a-f]{2})?\b/i',
		static function ( $m ) {
			$hex = strtoupper( $m[1] );
			return isset( MP_COLOR_MAP[ $hex ] ) ? '#' . MP_COLOR_MAP[ $hex ] . strtoupper( $m[2] ?? '' ) : $m[0];
		},
		$value
	);
}

function mp_map_font( string $font, bool $heading ): string {
	if ( in_array( $font, MP_KEEP_FONTS, true ) ) {
		return $font;
	}
	return ( $heading || in_array( $font, MP_SERIF_SOURCES, true ) ) ? MP_SERIF : MP_SANS;
}

/**
 * Walks Elementor data / theme mods and rewrites colors and font families in place.
 */
function mp_walk( $node, bool $heading = false ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) ) {
			$heading = 'heading' === $node['widgetType'];
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $value ) && is_string( $key ) && ( str_ends_with( $key, 'font_family' ) || 'font-family' === $key ) ) {
				$node[ $key ] = mp_map_font( $value, $heading );
			} else {
				$node[ $key ] = mp_walk( $value, $heading );
			}
		}
		return $node;
	}
	return is_string( $node ) ? mp_map_color( $node ) : $node;
}

function mp_log( string $message ): void {
	WP_CLI::log( $message );
}

// --- Child theme -------------------------------------------------------------

if ( ! wp_get_theme( MP_CHILD_THEME )->exists() ) {
	WP_CLI::error( 'Child theme ' . MP_CHILD_THEME . ' is not installed.' );
}

if ( get_stylesheet() !== MP_CHILD_THEME ) {
	switch_theme( MP_CHILD_THEME );
	mp_log( 'Activated ' . MP_CHILD_THEME );
}

$mods = get_option( 'theme_mods_' . MP_CHILD_THEME );
if ( ! $mods || count( $mods ) < 5 ) {
	$mods = get_option( 'theme_mods_oceanwp', array() );
	mp_log( 'Copied Customizer settings from OceanWP' );
}

// --- Logo ----------------------------------------------------------------------

$logo_id = (int) ( get_posts(
	array(
		'post_type'   => 'attachment',
		'meta_key'    => '_moudrost_logo',
		'fields'      => 'ids',
		'numberposts' => 1,
	)
)[0] ?? 0 );

if ( ! $logo_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( 'logo-moudrost-pochopeni.png' );
	copy( get_stylesheet_directory() . '/assets/images/logo-moudrost-pochopeni.png', $tmp );
	$logo_id = media_handle_sideload(
		array(
			'name'     => 'logo-moudrost-pochopeni.png',
			'tmp_name' => $tmp,
		),
		0,
		'Moudrost pochopení – Dita Doleželová'
	);
	if ( is_wp_error( $logo_id ) ) {
		WP_CLI::error( $logo_id->get_error_message() );
	}
	update_post_meta( $logo_id, '_moudrost_logo', 1 );
	update_post_meta( $logo_id, '_wp_attachment_image_alt', 'Moudrost pochopení – Dita Doleželová' );
	mp_log( "Uploaded logo (attachment $logo_id)" );
}

// --- Customizer ------------------------------------------------------------------

$mods = mp_walk( $mods );
$mods = array_merge(
	$mods,
	array(
		'custom_logo'                  => $logo_id,
		'ocean_logo_max_width'         => 244,
		'ocean_logo_max_height'        => 120,
		'ocean_logo_max_width_mobile'  => 180,
		'ocean_logo_max_height_mobile' => 88,
		'ocean_disable_google_font'    => true,
		'ocean_theme_button_hover_bg'  => '#8C4057',
		'ocean_theme_button_hover_color' => '#FFFCF9',
	)
);

// Shrikhand was a light display face; the serif reads best at regular weight.
foreach ( array( 'headings_typography', 'heading_h1_typography', 'heading_h2_typography', 'heading_h3_typography', 'blog_entry_title_typography' ) as $key ) {
	if ( isset( $mods[ $key ]['font-weight'] ) ) {
		$mods[ $key ]['font-weight'] = 'heading_h3_typography' === $key ? '500' : '400';
	}
}

update_option( 'theme_mods_' . MP_CHILD_THEME, $mods );
// WordPress prefers the block-editor Site Logo setting over the Customizer one.
update_option( 'site_logo', $logo_id );
mp_log( 'Updated Customizer settings' );

// --- Elementor Site Settings (kit) ------------------------------------------------

$kit_id   = (int) get_option( 'elementor_active_kit' );
$settings = mp_walk( get_post_meta( $kit_id, '_elementor_page_settings', true ) ?: array() );

$color_titles = array(
	'primary'   => 'Vínová',
	'secondary' => 'Inkoust',
	'text'      => 'Text',
	'accent'    => 'Švestková',
	'8dc6730'   => 'Růžová světlá',
	'5314f90'   => 'Papír',
);
foreach ( array( 'system_colors', 'custom_colors' ) as $group ) {
	foreach ( $settings[ $group ] ?? array() as $i => $color ) {
		if ( isset( $color_titles[ $color['_id'] ] ) ) {
			$settings[ $group ][ $i ]['title'] = $color_titles[ $color['_id'] ];
		}
	}
}
$existing_ids = wp_list_pluck( $settings['custom_colors'] ?? array(), '_id' );
foreach ( array(
	'mp0rose' => array( 'Starorůžová', '#96576A' ),
	'mp0pale' => array( 'Růžová mlha', '#EADDE1' ),
	'mp0soft' => array( 'Text jemný', '#62565B' ),
) as $id => [ $title, $hex ] ) {
	if ( ! in_array( $id, $existing_ids, true ) ) {
		$settings['custom_colors'][] = array( '_id' => $id, 'title' => $title, 'color' => $hex );
	}
}

$typography = array(
	'primary'   => array( MP_SERIF, '500' ),
	'secondary' => array( MP_SANS, '400' ),
	'text'      => array( MP_SANS, '400' ),
	'accent'    => array( MP_SANS, '600' ),
);
foreach ( $settings['system_typography'] ?? array() as $i => $item ) {
	if ( isset( $typography[ $item['_id'] ] ) ) {
		$settings['system_typography'][ $i ]['typography_font_family'] = $typography[ $item['_id'] ][0];
		$settings['system_typography'][ $i ]['typography_font_weight'] = $typography[ $item['_id'] ][1];
	}
}

$settings['body_typography_font_family'] = MP_SANS;
foreach ( array( 'h1' => '400', 'h2' => '400', 'h3' => '500' ) as $tag => $weight ) {
	$settings[ "{$tag}_typography_font_family" ] = MP_SERIF;
	$settings[ "{$tag}_typography_font_weight" ] = $weight;
}

update_post_meta( $kit_id, '_elementor_page_settings', $settings );
mp_log( "Updated Elementor Site Settings (kit $kit_id)" );

update_option( 'elementor_google_font', '0' );

// --- Elementor content -----------------------------------------------------------

global $wpdb;
$rows = $wpdb->get_results(
	"SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
	 JOIN {$wpdb->posts} p ON p.ID = m.post_id
	 WHERE ( m.meta_key IN ('_elementor_data', '_elementor_page_settings') OR m.meta_key LIKE 'ocean\\_%' )
	   AND p.post_type <> 'revision' AND p.ID <> $kit_id"
);

$changed = 0;
foreach ( $rows as $row ) {
	if ( '_elementor_data' === $row->meta_key ) {
		$data = json_decode( $row->meta_value, true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		$new = wp_slash( wp_json_encode( mp_walk( $data ) ) );
		if ( wp_unslash( $new ) !== $row->meta_value ) {
			update_metadata( 'post', $row->post_id, '_elementor_data', $new );
			++$changed;
		}
	} else {
		// OceanWP page settings (subheadings etc.) and Elementor page settings.
		$data = maybe_unserialize( $row->meta_value );
		$new  = mp_walk( $data );
		if ( $new !== $data ) {
			update_post_meta( $row->post_id, $row->meta_key, wp_slash( $new ) );
			++$changed;
		}
	}
}
mp_log( "Updated $changed Elementor and OceanWP page settings" );

// --- Complianz cookie banner -----------------------------------------------------

$banner_table = $wpdb->prefix . 'cmplz_cookiebanners';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $banner_table ) ) === $banner_table ) {
	foreach ( $wpdb->get_results( "SELECT * FROM $banner_table", ARRAY_A ) as $banner ) {
		$update = array();
		foreach ( $banner as $column => $value ) {
			if ( is_string( $value ) && mp_map_color( $value ) !== $value ) {
				// Hex codes keep their length, so serialized arrays stay valid.
				$update[ $column ] = mp_map_color( $value );
			}
		}
		if ( $update ) {
			$wpdb->update( $banner_table, $update, array( 'ID' => $banner['ID'] ) );
			mp_log( "Updated Complianz banner {$banner['ID']}" );
		}
		if ( class_exists( 'CMPLZ_COOKIEBANNER' ) ) {
			delete_transient( 'cmplz_generate_css_active' );
			( new CMPLZ_COOKIEBANNER( (int) $banner['ID'] ) )->generate_css();
		}
	}
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
mp_log( 'Cleared Elementor CSS cache' );

WP_CLI::success( 'Rebrand applied.' );
