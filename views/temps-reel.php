<?php
/** Temps réel : les trente dernières minutes. */

defined( 'ABSPATH' ) || exit;

$live  = cbaz_realtime();
$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 30 * MINUTE_IN_SECONDS );

global $wpdb;
$s = cbaz_table( 'sessions' );

$now_countries = $wpdb->get_results( $wpdb->prepare( "SELECT country AS label, COUNT(*) AS sessions FROM {$s} WHERE last_seen >= %s AND country <> '' GROUP BY country ORDER BY sessions DESC LIMIT 6", $since ) );
$now_sources   = $wpdb->get_results( $wpdb->prepare( "SELECT source AS label, MAX(medium) AS medium, COUNT(*) AS sessions FROM {$s} WHERE last_seen >= %s GROUP BY source ORDER BY sessions DESC LIMIT 6", $since ) );
$now_devices   = $wpdb->get_results( $wpdb->prepare( "SELECT device AS label, COUNT(*) AS sessions FROM {$s} WHERE last_seen >= %s AND device <> '' GROUP BY device ORDER BY sessions DESC", $since ) );

// Courbe minute par minute, sans trou : une minute creuse vaut zéro.
$per_minute = [];

foreach ( (array) $live['minutes'] as $m ) {
	$per_minute[ $m->m ] = (int) $m->views;
}

$curve  = [];
$cursor = strtotime( $since );

for ( $i = 0; $i < 30; $i++ ) {
	$key     = gmdate( 'Y-m-d H:i', $cursor );
	$curve[] = [
		'label'     => '-' . ( 30 - $i ) . ' min',
		'sessions'  => $per_minute[ $key ] ?? 0,
		'pageviews' => $per_minute[ $key ] ?? 0,
		'orders'    => 0,
		'revenue'   => 0,
	];

	$cursor += MINUTE_IN_SECONDS;
}

$online = max( 1, (int) $live['online'] );
?>

<section class="cbaz-card">
	<header class="cbaz-card__head">
		<?php echo cbaz_icon( 'bolt', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Visiteurs des 30 dernières minutes', 'shop-analytics-for-woocommerce' ); ?></h2><p><?php echo esc_html__( 'Mise à jour automatique toutes les 20 secondes.', 'shop-analytics-for-woocommerce' ); ?></p></div>
		<span class="cbaz-pill cbaz-pill--live"><?php echo esc_html__( 'En direct', 'shop-analytics-for-woocommerce' ); ?></span>
	</header>

	<?php echo cbaz_chart( $curve, [ 'sessions' ] ); // phpcs:ignore ?>
</section>

<div class="cbaz-grid cbaz-grid--3" data-cbaz-live data-nonce="<?php echo esc_attr( wp_create_nonce( 'cbaz_realtime' ) ); ?>">
	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'page', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Pages consultées', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<?php $maxp = $live['pages'] ? max( array_map( fn( $p ) => (int) $p->views, $live['pages'] ) ) : 1; ?>
		<ul class="cbaz-list">
			<?php foreach ( $live['pages'] as $i => $p ) : ?>
				<li>
					<div class="cbaz-list__row"><span class="cbaz-path"><?php echo esc_html( $p->label ); ?></span><span class="cbaz-num"><?php echo esc_html( cbaz_int( $p->views ) ); ?></span></div>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $p->views, $maxp ) ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $live['pages'] ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucune page consultée récemment.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'flag', 5 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Pays', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<ul class="cbaz-list">
			<?php $maxc = $now_countries ? max( array_map( fn( $c ) => (int) $c->sessions, $now_countries ) ) : 1; ?>
			<?php foreach ( $now_countries as $i => $c ) : ?>
				<li>
					<div class="cbaz-list__row">
						<span><?php echo cbaz_country_flag( $c->label ); // phpcs:ignore ?> <?php echo esc_html( cbaz_country_name( $c->label ) ); ?></span>
						<span class="cbaz-num"><?php echo esc_html( cbaz_int( $c->sessions ) ); ?></span>
					</div>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $c->sessions, $maxc ) ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $now_countries ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Pays non renseignés.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>

	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head"><?php echo cbaz_icon( 'device', 1 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Appareils', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
		<ul class="cbaz-list">
			<?php $maxd = $now_devices ? max( array_map( fn( $d ) => (int) $d->sessions, $now_devices ) ) : 1; ?>
			<?php foreach ( $now_devices as $i => $d ) : ?>
				<li>
					<div class="cbaz-list__row"><span><?php echo esc_html( ucfirst( $d->label ) ); ?></span><span class="cbaz-num"><?php echo esc_html( cbaz_int( $d->sessions ) ); ?></span></div>
					<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $d->sessions, $maxd ) ); // phpcs:ignore ?>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $now_devices ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Rien à afficher.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>
	</section>

	<section class="cbaz-card">
		<header class="cbaz-card__head">
			<?php echo cbaz_icon( 'bolt', 3 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Activité en direct', 'shop-analytics-for-woocommerce' ); ?><?php echo cbaz_pro_badge(); // phpcs:ignore ?></h2><p><?php echo esc_html__( 'Évènements anonymisés, aucune donnée personnelle.', 'shop-analytics-for-woocommerce' ); ?></p></div>
		</header>

		<?php if ( ! cbaz_can( 'realtime_details' ) ) : ?>
			<p class="cbaz-note">
				<?php echo esc_html__( 'Le compteur de visiteurs et les répartitions ci-dessus sont gratuits.
				Le détail évènement par évènement est disponible avec Pro.', 'shop-analytics-for-woocommerce' ); ?>
			</p>
		<?php else : ?>

		<ul class="cbaz-timeline" data-cbaz-feed>
			<?php foreach ( $live['feed'] as $f ) : ?>
				<?php $ev = cbaz_event_label( $f->kind, $f->object_id, $f->value, $f->path ); ?>
				<li class="cbaz-timeline--<?php echo esc_attr( $ev['tone'] ); ?>">
					<div class="cbaz-timeline__top">
						<span class="cbaz-timeline__time"><?php echo esc_html( wp_date( 'H:i', cbaz_ts( $f->at ) ) ); ?></span>
						<span class="cbaz-badge cbaz-badge--<?php echo esc_attr( $ev['tone'] ); ?>"><?php echo esc_html( $ev['label'] ); ?></span>
					</div>
					<p class="cbaz-timeline__what">
						<?php echo esc_html( $ev['detail'] ? $ev['detail'] : ( $f->title ? $f->title : $f->path ) ); ?>
					</p>
					<p class="cbaz-timeline__who">
						<?php echo cbaz_country_flag( $f->country ); // phpcs:ignore ?>
						<?php echo esc_html( cbaz_country_name( $f->country ) ); ?> · <?php echo esc_html( $f->device ); ?>
					</p>
				</li>
			<?php endforeach; ?>
			<?php if ( ! $live['feed'] ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Personne sur le site en ce moment.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
		</ul>

		<?php endif; ?>
	</section>
</div>

<section class="cbaz-card">
	<header class="cbaz-card__head"><?php echo cbaz_icon( 'globe', 6 ); // phpcs:ignore ?>
			<div><h2><?php echo esc_html__( 'Sources actuelles', 'shop-analytics-for-woocommerce' ); ?></h2></div></header>
	<?php $maxs = $now_sources ? max( array_map( fn( $x ) => (int) $x->sessions, $now_sources ) ) : 1; ?>
	<ul class="cbaz-list">
		<?php foreach ( $now_sources as $i => $x ) : ?>
			<li>
				<div class="cbaz-list__row">
					<span><?php echo cbaz_source_brand( $x->label ); // phpcs:ignore ?><?php echo esc_html( $x->label ); ?> <em>/ <?php echo esc_html( $x->medium ); ?></em></span>
					<span class="cbaz-num"><?php echo esc_html( cbaz_int( $x->sessions ) ); ?></span>
				</div>
				<?php echo str_replace( 'cbaz-bar"', 'cbaz-bar cbaz-bar--' . ( $i % 6 ) . '"', cbaz_bar( $x->sessions, $maxs ) ); // phpcs:ignore ?>
			</li>
		<?php endforeach; ?>
		<?php if ( ! $now_sources ) : ?><li class="cbaz-empty"><?php echo esc_html__( 'Aucune source active.', 'shop-analytics-for-woocommerce' ); ?></li><?php endif; ?>
	</ul>
</section>
