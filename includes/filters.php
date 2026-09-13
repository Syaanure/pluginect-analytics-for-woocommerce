<?php
/**
 * Filtres transversaux.
 *
 * Six dimensions — pays, source, support, campagne, appareil, type de
 * client — applicables depuis n'importe quel écran et conservées d'un
 * onglet à l'autre. Un filtre qu'il faut reposer à chaque page ne sert
 * à rien : c'est justement en le promenant d'un rapport au suivant
 * qu'on comprend d'où vient un écart.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ce fichier interroge les tables propres au plugin ({prefix}cbaz_*), pour lesquelles WordPress n'offre aucune API : les noms de tables viennent de cbaz_table(), les valeurs passent par $wpdb->prepare(), et les lectures lourdes sont consolidées par jour (history.php) plutôt que mises en cache objet.

function cbaz_filter_dims() {
	return [
		'pays'     => [ 'label' => __( 'Country', 'pluginect-analytics-for-woocommerce' ), 'column' => 'country' ],
		'source'   => [ 'label' => __( 'Source', 'pluginect-analytics-for-woocommerce' ), 'column' => 'source' ],
		'medium'   => [ 'label' => __( 'Medium', 'pluginect-analytics-for-woocommerce' ), 'column' => 'medium' ],
		'campagne' => [ 'label' => __( 'Campaign', 'pluginect-analytics-for-woocommerce' ), 'column' => 'campaign' ],
		'appareil' => [ 'label' => __( 'Device', 'pluginect-analytics-for-woocommerce' ), 'column' => 'device' ],
		'client'   => [ 'label' => __( 'Customer', 'pluginect-analytics-for-woocommerce' ), 'column' => 'is_new' ],
	];
}

/** Filtres actifs, lus dans l'adresse. */
function cbaz_filters() {
	static $active = null;

	if ( null !== $active ) {
		return $active;
	}

	$active = [];

	foreach ( cbaz_filter_dims() as $key => $dim ) {
		$value = isset( $_GET[ 'f_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'f_' . $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, paramètres d'affichage

		if ( '' !== $value ) {
			$active[ $key ] = substr( $value, 0, 120 );
		}
	}

	return $active;
}

/**
 * Clause SQL sur la table des visites.
 *
 * Les valeurs passent par prepare() : elles viennent de l'adresse, donc
 * de l'extérieur, et une liste déroulante ne protège de rien.
 */
function cbaz_filter_where( $alias = '' ) {
	global $wpdb;

	$prefix = $alias ? $alias . '.' : '';
	$dims   = cbaz_filter_dims();
	$sql    = '';

	foreach ( cbaz_filters() as $key => $value ) {
		$column = $dims[ $key ]['column'];

		if ( 'is_new' === $column ) {
			$sql .= $wpdb->prepare( " AND {$prefix}is_new = %d", 'nouveau' === $value ? 1 : 0 );
			continue;
		}

		$sql .= $wpdb->prepare( " AND {$prefix}{$column} = %s", $value );
	}

	return $sql;
}

/**
 * Clause équivalente sur les commandes.
 *
 * L'attribution est recopiée en métadonnée de commande : on peut donc
 * filtrer les ventes sans passer par les visites, et sans dépendre de
 * leur purge. Le type de client n'y figure pas — il appartient à la
 * visite, pas à la commande — et est donc ignoré ici.
 */
function cbaz_filter_order_where( $alias = 'o' ) {
	global $wpdb;

	$schema = cbaz_order_schema();
	$map    = [
		'pays'     => '_cbaz_country',
		'source'   => '_cbaz_source',
		'medium'   => '_cbaz_medium',
		'campagne' => '_cbaz_campaign',
		'appareil' => '_cbaz_device',
	];

	$sql = '';

	foreach ( cbaz_filters() as $key => $value ) {
		if ( 'client' === $key ) {
			$sessions = cbaz_table( 'sessions' );
			$sql     .= $wpdb->prepare(
				" AND EXISTS (SELECT 1 FROM {$sessions} fs
					WHERE fs.order_id = {$alias}.{$schema['id']} AND fs.is_new = %d)",
				'nouveau' === $value ? 1 : 0
			);
			continue;
		}

		if ( ! isset( $map[ $key ] ) ) {
			continue;
		}

		$sql .= $wpdb->prepare(
			" AND EXISTS (SELECT 1 FROM {$schema['meta']} fm
				WHERE fm.{$schema['meta_fk']} = {$alias}.{$schema['id']}
				AND fm.meta_key = %s AND fm.meta_value = %s)",
			$map[ $key ],
			$value
		);
	}

	return $sql;
}

/** Valeurs proposées pour une dimension, les plus fréquentes d'abord. */
function cbaz_filter_options( $key, array $range ) {
	global $wpdb;

	if ( 'client' === $key ) {
		return [ 'nouveau' => __( 'New visitors', 'pluginect-analytics-for-woocommerce' ), 'recurrent' => __( 'Returning visitors', 'pluginect-analytics-for-woocommerce' ) ];
	}

	$dims   = cbaz_filter_dims();
	$column = $dims[ $key ]['column'] ?? '';

	if ( ! $column ) {
		return [];
	}

	$s    = cbaz_table( 'sessions' );
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- noms de tables issus de la fonction de préfixe, colonnes issues d'une liste fermée, fragments déjà passés par \$wpdb->prepare()
	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT {$column} FROM {$s}
		WHERE started_at BETWEEN %s AND %s AND {$column} <> ''
		GROUP BY {$column} ORDER BY COUNT(*) DESC LIMIT 40",
		$range['from'],
		$range['to']
	) );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$out = [];

	foreach ( $rows as $value ) {
		$out[ $value ] = 'pays' === $key
			? cbaz_country_flag( $value ) . ' ' . cbaz_country_name( $value )
			: ucfirst( $value );
	}

	return $out;
}

/** Adresse de la page en cours, filtres et période conservés. */
function cbaz_url( array $changes = [], $drop = [] ) {
	$range = cbaz_range();

	$args = array_merge(
		[
			'page'    => sanitize_key( $_GET['page'] ?? 'cbaz' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, paramètres d'affichage
			'periode' => $range['preset'],
		],
		[],
		$changes
	);

	// Une plage personnalisée ne tient pas dans le seul mot « perso » :
	// ses bornes voyagent avec elle, sauf si l'appelant change de
	// période — auquel cas elles n'ont plus lieu d'être.
	if ( 'perso' === $range['preset'] && ! isset( $changes['periode'] ) ) {
		$args['du'] = $range['du'];
		$args['au'] = $range['au'];
	}

	foreach ( cbaz_filters() as $key => $value ) {
		$args[ 'f_' . $key ] = $value;
	}

	foreach ( (array) $drop as $key ) {
		unset( $args[ $key ] );
	}

	if ( ! empty( $_GET['compare'] ) && ! isset( $changes['compare'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, paramètres d'affichage
		$args['compare'] = 1;
	}

	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

// ══════════════════════════════════════════════════════════════
//  BARRE DE FILTRES
// ══════════════════════════════════════════════════════════════

function cbaz_filter_bar( array $range ) {
	$dims   = cbaz_filter_dims();
	$active = cbaz_filters();
	?>
	<div class="cbaz-filters">
		<span class="cbaz-filters__label">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
			<?php echo esc_html__( 'Filters', 'pluginect-analytics-for-woocommerce' ); ?>
		</span>

		<?php foreach ( $dims as $key => $dim ) : ?>
			<?php $options = cbaz_filter_options( $key, $range ); ?>

			<div class="cbaz-drop<?php echo isset( $active[ $key ] ) ? ' is-set' : ''; ?>">
				<button type="button" class="cbaz-drop__btn" data-cbaz-drop><?php echo esc_html( $dim['label'] ); ?></button>

				<div class="cbaz-drop__menu" hidden>
					<?php if ( ! $options ) : ?>
						<p class="cbaz-drop__empty"><?php echo esc_html__( 'No value over the period.', 'pluginect-analytics-for-woocommerce' ); ?></p>
					<?php endif; ?>

					<?php foreach ( $options as $value => $label ) : ?>
						<a href="<?php echo esc_url( cbaz_url( [ 'f_' . $key => $value ] ) ); ?>"
						   class="<?php echo ( $active[ $key ] ?? '' ) === (string) $value ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php foreach ( $active as $key => $value ) : ?>
			<a class="cbaz-chip" href="<?php echo esc_url( cbaz_url( [], [ 'f_' . $key ] ) ); ?>">
				<?php echo esc_html( $dims[ $key ]['label'] ); ?> : <strong><?php echo esc_html( $value ); ?></strong>
				<span aria-hidden="true">×</span>
			</a>
		<?php endforeach; ?>

		<?php if ( $active ) : ?>
			<a class="cbaz-clear" href="<?php echo esc_url( cbaz_url( [], array_map( fn( $k ) => 'f_' . $k, array_keys( $active ) ) ) ); ?>"><?php echo esc_html__( 'Clear all', 'pluginect-analytics-for-woocommerce' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}
