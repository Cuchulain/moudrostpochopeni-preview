<?php
/**
 * Rozcestník: new area pages, Psychosomatika, interlinking of service pages and the main menu.
 * Card texts and the area structure live in oceanwp-child/inc/rozcestnik.php.
 *
 * Run: docker compose run --rm cli wp eval-file /opt/moudrost/migrations/rozcestnik.php
 * Safe to run repeatedly – pages are created only once and replaced sections are recognised.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mp_areas' ) ) {
	WP_CLI::error( 'Activate the oceanwp-child theme first (migrations/rebrand.php).' );
}

const MP_CONTACT_URL = '/kontakt/';

// Bump when the texts of the generated pages below change; older generated pages get rewritten.
const MP_CONTENT_VERSION = 2;

// --- Elementor helpers -------------------------------------------------------------

function mp_el_id(): string {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
}

function mp_widget( string $type, array $settings ): array {
	return array(
		'id'         => mp_el_id(),
		'elType'     => 'widget',
		'settings'   => $settings,
		'elements'   => array(),
		'widgetType' => $type,
	);
}

function mp_section( array $widgets, array $settings = array() ): array {
	return array(
		'id'       => mp_el_id(),
		'elType'   => 'section',
		'settings' => $settings + array(
			'content_width' => array( 'unit' => 'px', 'size' => 1180, 'sizes' => array() ),
			'padding'       => array( 'unit' => 'px', 'top' => '50', 'right' => '20', 'bottom' => '50', 'left' => '20', 'isLinked' => false ),
		),
		'elements' => array(
			array(
				'id'       => mp_el_id(),
				'elType'   => 'column',
				'settings' => array( '_column_size' => 100, '_inline_size' => null ),
				'elements' => $widgets,
				'isInner'  => false,
			),
		),
		'isInner'  => false,
	);
}

function mp_heading( string $title, string $tag = 'h2', string $align = 'left' ): array {
	return mp_widget( 'heading', array( 'title' => $title, 'header_size' => $tag, 'align' => $align ) );
}

function mp_text( string $html ): array {
	return mp_widget( 'text-editor', array( 'editor' => $html ) );
}

function mp_button( string $text, string $url ): array {
	return mp_widget(
		'button',
		array(
			'text'  => $text,
			'link'  => array( 'url' => $url, 'is_external' => '', 'nofollow' => '' ),
			'align' => 'center',
		)
	);
}

function mp_shortcode( string $shortcode ): array {
	return mp_widget( 'shortcode', array( 'shortcode' => $shortcode ) );
}

function mp_rozcestnik_section( string $title = 'Zajímá tě, s čím dalším ti můžu pomoci?' ): array {
	return mp_section(
		array( mp_shortcode( '[mp_rozcestnik eyebrow="Jak ti můžu pomoci" title="' . esc_attr( $title ) . '"]' ) ),
		array( 'css_classes' => 'mp-rozcestnik-section' )
	);
}

function mp_cta_section(): array {
	return mp_section(
		array(
			mp_heading( 'Udělej první krok', 'h2', 'center' ),
			mp_text( '<p style="text-align: center;">Na úvodní konzultaci probereme tvoji situaci a domluvíme, jaká cesta ti dává smysl. Metodu si nemusíš vybírat předem.<br>Diskrétně, osobně v Havlíčkově Brodě nebo online.</p>' ),
			mp_button( 'Domluvit úvodní konzultaci', home_url( MP_CONTACT_URL ) ),
		)
	);
}

function mp_get_data( int $post_id ): array {
	return json_decode( get_post_meta( $post_id, '_elementor_data', true ) ?: '[]', true ) ?: array();
}

function mp_save_data( int $post_id, array $data ): void {
	update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	delete_post_meta( $post_id, '_elementor_css' );
	delete_post_meta( $post_id, '_elementor_element_cache' );
}

/**
 * Collects all link URLs and texts inside an Elementor element.
 */
function mp_collect( array $node, array &$links, array &$texts ): void {
	if ( isset( $node['settings']['link']['url'] ) ) {
		$links[] = $node['settings']['link']['url'];
	}
	foreach ( array( 'title', 'editor', 'shortcode' ) as $key ) {
		if ( isset( $node['settings'][ $key ] ) && is_string( $node['settings'][ $key ] ) ) {
			$texts[] = $node['settings'][ $key ];
		}
	}
	foreach ( $node['elements'] ?? array() as $child ) {
		mp_collect( $child, $links, $texts );
	}
}

/**
 * The old "Zajímá tě, jaké další služby poskytuji?" rows: three or more cards linking to service pages.
 */
function mp_is_old_services_row( array $section ): bool {
	$links = array();
	$texts = array();
	mp_collect( $section, $links, $texts );
	$services = array_filter(
		array_unique( $links ),
		static fn( $url ) => preg_match( '#/(koucink|etikoterapie|bachovy-kvetove-esence|kraniosakralni-terapie|lecive-masaze|tehotenska-masaz|seminare-a-workshopy)#', $url )
	);
	return count( $services ) >= 3;
}

function mp_has_rozcestnik( array $data ): bool {
	return str_contains( wp_json_encode( $data ), '[mp_rozcestnik' );
}

function mp_is_contact_row( array $section ): bool {
	$links = array();
	$texts = array();
	mp_collect( $section, $links, $texts );
	return in_array( 'Kontakt', $texts, true ) && in_array( 'Buďme ve spojení', $texts, true );
}

/**
 * Replaces the old services rows with the rozcestník, or adds it before the contact row.
 */
function mp_link_page( int $post_id, string $title = 'Zajímá tě, s čím dalším ti můžu pomoci?' ): string {
	$data = mp_get_data( $post_id );
	if ( ! $data || mp_has_rozcestnik( $data ) ) {
		return 'beze změny';
	}

	$replaced = false;
	foreach ( $data as $i => $section ) {
		if ( mp_is_old_services_row( $section ) ) {
			$data[ $i ] = $replaced ? null : mp_rozcestnik_section( $title );
			$replaced   = true;
		}
	}
	$data = array_values( array_filter( $data ) );

	if ( ! $replaced ) {
		$position = count( $data );
		foreach ( $data as $i => $section ) {
			if ( mp_is_contact_row( $section ) ) {
				$position = $i;
				break;
			}
		}
		array_splice( $data, $position, 0, array( mp_rozcestnik_section( $title ) ) );
	}

	mp_save_data( $post_id, $data );
	return $replaced ? 'nahrazen starý blok služeb' : 'rozcestník doplněn';
}

/**
 * Creates an Elementor page; OceanWP page settings are copied from a similar page.
 * An existing page is only rewritten when it was generated by an older MP_CONTENT_VERSION.
 */
function mp_create_page( string $slug, string $title, string $subheading, string $copy_from, int $header_image, string $metadesc, array $sections ): int {
	$source   = get_page_by_path( $copy_from );
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $existing ) {
		if ( (int) get_post_meta( $existing->ID, '_mp_content_version', true ) >= MP_CONTENT_VERSION ) {
			WP_CLI::log( "Stránka /$slug je aktuální (ID {$existing->ID})" );
		} else {
			mp_fill_page( $existing->ID, $subheading, $source->ID, $metadesc, $sections );
			WP_CLI::log( "Stránka /$slug přepsána na verzi textů " . MP_CONTENT_VERSION );
		}
		return $existing->ID;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( $post_id->get_error_message() );
	}

	foreach ( get_post_meta( $source->ID ) as $key => $values ) {
		if ( str_starts_with( $key, 'ocean_' ) ) {
			update_post_meta( $post_id, $key, maybe_unserialize( $values[0] ) );
		}
	}
	update_post_meta( $post_id, 'ocean_post_title', $title );
	update_post_meta( $post_id, 'ocean_post_title_background', $header_image );
	update_post_meta( $post_id, '_wp_page_template', 'default' );
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
	mp_fill_page( $post_id, $subheading, $source->ID, $metadesc, $sections );

	WP_CLI::log( "Vytvořena stránka /$slug (ID $post_id)" );
	return $post_id;
}

function mp_fill_page( int $post_id, string $subheading, int $source_id, string $metadesc, array $sections ): void {
	update_post_meta( $post_id, 'ocean_post_subheading', '<strong><span style="color: #EED6DF">' . esc_html( $subheading ) . '</span></strong>' );
	update_post_meta( $post_id, '_yoast_wpseo_metadesc', $metadesc );
	update_post_meta( $post_id, '_mp_content_version', MP_CONTENT_VERSION );
	// Same contact footer as the rest of the site.
	foreach ( mp_get_data( $source_id ) as $section ) {
		if ( mp_is_contact_row( $section ) ) {
			$sections[] = $section;
		}
	}
	mp_save_data( $post_id, $sections );
}

// --- Area pages --------------------------------------------------------------------

mp_create_page(
	'emoce-a-psychika',
	'Emoce a psychika',
	'Když navenek všechno zvládáš, ale uvnitř ti dochází síla',
	'etikoterapie-a-emoce',
	3601,
	'Únava, výkyvy nálad, neklid a pocit ztráty kontroly. Pomáhám podnikatelkám, manažerkám a ženám v životních změnách získat zpět energii a jistotu. Havlíčkův Brod i online.',
	array(
		mp_section(
			array(
				mp_text( '<p>Únava, která nepřejde ani po dovolené. Výkyvy nálad, úzkost a vnitřní neklid. Pocit, že ztrácíš kontrolu – nad svými reakcemi, nad časem i nad vlastním životem. Často to přichází ve chvíli, kdy se mění víc věcí najednou: náročná práce, děti odcházejí z domu, mění se tělo i role, kterou jsi roky hrála.</p><p>S klientkami hledáme, <strong>co a proč se jim skutečně děje</strong>, a stavíme způsob života, který jim dává smysl – s energií a s jistotou uvnitř.</p>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Komu nejčastěji pomáhám' ),
				mp_text( '<ul><li><strong>Podnikatelkám a manažerkám</strong>, které jsou dlouhodobě přetížené a cítí, že se blíží k vyhoření.</li><li><strong>Ženám po čtyřicítce</strong>, které procházejí změnou – v těle, v rodině i v tom, co od života chtějí.</li><li><strong>Ženám podnikatelů</strong>, které roky nesou rodinu i zázemí firmy a samy se přitom vytrácejí.</li><li><strong>Mužům</strong>, kteří cítí vyčerpání a chtějí na sobě pracovat dřív, než se něco zlomí.</li></ul>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Co ti spolupráce přinese' ),
				mp_text( '<ul><li>pochopíš, co tě vyčerpává a proč se ti některé situace pořád opakují</li><li>zpracuješ emoce, které v sobě dlouho neseš, a uleví se ti</li><li>získáš zpět energii a pocit, že svůj život řídíš ty</li><li>budeš vědět, jak dál – v práci, v rodině i sama se sebou</li></ul>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Jak spolu můžeme pracovat' ),
				mp_shortcode( '[mp_metody oblast="emoce"]' ),
				mp_text( '<p>Součástí obou přístupů je i <strong>práce s mentálními vzorci</strong> – s naučenými přesvědčeními a návyky, které nás drží v kruhu, dokud je nevidíme.</p>' ),
			)
		),
		mp_cta_section(),
		mp_rozcestnik_section(),
	)
);

mp_create_page(
	'vztahy-zmeny-a-vyzvy',
	'Vztahy, změny a výzvy',
	'Rozchod, krize, nebo vztah, od kterého chceš víc',
	'koucink',
	2259,
	'Rozchod, rozvod, partnerská krize nebo napětí v práci. Koučink a párová konzultace pro jednotlivce i páry – jak situaci ustát a nenést si zranění dál. Havlíčkův Brod i online.',
	array(
		mp_section(
			array(
				mp_text( '<p>Vztahy rozhodují o tom, jak se nám žije – doma i v práci. Ať procházíš rozchodem nebo rozvodem, řešíš partnerskou krizi, nebo chceš od vztahu víc než jen žít vedle sebe, pomohu ti pochopit, <strong>co se mezi vámi skutečně děje</strong>.</p><p>Cílem je situaci ustát, neztratit v ní sebe a nenést si zranění do dalších vztahů.</p>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'S čím za mnou chodí jednotlivci i páry' ),
				mp_text( '<p>Pracuji se vztahy <strong>pracovními, osobními i toxickými</strong>:</p><ul><li>rozchod a rozvod – jak je ustát a jít dál bez zátěže do budoucna</li><li>partnerská krize, odcizení nebo nevěra</li><li>vztah, od kterého chcete víc než jen fungovat vedle sebe</li><li>napětí s kolegy, se šéfem, s obchodním partnerem nebo v rodinné firmě</li><li>vztahy, ve kterých se cítíš přehlížený/á, ovládaný/á nebo vyčerpaný/á</li></ul>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Co si ze spolupráce odneseš' ),
				mp_text( '<ul><li>jasno v tom, co chceš a co už nechceš</li><li>porozumění vlastním reakcím a vzorcům, které se ve vztazích opakují</li><li>konkrétní kroky a způsob komunikace, který funguje</li><li>klid a jistotu pro rozhodnutí, která tě čekají</li></ul>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Jak spolu můžeme pracovat' ),
				mp_shortcode( '[mp_metody oblast="vztahy"]' ),
				mp_text( '<p>Ve všech vztazích hrají roli i <strong>mentální vzorce</strong> – to, co jsme se kdysi naučili o sobě a o druhých. Když jim porozumíš, můžeš si začít vybírat jinak.</p>' ),
			)
		),
		mp_cta_section(),
		mp_rozcestnik_section(),
	)
);

mp_create_page(
	'psychosomatika',
	'Psychosomatika',
	'Když tělo mluví za to, co v sobě dlouho nosíš',
	'etikoterapie-a-emoce',
	3657,
	'Psychosomatika – propojení psychologie, etikoterapie a uvolňujících technik. Jak se emoce a mentální vzorce projevují na těle a jak vystoupit z kruhu únavy.',
	array(
		mp_section(
			array(
				mp_text( '<p>Únava, která nepřejde ani po dovolené. Napětí v těle, které se pořád vrací. Pocit, že jen plníš, co musíš a co se od tebe očekává – a na sebe už nezbývá nic.</p><p>Tělo a psychika spolu úzce souvisejí. To, co dlouho potlačujeme, se často ozve právě přes tělo. Psychosomatika ti pomůže porozumět, <strong>co a proč se ti děje</strong>, a najít cestu, jak z toho kruhu vystoupit.</p>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Jak spolu souvisí emoce a tělo' ),
				mp_text( '<p>Emoce, které nemají kudy ven – vztek, smutek, strach nebo zklamání – v nás nemizí. Ukládají se jako napětí, únava nebo potíže, které se opakují. Tělo pak mluví za to, co jsme nahlas neřekli.</p><p>Společně se podíváme, co ti tělo svými signály ukazuje, a hledáme souvislosti mezi tím, co prožíváš, a tím, jak se cítíš.</p>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Mentální vzorce jako kořen zlozvyků' ),
				mp_text( '<p>Spousta zlozvyků a chybných návyků není otázkou slabé vůle. Jsou to naučené vzorce – způsoby, jak jsme se kdysi naučili přežít, zavděčit se nebo nebýt na obtíž. Dokud je nevidíme, řídí nás. Když jim porozumíš, můžeš si začít vybírat jinak.</p>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Jak spolu pracujeme' ),
				mp_text( '<ul><li><strong>Psychologie</strong> – v bezpečném a respektujícím rozhovoru rozklíčujeme, co se ti děje a proč.</li><li><strong><a href="' . esc_url( home_url( '/etikoterapie-a-emoce/' ) ) . '">Etikoterapie</a></strong> – pojmenuješ a zpracuješ nahromaděné pocity a emoce, které stojí za dlouhodobou nespokojeností.</li><li><strong>Katarzní techniky</strong> – pomáhají uvolnit to, co jsi dlouho potlačoval/a, a dát emocím bezpečný průchod.</li><li><strong>Práce s mentálními vzorci</strong> – odhalíš přesvědčení a návyky, které tě drží v kruhu, a najdeš nové, které ti slouží.</li></ul>' ),
			)
		),
		mp_section(
			array(
				mp_heading( 'Cesta ze začarovaného kruhu' ),
				mp_text( '<ol><li><strong>Zastavit se a pojmenovat</strong> – co tě vyčerpává, co musíš a co se od tebe očekává.</li><li><strong>Rozklíčovat příčiny</strong> – jaké emoce a vzorce za tím, co se ti děje, stojí.</li><li><strong>Uvolnit potlačené</strong> – dát průchod emocím, které jsi dlouho nesl/a.</li><li><strong>Vykročit jinak</strong> – malými kroky k životu, ve kterém je místo i pro tebe.</li></ol>' ),
				mp_text( '<p><em>Psychosomatické konzultace nenahrazují lékařské vyšetření ani léčbu. Pokud tě trápí zdravotní potíže, obrať se nejprve na lékaře – naše spolupráce může být podporou vedle jeho péče.</em></p>' ),
			)
		),
		mp_cta_section(),
		mp_rozcestnik_section(),
	)
);

// --- Rozcestník page (keeps the /nabidka-sluzeb URL) -------------------------------

$rozcestnik = get_page_by_path( MP_ROZCESTNIK_PAGE );
if ( 'Jak ti můžu pomoci' !== $rozcestnik->post_title ) {
	wp_update_post( array( 'ID' => $rozcestnik->ID, 'post_title' => 'Jak ti můžu pomoci' ) );
	update_post_meta( $rozcestnik->ID, 'ocean_post_title', 'Jak ti můžu pomoci' );
	WP_CLI::log( 'Stránka Nabídka služeb přejmenována na Jak ti můžu pomoci' );
}

// --- Interlinking ------------------------------------------------------------------

$linked = array( MP_ROZCESTNIK_PAGE => 'Vyber si, co tě teď nejvíc trápí', 'cenik-a-darkove-poukazky' => 'Více o jednotlivých oblastech' );
foreach ( mp_areas() as $area ) {
	foreach ( array_merge( array( $area['page'] ), $area['methods'] ) as $slug ) {
		if ( 'seberespekt' !== $slug ) {
			$linked[ $slug ] ??= 'Zajímá tě, s čím dalším ti můžu pomoci?';
		}
	}
}
foreach ( array( 'neco-malo-o-mne', 'seminare-a-workshopy', 'pribehy-a-zkusenosti-klientu', 'fotogalerie', 'blog' ) as $slug ) {
	$linked[ $slug ] = 'Zajímá tě, s čím ti můžu pomoci?';
}

foreach ( $linked as $slug => $title ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		WP_CLI::log( "/$slug: " . mp_link_page( $page->ID, $title ) );
	}
}

// Homepage: rozcestník right after the hero; "Nabídka služeb" pointed to the contact page by mistake.
$front_id = (int) get_option( 'page_on_front' );
$front    = mp_get_data( $front_id );
if ( $front && ! mp_has_rozcestnik( $front ) ) {
	array_splice( $front, 1, 0, array( mp_rozcestnik_section( 'Vyber si, co tě teď nejvíc trápí' ) ) );
	$fix_button = static function ( array $node ) use ( &$fix_button ) {
		if ( ( $node['widgetType'] ?? '' ) === 'button' && ( $node['settings']['text'] ?? '' ) === 'Nabídka služeb' ) {
			$node['settings']['text']        = 'Jak ti můžu pomoci';
			$node['settings']['link']['url'] = home_url( '/' . MP_ROZCESTNIK_PAGE . '/' );
		}
		$node['elements'] = array_map( $fix_button, $node['elements'] ?? array() );
		return $node;
	};
	mp_save_data( $front_id, array_map( $fix_button, $front ) );
	WP_CLI::log( 'Úvodní stránka: rozcestník pod úvodem, opraven odkaz tlačítka Nabídka služeb' );
}

// --- Teď jsem na řadě JÁ: no dates until the next run is announced ------------------

function mp_map_strings( $node, callable $fn ) {
	if ( is_array( $node ) ) {
		return array_map( static fn( $value ) => mp_map_strings( $value, $fn ), $node );
	}
	return is_string( $node ) ? $fn( $node ) : $node;
}

$program = get_page_by_path( 'ted-jsem-na-rade-ja' );
$data    = mp_get_data( $program->ID );
$updated = mp_map_strings(
	$data,
	static function ( string $value ): string {
		$value = str_replace( 'závěrečné vánoční setkání na Vysočině - Vision board pro rok 2027', 'závěrečné setkání na Vysočině - Vision board pro další rok', $value );
		$value = preg_replace( '/ZAČÍNÁME\s+19\.9\.\s*2026\s*/u', 'Termín dalšího běhu brzy zveřejním – ozvi se a dám ti vědět jako první.', $value );
		$value = str_replace( 'Chci si zarezervovat místo!', 'Chci vědět o dalším termínu!', $value );
		// The booking link points to the finished 2025 run.
		return preg_match( '#^https://moudrostpochopeni\.zarezervujse\.cz/#', $value ) ? home_url( MP_CONTACT_URL ) : $value;
	}
);
if ( $updated !== $data ) {
	mp_save_data( $program->ID, $updated );
	WP_CLI::log( 'Teď jsem na řadě JÁ: odstraněny termíny, rezervace vede na kontakt' );
}

// --- Retired pages -------------------------------------------------------------------

foreach ( array_keys( mp_retired_pages() ) as $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ) );
		WP_CLI::log( "/$slug skryta (koncept), přesměrována na Tělo a dotek" );
	}
}

// --- Main menu ----------------------------------------------------------------------

$menu_id = (int) ( get_nav_menu_locations()['main_menu'] ?? 0 );
if ( $menu_id && get_option( 'mp_menu_version' ) !== 'rozcestnik-1' ) {
	foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
		wp_delete_post( $item->ID, true );
	}

	$add = static function ( string $slug, string $title, int $parent = 0 ) use ( $menu_id ): int {
		$page = get_page_by_path( $slug );
		return wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $title,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
			)
		);
	};

	$help = $add( MP_ROZCESTNIK_PAGE, 'Jak ti můžu pomoci' );
	foreach ( mp_areas() as $area ) {
		$add( $area['page'], $area['title'], $help );
	}
	$about = $add( 'neco-malo-o-mne', 'O mně' );
	$add( 'pribehy-a-zkusenosti-klientu', 'Reference', $about );
	$add( 'fotogalerie', 'Fotogalerie', $about );
	$add( 'seminare-a-workshopy', 'Semináře' );
	$add( 'blog', 'Blog' );
	$add( 'cenik-a-darkove-poukazky', 'Ceník' );
	$add( 'kontakt', 'Kontakt' );

	update_option( 'mp_menu_version', 'rozcestnik-1' );
	WP_CLI::log( 'Hlavní menu přestavěno' );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
WP_CLI::success( 'Rozcestník hotový.' );
