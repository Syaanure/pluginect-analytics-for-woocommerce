/**
 * CamiBijoux – Analytics : interface d'administration.
 *
 * Quatre choses seulement : le globe, l'infobulle du graphique, la
 * copie des liens de campagne et le rafraîchissement du temps réel.
 * Aucune bibliothèque — le globe est de la trigonométrie, pas de la 3D.
 */
(function () {
    'use strict';

    var RAD = Math.PI / 180;

    // ══════════════════════════════════════════════════════
    //  GLOBE
    // ══════════════════════════════════════════════════════

    /**
     * Projection orthographique : la vue d'une sphère depuis très loin,
     * c'est-à-dire un globe vu de face. Trois lignes de trigonométrie
     * suffisent, et les points passés derrière sont simplement masqués.
     */
    function svgEl(name, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', name);

        for (var k in attrs) el.setAttribute(k, attrs[k]);

        return el;
    }

    /**
     * Globe.
     *
     * Trois partis pris, qui font la différence avec un rond plat
     * couvert de points :
     *
     * 1. Les terres sont un PAVAGE HEXAGONAL. Un semis de disques
     *    laisse des vides irréguliers ; des hexagones jointifs se
     *    lisent comme une surface, et c'est ce qui donne l'air
     *    « fabriqué » plutôt que « saupoudré ».
     *
     * 2. La sphère est MODELÉE par des dégradés superposés : une
     *    lumière en haut à gauche, un assombrissement au limbe, un
     *    halo qui déborde. Sans eux, un disque reste un disque, quel
     *    que soit le soin mis au reste.
     *
     * 3. Rien ne clignote. L'animation est une rotation lente et une
     *    apparition au chargement. Un réseau qui scintille fatigue
     *    sans rien apprendre.
     */
    function initGlobe(root) {
        var svg = root.querySelector('svg');
        var readout = document.querySelector('[data-cbaz-readout]');
        var spinBtn = document.querySelector('[data-cbaz-spin]');
        var metric = root.dataset.metric || 'sessions';

        var points = [];
        var home = null;

        try {
            points = JSON.parse(root.dataset.points || '[]');
        } catch (e) {
            points = [];
        }

        try {
            home = JSON.parse(root.dataset.home || 'null');
        } catch (e) {
            home = null;
        }

        /*
         * Au chargement, la sphère tient ENTIÈRE dans le cadre : on
         * voit le monde d'un bloc, et c'est ensuite au zoom d'aller
         * chercher le détail. Le cadre doit donc contenir le plus haut
         * des arcs, qui monte à 14 % au-dessus de la surface.
         *
         *   sphère        : 132
         *   arc le + haut : 132 × 1,14 = 150,5
         *   demi-cadre    :              152
         *
         * La lumière du limbe est peinte À L'INTÉRIEUR de la sphère :
         * aucun halo extérieur ne peut donc être tronqué.
         */
        var BASE = 132;
        var zoom = 1;
        var R = BASE;
        var rotation = -12;
        // Basculement nord-sud. Borné : passé les pôles, la sphère
        // s'inverse et le geste devient incompréhensible.
        var tilt = 8;
        var spinning = true;
        var dragging = false;
        var hovering = false;
        var lastX = 0;
        var lastY = 0;
        var active = null;
        var frame = 0;
        var intro = 0;

        /**
         * Orientation d'un point de la sphère.
         *
         * Deux rotations successives : autour de l'axe des pôles pour
         * le va-et-vient est-ouest, puis autour de l'axe horizontal
         * pour le basculement nord-sud. C'est ce second axe qui permet
         * de regarder le globe par-dessus ou par-dessous, et pas
         * seulement de le faire tourner comme une toupie.
         */
        function orient(v) {
            var a = rotation * RAD;
            var sa = Math.sin(a);
            var ca = Math.cos(a);

            var x = v[0] * ca - v[2] * sa;
            var z = v[0] * sa + v[2] * ca;

            var b = tilt * RAD;
            var sb = Math.sin(b);
            var cb = Math.cos(b);

            return [x, v[1] * cb - z * sb, v[1] * sb + z * cb];
        }

        function toVec(lat, lon) {
            var f = lat * RAD;
            var l = lon * RAD;
            var c = Math.cos(f);

            return [c * Math.sin(l), Math.sin(f), c * Math.cos(l)];
        }

        /** Un point de la surface, à l'écran. */
        function at(lat, lon) {
            var v = orient(toVec(lat, lon));

            return { x: v[0] * R, y: -v[1] * R, visible: v[2] > 0, depth: v[2] };
        }

        function metricOf(p) {
            if (metric === 'orders') return p.orders;
            if (metric === 'revenue') return p.revenue;
            if (metric === 'cr') return p.sessions ? (p.orders / p.sessions) * 100 : 0;
            return p.sessions;
        }

        var maxMetric = points.reduce(function (m, p) { return Math.max(m, metricOf(p)); }, 1);

        function sizeFor(p) {
            /*
             * Deux règles se combinent ici.
             *
             * La racine carrée d'abord : c'est la SURFACE du marqueur
             * qui doit être proportionnelle à la valeur, pas son rayon
             * — sinon un pays deux fois plus visité paraît quatre fois
             * plus gros.
             *
             * Le rapport au rayon ensuite : le marqueur grandit avec la
             * sphère. Une taille fixe donnait des pastilles énormes sur
             * un globe dézoomé et des têtes d'épingle une fois zoomé,
             * puisque seul le globe changeait de taille.
             */
            var echelle = R / BASE;

            return (1.15 + Math.sqrt(metricOf(p) / maxMetric) * 1.95) * echelle;
        }

        function inside(lon, lat, poly) {
            var hit = false;

            for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
                var xi = poly[i][0], yi = poly[i][1];
                var xj = poly[j][0], yj = poly[j][1];

                if ((yi > lat) !== (yj > lat)
                    && lon < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) {
                    hit = !hit;
                }
            }

            return hit;
        }

        /**
         * Le pavage, calculé UNE fois.
         *
         * Une rangée sur deux est décalée d'un demi-pas : c'est ce
         * décalage qui donne le nid d'abeille plutôt qu'un quadrillage.
         * Le test « cette cellule est-elle sur la terre ferme » ne
         * dépend que de la latitude et de la longitude, jamais de la
         * rotation — chaque image se contente ensuite de projeter une
         * liste déjà filtrée.
         *
         * Les hexagones ne se touchent pas tout à fait — le facteur
         * 0.46 laisse un jour entre eux. Jointifs, ils formeraient une
         * tache verte ; espacés, on lit la trame.
         *
         * Et surtout : leur taille est CONSTANTE à l'écran. En les
         * rétrécissant vers le limbe, on obtenait un dégradé mou ; en
         * les gardant identiques, ils se resserrent d'eux-mêmes à
         * mesure que la surface fuit, et cette densité croissante fait
         * la rondeur bien mieux qu'un fondu.
         */
        var STEP = 1.15;

        function buildLand() {
            var world = window.cbazWorld || [];
            var out = [];
            var row = 0;

            /*
             * Rectangle englobant de chaque contour, calculé d'avance.
             *
             * Sans lui, chaque cellule serait confrontée aux deux mille
             * six cents points du monde entier — quinze mille cellules
             * font quarante millions de comparaisons, et le navigateur
             * se fige une seconde au chargement. Avec, une cellule au
             * milieu du Pacifique est écartée en quatre soustractions.
             */
            var boxes = world.map(function (poly) {
                var minx = 180, maxx = -180, miny = 90, maxy = -90;

                for (var i = 0; i < poly.length; i++) {
                    if (poly[i][0] < minx) minx = poly[i][0];
                    if (poly[i][0] > maxx) maxx = poly[i][0];
                    if (poly[i][1] < miny) miny = poly[i][1];
                    if (poly[i][1] > maxy) maxy = poly[i][1];
                }

                return [minx, maxx, miny, maxy];
            });

            /*
             * Jusqu'aux pôles, et pas seulement jusqu'au 83e parallèle.
             * S'arrêter là laissait une calotte vide au centre de
             * l'Antarctique — un trou rond, d'autant plus visible que
             * le continent est dessiné tout autour.
             */
            for (var lat = -89.5; lat <= 89.5; lat += STEP) {
                /*
                 * Les méridiens se resserrent vers les pôles : on
                 * espace les cellules en conséquence, sinon les
                 * calottes deviennent un pâté. Le plancher de 0,10
                 * borne l'écartement — sans lui, la dernière rangée
                 * demanderait un tour complet pour deux cellules.
                 */
                var span = STEP / Math.max(0.10, Math.cos(lat * RAD));
                var shift = (row % 2) * span * 0.5;

                for (var lon = -180 + shift; lon < 180; lon += span) {
                    for (var k = 0; k < world.length; k++) {
                        var b = boxes[k];

                        if (lon < b[0] || lon > b[1] || lat < b[2] || lat > b[3]) {
                            continue;
                        }

                        if (inside(lon, lat, world[k])) {
                            out.push([lat, lon]);
                            break;
                        }
                    }
                }

                row++;
            }

            return out;
        }

        var land = buildLand();

        // Sommets d'un hexagone unité, pointe en haut. Calculés une
        // fois : six sinus et six cosinus par cellule et par image
        // seraient du gaspillage pur.
        var HEX = [];

        for (var a = 0; a < 6; a++) {
            var ang = (Math.PI / 3) * a + Math.PI / 6;
            HEX.push([Math.cos(ang), Math.sin(ang)]);
        }

        /**
         * Le pavage, en DEUX chemins.
         *
         * Les cellules proches du limbe sont rendues dans un second
         * chemin, plus pâle. Un pavage d'opacité uniforme se lit comme
         * un autocollant posé sur un disque ; ce dégradé de densité
         * fait tourner la surface avec la sphère.
         *
         * Les hexagones ne se touchent pas tout à fait — le facteur
         * 0.46 laisse un jour entre eux. Jointifs, ils formeraient une
         * tache verte ; espacés, on lit la trame.
         *
         * Et surtout : leur taille est CONSTANTE à l'écran. En les
         * rétrécissant vers le limbe, on obtenait un dégradé mou ; en
         * les gardant identiques, ils se resserrent d'eux-mêmes à
         * mesure que la surface fuit, et cette densité croissante fait
         * la rondeur bien mieux qu'un fondu.
         */
        function landPaths() {
            var near = '';
            var far = '';
            var unit = R * STEP * RAD * 0.46;

            for (var i = 0; i < land.length; i++) {
                var p = at(land[i][0], land[i][1]);
                if (!p.visible) continue;

                var r = unit;

                var d = '';

                for (var v = 0; v < 6; v++) {
                    d += (v ? 'L' : 'M')
                       + (p.x + HEX[v][0] * r).toFixed(2) + ' '
                       + (p.y + HEX[v][1] * r).toFixed(2);
                }

                d += 'Z';

                if (p.depth > 0.3) {
                    near += d;
                } else {
                    far += d;
                }
            }

            return { near: near, far: far };
        }

        var hub = home || points.reduce(function (best, p) {
            return !best || p.sessions > best.sessions ? p : best;
        }, null);

        var links = hub ? points.filter(function (p) { return p.code !== hub.code; }) : [];

        // ── Géométrie en trois dimensions ───────────────────────
        //
        // Les liaisons ne peuvent pas se calculer à plat. Un arc entre
        // deux points du globe doit S'ÉLEVER au-dessus de la surface,
        // passer derrière l'horizon puis en ressortir : c'est ce qui
        // fait qu'on lit une connexion et non un gribouillis posé sur
        // une image. On travaille donc sur des vecteurs, et on ne
        // projette qu'au dernier moment.

        /**
         * Un point de l'arc, à l'écran.
         *
         * Visible s'il est devant la sphère — ou s'il en dépasse la
         * silhouette, auquel cas il passe forcément devant le vide.
         * C'est cette seconde condition qui laisse les arcs franchir
         * l'horizon au lieu de s'interrompre net.
         */
        function screenOf(v, radius) {
            var x = v[0] * radius;
            var y = -v[1] * radius;

            return { x: x, y: y, seen: v[2] > 0 || Math.hypot(x, y) > radius };
        }

        /** Points échantillonnés le long d'un grand cercle surélevé. */
        function arcSamples(from, to) {
            var a = toVec(from.lat, from.lon);
            var b = toVec(to.lat, to.lon);
            var dot = Math.max(-1, Math.min(1, a[0] * b[0] + a[1] * b[1] + a[2] * b[2]));
            var omega = Math.acos(dot);

            // Deux points confondus : pas d'arc à tracer.
            if (omega < 0.002) return [];

            /*
             * Plus les points sont éloignés, plus l'arc monte haut —
             * mais jamais au-delà de 22 % du rayon, sous peine de
             * sortir du cadre et d'être tranché net. C'est le prix à
             * payer pour un halo entier : des arcs un peu plus posés.
             */
            var high = 0.07 + 0.07 * (omega / Math.PI);
            var sin = Math.sin(omega);
            var out = [];

            for (var i = 0; i <= 44; i++) {
                var t = i / 44;
                var s1 = Math.sin((1 - t) * omega) / sin;
                var s2 = Math.sin(t * omega) / sin;
                var lift = 1 + high * Math.sin(Math.PI * t);

                var p = [
                    (a[0] * s1 + b[0] * s2) * lift,
                    (a[1] * s1 + b[1] * s2) * lift,
                    (a[2] * s1 + b[2] * s2) * lift,
                ];

                out.push(screenOf(orient(p), R));
            }

            return out;
        }

        /** Une polyligne, coupée là où elle repasse derrière le globe. */
        function pathOf(samples, from, to) {
            var d = '';
            var open = false;

            for (var i = from; i <= to && i < samples.length; i++) {
                if (!samples[i].seen) {
                    open = false;
                    continue;
                }

                d += (open ? 'L' : 'M') + samples[i].x.toFixed(1) + ' ' + samples[i].y.toFixed(1);
                open = true;
            }

            return d;
        }

        function drawLinks() {
            if (!hub) return;

            links.forEach(function (p, i) {
                var samples = arcSamples(p, hub);
                if (!samples.length) return;

                var trace = pathOf(samples, 0, samples.length - 1);

                if (trace) {
                    svg.appendChild(svgEl('path', { class: 'thread', d: trace }));
                }

                /*
                 * Le signal : un court segment lumineux qui remonte le
                 * fil VERS la boutique. C'est le sens de lecture — le
                 * monde vient à toi — et c'est ce segment, plutôt qu'un
                 * point isolé, qui donne l'impression d'une traînée.
                 *
                 * Il est recalculé à chaque image : le globe étant
                 * redessiné en continu, une animation SVG déclarée
                 * repartirait sans cesse de zéro.
                 */
                var head = ((frame * 0.006) + i * 0.13) % 1.3 - 0.15;

                if (head > -0.14 && head < 1) {
                    var a = Math.max(0, Math.floor((head - 0.14) * 44));
                    var b = Math.min(43, Math.ceil(head * 44));
                    var comet = pathOf(samples, a, b);

                    if (comet) {
                        svg.appendChild(svgEl('path', { class: 'signal', d: comet }));
                    }
                }
            });
        }

        /**
         * Les dégradés, construits une seule fois.
         *
         * Ils sont réinsérés à chaque image plutôt que redéfinis :
         * déplacer un nœud existant ne coûte rien, le reconstruire
         * coûterait quatre éléments et une douzaine d'attributs par
         * image.
         */
        function buildDefs() {
            var defs = svgEl('defs', {});

            function gradient(id, cx, cy, r, stops) {
                var g = svgEl('radialGradient', { id: id, cx: cx, cy: cy, r: r });

                stops.forEach(function (st) {
                    g.appendChild(svgEl('stop', {
                        offset: st[0], 'stop-color': st[1], 'stop-opacity': st[2],
                    }));
                });

                defs.appendChild(g);
            }

            // Lumière en haut à gauche, fraîcheur en bas à droite :
            // c'est l'asymétrie du dégradé qui pose le volume, bien
            // avant l'ombre du limbe.
            gradient('cbazOcean', '34%', '26%', '82%', [
                ['0', '#ffffff', '1'],
                ['0.5', '#f7fdfd', '1'],
                ['0.82', '#e2f3f4', '1'],
                ['1', '#cfe8ee', '1'],
            ]);

            /*
             * Le limbe s'ÉCLAIRE au lieu de s'assombrir. Une bille de
             * verre s'assombrit sur les bords ; une planète vue de
             * l'espace s'y auréole de son atmosphère. C'est la seconde
             * lecture qu'on veut ici, et elle a l'avantage de rester
             * dans la sphère — donc de ne jamais être tronquée.
             */
            gradient('cbazLimb', '50%', '50%', '50%', [
                ['0.72', '#8fd3e8', '0'],
                ['0.93', '#8fd3e8', '0.22'],
                ['1', '#6ec3e0', '0.42'],
            ]);

            return defs;
        }

        var defs = buildDefs();

        function draw() {
            while (svg.firstChild) svg.removeChild(svg.firstChild);

            svg.appendChild(defs);

            svg.appendChild(svgEl('circle', { class: 'ocean', cx: 0, cy: 0, r: R }));

            var hexes = landPaths();

            svg.appendChild(svgEl('path', { class: 'land land--far', d: hexes.far, 'fill-opacity': (0.34 * intro).toFixed(3) }));
            svg.appendChild(svgEl('path', { class: 'land', d: hexes.near, 'fill-opacity': (0.92 * intro).toFixed(3) }));

            // Lumière du limbe par-dessus les terres, données par-dessus
            // la lumière : le modelé doit teinter la géographie, jamais
            // les chiffres.
            svg.appendChild(svgEl('circle', { class: 'limb', cx: 0, cy: 0, r: R }));

            drawLinks();

            points
                .map(function (p) { return { p: p, pos: at(p.lat, p.lon) }; })
                .filter(function (o) { return o.pos.visible; })
                .sort(function (a, b) { return a.pos.depth - b.pos.depth; })
                .forEach(function (o, i) {
                    var core = sizeFor(o.p) * intro;

                    /*
                     * Onde qui se propage, décalée d'un point à
                     * l'autre : ensemble, elles donnent l'impression
                     * d'un trafic qui arrive de partout plutôt que
                     * d'une guirlande qui clignote en cadence.
                     */
                    var wave = ((frame * 0.012) + i * 0.37) % 1;

                    svg.appendChild(svgEl('circle', {
                        class: 'ping',
                        cx: o.pos.x.toFixed(1),
                        cy: o.pos.y.toFixed(1),
                        r: (core * (1 + wave * 3.8)).toFixed(2),
                        'stroke-opacity': (0.45 * (1 - wave) * intro).toFixed(3),
                    }));

                    svg.appendChild(svgEl('circle', {
                        class: 'halo',
                        cx: o.pos.x.toFixed(1),
                        cy: o.pos.y.toFixed(1),
                        r: (core * 1.9).toFixed(2),
                    }));

                    svg.appendChild(svgEl('circle', {
                        class: 'pt' + (active === o.p.code ? ' is-active' : ''),
                        cx: o.pos.x.toFixed(1),
                        cy: o.pos.y.toFixed(1),
                        r: core.toFixed(2),
                    }));
                });
        }

        function show(p) {
            active = p.code;

            if (readout) {
                readout.innerHTML =
                    '<p class="cbaz-readout__flag">' + p.flag + '</p>' +
                    '<p class="cbaz-readout__name">' + p.name + '</p>' +
                    '<dl class="cbaz-stats">' +
                    '<div><dt>Visites</dt><dd>' + p.sessions.toLocaleString('fr-FR') + '</dd></div>' +
                    '<div><dt>Commandes</dt><dd>' + p.orders.toLocaleString('fr-FR') + '</dd></div>' +
                    '<div><dt>Chiffre d’affaires</dt><dd>' + p.money + '</dd></div>' +
                    '<div><dt>Conversion</dt><dd>' +
                        (p.sessions ? ((p.orders / p.sessions) * 100).toFixed(2).replace('.', ',') : '0,00') + ' %</dd></div>' +
                    '</dl>';
            }

            document.querySelectorAll('[data-cbaz-country]').forEach(function (li) {
                li.classList.toggle('is-active', li.dataset.cbazCountry === p.code);
            });

            draw();
        }

        /**
         * Survol : on cherche le marqueur le plus proche du curseur.
         *
         * Ils sont redessinés à chaque image, donc un écouteur posé sur
         * l'un d'eux disparaîtrait aussitôt. On calcule la distance
         * nous-mêmes, ce qui a l'avantage d'accrocher aussi les tout
         * petits.
         */
        function pointAt(event) {
            var ctm = svg.getScreenCTM();
            if (!ctm) return null;

            var sp = svg.createSVGPoint();
            sp.x = event.clientX;
            sp.y = event.clientY;

            var local = sp.matrixTransform(ctm.inverse());
            var best = null;
            // La tolérance de visée suit elle aussi le zoom : à fort
            // grossissement les pays s'écartent, et une tolérance fixe
            // finirait par accrocher le voisin.
            var bestDist = 13 * (R / BASE);

            points.forEach(function (pt) {
                var pos = at(pt.lat, pt.lon);
                if (!pos.visible) return;

                var d = Math.hypot(pos.x - local.x, pos.y - local.y);

                if (d < bestDist) {
                    bestDist = d;
                    best = pt;
                }
            });

            return best;
        }

        root.addEventListener('mousemove', function (e) {
            if (dragging) return;

            var pt = pointAt(e);

            if (pt && pt.code !== active) show(pt);
        });

        // La rotation s'arrête sous le curseur : viser un point qui
        // s'échappe est une petite torture.
        root.addEventListener('mouseenter', function () { hovering = true; });
        root.addEventListener('mouseleave', function () { hovering = false; });

        /**
         * Apparition.
         *
         * Le pavage, les marqueurs et l'épingle montent de zéro à leur
         * taille en une seconde. C'est le seul moment où le globe
         * s'anime vraiment, et il donne l'impression que la carte se
         * construit plutôt qu'elle ne surgit.
         */
        function tick() {
            frame++;

            if (intro < 1) {
                intro = Math.min(1, intro + 0.02);
                draw();
            } else if (frame % 2 === 0) {
                // La rotation s'arrête sous le curseur — viser un point
                // qui s'échappe est une petite torture — mais les
                // signaux, eux, continuent de circuler : c'est ce qui
                // garde la carte vivante pendant qu'on lit une fiche.
                if (spinning && !dragging && !hovering) {
                    rotation = (rotation + 0.26) % 360;
                }

                if (spinning || dragging) draw();
            }

            requestAnimationFrame(tick);
        }

        root.addEventListener('pointerdown', function (e) {
            if (e.target.closest && e.target.closest('.cbaz-globe__tools')) return;

            dragging = true;
            lastX = e.clientX;
            lastY = e.clientY;
            root.setPointerCapture(e.pointerId);
        });

        root.addEventListener('pointermove', function (e) {
            if (!dragging) return;

            rotation = (rotation - (e.clientX - lastX) * 0.4) % 360;

            // Le basculement s'arrête avant les pôles : au-delà, la
            // sphère se retourne et le geste perd tout sens.
            tilt = Math.max(-72, Math.min(72, tilt + (e.clientY - lastY) * 0.35));

            lastX = e.clientX;
            lastY = e.clientY;
            draw();
        });

        ['pointerup', 'pointercancel'].forEach(function (ev) {
            root.addEventListener(ev, function () { dragging = false; });
        });

        if (spinBtn) {
            spinBtn.addEventListener('click', function () {
                spinning = !spinning;
                spinBtn.textContent = spinning ? 'Pause rotation' : 'Reprendre la rotation';
            });
        }

        /**
         * Zoom.
         *
         * À la molette d'abord, parce que c'est le geste qu'on essaie
         * en premier sur une carte ; par les boutons ensuite, pour qui
         * n'a pas de molette ou navigue au clavier.
         *
         * Au-delà de 1, le halo et les arcs débordent du cadre et se
         * font rogner — c'est le comportement attendu d'un zoom, on
         * regarde de plus près et le reste sort du champ.
         */
        function setZoom(next) {
            zoom = Math.min(2.4, Math.max(0.7, next));
            R = BASE * zoom;
            draw();
        }

        root.addEventListener('wheel', function (e) {
            e.preventDefault();

            // Le pavé tactile envoie des dizaines d'évènements minuscules
            // là où une molette en envoie un gros : on borne le pas pour
            // que les deux gestes aient la même vivacité.
            var step = Math.max(-0.12, Math.min(0.12, -e.deltaY * 0.0016));

            setZoom(zoom + step);
        }, { passive: false });

        root.querySelectorAll('[data-cbaz-zoom]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                setZoom(zoom + (btn.dataset.cbazZoom === '+' ? 0.25 : -0.25));
            });
        });

        var resetBtn = root.querySelector('[data-cbaz-reset]');

        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                rotation = -12;
                tilt = 8;
                setZoom(1);
            });
        }

        // Qui a réglé son système sur « animations réduites » obtient
        // un globe fixe, affiché d'emblée à sa taille définitive.
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            spinning = false;
            intro = 1;
            if (spinBtn) spinBtn.textContent = 'Reprendre la rotation';
        }

        draw();
        requestAnimationFrame(tick);
    }

    // ══════════════════════════════════════════════════════
    //  INFOBULLE DU GRAPHIQUE
    // ══════════════════════════════════════════════════════

    /**
     * Graphique : bascules de série et infobulle.
     *
     * Les courbes sont toutes tracées côté serveur ; on ne fait ici
     * qu'afficher ou masquer celles qu'on veut voir. Rien n'est
     * recalculé, donc le basculement est instantané.
     */
    function initChart(card) {
        var root = card.querySelector('[data-cbaz-chart]');
        if (!root) return;

        var svg = root.querySelector('svg');

        // ── Bascules ────────────────────────────────────
        card.querySelectorAll('.cbaz-serie').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.dataset.serie;
                var on = !btn.classList.contains('is-on');

                btn.classList.toggle('is-on', on);

                svg.querySelectorAll('[data-serie="' + key + '"]').forEach(function (el) {
                    // L'attribut plutôt qu'un style : la feuille de
                    // style pose display:none en dur sur [hidden], et un
                    // display en ligne se ferait écraser.
                    if (on) {
                        el.removeAttribute('hidden');
                    } else {
                        el.setAttribute('hidden', '');
                    }
                });
            });
        });

        // ── Infobulle ───────────────────────────────────
        var tip = document.createElement('div');
        tip.className = 'cbaz-tip';
        tip.hidden = true;
        root.appendChild(tip);

        var labels = {
            sessions: 'Visites',
            pageviews: 'Pages vues',
            revenue: 'Chiffre d’affaires',
            orders: 'Commandes',
        };

        root.addEventListener('mousemove', function (e) {
            var dot = e.target.closest ? e.target.closest('.cbaz-chart__dot') : null;

            if (!dot) {
                tip.hidden = true;
                return;
            }

            var box = root.getBoundingClientRect();
            var lines = '<strong>' + dot.dataset.label + '</strong>';

            // Seules les séries affichées entrent dans l'infobulle :
            // lire quatre chiffres quand on n'en regarde qu'un seul
            // oblige à trier soi-même à chaque survol.
            card.querySelectorAll('.cbaz-serie.is-on').forEach(function (btn) {
                var k = btn.dataset.serie;
                lines += '<span class="cbaz-tip__row"><i data-serie="' + k + '"></i>'
                       + labels[k] + ' <b>' + (dot.dataset[k] || '0') + '</b></span>';
            });

            tip.innerHTML = lines;
            tip.hidden = false;

            var x = e.clientX - box.left;
            tip.style.left = Math.max(70, Math.min(box.width - 70, x)) + 'px';
            tip.style.top = Math.max(10, e.clientY - box.top - 12) + 'px';
        });

        root.addEventListener('mouseleave', function () { tip.hidden = true; });
    }

    // ══════════════════════════════════════════════════════
    //  COPIE DES LIENS
    // ══════════════════════════════════════════════════════

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-cbaz-copy]') : null;
        if (!btn) return;

        // Un lien de campagne tient dans un champ, un paragraphe de
        // politique de confidentialité dans une zone de texte : les
        // deux se copient de la même façon.
        var input = btn.parentNode.querySelector('input, textarea');
        if (!input) return;

        input.select();

        var done = function () {
            btn.textContent = 'Copié';
            setTimeout(function () { btn.textContent = 'Copier'; }, 1600);
        };

        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(done, function () {
                document.execCommand('copy');
                done();
            });
        } else {
            document.execCommand('copy');
            done();
        }
    });

    // ══════════════════════════════════════════════════════
    //  CONSTRUCTEUR DE LIEN
    // ══════════════════════════════════════════════════════

    /** Même normalisation que côté serveur, pour que l'aperçu ne mente pas. */
    function slugUtm(value) {
        return (value || '')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase().trim()
            .replace(/[^a-z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function initBuilder(form) {
        var out = form.querySelector('[data-cbaz-preview-url]');
        if (!out) return;

        function field(name) {
            var el = form.querySelector('[name="' + name + '"]');
            return el ? slugUtm(el.value) : '';
        }

        function refresh() {
            var target = form.querySelector('[name="target_url"]');
            var url = (target && target.value) || '';
            var ref = field('campaign');

            // Un seul paramètre, comme le lien réellement produit : le
            // reste est lu sur la fiche au moment de la visite.
            out.textContent = ref
                ? url + (url.indexOf('?') === -1 ? '?' : '&') + 'utm=' + encodeURIComponent(ref)
                : url;
        }

        form.addEventListener('input', refresh);
        refresh();

        document.querySelectorAll('[data-cbaz-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ['source', 'medium', 'content'].forEach(function (k) {
                    var el = form.querySelector('[name="' + k + '"]');
                    if (el && btn.dataset[k]) el.value = btn.dataset[k];
                });

                document.querySelectorAll('[data-cbaz-preset]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });

                refresh();
            });
        });
    }

    // ══════════════════════════════════════════════════════
    //  TEMPS RÉEL
    // ══════════════════════════════════════════════════════

    function initLive(root) {
        var online = root.querySelector('[data-cbaz-online]');
        var feed = root.querySelector('[data-cbaz-feed]');
        var nonce = root.dataset.nonce;

        function refresh() {
            var body = new URLSearchParams({ action: 'cbaz_realtime', _ajax_nonce: nonce });

            fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || !json.success) return;

                    if (online) online.textContent = Number(json.data.online).toLocaleString('fr-FR');

                    if (feed && json.data.feed.length) {
                        feed.innerHTML = json.data.feed.map(function (f) {
                            return '<li class="cbaz-timeline--' + f.tone + '">' +
                                '<div class="cbaz-timeline__top">' +
                                '<span class="cbaz-timeline__time">' + f.time + '</span>' +
                                '<span class="cbaz-badge cbaz-badge--' + f.tone + '">' + f.label + '</span>' +
                                '</div>' +
                                '<p class="cbaz-timeline__what">' + f.what + '</p>' +
                                '<p class="cbaz-timeline__who">' + f.flag + ' ' + f.country + ' · ' + f.device + '</p>' +
                                '</li>';
                        }).join('');
                    }
                })
                .catch(function () { /* on garde l'affichage précédent */ });
        }

        setInterval(refresh, 20000);
    }

    // ══════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════
    //  RECHERCHE DANS LES TABLEAUX
    //
    //  Le filtrage se fait sur les lignes déjà affichées : aucun
    //  rechargement, et le tri en place ne bouge pas sous les doigts.
    // ══════════════════════════════════════════════════════

    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('[data-cbaz-search]')) return;

        var card = input.closest('.cbaz-card');
        if (!card) return;

        // Un tableau filtre ses lignes ; une liste de parcours filtre
        // ses éléments. Même geste, deux structures.
        var body = card.querySelector('.cbaz-table tbody') || card.querySelector('.cbaz-trails');
        if (!body) return;

        var selector = body.matches('.cbaz-trails') ? ':scope > li' : 'tr';
        var needle = input.value.trim().toLowerCase();
        var shown = 0;

        body.querySelectorAll(selector).forEach(function (row) {
            if (row.classList.contains('cbaz-empty') || row.querySelector('.cbaz-empty')) return;

            var match = !needle || row.textContent.toLowerCase().indexOf(needle) !== -1;
            row.hidden = !match;

            if (match) shown++;
        });

        var count = card.querySelector('[data-cbaz-count]');

        if (count) {
            var total = body.querySelectorAll(selector).length;
            count.textContent = shown === total
                ? '1–' + total + ' sur ' + total + ' ' + count.dataset.unit
                : shown + ' sur ' + total + ' ' + count.dataset.unit;
        }
    });

    // ══════════════════════════════════════════════════════
    //  RECHERCHE DANS LE CATALOGUE
    // ══════════════════════════════════════════════════════

    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('[data-cbaz-report-search]')) return;

        var needle = input.value.trim().toLowerCase();
        var found = 0;

        document.querySelectorAll('[data-cbaz-report]').forEach(function (card) {
            var match = !needle || card.textContent.toLowerCase().indexOf(needle) !== -1;
            card.hidden = !match;

            if (match) found++;
        });

        // Un intitulé de famille sans fiche visible n'a plus lieu d'être.
        document.querySelectorAll('[data-cbaz-group]').forEach(function (group) {
            var visible = group.querySelectorAll('[data-cbaz-report]:not([hidden])').length;
            group.hidden = visible === 0;
        });

        var none = document.querySelector('[data-cbaz-noresult]');
        if (none) none.hidden = found > 0;
    });

    // ══════════════════════════════════════════════════════
    //  MENUS DÉROULANTS
    // ══════════════════════════════════════════════════════

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-cbaz-drop]') : null;
        var open = btn ? btn.parentNode.querySelector('.cbaz-drop__menu') : null;

        // Le menu de période contient un formulaire de dates : cliquer
        // dedans doit pouvoir saisir, pas tout refermer.
        if (!btn && e.target.closest && e.target.closest('.cbaz-drop__menu')) return;

        document.querySelectorAll('.cbaz-drop__menu').forEach(function (menu) {
            if (menu !== open) menu.hidden = true;
        });

        if (open) {
            e.preventDefault();
            open.hidden = !open.hidden;
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;

        document.querySelectorAll('.cbaz-drop__menu').forEach(function (m) { m.hidden = true; });
    });

    document.addEventListener('DOMContentLoaded', function () {
        var globe = document.querySelector('[data-cbaz-globe]');
        if (globe) initGlobe(globe);

        document.querySelectorAll('.cbaz-card').forEach(initChart);

        var live = document.querySelector('[data-cbaz-live]');
        if (live) initLive(live);

        var builder = document.querySelector('[data-cbaz-builder]');
        if (builder) initBuilder(builder);
    });
})();
