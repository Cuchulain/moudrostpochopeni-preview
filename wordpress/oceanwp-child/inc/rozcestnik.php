<?php
/**
 * Rozcestník: five areas of help, the services in them and the links between them.
 *
 * Shortcodes (usable in Elementor's Shortcode widget):
 *   [mp_rozcestnik title="…" eyebrow="…"]  cards of all five areas
 *   [mp_metody oblast="emoce"]             cards of the services in one area
 * Breadcrumbs are added automatically to every page that belongs to an area.
 */

defined( 'ABSPATH' ) || exit;

const MP_ROZCESTNIK_PAGE = 'nabidka-sluzeb';

/**
 * Areas in the order they appear on the site. `page` is the slug the card links to.
 */
function mp_areas(): array {
	return array(
		'emoce'    => array(
			'title'   => 'Emoce a psychika',
			'page'    => 'emoce-a-psychika',
			'text'    => 'Když se v tobě hromadí stres, únava a nevyslovené pocity a tělo se začíná ozývat.',
			'methods' => array( 'etikoterapie-a-emoce', 'psychosomatika' ),
		),
		'vztahy'   => array(
			'title'   => 'Vztahy, změny a výzvy',
			'page'    => 'vztahy-zmeny-a-vyzvy',
			'text'    => 'Pracovní, osobní i toxické vztahy, rozchody, partnerské krize a životní křižovatky.',
			'methods' => array( 'koucink', 'koucink-pro-muze' ),
		),
		'bachovky' => array(
			'title'   => 'Bachovy květové esence',
			'page'    => 'bachovy-kvetove-esence',
			'text'    => 'Jemná přírodní podpora při emočním napětí, stresu a v náročných obdobích.',
			'methods' => array(),
		),
		'telo'     => array(
			'title'   => 'Tělo a dotek',
			'page'    => 'kraniosakralni-terapie',
			'text'    => 'Kraniosakrální terapie pro hluboké uvolnění, zklidnění a odpočinek.',
			'methods' => array( 'kraniosakralni-terapie' ),
		),
		'projekt'  => array(
			'title'   => 'Teď jsem na řadě JÁ',
			'label'   => 'Projekt',
			'page'    => 'ted-jsem-na-rade-ja',
			'text'    => 'Program pro ženy, které se dlouho staraly o všechny kolem a chtějí se vrátit samy k sobě.',
			'methods' => array( 'seberespekt' ),
		),
	);
}

/**
 * Services shown inside the areas, keyed by page slug.
 */
function mp_methods(): array {
	return array(
		'etikoterapie-a-emoce'   => array(
			'title' => 'Etikoterapie',
			'text'  => 'Pojmenuješ a zpracuješ nahromaděné pocity a emoce a pochopíš, proč se ti určité situace v životě opakují.',
		),
		'psychosomatika'         => array(
			'title' => 'Psychosomatika',
			'text'  => 'Propojení psychologie, etikoterapie a uvolňujících technik. Porozumíš tomu, jak se emoce a naučené vzorce projevují na tvém těle.',
		),
		'koucink'                => array(
			'title' => 'Koučink',
			'text'  => 'Ujasníš si, co chceš, najdeš vlastní řešení a uděláš kroky, které můžeš ovlivnit – v práci i v osobním životě.',
		),
		'koucink-pro-muze'       => array(
			'title' => 'Párová konzultace',
			'text'  => 'Prostor pro oba partnery: pochopit, co mezi vámi drhne, a najít cestu, jak spolu znovu mluvit.',
		),
		'kraniosakralni-terapie' => array(
			'title' => 'Kraniosakrální terapie',
			'text'  => 'Jemný dotek, který pomáhá tělu i mysli zpomalit a hluboce si odpočinout.',
		),
		'seberespekt'            => array(
			'title' => 'E-book SEBErespekt zdarma',
			'text'  => 'Pracovní listy, které ti ukážou, kde a proč ztrácíš energii.',
		),
	);
}

/**
 * Pages that were removed from the offer, redirected to their closest replacement.
 */
function mp_retired_pages(): array {
	return array(
		'lecive-masaze'    => 'kraniosakralni-terapie',
		'tehotenska-masaz' => 'kraniosakralni-terapie',
	);
}

function mp_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( "/$slug" );
}

/**
 * Area of the current page: either the area's own page or one of its services.
 */
function mp_current_area(): ?string {
	if ( ! is_page() ) {
		return null;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	foreach ( mp_areas() as $key => $area ) {
		if ( $area['page'] === $slug || in_array( $slug, $area['methods'], true ) ) {
			return $key;
		}
	}
	return null;
}

add_shortcode(
	'mp_rozcestnik',
	static function ( $atts ) {
		$atts    = shortcode_atts(
			array(
				'eyebrow' => 'Jak ti můžu pomoci',
				'title'   => 'Vyber si, co tě teď nejvíc trápí',
			),
			$atts
		);
		$current = mp_current_area();
		$methods = mp_methods();
		$number  = 0;

		ob_start();
		?>
		<section class="mp-rozcestnik" aria-labelledby="mp-rozcestnik-title">
			<div class="mp-rozcestnik__intro">
				<?php if ( $atts['eyebrow'] ) : ?>
					<p class="mp-eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 id="mp-rozcestnik-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			</div>
			<ul class="mp-rozcestnik__grid">
				<?php foreach ( mp_areas() as $key => $area ) : ?>
					<?php ++$number; ?>
					<li class="mp-card<?php echo $key === $current ? ' is-current' : ''; ?>">
						<span class="mp-card__number"><?php echo esc_html( $area['label'] ?? sprintf( '%02d', $number ) ); ?></span>
						<h3 class="mp-card__title">
							<a class="mp-card__link" href="<?php echo esc_url( mp_page_url( $area['page'] ) ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>>
								<?php echo esc_html( $area['title'] ); ?>
							</a>
						</h3>
						<p class="mp-card__text"><?php echo esc_html( $area['text'] ); ?></p>
						<?php if ( $area['methods'] ) : ?>
							<ul class="mp-card__methods">
								<?php foreach ( $area['methods'] as $slug ) : ?>
									<li><a href="<?php echo esc_url( mp_page_url( $slug ) ); ?>"><?php echo esc_html( $methods[ $slug ]['title'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<span class="mp-card__more" aria-hidden="true"><?php echo $key === $current ? 'Právě jsi tady' : 'Zjistit víc'; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}
);

add_shortcode(
	'mp_metody',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'oblast' => '' ), $atts );
		$area = mp_areas()[ $atts['oblast'] ] ?? null;
		if ( ! $area ) {
			return '';
		}
		$methods = mp_methods();

		ob_start();
		?>
		<ul class="mp-metody">
			<?php foreach ( $area['methods'] as $slug ) : ?>
				<li class="mp-card">
					<h3 class="mp-card__title">
						<a class="mp-card__link" href="<?php echo esc_url( mp_page_url( $slug ) ); ?>"><?php echo esc_html( $methods[ $slug ]['title'] ); ?></a>
					</h3>
					<p class="mp-card__text"><?php echo esc_html( $methods[ $slug ]['text'] ); ?></p>
					<span class="mp-card__more" aria-hidden="true">Zjistit víc</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}
);

/**
 * Breadcrumbs under the page title: Jak ti můžu pomoci › Area › Service.
 */
add_action(
	'ocean_after_page_header',
	static function () {
		$key = mp_current_area();
		if ( ! $key ) {
			return;
		}
		$area  = mp_areas()[ $key ];
		$slug  = get_post_field( 'post_name', get_queried_object_id() );
		$items = array( array( 'Jak ti můžu pomoci', mp_page_url( MP_ROZCESTNIK_PAGE ) ) );

		if ( $area['page'] === $slug ) {
			$items[] = array( $area['title'], null );
		} else {
			$items[] = array( $area['title'], mp_page_url( $area['page'] ) );
			$items[] = array( mp_methods()[ $slug ]['title'] ?? get_the_title(), null );
		}
		?>
		<nav class="mp-drobecky" aria-label="Drobečková navigace">
			<ol>
				<?php foreach ( $items as [ $label, $url ] ) : ?>
					<li>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $label ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}
);

add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() ) {
			return;
		}
		$path   = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$target = mp_retired_pages()[ $path ] ?? null;
		if ( $target ) {
			wp_safe_redirect( mp_page_url( $target ), 301 );
			exit;
		}
	}
);
