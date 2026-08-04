import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// ===== Menu burger mobile (délégation d'événement) =====
document.addEventListener('click', (e) => {
    // clic sur le burger (ou un de ses <span>)
    if (e.target.closest('.burger')) {
        const nav = document.querySelector('.nav');
        const burger = document.querySelector('.burger');
        const isOpen = nav.classList.toggle('open');
        burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        return;
    }
    // clic sur un lien du menu → on ferme
    if (e.target.closest('.nav a')) {
        document.querySelector('.nav')?.classList.remove('open');
    }
});


// Galerie fiche produit : clic sur une miniature → défile le carrousel
document.addEventListener('click', (e) => {
    const thumb = e.target.closest('.gal-thumb');
    if (!thumb) return;

    const gallery = thumb.closest('.gallery');
    const viewport = gallery.querySelector('.gal-viewport');
    const index = Number(thumb.dataset.index);

    // fait défiler le carrousel jusqu'à la photo choisie
    viewport.scrollTo({ left: viewport.children[index].offsetLeft, behavior: 'smooth' });

    // met à jour la miniature active
    gallery.querySelectorAll('.gal-thumb').forEach(t => t.classList.remove('is-active'));
    thumb.classList.add('is-active');
});


// Galerie fiche produit : flèches précédent / suivant
document.addEventListener('click', (e) => {
    const arrow = e.target.closest('.gal-prev, .gal-next');
    if (!arrow) return;

    const viewport = arrow.closest('.gallery').querySelector('.gal-viewport');
    const step = arrow.classList.contains('gal-next') ? viewport.clientWidth : -viewport.clientWidth;
    viewport.scrollBy({ left: step, behavior: 'smooth' });
});


// Galerie : garder la miniature active synchronisée avec la photo visible
document.querySelectorAll('.gal-viewport').forEach((viewport) => {
    viewport.addEventListener('scroll', () => {
        const index = Math.round(viewport.scrollLeft / viewport.clientWidth);
        const gallery = viewport.closest('.gallery');
        gallery.querySelectorAll('.gal-thumb').forEach((thumb, i) => {
            thumb.classList.toggle('is-active', i === index);
        });
    });
});


// Configurateur : sélection tissu/couleur + mise à jour du devis WhatsApp
function updateDevis() {
    const btn = document.querySelector('.btn-devis');
    if (!btn) return;

    const fabric = document.querySelector('.mat-opt.is-active')?.dataset.fabric;
    const color  = document.querySelector('.col-dot.is-active')?.dataset.color;

    const fLabel = document.querySelector('.cfg-choice[data-choice="fabric"]');
    const cLabel = document.querySelector('.cfg-choice[data-choice="color"]');
    if (fLabel && fabric) fLabel.textContent = fabric;
    if (cLabel && color)  cLabel.textContent = color;

    let msg = 'Bonjour, je suis intéressée par le ' + btn.dataset.product;
    if (fabric) msg += ', tissu ' + fabric;
    if (color)  msg += ', coloris ' + color;
    msg += '. Pouvez-vous me faire un devis ?';

    btn.href = 'https://wa.me/' + btn.dataset.wa + '?text=' + encodeURIComponent(msg);
}

// Clic sur un tissu ou une couleur → sélection
document.addEventListener('click', (e) => {
    const opt = e.target.closest('.mat-opt, .col-dot');
    if (!opt) return;
    // un seul actif par groupe (les frères sont dans le même .mat-row / .col-row)
    opt.parentElement.querySelectorAll('.is-active').forEach(o => o.classList.remove('is-active'));
    opt.classList.add('is-active');
    updateDevis();
});

updateDevis();   // au chargement : reflète la pré-sélection par défaut


// Bandeau cookies : afficher si aucun choix mémorisé
const cookieBanner = document.getElementById('cookie-banner');
if (cookieBanner && !localStorage.getItem('cookie-consent')) {
    cookieBanner.classList.add('show');
}
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cookie]');
    if (!btn) return;
    localStorage.setItem('cookie-consent', btn.dataset.cookie);   // mémorise le choix
    document.getElementById('cookie-banner')?.classList.remove('show');
});

// Variantes : changer de produit via le menu déroulant
document.addEventListener('change', (e) => {
    const sel = e.target.closest('.variant-select');
    if (sel) window.location.href = sel.value;
});

// Devis sur-mesure : envoie les dimensions saisies vers WhatsApp
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-quote');
    if (!btn) return;
    const dims = btn.closest('.custom-quote').querySelector('.quote-dims')?.value.trim();
    let msg = 'Bonjour, je souhaite un devis sur-mesure pour le ' + btn.dataset.product + '.';
    if (dims) msg += ' Dimensions souhaitées : ' + dims;
    window.open('https://wa.me/' + btn.dataset.wa + '?text=' + encodeURIComponent(msg), '_blank');
});


// Swatches : révéler les options supplémentaires (+N)
document.addEventListener('click', (e) => {
    const more = e.target.closest('.swatch-more');
    if (more) more.closest('.swatch-group').classList.add('is-expanded');
});