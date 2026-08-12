=== CamiBijoux Analytics ===
Contributors: camibijoux
Tags: analytics, woocommerce, statistiques, utm, rgpd
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.31.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mesure d'audience, de ventes et de campagnes, hébergée sur votre serveur. Sans service tiers, sans cookie, sans donnée personnelle conservée.

== Description ==

Une boutique en ligne pose trois questions, toujours les mêmes : d'où viennent les gens, ce qu'ils font une fois arrivés, et combien ça rapporte. Les outils habituels y répondent en envoyant vos données chez un tiers, en posant des cookies, et en imposant un bandeau de consentement qui fait fuir une partie des visiteurs — donc en mesurant moins bien ce qu'ils prétendent mesurer.

Cette extension répond aux mêmes questions sans rien de tout cela. Les données restent dans votre base WordPress. Aucune requête ne sort vers l'extérieur. Aucun cookie n'est déposé.

= Ce qu'elle mesure =

* **Audience** — visites, visiteurs, pages vues, durée, rebond, appareils, navigateurs, systèmes, langues, résolutions.
* **Acquisition** — sources, supports, sites référents, moteurs de recherche, avec le logo de chaque canal.
* **Comportement** — pages vues, pages d'entrée et de sortie, chemins types, recherches internes, points d'abandon.
* **E-commerce** — chiffre d'affaires, commandes, panier moyen, taux de conversion, entonnoir complet, lu directement dans WooCommerce.
* **Produits** — vues, ajouts au panier, taux d'ajout, ventes par référence.
* **Campagnes UTM** — création de campagnes avec lien court, budget, retour sur investissement, coût par commande.
* **Attribution** — premier contact contre dernier contact, délai de conversion, nombre de visites avant achat.
* **Géographie** — pays, avec conversion et chiffre d'affaires par pays, sur un globe interactif.
* **Parcours** — chaque visite déroulée page par page, avec l'étape précise où elle s'est arrêtée.
* **Historique** — comparaison mois par mois et année par année, sur dix ans de profondeur.
* **Temps réel** — qui est sur le site en ce moment, et ce qu'il y fait.

= Ce qu'elle ne fait pas =

Elle ne suit personne. L'empreinte qui relie deux pages d'une même visite change chaque nuit : un visiteur qui revient le lendemain est quelqu'un d'autre pour le système. C'est une limite assumée, et c'est le prix de l'absence d'identifiant.

== Confidentialité ==

= Aucun cookie =

Réglage par défaut : aucun cookie, aucun stockage local, aucun stockage de session. L'extension n'écrit rien dans le navigateur. L'article 82 de la loi Informatique et Libertés — celui qui impose le bandeau de consentement — ne s'applique donc pas.

Un seul cookie facultatif existe : la mémoire d'attribution, désactivée par défaut, qui permet de rattacher un achat à un clic de campagne survenu plusieurs jours plus tôt. Elle ne contient que la provenance, jamais d'identifiant. L'écran Réglages indique en permanence, et sans détour, si un cookie est déposé ou non.

= L'adresse IP n'est jamais enregistrée =

Elle sert une fraction de seconde à calculer une empreinte, puis elle est oubliée. Et elle est **tronquée avant même ce calcul** : seuls le réseau est retenu — trois octets en IPv4, trois groupes en IPv6. Une empreinte ne peut donc pas être recalculée pour retrouver les visites de quelqu'un dont on connaîtrait l'adresse : elle désigne un réseau entier, jamais une personne.

= Le point à connaître =

Une visite qui aboutit à une commande est rattachée à cette commande, faute de quoi il n'y aurait ni chiffre d'affaires par campagne, ni retour sur investissement. Pour ces visites-là — et seulement celles-là — un lien existe vers un client identifiable via la commande. C'est de la donnée personnelle au sens du RGPD, et c'est ce qui justifie la purge automatique du détail.

Nous préférons l'écrire noir sur blanc plutôt que d'afficher « aucune donnée personnelle » sans nuance.

= Deux durées de conservation =

* **Le détail** — visites, pages, évènements — est purgé automatiquement. Treize mois est la durée retenue par la CNIL pour une mesure d'audience dispensée de consentement.
* **L'historique consolidé** — un résumé quotidien sans aucune visite individuelle — est conservé jusqu'à dix ans. N'étant pas une donnée personnelle, il échappe à cette limite, et permet de comparer une rentrée à celle d'il y a trois ans.

La consolidation a toujours lieu **avant** la purge : un jour effacé sans avoir été résumé serait perdu deux fois.

= Ce qu'il vous reste à faire =

Mentionner la mesure dans votre politique de confidentialité. L'obligation d'information ne dépend pas des cookies : elle s'applique dès qu'il y a traitement. Base légale : intérêt légitime.

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

Supprimer l'extension efface tout : les six tables, les réglages, les secrets, la tâche planifiée, les transients et les métadonnées d'attribution posées sur les commandes. Une désactivation, elle, ne touche à rien — on désactive souvent pour diagnostiquer.

== Installation ==

1. Déposer le dossier dans `/wp-content/plugins/` puis activer l'extension.
2. Aller dans **Réglages → Permaliens** et enregistrer, une seule fois : les liens courts de campagne en dépendent.
3. Vérifier **Analytics → Paramètres** : durées de conservation, rôles exclus, chemins exclus.

La mesure démarre immédiatement. L'historique consolidé, lui, se construit nuit après nuit.

== Frequently Asked Questions ==

= Faut-il un bandeau de consentement ? =

Non, dans le réglage par défaut : aucun cookie n'est déposé et l'adresse IP est tronquée avant tout calcul. Cela ne dispense ni d'un bandeau pour vos autres traceurs, ni de mentionner la mesure dans votre politique de confidentialité.

= Pourquoi les chiffres diffèrent-ils de Google Analytics ? =

Les nôtres sont généralement plus élevés, pour deux raisons. Les bloqueurs de publicité arrêtent les scripts tiers mais pas un script servi par votre propre domaine. Et sans bandeau à refuser, aucune visite n'est perdue faute de consentement.

= Un visiteur qui revient demain est-il reconnu ? =

Non, et c'est volontaire. L'empreinte change chaque nuit. « Récurrent » signifie ici : revenu au cours de la même journée.

= Que se passe-t-il si WooCommerce est désactivé ? =

La mesure d'audience continue. Les écrans de vente restent accessibles mais vides.

= Les tâches planifiées de WordPress sont désactivées sur mon serveur =

La purge et la consolidation ne s'exécuteront pas d'elles-mêmes. La consolidation rattrape son retard à chaque ouverture de l'administration ; la purge, non. Programmez une tâche système sur `wp-cron.php`.

== Pour les développeurs ==

Filtres disponibles :

* `cbaz_hits_per_hour` — plafond d'appels au point de collecte, par réseau et par heure. Défaut : 600.
* `cbaz_hide_admin_notices` — renvoyer `false` pour réafficher les bandeaux des autres extensions sur nos écrans.

Tables créées, toutes préfixées `{$wpdb->prefix}cbaz_` : `sessions`, `views`, `events`, `campaigns`, `daily`, `daily_dim`.

== Changelog ==

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
