<?php
/**
 * Pays : nom, drapeau et position.
 *
 * Les coordonnées sont des centres approximatifs, suffisants pour
 * poser un point sur un globe. On ne charge aucune base GeoIP : elle
 * pèserait plusieurs mégaoctets pour une précision dont un tableau de
 * bord de boutique n'a pas l'usage.
 *
 * Le drapeau est composé à partir du code pays, en emoji : deux
 * caractères au lieu d'une image par pays.
 */

defined( 'ABSPATH' ) || exit;

function cbaz_country_table() {
	static $t = null;

	if ( null !== $t ) {
		return $t;
	}

	// code => [ nom, latitude, longitude ]
	$t = [
		'FR' => [ __( 'France', 'pluginect-analytics-for-woocommerce' ), 46.6, 2.4 ],
		'BE' => [ __( 'Belgium', 'pluginect-analytics-for-woocommerce' ), 50.6, 4.6 ],
		'CH' => [ __( 'Switzerland', 'pluginect-analytics-for-woocommerce' ), 46.8, 8.2 ],
		'LU' => [ __( 'Luxembourg', 'pluginect-analytics-for-woocommerce' ), 49.8, 6.1 ],
		'MC' => [ __( 'Monaco', 'pluginect-analytics-for-woocommerce' ), 43.7, 7.4 ],
		'ES' => [ __( 'Spain', 'pluginect-analytics-for-woocommerce' ), 40.3, -3.7 ],
		'PT' => [ __( 'Portugal', 'pluginect-analytics-for-woocommerce' ), 39.5, -8.0 ],
		'IT' => [ __( 'Italy', 'pluginect-analytics-for-woocommerce' ), 42.8, 12.6 ],
		'DE' => [ __( 'Germany', 'pluginect-analytics-for-woocommerce' ), 51.1, 10.4 ],
		'NL' => [ __( 'Netherlands', 'pluginect-analytics-for-woocommerce' ), 52.2, 5.3 ],
		'GB' => [ __( 'United Kingdom', 'pluginect-analytics-for-woocommerce' ), 54.0, -2.0 ],
		'IE' => [ __( 'Ireland', 'pluginect-analytics-for-woocommerce' ), 53.2, -8.0 ],
		'AT' => [ __( 'Austria', 'pluginect-analytics-for-woocommerce' ), 47.6, 14.1 ],
		'DK' => [ __( 'Denmark', 'pluginect-analytics-for-woocommerce' ), 56.1, 9.5 ],
		'SE' => [ __( 'Sweden', 'pluginect-analytics-for-woocommerce' ), 62.0, 15.0 ],
		'NO' => [ __( 'Norway', 'pluginect-analytics-for-woocommerce' ), 64.0, 12.0 ],
		'FI' => [ __( 'Finland', 'pluginect-analytics-for-woocommerce' ), 64.0, 26.0 ],
		'PL' => [ __( 'Poland', 'pluginect-analytics-for-woocommerce' ), 52.1, 19.4 ],
		'CZ' => [ __( 'Czechia', 'pluginect-analytics-for-woocommerce' ), 49.8, 15.5 ],
		'GR' => [ __( 'Greece', 'pluginect-analytics-for-woocommerce' ), 39.1, 21.8 ],
		'RO' => [ __( 'Romania', 'pluginect-analytics-for-woocommerce' ), 45.9, 25.0 ],
		'HU' => [ __( 'Hungary', 'pluginect-analytics-for-woocommerce' ), 47.2, 19.5 ],
		'BG' => [ __( 'Bulgaria', 'pluginect-analytics-for-woocommerce' ), 42.7, 25.5 ],
		'HR' => [ __( 'Croatia', 'pluginect-analytics-for-woocommerce' ), 45.1, 15.2 ],
		'SI' => [ __( 'Slovenia', 'pluginect-analytics-for-woocommerce' ), 46.1, 14.8 ],
		'SK' => [ __( 'Slovakia', 'pluginect-analytics-for-woocommerce' ), 48.7, 19.7 ],
		'EE' => [ __( 'Estonia', 'pluginect-analytics-for-woocommerce' ), 58.6, 25.0 ],
		'LV' => [ __( 'Latvia', 'pluginect-analytics-for-woocommerce' ), 56.9, 24.6 ],
		'LT' => [ __( 'Lithuania', 'pluginect-analytics-for-woocommerce' ), 55.2, 23.9 ],
		'UA' => [ __( 'Ukraine', 'pluginect-analytics-for-woocommerce' ), 48.4, 31.2 ],
		'RU' => [ __( 'Russia', 'pluginect-analytics-for-woocommerce' ), 61.5, 100.0 ],
		'TR' => [ __( 'Türkiye', 'pluginect-analytics-for-woocommerce' ), 39.0, 35.2 ],
		'MA' => [ __( 'Morocco', 'pluginect-analytics-for-woocommerce' ), 31.8, -7.1 ],
		'DZ' => [ __( 'Algeria', 'pluginect-analytics-for-woocommerce' ), 28.0, 1.7 ],
		'TN' => [ __( 'Tunisia', 'pluginect-analytics-for-woocommerce' ), 33.9, 9.6 ],
		'SN' => [ __( 'Senegal', 'pluginect-analytics-for-woocommerce' ), 14.5, -14.5 ],
		'CI' => [ __( 'Ivory Coast', 'pluginect-analytics-for-woocommerce' ), 7.5, -5.5 ],
		'CM' => [ __( 'Cameroon', 'pluginect-analytics-for-woocommerce' ), 5.7, 12.7 ],
		'ZA' => [ __( 'South Africa', 'pluginect-analytics-for-woocommerce' ), -30.6, 22.9 ],
		'EG' => [ __( 'Egypt', 'pluginect-analytics-for-woocommerce' ), 26.8, 30.8 ],
		'IL' => [ __( 'Israel', 'pluginect-analytics-for-woocommerce' ), 31.5, 34.9 ],
		'AE' => [ __( 'United Arab Emirates', 'pluginect-analytics-for-woocommerce' ), 24.0, 54.0 ],
		'SA' => [ __( 'Saudi Arabia', 'pluginect-analytics-for-woocommerce' ), 24.0, 45.0 ],
		'IN' => [ __( 'India', 'pluginect-analytics-for-woocommerce' ), 21.0, 78.0 ],
		'CN' => [ __( 'China', 'pluginect-analytics-for-woocommerce' ), 35.9, 104.2 ],
		'JP' => [ __( 'Japan', 'pluginect-analytics-for-woocommerce' ), 36.2, 138.3 ],
		'KR' => [ __( 'South Korea', 'pluginect-analytics-for-woocommerce' ), 36.5, 127.9 ],
		'TH' => [ __( 'Thailand', 'pluginect-analytics-for-woocommerce' ), 15.9, 101.0 ],
		'VN' => [ __( 'Vietnam', 'pluginect-analytics-for-woocommerce' ), 14.1, 108.3 ],
		'SG' => [ __( 'Singapore', 'pluginect-analytics-for-woocommerce' ), 1.35, 103.8 ],
		'ID' => [ __( 'Indonesia', 'pluginect-analytics-for-woocommerce' ), -2.5, 118.0 ],
		'AU' => [ __( 'Australia', 'pluginect-analytics-for-woocommerce' ), -25.3, 133.8 ],
		'NZ' => [ __( 'New Zealand', 'pluginect-analytics-for-woocommerce' ), -41.0, 174.0 ],
		'US' => [ __( 'United States', 'pluginect-analytics-for-woocommerce' ), 39.8, -98.6 ],
		'CA' => [ __( 'Canada', 'pluginect-analytics-for-woocommerce' ), 56.1, -106.3 ],
		'MX' => [ __( 'Mexico', 'pluginect-analytics-for-woocommerce' ), 23.6, -102.6 ],
		'BR' => [ __( 'Brazil', 'pluginect-analytics-for-woocommerce' ), -14.2, -51.9 ],
		'AR' => [ __( 'Argentina', 'pluginect-analytics-for-woocommerce' ), -38.4, -63.6 ],
		'CL' => [ __( 'Chile', 'pluginect-analytics-for-woocommerce' ), -35.7, -71.5 ],
		'CO' => [ __( 'Colombia', 'pluginect-analytics-for-woocommerce' ), 4.6, -74.3 ],
		'PE' => [ __( 'Peru', 'pluginect-analytics-for-woocommerce' ), -9.2, -75.0 ],
		'RE' => [ __( 'Reunion', 'pluginect-analytics-for-woocommerce' ), -21.1, 55.5 ],
		'GP' => [ __( 'Guadeloupe', 'pluginect-analytics-for-woocommerce' ), 16.2, -61.6 ],
		'MQ' => [ __( 'Martinique', 'pluginect-analytics-for-woocommerce' ), 14.6, -61.0 ],
		'GF' => [ __( 'Guyana', 'pluginect-analytics-for-woocommerce' ), 3.9, -53.1 ],
		'NC' => [ __( 'New Caledonia', 'pluginect-analytics-for-woocommerce' ), -20.9, 165.6 ],
		'PF' => [ __( 'French Polynesia', 'pluginect-analytics-for-woocommerce' ), -17.7, -149.4 ],
	];

	return $t;
}

function cbaz_country_name( $code ) {
	$code = strtoupper( (string) $code );
	$row  = cbaz_country_table()[ $code ] ?? null;

	return $row ? $row[0] : ( $code ? $code : __( 'Unknown', 'pluginect-analytics-for-woocommerce' ) );
}

/** Drapeau en emoji, calculé depuis le code ISO. */
function cbaz_country_flag( $code ) {
	$code = strtoupper( (string) $code );

	if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
		return '🌍';
	}

	$flag = '';

	// Les indicatifs régionaux occupent le bloc U+1F1E6 à U+1F1FF, dans
	// le même ordre que l'alphabet. On les compose sans mbstring, qui
	// n'est pas garanti sur tous les hébergements.
	foreach ( str_split( $code ) as $letter ) {
		$flag .= html_entity_decode(
			'&#' . ( 0x1F1E6 + ( ord( $letter ) - 65 ) ) . ';',
			ENT_QUOTES,
			'UTF-8'
		);
	}

	return $flag;
}

/** Points à poser sur le globe, prêts pour le JavaScript. */
function cbaz_map_points( array $rows ) {
	$table  = cbaz_country_table();
	$points = [];

	foreach ( $rows as $row ) {
		$code = strtoupper( (string) $row->label );

		if ( ! isset( $table[ $code ] ) ) {
			continue;
		}

		$points[] = [
			'code'     => $code,
			'name'     => $table[ $code ][0],
			'flag'     => cbaz_country_flag( $code ),
			'lat'      => $table[ $code ][1],
			'lon'      => $table[ $code ][2],
			'sessions' => (int) $row->sessions,
			'orders'   => (int) $row->orders,
			'revenue'  => (float) $row->revenue,
			'money'    => cbaz_money( $row->revenue ),
		];
	}

	return $points;
}

/**
 * Le point d'ancrage de la boutique, pour le globe.
 *
 * Renvoie null si le pays est inconnu de la table : mieux vaut un
 * globe sans faisceau qu'un faisceau partant d'un point inventé au
 * milieu de l'Atlantique.
 */
function cbaz_home_point( $code ) {
	$table = cbaz_country_table();
	$code  = strtoupper( (string) $code );

	if ( ! isset( $table[ $code ] ) ) {
		return null;
	}

	return [
		'code' => $code,
		'name' => $table[ $code ][0],
		'lat'  => $table[ $code ][1],
		'lon'  => $table[ $code ][2],
	];
}
