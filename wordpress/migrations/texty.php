<?php
/**
 * Sales-oriented copy for existing pages, aimed at business owners, managers and
 * entrepreneurs' wives (40+) who want a real change.
 *
 * Run: docker compose run --rm cli wp eval-file /opt/moudrost/migrations/texty.php
 * Each widget is rewritten only while it still holds the original text, so edits
 * made later in Elementor are never overwritten.
 */

defined( 'ABSPATH' ) || exit;

/**
 * page slug => widget id => [ text the original starts with, new settings ]
 */
$changes = array(
	'' => array(
		'938dfb4' => array(
			'Řešíš dlouhodobé problémy',
			array(
				'title'       => 'Navenek všechno zvládáš.<br>Uvnitř ti dochází síla.',
				'header_size' => 'h1',
				'typography_typography'       => 'custom',
				'typography_font_size'        => array( 'unit' => 'px', 'size' => 48, 'sizes' => array() ),
				'typography_font_size_tablet' => array( 'unit' => 'px', 'size' => 40, 'sizes' => array() ),
				'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 32, 'sizes' => array() ),
				'typography_line_height'      => array( 'unit' => 'em', 'size' => 1.1, 'sizes' => array() ),
			),
		),
		'5345e0f' => array(
			'<p><span',
			array(
				'editor' => '<p>Vedeš firmu nebo tým, držíš pohromadě rodinu a okolí obdivuje, jak to všechno skvěle zvládáš. Jenže únava nepřechází, přibývají výkyvy nálad a vnitřní neklid a věci, které tě dřív těšily, ztratily chuť.</p>'
					. '<p>Pomáhám podnikatelkám, manažerkám a ženám v náročných životních změnách <strong>pochopit, co se s nimi děje, a udělat změnu, která vydrží</strong> – v práci, ve vztazích i ve vztahu k sobě.</p>'
					. '<p>Jsem <strong>Dita Doleželová</strong>, koučka a mentorka psychosomatiky s více než 15 lety praxe. Pracuji individuálně a diskrétně, osobně v Havlíčkově Brodě i online.</p>',
			),
		),
		'a74d139' => array(
			'Objednat',
			array( 'text' => 'Domluvit úvodní konzultaci' ),
		),
	),
);

function mp_texty_apply( array $nodes, array $widgets, int &$changed ): array {
	foreach ( $nodes as $i => $node ) {
		$id = $node['id'] ?? '';
		if ( isset( $widgets[ $id ] ) ) {
			[ $original, $settings ] = $widgets[ $id ];
			$key     = array_key_first( array_intersect_key( $node['settings'], array_flip( array( 'title', 'editor', 'text' ) ) ) );
			$current = (string) ( $node['settings'][ $key ] ?? '' );
			if ( str_starts_with( ltrim( $current ), $original ) ) {
				$nodes[ $i ]['settings'] = array_merge( $node['settings'], $settings );
				++$changed;
			}
		}
		if ( ! empty( $node['elements'] ) ) {
			$nodes[ $i ]['elements'] = mp_texty_apply( $node['elements'], $widgets, $changed );
		}
	}
	return $nodes;
}

foreach ( $changes as $slug => $widgets ) {
	$post_id = '' === $slug ? (int) get_option( 'page_on_front' ) : (int) get_page_by_path( $slug )?->ID;
	$data    = json_decode( get_post_meta( $post_id, '_elementor_data', true ) ?: '[]', true );
	$changed = 0;
	$data    = mp_texty_apply( $data, $widgets, $changed );
	if ( $changed ) {
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_element_cache' );
	}
	WP_CLI::log( sprintf( '%s: %d/%d textů přepsáno', '' === $slug ? 'úvodní stránka' : "/$slug", $changed, count( $widgets ) ) );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
WP_CLI::success( 'Texty hotové.' );
