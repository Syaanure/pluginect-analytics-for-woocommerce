=== Shop Analytics for WooCommerce ===
Contributors: syaanure
Tags: analytics, woocommerce, statistiques, utm, rgpd
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.31.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mesure d'audience et de ventes, hébergée sur votre serveur. Sans service tiers et sans cookie par défaut.

== Description ==

Une boutique en ligne pose trois questions, toujours les mêmes : d'où viennent les gens, ce qu'ils font une fois arrivés, et combien ça rapporte. Les outils habituels y répondent en envoyant vos données chez un tiers, en posant des cookies, et en imposant un bandeau de consentement qui fait fuir une partie des visiteurs — donc en mesurant moins bien ce qu'ils prétendent mesurer.

Cette extension répond à ces questions en gardant les données de mesure dans votre base WordPress. Aucune donnée de mesure n'est envoyée à un service tiers. Aucun cookie n'est déposé avec le réglage par défaut.

= Ce qu'elle mesure =

* **Audience** — visites, visiteurs, pages vues, durée, rebond, appareils, navigateurs, systèmes, langues, résolutions.
* **Acquisition** — sources, supports, sites référents, moteurs de recherche, avec le logo de chaque canal.
* **Comportement** — pages vues, pages d'entrée et de sortie, chemins types, recherches internes, points d'abandon.
* **E-commerce** — chiffre d'affaires, commandes, panier moyen, taux de conversion, entonnoir complet, lu directement dans WooCommerce.
* **Produits** — vues, ajouts au panier, taux d'ajout, ventes par référence.
* **Géographie** — pays, avec conversion et chiffre d'affaires par pays, sur un globe interactif.
* **Parcours** — les dix visites les plus récentes, déroulées page par page.
* **Temps réel** — fréquentation et pages actives au cours des trente dernières minutes.
* **Campagnes** — création de liens UTM et de liens courts.

= Extension Pro distincte =

Le module payant, distribué séparément et absent de ce paquet, ajoute l'historique complet et les filtres des parcours, les performances et l'attribution des campagnes, les détails du temps réel, les comparaisons historiques et les exports CSV. La collecte commune continue de fonctionner sans le module Pro.

= Ce qu'elle ne fait pas =

L'identifiant pseudonyme qui relie deux pages d'une même visite change chaque nuit : un visiteur qui revient le lendemain est quelqu'un d'autre pour le système. C'est une limite assumée.

== Confidentialité ==

= Aucun cookie =

Réglage par défaut : aucun cookie, aucun stockage local, aucun stockage de session. L'extension n'écrit rien dans le navigateur. La qualification juridique dépend néanmoins de votre configuration, des autres extensions et de votre juridiction ; documentez la mesure dans votre politique de confidentialité.

Un seul cookie facultatif existe : la mémoire d'attribution, désactivée par défaut, qui permet de rattacher un achat à un clic de campagne survenu plusieurs jours plus tôt. Elle ne contient que la provenance, jamais d'identifiant. L'écran Réglages indique en permanence, et sans détour, si un cookie est déposé ou non.

= L'adresse IP n'est jamais enregistrée =

Elle sert une fraction de seconde à calculer un identifiant, puis elle est oubliée. Elle est **tronquée avant même ce calcul** : seul le préfixe réseau est retenu — trois octets en IPv4, trois groupes en IPv6. Le résultat est un identifiant pseudonyme quotidien fondé sur ce préfixe et la signature du navigateur. Il réduit la précision par rapport à une adresse complète, mais ne doit pas être présenté comme une anonymisation garantie dans tous les contextes.

= Le point à connaître =

Une visite qui aboutit à une commande est rattachée à cette commande, faute de quoi il n'y aurait ni chiffre d'affaires par campagne, ni retour sur investissement. Pour ces visites-là — et seulement celles-là — un lien existe vers un client identifiable via la commande. C'est de la donnée personnelle au sens du RGPD, et c'est ce qui justifie la purge automatique du détail.

Nous préférons l'écrire noir sur blanc plutôt que d'afficher « aucune donnée personnelle » sans nuance.

= Deux durées de conservation =

* **Le détail** — visites, pages, évènements — est purgé automatiquement. La valeur par défaut est 24 mois et reste configurable de 1 à 120 mois.
* **L'historique consolidé** — un résumé quotidien sans visite individuelle — est conservé jusqu'à dix ans par défaut, avec une durée configurable.

La consolidation a toujours lieu **avant** la purge : un jour effacé sans avoir été résumé serait perdu deux fois.

= Ce qu'il vous reste à faire =

Décrire la mesure, les données, les finalités et les durées dans votre politique de confidentialité. Déterminez avec votre conseil la base légale et les obligations applicables à votre configuration et à votre juridiction.

== Sécurité ==

= Le point de collecte =

Il est public, et il doit l'être : c'est un navigateur anonyme qui l'appelle. Il est donc traité comme une surface d'attaque.

* Les robots sont écartés sur signature — une cinquantaine de familles, réglable.
* Une visite cesse d'être enregistrée au-delà de cinq cents pages : aucune visite réelle n'atteint ce chiffre.
* Le débit est plafonné par réseau et par heure, ce qui empêche de gonfler les tables en changeant de signature de navigateur à chaque appel.
* Les paramètres reçus sont bornés en nombre et en longueur avant tout traitement.

= L'administration =

* Chaque action d'écriture vérifie une capacité **et** un jeton anti-CSRF.
* Les tâches de maintenance ne se déclenchent que pour un compte habilité, jamais depuis un appel AJAX quelconque.
* Toutes les requêtes paramétrées passent par `$wpdb->prepare()`. Les seules valeurs interpolées sont des noms de tables construits par l'extension.
* Toutes les sorties sont échappées à l'affichage.

= Les exports CSV =

Un tableur exécute toute cellule commençant par `=`, `+`, `-` ou `@`. Or nos exports contiennent des valeurs venues de l'extérieur — un nom de campagne, un chemin de page, un terme de recherche. Un visiteur arrivant avec `?utm_campaign==1+1` aurait suffi à déclencher une formule sur le poste de qui ouvre l'export.

Ces cellules sont neutralisées par une apostrophe de tête. Les montants négatifs, eux, sont reconnus comme des nombres et laissés intacts : un export sûr mais incalculable n'aurait servi à rien.

= Désinstallation =

La désactivation ne touche à aucune donnée. La suppression les conserve également par défaut. Pour demander un effacement complet, activez d'abord l'option correspondante dans **Analytics → Paramètres → Données**, puis supprimez l'extension.

== Installation ==

1. Installer et activer WooCommerce.
2. Installer puis activer cette extension.
3. Vérifier **Analytics → Paramètres** : durées de conservation, rôles exclus, chemins exclus.

La mesure démarre immédiatement. L'historique consolidé, lui, se construit nuit après nuit.

== Frequently Asked Questions ==

= Faut-il un bandeau de consentement ? =

Le réglage par défaut ne dépose aucun cookie, mais cette seule caractéristique ne permet pas une conclusion juridique universelle. Vérifiez votre configuration complète et votre juridiction, et mentionnez la mesure dans votre politique de confidentialité.

= Pourquoi les chiffres diffèrent-ils de Google Analytics ? =

Les périmètres diffèrent : la balise est servie par votre domaine, les robots et rôles exclus sont configurables, l'identifiant change chaque jour et les définitions des sessions, rebonds et conversions ne sont pas nécessairement celles d'un autre outil.

= Un visiteur qui revient demain est-il reconnu ? =

Non, et c'est volontaire. L'empreinte change chaque nuit. « Récurrent » signifie ici : revenu au cours de la même journée.

= Les tâches planifiées de WordPress sont désactivées sur mon serveur =

La purge et la consolidation ne s'exécuteront pas d'elles-mêmes. La consolidation rattrape son retard à chaque ouverture de l'administration ; la purge, non. Programmez une tâche système sur `wp-cron.php`.

== Pour les développeurs ==

Filtres disponibles :

* `cbaz_hits_per_hour` — plafond d'appels au point de collecte, par réseau et par heure. Défaut : 600.
* `cbaz_hide_admin_notices` — renvoyer `true` pour masquer volontairement les bandeaux des autres extensions sur nos écrans (déconseillé).

Tables créées, toutes préfixées `{$wpdb->prefix}cbaz_` : `sessions`, `views`, `events`, `campaigns`, `daily`, `daily_dim`.

== Changelog ==

= 4.31.1 =
* Exactitude : une page vue n'est plus incrémentée par chacun de ses événements ; durée engagée réellement visible.
* WooCommerce : HPOS, fuseaux, remboursements, panier classique et blocs rapprochés des mêmes définitions.
* Sécurité : événements publics bornés et attribution persistante signée.
* Free/Pro : limite des dix parcours appliquée côté données et compatibilité vérifiée avant déverrouillage.
* Données : conservation par défaut lors de la désinstallation, effacement complet sur choix explicite.

= 4.31.0 =
* Correction : heures affichées avec deux heures d'avance en été. Nos tables retiennent l'heure du site, mais elle était relue comme de l'UTC avant d'être reconvertie — une visite de 17 h 47 s'affichait à 19 h 47. Les durées, elles, n'étaient pas touchées.
* Le nom du produit est retenu au moment de la consultation : il reste lisible dans le parcours même après un renommage ou une suppression.
* Parcours : le produit consulté ou ajouté au panier est nommé, affiche son prix, et mène à sa fiche.


= 4.14.0 =
* Sécurité : neutralisation de l'injection de formule dans les exports CSV.
* Sécurité : plafond de débit par réseau sur le point de collecte.
* Sécurité : tâches de maintenance réservées aux comptes habilités.
* Désinstallation complète (tables, réglages, transients, métadonnées de commande).
* Icônes sur les indicateurs et sur les en-têtes de bloc.
* Documentation complète.

= 4.13.0 =
* Adresse IP tronquée avant le calcul de l'empreinte : celle-ci n'est plus recalculable.
* Secret propre à l'installation dans le calcul de l'empreinte.
* Plafond de cinq cents pages par visite.
* Plage de dates personnalisée dans le sélecteur de période.

= 4.12.0 =
* Cartes disposées en colonnes : plus d'espaces vides entre les blocs.
* Variations affichées en permanence, flèche après le pourcentage.

= 4.11.0 =
* Historique consolidé sur dix ans, avec écran de comparaison mois par mois et année par année.

= 4.10.0 =
* Logos de marque embarqués, sans requête sortante.
* Réglage d'exclusion des robots.

= 4.9.0 =
* Répertoire des sources : « l.instagram.com », « instagram.com » et « ig » comptent enfin pour un seul canal.
* Support « direct » au lieu de « (none) ».

= 4.8.0 =
* Écran Parcours : chaque visite déroulée page par page.
* Aucun cookie par défaut.
