import './stimulus_bootstrap.js';
import './styles/app.css';

/* -------------------------------------------------------------------------
   Tout est écrit en délégation : on écoute sur `document`, jamais sur les
   éléments eux-mêmes. Le code reste valable même si le contenu de la page
   est remplacé après le chargement.
   ------------------------------------------------------------------------- */


// ===== Outils communs =====

// Rend `element` seul porteur de `classe` parmi les éléments `selecteur` de `racine`.
function activerSeul(element, selecteur, racine = document, classe = 'is-active') {
    racine.querySelectorAll(selecteur + '.' + classe).forEach(e => e.classList.remove(classe));
    element.classList.add(classe);
}

// Une option choisie derrière un « +N » doit rester visible sur la fiche.
// Une seule à la fois, et seulement si elle fait partie des masquées.
function rendreVisible(element, selecteur) {
    document.querySelectorAll(selecteur + '.swatch-shown').forEach(e => e.classList.remove('swatch-shown'));
    if (element.classList.contains('swatch-extra')) element.classList.add('swatch-shown');
}

function lienWhatsApp(numero, message) {
    return 'https://wa.me/' + numero + '?text=' + encodeURIComponent(message);
}


// ===== Menu mobile =====
document.addEventListener('click', (e) => {
    if (e.target.closest('.burger')) {
        const nav = document.querySelector('.nav');
        const burger = document.querySelector('.burger');
        burger.setAttribute('aria-expanded', nav.classList.toggle('open') ? 'true' : 'false');
        return;
    }
    if (e.target.closest('.nav a')) {
        document.querySelector('.nav')?.classList.remove('open');
    }
});


// ===== Galerie fiche produit =====

// Photo affichée : celle dont le bord gauche est le plus proche du bord du cadre.
// On lit les positions réelles, donc ni l'écart entre photos ni les arrondis ne faussent le calcul.
function photoCourante(viewport) {
    let index = 0, ecart = Infinity;
    [...viewport.children].forEach((photo, i) => {
        const distance = Math.abs(photo.offsetLeft - viewport.scrollLeft);
        if (distance < ecart) { ecart = distance; index = i; }
    });
    return index;
}

// Souligne la miniature correspondante et la ramène dans le champ de vision
function marquerMiniature(gallery, index) {
    const active = [...gallery.querySelectorAll('.gal-thumb')][index];
    if (!active) return;

    activerSeul(active, '.gal-thumb', gallery);

    const bande = gallery.querySelector('.gal-thumbs');
    if (bande) {
        bande.scrollTo({
            left: active.offsetLeft - (bande.clientWidth - active.clientWidth) / 2,
            behavior: 'smooth',
        });
    }
}

// Affiche une photo, en bouclant aux extrémités
function afficherPhoto(gallery, voulu) {
    const viewport = gallery.querySelector('.gal-viewport');
    const photos = [...viewport.children];
    const index = ((voulu % photos.length) + photos.length) % photos.length;

    viewport.scrollTo({ left: photos[index].offsetLeft, behavior: 'smooth' });
    marquerMiniature(gallery, index);
}

// Miniatures et flèches
document.addEventListener('click', (e) => {
    const cible = e.target.closest('.gal-thumb, .gal-prev, .gal-next');
    if (!cible) return;

    const gallery = cible.closest('.gallery');
    const miniature = cible.closest('.gal-thumb');
    const pas = cible.classList.contains('gal-next') ? 1 : -1;

    afficherPhoto(gallery, miniature
        ? [...gallery.querySelectorAll('.gal-thumb')].indexOf(miniature)
        : photoCourante(gallery.querySelector('.gal-viewport')) + pas);
});

// Défilement au doigt ou à la molette : la miniature suit, une fois le geste terminé.
// L'événement `scroll` ne remonte pas dans l'arbre : on l'attrape en phase de capture, d'où le `true`.
const minuteries = new WeakMap();
document.addEventListener('scroll', (e) => {
    const viewport = e.target;
    if (!viewport.classList?.contains('gal-viewport')) return;

    clearTimeout(minuteries.get(viewport));
    minuteries.set(viewport, setTimeout(() => {
        marquerMiniature(viewport.closest('.gallery'), photoCourante(viewport));
    }, 120));
}, true);

// ===== Façades : vidéo YouTube, carte Google Maps =====
// Rien n'est chargé depuis Google tant que personne ne clique. Le bouton porte l'adresse de
// l'iframe, qui remplace au clic tout le contenu de la façade (le parent du bouton).
// Le clic vaut consentement éclairé, puisque la phrase d'information est visible.
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-facade]');
    if (!button) return;

    const iframe = document.createElement('iframe');
    iframe.src = button.dataset.facade;
    iframe.title = button.dataset.facadeTitle;
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    button.parentElement.replaceChildren(iframe);
});

// ===== Configurateur : tissu, coloris, message WhatsApp =====

// Reflète la sélection courante dans les libellés et dans le lien WhatsApp
function refleterSelection() {
    const btn = document.querySelector('.btn-devis');
    if (!btn) return;

    const tissu = document.querySelector('.mat-opt.is-active')?.dataset.fabric;
    const coloris = document.querySelector('.nuancier-group.is-visible .col-dot.is-active')?.dataset.color;

    const libelleTissu = document.querySelector('.cfg-choice[data-choice="fabric"]');
    const libelleColoris = document.querySelector('.nuancier-group.is-visible .cfg-choice[data-choice="color"]');

    if (libelleTissu && tissu) libelleTissu.textContent = tissu;
    if (libelleColoris && coloris) libelleColoris.textContent = coloris;

    let message = 'Bonjour, je suis intéressée par le ' + btn.dataset.product;
    if (tissu) message += ', tissu ' + tissu;
    if (coloris) message += ', coloris ' + coloris;
    message += '. Je souhaite le commander.';

    btn.href = lienWhatsApp(btn.dataset.wa, message);
}

// Applique un tissu : bouton actif, nuancier correspondant affiché, libellés à jour
function choisirTissu(bouton) {
    if (!bouton) return;

    rendreVisible(bouton, '.mat-opt');
    activerSeul(bouton, '.mat-opt', bouton.parentElement);

    document.querySelectorAll('[data-nuancier]').forEach((groupe) => {
        groupe.classList.toggle('is-visible', groupe.dataset.nuancier === bouton.dataset.fabricId);
    });

    refleterSelection();
}

// Applique un coloris
function choisirColoris(pastille) {
    if (!pastille) return;

    rendreVisible(pastille, '.col-dot');
    activerSeul(pastille, '.col-dot', pastille.parentElement);
    refleterSelection();
}

// Clic sur la fiche
document.addEventListener('click', (e) => {
    const tissu = e.target.closest('.mat-opt');
    if (tissu) { choisirTissu(tissu); return; }

    const pastille = e.target.closest('.col-dot');
    if (pastille) choisirColoris(pastille);
});


// ===== Panneaux « +N » =====

// Le « +N » ouvre son panneau s'il en a un, sinon déplie la rangée sur place
document.addEventListener('click', (e) => {
    const plus = e.target.closest('.swatch-more');
    if (!plus) return;

    const panneau = plus.dataset.panneau && document.getElementById(plus.dataset.panneau);
    if (panneau) panneau.showModal();
    else plus.closest('.swatch-group')?.classList.add('is-expanded');
});

// Fermeture : la croix, le bouton Valider, ou le fond sombre
document.addEventListener('click', (e) => {
    const panneau = e.target.closest('.nuancier-panneau');
    if (panneau && (e.target === panneau || e.target.closest('.panneau-close, .btn-valider'))) {
        panneau.close();
    }
});

// Panneau coloris : on reste dans le panneau pour comparer, la fiche suit
document.addEventListener('click', (e) => {
    const option = e.target.closest('.panneau-opt');
    if (!option) return;

    const panneau = option.closest('.nuancier-panneau');
    activerSeul(option, '.panneau-opt', panneau);
    panneau.querySelector('.panneau-courant strong').textContent = option.dataset.color;

    const groupe = panneau.closest('.nuancier-group');
    choisirColoris(groupe?.querySelector('.col-dot[data-index="' + option.dataset.index + '"]'));
});

// Panneau tissus : on applique et on ferme
document.addEventListener('click', (e) => {
    const ligne = e.target.closest('.panneau-ligne');
    if (!ligne) return;

    choisirTissu(document.querySelector('.mat-opt[data-fabric-id="' + ligne.dataset.fabricId + '"]'));
    ligne.closest('.nuancier-panneau').close();
});


// ===== Devis sur-mesure =====
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-quote');
    if (!btn) return;

    const dimensions = btn.closest('.custom-quote').querySelector('.quote-dims')?.value.trim();
    let message = 'Bonjour, je souhaite un devis sur-mesure pour le ' + btn.dataset.product + '.';
    if (dimensions) message += ' Dimensions souhaitées : ' + dimensions;

    window.open(lienWhatsApp(btn.dataset.wa, message), '_blank');
});


// ===== Bandeau cookies =====
// Google Analytics ne se charge qu'après « Accepter » : en prod, le bandeau porte son identifiant.
// Le choix est gardé six mois avec sa date, puis le bandeau revient.
const SIX_MOIS = 182 * 24 * 60 * 60 * 1000;

// Choix encore valable, sinon null. localStorage peut lever une exception en navigation privée
// stricte, et un choix enregistré sans date ne se lit pas : dans les deux cas, on redemande.
function choixCookies() {
    try {
        const { choix, date } = JSON.parse(localStorage.getItem('cookie-consent'));
        return Date.now() - date < SIX_MOIS ? choix : null;
    } catch (err) { return null; }
}

function chargerAnalytics(id) {
    if (window.gtag) return;   // déjà chargé dans la page
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);

    const script = document.createElement('script');
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
    document.head.append(script);
}

// Analytics pose ses cookies sur le domaine principal, sans « www. »
function effacerCookiesAnalytics() {
    const domaine = location.hostname.replace(/^www\./, '');
    document.cookie.split('; ')
        .map(cookie => cookie.split('=')[0])
        .filter(nom => nom.startsWith('_ga'))
        .forEach(nom => { document.cookie = nom + '=; max-age=0; path=/; domain=' + domaine; });
}

// Bandeau affiché tant qu'il n'y a pas de choix. Analytics seulement sur « accept » : sinon,
// on le coupe s'il tourne déjà dans la page (retrait de l'accord) et on efface ses cookies.
function appliquerChoix(choix) {
    const bandeau = document.getElementById('cookie-banner');
    bandeau?.classList.toggle('show', !choix);

    const id = bandeau?.dataset.ga;
    if (!id) return;   // hors prod : jamais d'Analytics
    window['ga-disable-' + id] = choix !== 'accept';
    if (choix === 'accept') chargerAnalytics(id);
    else effacerCookiesAnalytics();
}

appliquerChoix(choixCookies());

document.addEventListener('click', (e) => {
    const choix = e.target.closest('[data-cookie]')?.dataset.cookie;
    if (!choix) return;
    try { localStorage.setItem('cookie-consent', JSON.stringify({ choix, date: Date.now() })); } catch (err) { /* ignoré */ }
    appliquerChoix(choix);
});

// « Gérer les cookies », dans le pied de page, rouvre le bandeau
document.addEventListener('click', (e) => {
    if (e.target.closest('.cookie-manage')) document.getElementById('cookie-banner')?.classList.add('show');
});



// Au chargement : les libellés et le lien WhatsApp reflètent la pré-sélection
refleterSelection();