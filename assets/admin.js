import './stimulus_bootstrap.js';
import Sortable from 'sortablejs';

// ===== Nuancier d'un tissu : ajouter ou retirer une ligne de coloris =====
document.addEventListener('click', (e) => {
    const nuancier = document.getElementById('nuancier');

    if (nuancier && e.target.closest('#ajouter-couleur')) {
        const index = parseInt(nuancier.dataset.index, 10);
        const ligne = document.createElement('div');
        ligne.className = 'nuancier-ligne';
        // __name__ est le marqueur laissé par Symfony : on le remplace par le numéro de ligne
        ligne.innerHTML = nuancier.dataset.prototype.replace(/__name__/g, index)
            + '<button type="button" class="btn-supprimer-ligne">Retirer</button>';
        nuancier.appendChild(ligne);
        nuancier.dataset.index = index + 1;
        return;
    }

    const retirer = e.target.closest('.btn-supprimer-ligne');
    if (retirer) retirer.closest('.nuancier-ligne').remove();
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


async function enregistrerOrdre(liste) {
    const lignes = [...liste.querySelectorAll('li')];
    const message = document.getElementById('ordre-message');

    // Renumérotation à l'écran : 1, 2, 3…
    lignes.forEach((ligne, i) => {
        const rang = ligne.querySelector('.ordre-rang');
        if (rang) rang.textContent = i + 1;
    });

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