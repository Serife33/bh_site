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


// ===== Galerie fiche produit =====
document.querySelectorAll('.gallery').forEach((gallery) => {
    const viewport = gallery.querySelector('.gal-viewport');
    if (!viewport) return;

    const slides = [...viewport.children];
    const thumbs = [...gallery.querySelectorAll('.gal-thumb')];
    const bande  = gallery.querySelector('.gal-thumbs');
    if (!slides.length) return;

    // Photo actuellement affichée : celle dont le bord gauche est
    // le plus proche du bord du cadre. On lit les positions réelles,
    // donc le gap et les arrondis ne faussent plus rien.
    const indexCourant = () => {
        let best = 0, ecart = Infinity;
        slides.forEach((slide, i) => {
            const d = Math.abs(slide.offsetLeft - viewport.scrollLeft);
            if (d < ecart) { ecart = d; best = i; }
        });
        return best;
    };

    // Met le contour sur la bonne miniature et la ramène dans le champ
    const marquer = (i) => {
        thumbs.forEach((t, k) => t.classList.toggle('is-active', k === i));
        if (bande && thumbs[i]) {
            const t = thumbs[i];
            bande.scrollTo({
                left: t.offsetLeft - (bande.clientWidth - t.clientWidth) / 2,
                behavior: 'smooth',
            });
        }
    };

    // Le modulo fait la boucle : après la dernière photo on revient à la première
    const allerA = (i) => {
        const n = slides.length;
        const cible = ((i % n) + n) % n;
        viewport.scrollTo({ left: slides[cible].offsetLeft, behavior: 'smooth' });
        marquer(cible);
    };

    thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => allerA(i)));

    gallery.querySelector('.gal-prev')?.addEventListener('click', () => allerA(indexCourant() - 1));
    gallery.querySelector('.gal-next')?.addEventListener('click', () => allerA(indexCourant() + 1));

    // Défilement au doigt ou à la molette : on resynchronise une fois posé
    let attente;
    viewport.addEventListener('scroll', () => {
        clearTimeout(attente);
        attente = setTimeout(() => marquer(indexCourant()), 150);
    });

    marquer(0);
});


// Configurateur : sélection tissu/couleur + mise à jour du devis WhatsApp
function updateDevis() {
    const btn = document.querySelector('.btn-devis');
    if (!btn) return;

    const fabric = document.querySelector('.mat-opt.is-active')?.dataset.fabric;
    const color = (document.querySelector('.nuancier-group.is-visible .col-dot.is-active')
                || document.querySelector('.swatch-group .col-dot.is-active'))?.dataset.color;

    const fLabel = document.querySelector('.cfg-choice[data-choice="fabric"]');
    const cLabel = document.querySelector('.nuancier-group.is-visible .cfg-choice[data-choice="color"]')
                || document.querySelector('.swatch-group .cfg-choice[data-choice="color"]');
    if (fLabel && fabric) fLabel.textContent = fabric;
    if (cLabel && color)  cLabel.textContent = color;

    let msg = 'Bonjour, je suis intéressée par le ' + btn.dataset.product;
    if (fabric) msg += ', tissu ' + fabric;
    if (color)  msg += ', coloris ' + color;
    msg += '. Je souhaite le commander.';

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


// Swatches : le « +N » ouvre son panneau s'il en a un, sinon déplie sur place
document.addEventListener('click', (e) => {
    const more = e.target.closest('.swatch-more');
    if (!more) return;
    const panneau = more.dataset.panneau ? document.getElementById(more.dataset.panneau) : null;
    if (panneau) panneau.showModal();
    else more.closest('.swatch-group')?.classList.add('is-expanded');
});

// Panneau nuancier : choisir un coloris, puis fermer
document.addEventListener('click', (e) => {
    const panneau = e.target.closest('.nuancier-panneau');
    if (!panneau) return;

    // croix, bouton Valider, ou clic sur le fond sombre
    if (e.target.closest('.panneau-close, .btn-valider') || e.target === panneau) {
        panneau.close();
        return;
    }

    const opt = e.target.closest('.panneau-opt');
    if (!opt) return;

    panneau.querySelectorAll('.panneau-opt.is-active').forEach(o => o.classList.remove('is-active'));
    opt.classList.add('is-active');
    panneau.querySelector('.panneau-courant strong').textContent = opt.dataset.color;

    // on reporte le choix sur la ligne d'aperçu de la fiche
    const groupe = panneau.closest('.nuancier-group');
    const dot = groupe?.querySelector('.col-dot[data-index="' + opt.dataset.index + '"]');
    if (dot) {
        groupe.querySelectorAll('.col-dot.is-active').forEach(d => d.classList.remove('is-active'));
        groupe.querySelectorAll('.col-dot.swatch-shown').forEach(d => d.classList.remove('swatch-shown'));
        dot.classList.add('is-active');
        if (dot.classList.contains('swatch-extra')) dot.classList.add('swatch-shown');
    }
    updateDevis();
});

// Panneau tissus : choisir un tissu, puis fermer
document.addEventListener('click', (e) => {
    const ligne = e.target.closest('.panneau-ligne');
    if (!ligne) return;

    const bouton = document.querySelector('.mat-opt[data-fabric-id="' + ligne.dataset.fabricId + '"]');
    if (bouton) {
        // si ce tissu fait partie des masqués, on le rend visible sur la fiche
        if (bouton.classList.contains('swatch-extra')) {
            document.querySelectorAll('.mat-opt.swatch-shown').forEach(b => b.classList.remove('swatch-shown'));
            bouton.classList.add('swatch-shown');
        }
        bouton.click();   // réutilise la logique existante : nuancier, libellé, message WhatsApp
    }

    ligne.closest('.nuancier-panneau')?.close();
});


// ===== NUANCIER : ajouter / retirer une couleur dans le formulaire tissu =====
const nuancier = document.getElementById('nuancier');

if (nuancier) {
    // Ajouter une ligne : on clone le gabarit fourni par Symfony
    document.getElementById('ajouter-couleur').addEventListener('click', () => {
        const index = parseInt(nuancier.dataset.index, 10);
        const ligne = document.createElement('div');
        ligne.className = 'nuancier-ligne';
        // __name__ est le marqueur laissé par Symfony : on le remplace par le numéro de ligne
        ligne.innerHTML = nuancier.dataset.prototype.replace(/__name__/g, index)
            + '<button type="button" class="btn-supprimer-ligne">Retirer</button>';
        nuancier.appendChild(ligne);
        nuancier.dataset.index = index + 1;
    });

    // Retirer une ligne : délégation d'événement (fonctionne aussi sur les lignes ajoutées)
    nuancier.addEventListener('click', (e) => {
        const bouton = e.target.closest('.btn-supprimer-ligne');
        if (bouton) bouton.closest('.nuancier-ligne').remove();
    });
}


// ===== CONFIGURATEUR : clic sur un tissu → afficher SON nuancier =====
document.addEventListener('click', (e) => {
    const opt = e.target.closest('.mat-opt');
    if (!opt) return;

    const id = opt.dataset.fabricId;
    document.querySelectorAll('[data-nuancier]').forEach((groupe) => {
        groupe.classList.toggle('is-visible', groupe.dataset.nuancier === id);
    });

    updateDevis();   // le message WhatsApp doit refléter le nouveau coloris
});
