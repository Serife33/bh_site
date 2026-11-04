import './stimulus_bootstrap.js';
import './styles/app.css';

/* -------------------------------------------------------------------------
   Tout est écrit en délégation : on écoute sur `document`, jamais sur les
   éléments eux-mêmes. Le code reste valable même si le contenu de la page
   est remplacé après le chargement.
   ------------------------------------------------------------------------- */


// ===== Outils communs =====

// Rend `element` seul choisi parmi les éléments `selecteur` de `racine` :
// la classe `is-active` pour l'œil, aria-pressed pour les lecteurs d'écran.
function activerSeul(element, selecteur, racine = document) {
    racine.querySelectorAll(selecteur).forEach((e) => {
        e.classList.toggle('is-active', e === element);
        e.setAttribute('aria-pressed', e === element);
    });
}

// Une option choisie derrière un « +N » doit rester visible sur la fiche.
// Une seule à la fois dans sa rangée, et seulement si elle fait partie des masquées.
function rendreVisible(element, selecteur) {
    element.parentElement.querySelectorAll(selecteur + '.swatch-shown').forEach(e => e.classList.remove('swatch-shown'));
    if (element.classList.contains('swatch-extra')) element.classList.add('swatch-shown');
}

function lienWhatsApp(numero, message) {
    return 'https://wa.me/' + numero + '?text=' + encodeURIComponent(message);
}


// ===== Menu mobile =====
// Plein écran, il cache la page : tant qu'il est ouvert, la page dessous est inerte (ni focus ni clic).
function basculerMenu(ouvrir) {
    document.querySelector('.nav')?.classList.toggle('open', ouvrir);
    document.querySelector('.burger')?.setAttribute('aria-expanded', ouvrir);
    document.querySelectorAll('.main, .ft').forEach(e => e.inert = ouvrir);
}

document.addEventListener('click', (e) => {
    if (e.target.closest('.burger')) {
        const ouvrir = !document.querySelector('.nav').classList.contains('open');
        basculerMenu(ouvrir);
        if (ouvrir) document.querySelector('.nav a').focus();   // le clavier part du premier lien du menu
        return;
    }
    if (e.target.closest('.nav a')) basculerMenu(false);
});

// Échap ferme le menu et rend le focus au burger
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.querySelector('.nav.open')) {
        basculerMenu(false);
        document.querySelector('.burger').focus();
    }
});

// Passage en largeur bureau (tablette tournée) : le menu redevient la barre, la page ne doit pas rester inerte
matchMedia('(min-width: 68.75em)').addEventListener('change', () => basculerMenu(false));   // même seuil que le CSS du menu


// ===== Bandeau défilant : bouton pause (critère 2.2.2), état gardé le temps de la session =====
function pauseBandeau(pause) {
    const zone = document.querySelector('.band-zone');
    if (!zone) return;
    zone.classList.toggle('is-paused', pause);
    zone.querySelector('.band-pause').setAttribute('aria-label', pause ? 'Relancer le bandeau' : 'Mettre en pause le bandeau');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.band-pause')) return;
    const pause = !document.querySelector('.band-zone').classList.contains('is-paused');
    pauseBandeau(pause);
    try { sessionStorage.setItem('bandeau-pause', pause); } catch (err) { /* ignoré : la pause vaut pour cette page */ }
});

// Chaque page affichée par Turbo reprend l'état choisi
document.addEventListener('turbo:load', () => {
    try { pauseBandeau(sessionStorage.getItem('bandeau-pause') === 'true'); } catch (err) { /* ignoré */ }
});


// ===== Bande « Nos univers » : une flèche avance d'une carte, l'accroche (scroll-snap) cale la position =====
document.addEventListener('click', (e) => {
    const fleche = e.target.closest('.fleche');
    if (!fleche) return;
    const bande = document.getElementById(fleche.getAttribute('aria-controls'));
    bande.scrollBy({ left: fleche.dataset.sens * bande.firstElementChild.offsetWidth });
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

// Souligne la miniature correspondante et la ramène dans le champ de vision.
// Seule la photo affichée se prend au clavier : un seul arrêt de Tab, jamais hors du cadre.
function marquerMiniature(gallery, index) {
    const photo = gallery.querySelector('.gal-viewport').children[index];
    gallery.querySelectorAll('.gal-photo').forEach(p => p.tabIndex = p === photo ? 0 : -1);

    const active = [...gallery.querySelectorAll('.gal-thumb')][index];
    if (!active) return;

    activerSeul(active, '.gal-thumb', gallery);

    const bande = gallery.querySelector('.gal-thumbs');
    if (bande) {
        bande.scrollTo({ left: active.offsetLeft - (bande.clientWidth - active.clientWidth) / 2 });
    }
}

// Affiche une photo, en bouclant aux extrémités. Elle glisse comme le veut le CSS
// (scroll-behavior), sauf si on la veut tout de suite.
function afficherPhoto(gallery, voulu, instantane) {
    const viewport = gallery.querySelector('.gal-viewport');
    const photos = [...viewport.children];
    const index = ((voulu % photos.length) + photos.length) % photos.length;

    viewport.scrollTo({ left: photos[index].offsetLeft, behavior: instantane ? 'instant' : 'auto' });
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

// ===== Visionneuse plein écran =====
// Le <dialog> est lui-même une .gallery : flèches, glissé du doigt et défilement viennent de la
// galerie, ci-dessus. Échap et le piège du focus sont natifs.

// Double la photo, ou lui rend sa taille. Sa largeur affichée dépend de l'écran :
// on la lit avant que la classe ne lève ses bornes.
function basculerZoom(photo) {
    const largeur = 2 * photo.offsetWidth;
    photo.style.width = photo.classList.toggle('is-zoomed') ? largeur + 'px' : '';
}

// Clic sur une photo de la galerie : la visionneuse s'ouvre sur elle, d'un coup, photos à leur taille
document.addEventListener('click', (e) => {
    const photo = e.target.closest('.gal-photo');
    if (!photo) return;

    const visionneuse = document.querySelector('.visionneuse');
    visionneuse.querySelectorAll('.is-zoomed').forEach(basculerZoom);
    visionneuse.showModal();
    afficherPhoto(visionneuse, [...document.querySelectorAll('.gal-photo')].indexOf(photo), true);
});

// Dans la visionneuse, les flèches mises à part : un clic sur la photo la double ou la remet à
// sa taille (sur ordinateur ; au doigt, c'est le pincement natif), un clic ailleurs ferme.
document.addEventListener('click', (e) => {
    const visionneuse = e.target.closest('.visionneuse');
    if (!visionneuse || e.target.closest('.gal-arrow')) return;

    const photo = e.target.closest('.vis-cadre img');
    if (!photo) { visionneuse.close(); return; }
    if (!matchMedia('(pointer: fine)').matches) return;

    // Le point cliqué reste sous les yeux : sa position double avec la photo
    basculerZoom(photo);
    const cadre = photo.parentElement;
    cadre.scrollTo(2 * e.offsetX - cadre.clientWidth / 2, 2 * e.offsetY - cadre.clientHeight / 2);
});

// Touches ← →, visionneuse ouverte : elles actionnent ses flèches
document.addEventListener('keydown', (e) => {
    const fleche = { ArrowLeft: '.gal-prev', ArrowRight: '.gal-next' }[e.key];
    const bouton = fleche && document.querySelector('.visionneuse[open] ' + fleche);
    if (!bouton) return;

    e.preventDefault();   // sinon le cadre défile en plus, de quelques pixels
    bouton.click();
});

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

// ===== Configurateur : taille, matière, finition, tissu, coloris, message WhatsApp =====

// Les variantes écrites sur la fiche (aucune hors d'une fiche à variantes). Prix déjà au format du site.
function variantes() {
    return JSON.parse(document.querySelector('[data-variantes]')?.dataset.variantes ?? '[]');
}

// Prix, prix barré, remise et disponibilité de la variante choisie.
// Une taille ou une matière que le produit ne propose pas vaut null, comme dans les données.
function afficherVariante(taille, matiere) {
    const variante = variantes().find(v => v.taille === taille && v.matiere === matiere);
    if (!variante) return;

    document.querySelector('.price').textContent = variante.prix;
    document.querySelector('.price-old').textContent = variante.prixBarre;
    document.querySelector('.price-remise').textContent = '-' + variante.remise + '\u00a0%';
    document.querySelectorAll('.price-old, .price-remise').forEach(e => { e.hidden = !variante.remise; });
    document.querySelector('.dispo-stock').hidden = !variante.enStock;
    document.querySelector('.dispo-commande').hidden = variante.enStock;
}

// Reflète la sélection courante dans les libellés, le prix et le lien WhatsApp
function refleterSelection() {
    const btn = document.querySelector('.btn-devis');
    if (!btn) return;

    // Les options choisies, dans l'ordre du message ; chacune porte sa valeur dans data-choix.
    // Une matière masquée n'existe pas dans la taille choisie ; finition et coloris sont ceux du nuancier visible.
    const choix = {
        taille: document.querySelector('.taille-opt.is-active'),
        matière: document.querySelector('.matiere-opt.is-active:not([hidden])'),
        finition: document.querySelector('[data-finitions].is-visible .col-dot.is-active'),
        tissu: document.querySelector('.mat-opt.is-active'),
        coloris: document.querySelector('.nuancier-group.is-visible:not([data-finitions]) .col-dot.is-active'),
    };

    let message = 'Bonjour, je suis intéressée par le ' + btn.dataset.product;
    for (const [nom, option] of Object.entries(choix)) {
        if (!option) continue;
        option.closest('.cfg-group').querySelector('.cfg-choice').textContent = option.dataset.choix;
        // Les tailles sont en centimètres, comme l'annonce le libellé « Taille (cm) »
        message += ', ' + nom + ' ' + option.dataset.choix + (nom === 'taille' ? ' cm' : '');
    }
    message += '. Je souhaite le commander.';

    btn.href = lienWhatsApp(btn.dataset.wa, message);
    afficherVariante(choix.taille?.dataset.choix ?? null, choix.matière?.dataset.choix ?? null);
}

// Applique une taille : ne restent que les matières proposées dans cette taille.
// Si la matière choisie n'en fait pas partie, on passe à la première qui reste.
function choisirTaille(bouton) {
    activerSeul(bouton, '.taille-opt', bouton.parentElement);

    const liste = variantes();
    document.querySelectorAll('.matiere-opt').forEach((matiere) => {
        matiere.hidden = !liste.some(v => v.taille === bouton.dataset.choix && v.matiere === matiere.dataset.choix);
    });
    const premiere = document.querySelector('.matiere-opt:not([hidden])');
    if (premiere && !document.querySelector('.matiere-opt.is-active:not([hidden])')) choisirMatiere(premiere);
    else refleterSelection();
}

// Applique une matière : seul son nuancier de finitions reste visible, sur la finition déjà choisie
// (la première au départ), comme les coloris d'un tissu. Une matière sans finition n'en a pas : l'étape disparaît.
function choisirMatiere(bouton) {
    activerSeul(bouton, '.matiere-opt', bouton.parentElement);

    document.querySelectorAll('[data-finitions]').forEach((groupe) => {
        groupe.classList.toggle('is-visible', groupe.dataset.finitions === bouton.dataset.choix);
    });

    refleterSelection();
}

// Applique un tissu : bouton actif, nuancier correspondant affiché, libellés à jour
function choisirTissu(bouton) {
    if (!bouton) return;

    rendreVisible(bouton, '.mat-opt');
    activerSeul(bouton, '.mat-opt', bouton.parentElement);
    const ligne = document.querySelector('.panneau-ligne[data-fabric-id="' + bouton.dataset.fabricId + '"]');
    if (ligne) activerSeul(ligne, '.panneau-ligne', ligne.parentElement);   // le panneau des tissus coche le même

    document.querySelectorAll('[data-nuancier]').forEach((groupe) => {
        groupe.classList.toggle('is-visible', groupe.dataset.nuancier === bouton.dataset.fabricId);
    });

    refleterSelection();
}

// Applique un coloris ou une finition. Le panneau du nuancier, s'il y en a un, coche la même teinte.
function choisirColoris(pastille) {
    if (!pastille) return;

    rendreVisible(pastille, '.col-dot');
    activerSeul(pastille, '.col-dot', pastille.parentElement);

    const groupe = pastille.closest('.nuancier-group');
    const option = groupe.querySelector('.panneau-opt[data-index="' + pastille.dataset.index + '"]');
    if (option) {
        activerSeul(option, '.panneau-opt', groupe);
        groupe.querySelector('.panneau-courant strong').textContent = option.dataset.color;
    }

    refleterSelection();
}

// Clic sur la fiche
document.addEventListener('click', (e) => {
    const taille = e.target.closest('.taille-opt');
    if (taille) { choisirTaille(taille); return; }

    const matiere = e.target.closest('.matiere-opt');
    if (matiere) { choisirMatiere(matiere); return; }

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

// Panneau coloris ou finitions : on reste dans le panneau pour comparer, la fiche suit
document.addEventListener('click', (e) => {
    const option = e.target.closest('.panneau-opt');
    if (!option) return;

    choisirColoris(option.closest('.nuancier-group').querySelector('.col-dot[data-index="' + option.dataset.index + '"]'));
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


// ===== Composition d'un canapé modulable =====
// Les quantités sont écrites dans la page : chaque fiche affichée part des modules de sa photo.
const MODULES_MAX = 9;

// Même format que le filtre Twig `prix` : « 2 847 € », espaces insécables, sans décimales
function formaterPrix(montant) {
    return Math.round(montant).toString().replace(/\B(?=(\d{3})+$)/g, ' ') + ' €';
}

// Le récapitulatif (modules choisis et total) et l'état des boutons − et +, d'après les quantités
function afficherComposition(composition) {
    let total = 0;
    const lignes = [];
    composition.querySelectorAll('.compo-module').forEach((module) => {
        const nombre = +module.querySelector('.compo-nb').textContent;
        module.querySelector('.compo-moins').setAttribute('aria-disabled', nombre === 0);
        module.querySelector('.compo-plus').setAttribute('aria-disabled', nombre === MODULES_MAX);
        if (!nombre) return;

        total += nombre * module.dataset.prix;
        lignes.push(Object.assign(document.createElement('li'), { textContent: nombre + ' × ' + module.dataset.nom }));
    });

    composition.querySelector('.compo-liste').replaceChildren(...lignes);
    composition.querySelector('.compo-total').textContent = formaterPrix(total);
    composition.querySelector('.compo-commander').disabled = !lignes.length;
}

// − et + : une quantité entre 0 et MODULES_MAX
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('.compo-moins, .compo-plus');
    if (!bouton) return;

    const nb = bouton.parentElement.querySelector('.compo-nb');
    nb.textContent = Math.min(MODULES_MAX, Math.max(0, +nb.textContent + (bouton.classList.contains('compo-plus') ? 1 : -1)));
    afficherComposition(bouton.closest('.composition'));
});

// Le message reprend le récapitulatif affiché
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.compo-commander');
    if (!btn) return;

    const composition = btn.closest('.composition');
    const lignes = [...composition.querySelectorAll('.compo-liste li')].map(li => '\n- ' + li.textContent);
    const message = 'Bonjour, je souhaite commander cette composition ' + composition.dataset.gamme + ' :'
        + lignes.join('') + '\nTotal : ' + composition.querySelector('.compo-total').textContent;

    window.open(lienWhatsApp(btn.dataset.wa, message), '_blank');
});

// Le récapitulatif de la composition de départ
document.addEventListener('turbo:load', () => document.querySelectorAll('.composition').forEach(afficherComposition));

// Turbo garde une copie de la page pour le retour arrière : on la remet comme sur la photo
document.addEventListener('turbo:before-cache', () => {
    const composition = document.querySelector('.composition');
    if (!composition) return;

    composition.querySelectorAll('.compo-nb').forEach((nb) => { nb.textContent = nb.dataset.initial; });
    afficherComposition(composition);
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
    window.gtag('config', id, { cookie_expires: 13 * 30 * 24 * 60 * 60, cookie_update: false });   // 13 mois au plus, sans prolongation, comme l'annonce la politique

    const script = document.createElement('script');
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
    document.head.append(script);
}

// Analytics pose ses cookies sur le domaine principal (brillancehome.fr), quel que soit le sous-domaine
function effacerCookiesAnalytics() {
    const domaine = location.hostname.split('.').slice(-2).join('.');
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

// « Gérer les cookies », dans le pied de page, rouvre le bandeau et y amène le focus (clavier, lecteur d'écran)
document.addEventListener('click', (e) => {
    if (!e.target.closest('.cookie-manage')) return;
    const bandeau = document.getElementById('cookie-banner');
    bandeau?.classList.add('show');
    bandeau?.querySelector('[data-cookie]')?.focus();
});


// Les libellés, le prix et le lien WhatsApp reflètent la pré-sélection : tout de suite au premier
// affichage (Turbo n'annonce le sien qu'une fois les images chargées), puis à chaque page affichée
// par Turbo. Le second passage du premier affichage refait le même calcul, sans effet.
refleterSelection();
document.addEventListener('turbo:load', refleterSelection);