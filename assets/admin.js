import './stimulus_bootstrap.js';
import Sortable from 'sortablejs';
import './devis.js';   // l'écran d'un devis

// ===== Collections (nuancier d'un tissu, variantes d'un produit) : ajouter ou retirer une ligne =====
// Le modèle de ligne, bouton Retirer compris, vient de templates/admin/_collection.html.twig.
document.addEventListener('click', (e) => {
    const ajouter = e.target.closest('.btn-ajouter-ligne');

    if (ajouter) {
        const collection = ajouter.closest('.collection');
        const index = parseInt(collection.dataset.index, 10);
        // __name__ est le marqueur laissé par Symfony : on le remplace par le numéro de ligne
        ajouter.insertAdjacentHTML('beforebegin', collection.dataset.prototype.replace(/__name__/g, index));
        ajouter.previousElementSibling.querySelector('input, select').focus();   // on remplit la ligne sans remonter au clavier
        collection.dataset.index = index + 1;
        return;
    }

    const retirer = e.target.closest('.btn-supprimer-ligne');
    if (retirer) {
        // Le bouton cliqué disparaît avec sa ligne : le focus passe au bouton d'ajout au lieu de se perdre
        retirer.closest('.collection').querySelector('.btn-ajouter-ligne').focus();
        retirer.closest('.collection-ligne').remove();
    }
});


// ===== Page « Ordre d'affichage » : ranger les produits à la souris =====

// Le menu déroulant des catégories recharge la page tout seul.
document.addEventListener('change', (e) => {
    if (e.target.id === 'choix-categorie') {
        e.target.form.requestSubmit();
    }
});

// ===== Barre de filtres de la liste Produits : les menus rechargent la page =====
document.addEventListener('change', (e) => {
    const champ = e.target.closest('.filtre-auto');
    if (!champ) return;

    // Changer de catégorie vide la sous-catégorie : l'ancienne n'existe sans doute
    // pas dans la nouvelle, et le navigateur enverrait quand même sa valeur.
    if (champ.id === 'filtre-categorie') {
        const sousCategorie = document.getElementById('filtre-sous-categorie');
        if (sousCategorie) sousCategorie.value = '';
    }

    champ.form.requestSubmit();
});


// « Vignette catalogue » sur la 1re photo, « Au survol » sur la 2e.
// Twig les a posées au rendu : après un glisser, c'est à nous de les déplacer,
// sinon elles restent en place jusqu'au rechargement de la page.
function moveLabels(items) {
    const labels = [
        { className: 'photo-principale', text: 'Vignette catalogue' },
        { className: 'photo-survol', text: 'Au survol' },
    ];

    // On retire les anciennes, où qu'elles soient.
    items.forEach((item) => {
        labels.forEach(({ className }) => item.querySelector('.' + className)?.remove());
    });

    // On les repose sur les deux premières cartes.
    labels.forEach(({ className, text }, i) => {
        const infos = items[i]?.querySelector('.photo-infos');
        if (!infos) return;   // page « Ordre d'affichage » : rien à poser

        const label = document.createElement('span');
        label.className = 'photo-etiquette ' + className;
        label.textContent = text;
        infos.prepend(label);
    });
}

// annonce : ce que lit le lecteur d'écran avant « Ordre enregistré. » (la place de la ligne déplacée par un bouton)
async function enregistrerOrdre(liste, annonce = '') {
    const lignes = [...liste.querySelectorAll('li')];
    const message = document.getElementById('ordre-message');

    // Renumérotation à l'écran : 1, 2, 3…
    lignes.forEach((ligne, i) => {
        const rang = ligne.querySelector('.ordre-rang');
        if (rang) rang.textContent = i + 1;
    });

    moveLabels(lignes);

    if (message) message.textContent = 'Enregistrement…';

    try {
        const reponse = await fetch(liste.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ids: lignes.map((ligne) => ligne.dataset.id),
                _token: liste.dataset.token,
            }),
        });

        const donnees = await reponse.json();

        if (message) {
            message.textContent = donnees.ok
                ? `${annonce}Ordre enregistré.`
                : (donnees.message ?? "L'enregistrement a échoué.");
        }
    } catch (erreur) {
        if (message) message.textContent = "L'enregistrement a échoué : rechargez la page.";
    }
}

// Deux listes glissables dans le back-office : l'ordre des produits et la grille de photos.
// Même code pour les deux : chacune porte son adresse d'enregistrement et son jeton.
function activerGlisser() {
    document.querySelectorAll('#liste-ordre, #grille-photos').forEach((liste) => {
        if (Sortable.get(liste)) return;   // déjà branchée

        Sortable.create(liste, {
            animation: 150,
            handle: '.ordre-poignee',   // on ne tire que la poignée
            ghostClass: 'ordre-fantome',
            onEnd: () => enregistrerOrdre(liste),
        });
    });
}

// Monter / Descendre : le même rangement sans glisser-déposer, pour le clavier et le doigt (WCAG 2.5.7).
// En tête ou en bas, la ligne ne bouge pas, et l'annonce redit sa place.
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('.ordre-monter, .ordre-descendre');
    if (!bouton) return;

    const ligne = bouton.closest('.ordre-ligne');
    const liste = ligne.parentElement;
    if (bouton.matches('.ordre-monter')) ligne.previousElementSibling?.before(ligne);
    else ligne.nextElementSibling?.after(ligne);
    bouton.focus();   // déplacer la ligne a retiré le focus au bouton

    const place = [...liste.children].indexOf(ligne) + 1;
    enregistrerOrdre(liste, `${ligne.querySelector('.ordre-nom').textContent} : position ${place} sur ${liste.children.length}. `);
});

// Ce que chaque page doit brancher : les listes glissables et les champs de recherche des pastilles.
function brancherPage() {
    activerGlisser();
    ajouterRecherches();
}

// Turbo remplace le <body> sans relancer le script : on rebranche à chaque navigation.
document.addEventListener('turbo:load', brancherPage);
// Filet de sécurité si Turbo est coupé un jour.
document.addEventListener('DOMContentLoaded', brancherPage);


// ===== Groupes de pastilles : un champ « Rechercher » au-dessus des longues listes =====
// Les mêmes groupes que « GROUPES DE CHOIX EN PASTILLES » dans admin.css. La barre est celle de la liste
// des produits (.filtres), mais ici le filtre ne fait que masquer des pastilles déjà dans la page : sans
// rechargement, une section pas encore enregistrée garde ses cases cochées. Une pastille cochée reste visible.
const GROUPES_PASTILLES = '[id$="_modules"], [id$="_products"], [id$="_fabrics"], [id$="_colors"], [id$="_subCategories"]';
const SEUIL_RECHERCHE = 12;

// « canape » trouve « Canapé » : minuscules, accents retirés
const normaliser = (texte) => texte.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const pluriel = (nombre, mot) => `${nombre} ${mot}${nombre > 1 ? 's' : ''}`;

// Le groupe que filtre ce champ ; null pour la barre de la liste des produits, qui recharge la page
function groupeFiltre(champ) {
    const groupe = champ.closest('.filtres')?.nextElementSibling;
    return groupe?.matches(GROUPES_PASTILLES) ? groupe : null;
}

function ajouterRecherches() {
    document.querySelectorAll(GROUPES_PASTILLES).forEach((groupe) => {
        if (groupe.querySelectorAll('input[type=checkbox]').length <= SEUIL_RECHERCHE) return;
        if (groupe.previousElementSibling.matches('.filtres')) return;   // déjà ajoutée (Turbo et DOMContentLoaded)

        const barre = document.createElement('div');
        barre.className = 'filtres';
        barre.innerHTML = '<input type="search" class="filtre-recherche"><span class="filtre-compte" aria-live="polite"></span>';
        const champ = barre.firstChild;
        champ.placeholder = groupe.matches('[id$="_products"], [id$="_modules"]') ? 'Rechercher un produit…' : 'Rechercher…';
        // Le titre du groupe (« Tissus disponibles »…) est le libellé de sa ligne de formulaire
        champ.setAttribute('aria-label', `Rechercher dans « ${groupe.parentElement.querySelector(':scope > label').textContent.trim()} »`);

        // Produits et modules : le menu des catégories de la liste des produits, limité à celles du groupe
        const categories = [...new Set([...groupe.querySelectorAll('[data-categorie]')].map((caseACocher) => caseACocher.dataset.categorie))];
        if (categories.length) {
            const menu = document.createElement('select');
            menu.setAttribute('aria-label', 'Catégorie');
            menu.add(new Option('Toutes les catégories', ''));
            categories.sort((a, b) => a.localeCompare(b, 'fr')).forEach((nom) => menu.add(new Option(nom, nom)));
            champ.after(menu);
        }

        groupe.before(barre);
        filtrerPastilles(groupe);
    });
}

// Affiche les pastilles qui contiennent tous les mots cherchés et sont de la catégorie choisie,
// plus celles déjà cochées, et les compte.
function filtrerPastilles(groupe) {
    const barre = groupe.previousElementSibling;
    const mots = normaliser(barre.querySelector('input').value).split(/\s+/).filter(Boolean);
    const categorie = barre.querySelector('select')?.value;
    let cochees = 0;
    let affichees = 0;

    groupe.querySelectorAll('input[type=checkbox]').forEach((caseACocher) => {
        const pastille = caseACocher.nextElementSibling;   // Symfony écrit chaque libellé juste après sa case
        const visible = caseACocher.checked || ((!categorie || caseACocher.dataset.categorie === categorie)
            && mots.every((mot) => normaliser(pastille.textContent).includes(mot)));
        caseACocher.hidden = pastille.hidden = !visible;
        cochees += caseACocher.checked ? 1 : 0;
        affichees += visible ? 1 : 0;
    });

    barre.querySelector('.filtre-compte').textContent = `${pluriel(cochees, 'coché')} · ${pluriel(affichees, 'affiché')}`;
}

// Le champ et le menu des catégories (un select émet aussi « input »)
document.addEventListener('input', (e) => {
    const groupe = groupeFiltre(e.target);
    if (groupe) filtrerPastilles(groupe);
});

// Cocher ou décocher met le compte à jour
document.addEventListener('change', (e) => {
    const groupe = e.target.closest(GROUPES_PASTILLES);
    if (groupe?.previousElementSibling.matches('.filtres')) filtrerPastilles(groupe);
});

// Au clavier, la pastille qui reçoit le focus est ramenée dans sa liste qui défile : sa case, invisible, reste en haut
document.addEventListener('focusin', (e) => {
    if (e.target.matches('input[type=checkbox]') && e.target.closest(GROUPES_PASTILLES)) {
        e.target.nextElementSibling.scrollIntoView({ block: 'nearest' });
    }
});

// Échap vide le champ ; Entrée ne doit pas envoyer tout le formulaire
document.addEventListener('keydown', (e) => {
    const groupe = groupeFiltre(e.target);
    if (!groupe) return;

    if (e.key === 'Enter') e.preventDefault();
    if (e.key === 'Escape' && e.target.value) {
        e.preventDefault();
        e.target.value = '';
        filtrerPastilles(groupe);
    }
});


// ===== Page « Ajouter des photos » : une vignette et un champ alt par fichier choisi =====
// Les fichiers sont lus dans le navigateur, rien n'est envoyé avant la validation du
// formulaire. Les champs alt sont créés ici ; le contrôleur les apparie aux fichiers par index,
// d'où les noms photo_batch[alts][0], [1], [2]… que Symfony attend.
function buildPhotoPreview(input) {
    const container = document.getElementById('apercu-photos');
    if (!container) return;

    const prefix = container.getAttribute('name') || 'photo_batch[alts]';
    container.replaceChildren();   // une nouvelle sélection remplace l'ancienne

    [...input.files].forEach((file, index) => {
        const row = document.createElement('div');
        row.className = 'apercu-ligne';

        const thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(file);
        thumb.alt = '';
        thumb.onload = () => URL.revokeObjectURL(thumb.src);   // la mémoire est rendue après l'affichage

        const field = document.createElement('label');
        field.className = 'apercu-champ';
        field.textContent = file.name;

        const alt = document.createElement('input');
        alt.type = 'text';
        alt.name = `${prefix}[${index}]`;
        alt.maxLength = 180;   // longueur de la colonne alt
        alt.placeholder = 'Texte alternatif (facultatif)';

        field.appendChild(alt);
        row.append(thumb, field);
        container.appendChild(row);
    });
}

// Délégation sur document : Turbo remplace le <body> sans relancer le script.
document.addEventListener('change', (e) => {
    if (e.target.matches('#photo_batch_images')) buildPhotoPreview(e.target);
});


// ===== Grille de photos : le texte alternatif s'enregistre en quittant le champ =====
// Même principe que le glisser-déposer : pas de bouton, rien à oublier de cliquer,
// et la ligne d'état « ordre-message » sert de confirmation.
async function saveAltText(field) {
    const grid = field.closest('#grille-photos');
    const card = field.closest('.photo-carte');
    const message = document.getElementById('ordre-message');

    if (!grid || !card) return;
    if (field.value === field.dataset.saved) return;   // rien n'a changé

    if (message) message.textContent = 'Enregistrement…';

    try {
        const response = await fetch(grid.dataset.altUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: card.dataset.id,
                alt: field.value,
                _token: grid.dataset.altToken,
            }),
        });

        const data = await response.json();

        if (!data.ok) {
            if (message) message.textContent = data.message ?? "L'enregistrement a échoué.";
            return;
        }

        field.dataset.saved = field.value;
        toggleMissingAltBadge(card, data.empty);
        if (message) message.textContent = 'Texte alternatif enregistré.';
    } catch (erreur) {
        if (message) message.textContent = "L'enregistrement a échoué : rechargez la page.";
    }
}

// L'étiquette rouge « Alt manquant » suit la saisie, sans rechargement.
function toggleMissingAltBadge(card, isEmpty) {
    const infos = card.querySelector('.photo-infos');
    const existing = card.querySelector('.photo-alerte');

    if (!isEmpty) {
        existing?.remove();
        return;
    }

    if (existing || !infos) return;

    const badge = document.createElement('span');
    badge.className = 'photo-etiquette photo-alerte';
    badge.textContent = 'Alt manquant';
    infos.insertBefore(badge, infos.querySelector('textarea.photo-alt'));
}

// focusout et non blur : blur ne remonte pas jusqu'à document, la délégation ne marcherait pas.
document.addEventListener('focusout', (e) => {
    if (e.target.matches('textarea.photo-alt')) saveAltText(e.target);
});

// Entrée enregistre au lieu d'insérer un retour à la ligne.
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.matches('textarea.photo-alt')) {
        e.preventDefault();
        e.target.blur();
    }
});
