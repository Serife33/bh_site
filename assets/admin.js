import './stimulus_bootstrap.js';

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