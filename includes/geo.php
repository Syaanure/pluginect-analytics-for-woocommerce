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
		'FR' => [ 'France', 46.6, 2.4 ],
		'BE' => [ 'Belgique', 50.6, 4.6 ],
		'CH' => [ 'Suisse', 46.8, 8.2 ],
		'LU' => [ 'Luxembourg', 49.8, 6.1 ],
		'MC' => [ 'Monaco', 43.7, 7.4 ],
		'ES' => [ 'Espagne', 40.3, -3.7 ],
		'PT' => [ 'Portugal', 39.5, -8.0 ],
		'IT' => [ 'Italie', 42.8, 12.6 ],
		'DE' => [ 'Allemagne', 51.1, 10.4 ],
		'NL' => [ 'Pays-Bas', 52.2, 5.3 ],
		'GB' => [ 'Royaume-Uni', 54.0, -2.0 ],
		'IE' => [ 'Irlande', 53.2, -8.0 ],
		'AT' => [ 'Autriche', 47.6, 14.1 ],
		'DK' => [ 'Danemark', 56.1, 9.5 ],
		'SE' => [ 'Suède', 62.0, 15.0 ],
		'NO' => [ 'Norvège', 64.0, 12.0 ],
		'FI' => [ 'Finlande', 64.0, 26.0 ],
		'PL' => [ 'Pologne', 52.1, 19.4 ],
		'CZ' => [ 'Tchéquie', 49.8, 15.5 ],
		'GR' => [ 'Grèce', 39.1, 21.8 ],
		'RO' => [ 'Roumanie', 45.9, 25.0 ],
		'HU' => [ 'Hongrie', 47.2, 19.5 ],
		'BG' => [ 'Bulgarie', 42.7, 25.5 ],
		'HR' => [ 'Croatie', 45.1, 15.2 ],
		'SI' => [ 'Slovénie', 46.1, 14.8 ],
		'SK' => [ 'Slovaquie', 48.7, 19.7 ],
		'EE' => [ 'Estonie', 58.6, 25.0 ],
		'LV' => [ 'Lettonie', 56.9, 24.6 ],
		'LT' => [ 'Lituanie', 55.2, 23.9 ],
		'UA' => [ 'Ukraine', 48.4, 31.2 ],
		'RU' => [ 'Russie', 61.5, 100.0 ],
		'TR' => [ 'Turquie', 39.0, 35.2 ],
		'MA' => [ 'Maroc', 31.8, -7.1 ],
		'DZ' => [ 'Algérie', 28.0, 1.7 ],
		'TN' => [ 'Tunisie', 33.9, 9.6 ],
		'SN' => [ 'Sénégal', 14.5, -14.5 ],
		'CI' => [ "Côte d'Ivoire", 7.5, -5.5 ],
		'CM' => [ 'Cameroun', 5.7, 12.7 ],
		'ZA' => [ 'Afrique du Sud', -30.6, 22.9 ],
		'EG' => [ 'Égypte', 26.8, 30.8 ],
		'IL' => [ 'Israël', 31.5, 34.9 ],
		'AE' => [ 'Émirats arabes unis', 24.0, 54.0 ],
		'SA' => [ 'Arabie saoudite', 24.0, 45.0 ],
		'IN' => [ 'Inde', 21.0, 78.0 ],
		'CN' => [ 'Chine', 35.9, 104.2 ],
		'JP' => [ 'Japon', 36.2, 138.3 ],
		'KR' => [ 'Corée du Sud', 36.5, 127.9 ],
		'TH' => [ 'Thaïlande', 15.9, 101.0 ],
		'VN' => [ 'Viêt Nam', 14.1, 108.3 ],
		'SG' => [ 'Singapour', 1.35, 103.8 ],
		'ID' => [ 'Indonésie', -2.5, 118.0 ],
		'AU' => [ 'Australie', -25.3, 133.8 ],
		'NZ' => [ 'Nouvelle-Zélande', -41.0, 174.0 ],
		'US' => [ 'États-Unis', 39.8, -98.6 ],
		'CA' => [ 'Canada', 56.1, -106.3 ],
		'MX' => [ 'Mexique', 23.6, -102.6 ],
		'BR' => [ 'Brésil', -14.2, -51.9 ],
		'AR' => [ 'Argentine', -38.4, -63.6 ],
		'CL' => [ 'Chili', -35.7, -71.5 ],
		'CO' => [ 'Colombie', 4.6, -74.3 ],
		'PE' => [ 'Pérou', -9.2, -75.0 ],
		'RE' => [ 'La Réunion', -21.1, 55.5 ],
		'GP' => [ 'Guadeloupe', 16.2, -61.6 ],
		'MQ' => [ 'Martinique', 14.6, -61.0 ],
		'GF' => [ 'Guyane', 3.9, -53.1 ],
		'NC' => [ 'Nouvelle-Calédonie', -20.9, 165.6 ],
		'PF' => [ 'Polynésie française', -17.7, -149.4 ],
	];

	return $t;
}

function cbaz_country_name( $code ) {
	$code = strtoupper( (string) $code );
	$row  = cbaz_country_table()[ $code ] ?? null;

	return $row ? $row[0] : ( $code ? $code : 'Inconnu' );
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
