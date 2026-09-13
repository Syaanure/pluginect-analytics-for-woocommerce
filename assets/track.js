/**
 * Pluginect Analytics for WooCommerce : balise de mesure.
 *
 * Un peu moins de deux kilo-octets, sans dépendance, sans cookie et
 * sans rien écrire dans le navigateur. On envoie la page vue, puis les
 * quelques évènements marchands qui font l'entonnoir de conversion.
 */
(function () {
    'use strict';

    var cfg = window.cbazTrack;
    if (!cfg || !cfg.url) return;

    /**
     * sendBeacon plutôt que fetch : le navigateur se charge de
     * l'expédition, y compris si la page se ferme dans la seconde.
     * Une mesure ne doit jamais retarder une navigation.
     */
    function send(payload) {
        try {
            var body = new Blob([JSON.stringify(payload)], { type: 'application/json' });

            if (navigator.sendBeacon && navigator.sendBeacon(cfg.url, body)) return;
        } catch (e) {
            // On retombe sur fetch ci-dessous.
        }

        try {
            fetch(cfg.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true,
                credentials: 'same-origin',
            });
        } catch (e) {
            // Mesure perdue : sans conséquence pour le visiteur.
        }
    }

    /**
     * Paramètres de campagne portés par l'URL.
     *
     * « utm » tout court est notre forme courte ; « utm_source » et les
     * autres sont la forme standard, ajoutée par les régies.
     */
    function query() {
        var out = {};

        try {
            new URLSearchParams(location.search).forEach(function (value, key) {
                if (key === 'utm' || key.indexOf('utm_') === 0) out[key] = value;
            });
        } catch (e) {
            // Navigateur ancien : la campagne sera comptée en direct.
        }

        return out;
    }

    /**
     * Efface le paramètre de l'adresse une fois la visite comptée.
     *
     * Deux raisons. Le visiteur voit une adresse propre, qu'elle peut
     * partager ou mettre en favori sans y coller une campagne qui n'est
     * pas la sienne. Et un rechargement de page ne recompte pas le clic.
     */
    function cleanUrl() {
        if (!history.replaceState || !location.search) return;

        try {
            var params = new URLSearchParams(location.search);
            var touched = false;

            ['utm', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (k) {
                if (params.has(k)) { params.delete(k); touched = true; }
            });

            if (!touched) return;

            var rest = params.toString();
            history.replaceState(null, '', location.pathname + (rest ? '?' + rest : '') + location.hash);
        } catch (e) {
            // L'adresse reste telle quelle : sans conséquence.
        }
    }

    /**
     * Titre de la page, sans le nom du site.
     *
     * WordPress accole « – Nom du site » à chaque titre : le répéter sur
     * chaque ligne du fil d'activité n'apprend rien à qui regarde son
     * propre tableau de bord.
     */
    function pageTitle() {
        return (document.title || '')
            .replace(/\s*[–—|-]\s*[^–—|-]+$/, '')
            .trim() || document.title;
    }

    function pageview() {
        send({
            p: location.pathname,
            t: pageTitle(),
            r: document.referrer || '',
            q: query(),
            // Résolution et langue : deux repères utiles pour savoir
            // sur quoi tester la boutique, et sans lien avec une
            // personne en particulier.
            s: (window.screen ? screen.width + 'x' + screen.height : ''),
            g: (navigator.language || ''),
        });

        cleanUrl();
    }

    function event(name, objectId, value, label) {
        send({
            e: name,
            o: objectId || 0,
            v: value || 0,
            l: label || '',
            p: location.pathname,
            r: '',
            q: {},
        });
    }

    // La page vue part une fois le document prêt : plus tôt, le titre
    // n'est pas encore fixé sur les pages construites par Elementor.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pageview);
    } else {
        pageview();
    }

    /**
     * Temps passé sur la page.
     *
     * Mesuré au départ, pas à l'arrivée : on ne connaît la durée qu'au
     * moment où l'on s'en va. « visibilitychange » plutôt que
     * « beforeunload », le seul évènement sur lequel les navigateurs
     * mobiles soient fiables — un onglet quitté d'un balayage ne
     * déclenche pas le second.
     *
     * Le temps ne compte que si l'onglet est visible : une page laissée
     * ouverte en arrière-plan toute la nuit ne vaut pas huit heures de
     * lecture.
     */
    var visibleSince = document.visibilityState === 'visible' ? Date.now() : 0;
    var accumulated = 0;
    var reported = false;

    function flushTime() {
        if (visibleSince) {
            accumulated += Date.now() - visibleSince;
            visibleSince = 0;
        }

        var seconds = Math.round(accumulated / 1000);

        // En deçà d'une seconde ou au-delà de trente minutes, la mesure
        // ne veut plus rien dire : rebond involontaire d'un côté,
        // onglet oublié de l'autre.
        if (reported || seconds < 1 || seconds > 1800) return;

        reported = true;
        event('page_time', 0, seconds, location.pathname);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flushTime();
        } else {
            visibleSince = Date.now();
        }
    });

    window.addEventListener('pagehide', flushTime);

    if (!cfg.events) return;

    // Produit consulté : c'est ce qui permet ensuite de rapporter les
    // ajouts au panier au nombre de vues, produit par produit.
    if (cfg.product) event('view_item', cfg.product);

    // Recherche interne : savoir ce qu'on cherche sans trouver vaut
    // souvent plus qu'un classement de pages vues.
    if (cfg.search) event('search', 0, 0, cfg.search);

    // L'ajout au panier est enregistré côté serveur uniquement après
    // confirmation de WooCommerce, pour le panier classique comme les blocs.
    if (cfg.cart) event('view_cart');
    if (cfg.checkout) event('begin_checkout');
})();
