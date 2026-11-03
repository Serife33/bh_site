import './stimulus_bootstrap.js';
import Sortable from 'sortablejs';

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

async function enregistrerOrdre(liste) {
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
                ? 'Ordre enregistré.'
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

// Turbo remplace le <body> sans relancer le script : on rebranche à chaque navigation.
document.addEventListener('turbo:load', activerGlisser);
// Filet de sécurité si Turbo est coupé un jour.
document.addEventListener('DOMContentLoaded', activerGlisser);


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

// ===== Écran d'un devis : pré-remplir une ligne depuis le catalogue =====
// Choisir un produit ou une de ses variantes reprend sa désignation, sa taille, son prix et
// « fabriqué selon les choix du client » pour un produit sur mesure. Tout reste modifiable ensuite.
function preRemplirLigne(select) {
    const option = select.selectedOptions[0];
    if (!option?.value) return;   // « Ligne libre » ou « Aucune » : rien à reprendre

    const ligne = select.closest('.collection-ligne');
    const champ = (nom) => ligne.querySelector(`[name$="[${nom}]"]`);

    if (select === champ('variant')) {
        champ('product').value = option.dataset.produit;
        champ('details').value = option.dataset.precision;
    } else {
        champ('variant').value = '';   // la variante d'un autre produit ne vaut plus
    }

    const produit = champ('product').selectedOptions[0];
    champ('label').value = produit.text;
    champ('customMade').checked = produit.dataset.surMesure === '1';
    champ('unitPrice').value = option.dataset.prix;
}

document.addEventListener('change', (e) => {
    if (e.target.matches('#form-devis [name$="[product]"], #form-devis [name$="[variant]"]')) preRemplirLigne(e.target);
});


// ===== Écran d'un devis : les montants se recalculent pendant la saisie =====
// Le calcul de src/Service/QuoteCalculator.php, en centimes pour éviter les erreurs d'arrondi des nombres à virgule.
// Pour l'affichage seulement : le serveur recalcule tout à l'enregistrement.
const centimes = (champ) => Math.round((parseFloat(champ.value) || 0) * 100);
const euros = (montant) => (montant / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
// Remise en % (centièmes de %) ou en centimes d'euro, jamais plus que le montant remisé
const reduction = (base, valeur, type) => Math.min(base, type === 'percent' ? Math.round(base * valeur / 10000) : valeur);

function recalculerDevis(form) {
    const lignes = [...form.querySelectorAll('.collection-ligne')].map((ligne) => {
        const champ = (nom) => ligne.querySelector(`[name$="[${nom}]"]`);
        const brut = (parseInt(champ('quantity').value, 10) || 0) * centimes(champ('unitPrice'));
        const total = brut - reduction(brut, centimes(champ('discountValue')), champ('discountType').value);
        champ('total').value = euros(total);
        return total;
    });

    const champ = (nom) => form.elements[`quote[${nom}]`];
    const articles = lignes.reduce((somme, total) => somme + total, 0);
    const remise = reduction(articles, centimes(champ('discountValue')), champ('discountType').value);
    const retrait = champ('pickup').checked;   // retrait au showroom : ni livraison ni montage
    const livraison = retrait ? 0 : centimes(champ('deliveryFee'));
    const montage = retrait ? 0 : centimes(champ('assemblyFee'));
    const ttc = articles - remise + livraison + montage;
    const ht = Math.round(ttc * 100 / (100 + Number(form.dataset.tva)));
    const acompte = Math.round(ttc * Number(form.dataset.acompte) / 100);

    const montants = { items: articles, discount: remise, delivery: livraison, assembly: montage, ttc, ht, tva: ttc - ht, deposit: acompte, balance: ttc - acompte };
    Object.entries(montants).forEach(([cle, montant]) => {
        form.querySelector(`[data-total="${cle}"]`).textContent = (cle === 'discount' && montant ? '-' : '') + euros(montant);
    });
}

// Chaque frappe, chaque ligne ajoutée ou retirée, et le premier affichage.
// getElementById et non closest : une ligne retirée n'est plus dans la page quand son clic arrive ici.
['input', 'change', 'click', 'turbo:load'].forEach((type) => document.addEventListener(type, () => {
    const form = document.getElementById('form-devis');
    if (form) recalculerDevis(form);
}));
