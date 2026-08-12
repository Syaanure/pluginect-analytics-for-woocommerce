# Séparation Free / Pro

## 1. Architecture

Deux extensions WordPress distinctes, sans duplication de code.

```
shop-analytics-for-woocommerce/          FREE — autonome, destiné à WordPress.org
├── includes/
│   ├── features.php           registre Free/Pro + limites (nouveau)
│   ├── db.php track.php       cœur partagé : suivi, base, WooCommerce
│   ├── query.php history.php  calculs et consolidation
│   ├── campaigns.php          création de campagnes, UTM, redirections
│   └── admin.php              menu, filtres, rendu des écrans
└── views/                     11 écrans gratuits

shop-analytics-for-woocommerce-pro/      PRO — add-on, nécessite FREE
├── includes/
│   ├── campaigns-stats.php    performances, attribution, ROI
│   ├── exports.php            exports CSV
│   └── screens.php            greffe les écrans premium
├── views/                     attribution, historique, rapports
└── distribution/              emplacement réservé au licensing
```

PRO n'embarque aucune copie du cœur. Il lit les mêmes tables, les mêmes
options, les mêmes fonctions.

### Points d'extension

Trois suffisent, et le cœur ignore tout du module premium.

| Hook | Rôle |
|---|---|
| `cbaz_ready` (action) | Le cœur est chargé ; PRO s'y branche. Reçoit `CBAZ_API`. |
| `cbaz_tabs` (filtre) | Ajouter des écrans au menu. |
| `cbaz_view` (filtre) | Servir un fichier de vue hors du dossier du cœur. |

Le registre `cbaz_pro_features()` liste ce qui est premium. `cbaz_can( 'clé' )`
répond partout. Ajouter une entrée au registre suffit à verrouiller une
fonctionnalité — aucune condition à disperser dans les vues.

## 2. Fonctions FREE

Vue d'ensemble, Temps réel, Acquisition, Comportement, E-commerce,
Produits, Campagnes, Géographie, Visiteurs, Parcours, Paramètres.

Y compris : visiteurs, visites, pages vues, CA, commandes, taux de
conversion, panier moyen, comparaison de période, sources, référents,
pages d'entrée et de sortie, recherches internes, produits consultés et
vendus, pays, **globe 3D**, appareils, navigateurs, langues, résolutions,
entonnoir de conversion de base.

**Campagnes** : création, nom, URL de destination, génération et
paramètres UTM, redirections, modification, suppression, copie du lien.

**Parcours** : les 10 visites les plus récentes, avec source, pays,
appareil, nouveau/ancien, page d'entrée, pages visitées, page de sortie.

## 3. Fonctions PRO

**Parcours** : historique complet, recherche, filtres, segmentation
(acheteurs, rebonds, longs), statistiques du lot, points d'arrêt.

**Campagnes** : visites, commandes, CA, conversion, ROI, séries
temporelles, commandes attribuées, comparaison.

**Attribution** : premier et dernier contact, attribution linéaire,
délai de conversion, revenus par source.

**Historique** : comparaison mois et années sur la profondeur conservée.

**Rapports** et **exports CSV** : campagnes, sources, produits, pays, pages.

## 4. Dépendance PRO → FREE

`cbaz_pro_blocker()` vérifie, dans cet ordre :

1. `CBAZ_VERSION` définie — FREE installé et actif ;
2. `CBAZ_VERSION >= CBAZ_PRO_NEEDS_FREE` (4.31.0) ;
3. `CBAZ_API` correspond à la version de contrat attendue.

En cas d'échec : **aucune erreur fatale**. Une notice d'administration
explique la situation, et FREE continue de fonctionner normalement.

PRO ne charge ses fichiers que depuis `cbaz_ready`, jamais avant.

## 5. Fichiers propres à PRO

- `shop-analytics-for-woocommerce-pro.php` — amorçage et vérification de dépendance
- `includes/campaigns-stats.php` — 11 fonctions extraites de `campaigns.php`
- `includes/exports.php` — 10 fonctions extraites d'`admin.php`
- `includes/screens.php` — greffe des écrans
- `views/attribution.php`, `views/historique.php`, `views/rapports.php`
- `distribution/` — vide, réservé au licensing

Aucun de ces fichiers n'existe dans le paquet FREE ; le script de
construction échoue si l'un d'eux s'y retrouve.

## 6. Ajouter une fonctionnalité Free

1. Créer `views/mon-ecran.php` ;
2. Ajouter la ligne dans `cbaz_tabs()` (`includes/admin.php`) ;
3. Placer les requêtes dans `includes/query.php`.

## 7. Ajouter une fonctionnalité Pro

1. Ajouter la clé dans `cbaz_pro_features()` (FREE) — elle devient
   indisponible sans PRO ;
2. Garder les appels côté FREE derrière `cbaz_can( 'ma-cle' )` ;
3. Écrire le code dans `shop-analytics-for-woocommerce-pro/includes/` ;
4. Pour un écran : l'ajouter à `cbaz_pro_screens()` et créer la vue.

Appliquer les limites **dans la requête**, jamais à l'affichage — voir
`cbaz_journeys_limit()` dans `includes/query.php`.

## 8. Construction

```bash
./build.sh
```

Produit `dist/shop-analytics-for-woocommerce/` et `dist/shop-analytics-for-woocommerce-pro/`,
plus les deux ZIP. Le script vérifie qu'aucun module premium ne s'est
glissé dans le paquet gratuit et s'interrompt si c'est le cas.
