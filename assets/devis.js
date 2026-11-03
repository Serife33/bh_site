// ===== L'écran d'un devis (templates/quote/edit.html.twig) =====
// La spécification : docs/UX-DEVIS.md. Les montants sont ceux de src/Service/QuoteCalculator.php, calculés ici en
// centimes pour l'affichage seulement : le serveur recalcule tout à l'enregistrement. Rien n'apparaît ni ne disparaît :
// un champ inutile est grisé et une phrase dit pourquoi. Délégation sur document : Turbo remplace la page sans
// relancer ce fichier.

// ----- Outils -----
const centimes = (valeur) => Math.round((parseFloat(valeur) || 0) * 100);
const euros = (montant) => (montant / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
// La part d'un montant dans un autre, au centième de % : « 16,67 »
const pourcent = (part, tout) => (tout ? Math.round(part * 10000 / tout) / 100 : 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formulaire = () => document.getElementById('form-devis');
// Un champ d'un article (quote[lines][0][unitPrice]), ou du devis lui-même (quote[deliveryFee])
const champ = (article, nom) => article.querySelector(`[name$="[${nom}]"]`);
const champDevis = (nom) => formulaire().elements[`quote[${nom}]`];
// L'aide d'un champ, réécrite en résultat (« Catalogue : 2 399,00 € »)
const aideDe = (element) => document.getElementById(`${element.id}_help`);
const lireJson = (texte) => (texte ? JSON.parse(texte) : null);

function resultat(element, texte, etat = '') {
    element.textContent = texte;
    element.classList.toggle('attention', etat === 'attention');
    element.classList.toggle('faux', etat === 'faux');
}

// ----- Annonces au lecteur d'écran : une zone, après une courte pause, les montants s'ils ont changé -----
let aDire = [];
let pauseAnnonce;
let derniersMontants = '';
const cellule = (cle) => formulaire().querySelector(`[data-total="${cle}"]`);
const texteMontants = () => `Total TTC ${cellule('ttc').textContent}. Acompte ${cellule('deposit').textContent}. Reste à payer ${cellule('balance').textContent}.`;

function annoncer(texte = '') {
    if (texte) aDire.push(texte);
    clearTimeout(pauseAnnonce);
    pauseAnnonce = setTimeout(() => {
        const zone = document.getElementById('annonce');
        if (!zone || !formulaire()) return;
        const montants = texteMontants();
        if (montants !== derniersMontants) {
            aDire.push(montants);
            derniersMontants = montants;
        }
        if (aDire.length) zone.textContent = aDire.join(' ');
        aDire = [];
    }, 500);
}


// ===== Un article =====
const articles = () => [...document.querySelectorAll('#articles .article')];
const articlesActifs = () => articles().filter((article) => !article.classList.contains('est-retire'));
const produitDe = (article) => lireJson(article.dataset.produit);
const varianteDe = (article) => produitDe(article)?.variants.find((v) => String(v.id) === champ(article, 'variant').value) ?? null;
const taillesDe = (produit) => [...new Set((produit?.variants ?? []).map((v) => v.size).filter(Boolean))];

// Taille puis matière : la liste des matières ne garde que celles qui existent dans la taille choisie, avec leur prix
function remplirTailles(article) {
    const tailles = taillesDe(produitDe(article));
    const select = article.querySelector('[data-taille]');
    select.replaceChildren(...(tailles.length ? [new Option('Choisir la taille', ''), ...tailles.map((t) => new Option(t, t))] : [new Option('Sans objet', '')]));
    select.disabled = !tailles.length;
    select.value = varianteDe(article)?.size ?? '';
    remplirMatieres(article, varianteDe(article)?.material ?? '');
}

function remplirMatieres(article, garder) {
    const produit = produitDe(article);
    const variantes = produit?.variants ?? [];
    const avecMatiere = variantes.some((v) => v.material);
    const avecTaille = taillesDe(produit).length > 0;
    const taille = article.querySelector('[data-taille]').value;
    const dispo = variantes.filter((v) => v.material && (!avecTaille || v.size === taille));
    const enAttente = avecMatiere && avecTaille && !taille;
    const select = article.querySelector('[data-matiere]');
    select.replaceChildren(...(!avecMatiere ? [new Option('Sans objet', '')]
        : enAttente ? [new Option("Choisissez d'abord la taille", '')]
        : [new Option('Choisir la matière', ''), ...dispo.map((v) => new Option(`${v.material} : ${euros(centimes(v.price))}`, v.material))]));
    select.disabled = !avecMatiere || enAttente;
    select.value = dispo.some((v) => v.material === garder) ? garder : '';
}

// Les finitions proposées : celles du produit, et seulement celles de la matière choisie
function remplirFinitions(article) {
    const matiere = varianteDe(article)?.material;
    const finitions = (produitDe(article)?.finishes ?? []).filter((f) => !f.material || !matiere || f.material === matiere);
    champ(article, 'finish').list.replaceChildren(...finitions.map((f) => new Option(f.label)));
}

// Variante complète : son prix, et son libellé dans les précisions sans jamais effacer le texte du vendeur
function appliquerVariante(article) {
    const produit = produitDe(article);
    if (!produit) return;
    const taille = taillesDe(produit).length ? article.querySelector('[data-taille]').value : '';
    const matiere = produit.variants.some((v) => v.material) ? article.querySelector('[data-matiere]').value : '';
    const variante = produit.variants.find((v) => (v.size ?? '') === taille && (v.material ?? '') === matiere);
    const avant = champ(article, 'variant').value;
    champ(article, 'variant').value = variante?.id ?? '';
    remplirFinitions(article);
    if (!variante || String(variante.id) === avant) return;

    champ(article, 'unitPrice').value = variante.price;
    const precisions = champ(article, 'details');
    const ancien = article.dataset.libelleVariante;
    if (ancien && precisions.value.includes(ancien)) precisions.value = precisions.value.replace(ancien, variante.label);
    else if (!precisions.value.includes(variante.label)) precisions.value = precisions.value ? `${variante.label}\n${precisions.value}` : variante.label;
    article.dataset.libelleVariante = variante.label;
    annoncer(`Prix unitaire : ${euros(centimes(variante.price))}.`);
}

// Le produit lié à la ligne (champ caché) et ce qui en découle ; null : ligne libre
function lier(article, produit, variante = '') {
    article.dataset.produit = produit ? JSON.stringify(produit) : '';
    champ(article, 'product').value = produit?.id ?? '';
    champ(article, 'variant').value = variante;
    remplirTailles(article);
    remplirFinitions(article);
}

// Un seul champ « Article » : son texte est le nom imprimé ; le choisir dans la liste lie en plus le produit.
// Précisions, finition, quantité et remise ne changent pas.
function choisirProduit(article, produit) {
    delete article.dataset.ancienProduit;
    delete article.dataset.ancienneVariante;
    lier(article, produit);
    champ(article, 'label').value = produit.name;
    champ(article, 'unitPrice').value = produit.price;
    champ(article, 'customMade').checked = produit.customMade;
    annoncer(`${produit.name} choisi : prix ${euros(centimes(produit.price))} et case « fabriqué selon les choix du client » remplis.`);
    apresChangement();
}

// Quitter le champ sans choisir alors que le texte n'est plus le nom du produit : la ligne redevient libre, rien d'autre ne bouge
function quitterArticle(input) {
    const article = input.closest('.article');
    const produit = produitDe(article);
    if (!produit || input.value.trim() === produit.name) return;

    article.dataset.ancienProduit = article.dataset.produit;
    article.dataset.ancienneVariante = champ(article, 'variant').value;
    lier(article, null);
    annoncer(`Article ${article.dataset.numero} : ligne libre, le texte tapé sera le nom imprimé. Prix et précisions gardés. Le bouton Revenir à ${produit.name} est sous le champ.`);
    apresChangement();
}

// Revenir au produit : le texte tapé n'est jamais perdu, il passe dans les précisions s'il disait autre chose que le nom
function revenir(article) {
    const produit = lireJson(article.dataset.ancienProduit);
    const input = champ(article, 'label');
    const tape = input.value.trim();
    const reste = tape.toLowerCase().startsWith(produit.name.toLowerCase()) ? tape.slice(produit.name.length).replace(/^[\s,;:.]+/, '') : tape;
    let repris = '';
    if (reste && !produit.name.toLowerCase().startsWith(reste.toLowerCase())) {
        const precisions = champ(article, 'details');
        precisions.value = precisions.value ? `${reste}\n${precisions.value}` : reste;
        repris = ` Texte « ${reste} » repris dans les précisions.`;
    }
    const variante = article.dataset.ancienneVariante ?? '';
    delete article.dataset.ancienProduit;
    delete article.dataset.ancienneVariante;
    input.value = produit.name;
    lier(article, produit, variante);
    input.focus();
    annoncer(`Article ${article.dataset.numero} : ${produit.name} rétabli.${repris}`);
    apresChangement();
}

// Une remise, d'un article ou de l'ensemble : le libellé et l'unité suivent le type choisi, le résultat est toujours
// écrit, avec les vérifications immédiates. Même règle que QuoteCalculator::reduction().
function remise(groupe, brut, quantite = 1, prixUnitaire = brut) {
    const libelles = JSON.parse(groupe.dataset.libelles);
    const type = groupe.querySelector('input[type=radio]:checked')?.value ?? 'percent';
    const champValeur = groupe.querySelector('.c-rv input');
    groupe.querySelector(`label[for="${champValeur.id}"]`).textContent = libelles[type][0];
    groupe.querySelector('[data-unite]').textContent = libelles[type][1];

    const valeur = centimes(champValeur.value);
    const montant = Math.max(0, Math.min(brut, type === 'percent' ? Math.round(brut * valeur / 10000) : type === 'final' ? brut - valeur * quantite : valeur));
    const erreur = type === 'percent' && valeur > 10000 ? 'Une remise ne peut pas dépasser 100 %.'
        : type === 'final' && valeur > prixUnitaire ? `Le prix final dépasse le prix unitaire (${euros(prixUnitaire)}). Indiquez un prix plus bas.`
        : type === 'amount' && valeur > brut ? `La remise dépasse le total ${groupe.closest('.article') ? "de l'article" : 'des articles'} (${euros(brut)}).`
        : '';
    resultat(aideDe(champValeur), erreur || (!montant ? 'Aucune remise'
        : `Remise : -${euros(montant)} (-${pourcent(montant, brut)} %)${type === 'final' && quantite > 1 ? `, soit ${euros(valeur)} par article` : ''}`), erreur ? 'faux' : '');

    return { montant, erreur, champ: champValeur };
}

// Changer de type de remise repart d'une valeur neutre : jamais un article gratuit par accident
function valeurNeutre(groupe) {
    const article = groupe.closest('.article');
    return groupe.querySelector('input[type=radio]:checked')?.value === 'final' ? (champ(article, 'unitPrice').value || '0') : '0';
}

// Tout ce qui s'affiche d'un article ; rend son total et ce qui reste à faire (« Avant d'enregistrer »)
function majArticle(article) {
    const numero = article.dataset.numero;
    const produit = produitDe(article);
    const variante = varianteDe(article);
    const ancien = lireJson(article.dataset.ancienProduit);
    const nom = champ(article, 'label').value.trim();
    const problemes = [];

    const etiquette = article.querySelector('[data-etiquette]');
    etiquette.textContent = produit ? 'Catalogue' : nom ? 'Ligne libre' : 'Nouvel article';
    etiquette.className = `etiquette ${produit ? 'e-catalogue' : nom ? 'e-libre' : 'e-nouveau'}`;

    // Sous le champ Article : l'aide, ou le moyen de revenir au produit, à la même place (réécrite seulement si l'état change)
    const place = article.querySelector('[data-article-aide]');
    const etat = ancien && !produit ? 'revenir' : 'aide';
    if (place.dataset.etat !== etat) {
        if (!place.dataset.aide) place.dataset.aide = place.textContent.trim();
        place.dataset.etat = etat;
        const element = etat === 'aide'
            ? Object.assign(document.createElement('p'), { className: 'aide', textContent: place.dataset.aide })
            : Object.assign(document.createElement('button'), { type: 'button', className: 'btn-secondaire btn-revenir', textContent: `Revenir à : ${ancien.name}` });
        if (etat === 'revenir') element.dataset.action = 'revenir';
        place.replaceChildren(element);
    }

    // Taille et matière : un état, une phrase
    const sansVariante = produit && produit.variants.length && !variante;
    const taille = article.querySelector('[data-taille]');
    resultat(article.querySelector('[data-variante-aide]'),
        !produit ? (nom ? 'Article hors catalogue : pas de variante.' : "Choisissez d'abord un produit.")
            : !produit.variants.length ? "Ce produit n'a pas de variante."
            : variante ? `${variante.label} : ${euros(centimes(variante.price))}.`
            : !taille.disabled && !taille.value ? 'Choisissez la taille, puis la matière : le prix en dépend.'
            : 'Choisissez la matière : le prix en dépend.',
        sansVariante ? 'attention' : '');
    if (sansVariante) problemes.push({ texte: `Article ${numero} : choisissez la taille et la matière`, cible: !taille.disabled && !taille.value ? taille.id : article.querySelector('[data-matiere]').id });

    // Le prix du catalogue reste visible sous le prix unitaire
    const prix = champ(article, 'unitPrice');
    const pu = centimes(prix.value);
    const reference = produit && !sansVariante ? centimes(variante ? variante.price : produit.price) : null;
    resultat(aideDe(prix),
        !produit ? (ancien ? 'Ligne libre : prix gardé, modifiable.' : nom ? 'Hors catalogue : prix libre.' : 'Rempli par le produit choisi.')
            : sansVariante ? 'Prix de base. Choisissez la variante.'
            : prix.value !== '' && pu === reference ? `Catalogue : ${euros(reference)}` : `Modifié. Catalogue : ${euros(reference)}`,
        produit && (sansVariante || pu !== reference) ? 'attention' : '');
    if (!nom) problemes.push({ texte: `Article ${numero} : choisissez un produit ou tapez son nom`, cible: champ(article, 'label').id });
    if (prix.value === '') problemes.push({ texte: `Article ${numero} : indiquez le prix unitaire`, cible: prix.id });

    const quantite = parseInt(champ(article, 'quantity').value, 10) || 0;
    const brut = quantite * pu;
    const { montant, erreur, champ: valeur } = remise(article.querySelector('.c-remise'), brut, quantite, pu);
    if (erreur) problemes.push({ texte: `Article ${numero} : ${erreur.split(' (')[0].replace(/\.$/, '').toLowerCase()}`, cible: valeur.id });

    article.querySelector('[data-total-article]').textContent = euros(brut - montant);
    article.querySelector('[data-action="retirer"]').setAttribute('aria-label', `Retirer l'article ${numero}${nom ? `, ${nom}` : ''}`);
    return { total: brut - montant, problemes };
}

// Retirer replie la carte et désactive ses champs : non envoyés, Symfony supprime la ligne à l'enregistrement
function retirer(article) {
    article.classList.add('est-retire');
    article.querySelector('.article-corps').hidden = true;
    const bande = article.querySelector('.article-retire');
    bande.hidden = false;
    bande.querySelector('[data-retire-nom]').textContent = champ(article, 'label').value.trim() || 'sans nom';
    article.querySelectorAll('input, select, textarea').forEach((element) => { element.disabled = true; });
    bande.querySelector('button').focus();
    annoncer(`Article ${article.dataset.numero} retiré. Vous pouvez annuler jusqu'à l'enregistrement.`);
    apresChangement();
}

function retablir(article) {
    article.classList.remove('est-retire');
    article.querySelector('.article-retire').hidden = true;
    article.querySelector('.article-corps').hidden = false;
    article.querySelectorAll('input, textarea').forEach((element) => { element.disabled = false; });
    remplirTailles(article);
    article.querySelector('[data-action="retirer"]').focus();
    annoncer(`Article ${article.dataset.numero} rétabli.`);
    apresChangement();
}

// Un article ajouté : le modèle de carte de la collection, son numéro de ligne et son numéro d'article
function ajouterArticle() {
    const conteneur = document.getElementById('articles');
    const index = Number(conteneur.dataset.index);
    const numero = Math.max(0, ...articles().map((article) => Number(article.dataset.numero))) + 1;
    conteneur.insertAdjacentHTML('beforeend', conteneur.dataset.prototype.replace(/__name__/g, index).replace(/__numero__/g, numero));
    conteneur.dataset.index = index + 1;
    const article = conteneur.lastElementChild;
    preparer(article);
    champ(article, 'label').focus();
    annoncer(`Article ${numero} ajouté.`);
    apresChangement();
}

// Une carte telle qu'elle arrive du serveur : listes de variantes et propositions de finition remplies
function preparer(article) {
    if (article.dataset.prete) return;
    article.dataset.prete = '1';
    article.dataset.libelleVariante = varianteDe(article)?.label ?? '';
    remplirTailles(article);
    remplirFinitions(article);
}


// ===== Tous les montants, à chaque changement =====
function recalculer() {
    const form = formulaire();
    const problemes = [];
    let totalArticles = 0;
    articlesActifs().forEach((article) => {
        const resultats = majArticle(article);
        totalArticles += resultats.total;
        problemes.push(...resultats.problemes);
    });

    const globale = remise(form.querySelector('.remise-globale .c-remise'), totalArticles);
    if (globale.erreur) problemes.push({ texte: `Remise sur l'ensemble : ${globale.erreur.split(' (')[0].replace(/\.$/, '').toLowerCase()}`, cible: globale.champ.id });

    // Livraison et montage : leur prix ne compte que si la case est cochée
    const options = { delivery: ['offerte', 'non comprise'], assembly: ['offert', 'non compris'] };
    const prixOption = (cle) => (champDevis(cle).checked ? centimes(champDevis(`${cle}Fee`).value) : 0);
    const livraison = prixOption('delivery');
    const montage = prixOption('assembly');
    const ttc = totalArticles - globale.montant + livraison + montage;
    const ht = Math.round(ttc * 100 / (100 + Number(form.dataset.tva)));

    // L'acompte : le montant saisi, sinon le taux du devis, montré en indication dans le champ vide
    const taux = Number(form.dataset.acompte);
    const calcule = Math.round(ttc * taux / 100);
    const saisi = champDevis('depositAmount').value !== '';
    const acompte = saisi ? centimes(champDevis('depositAmount').value) : calcule;
    champDevis('depositAmount').placeholder = `${taux.toLocaleString('fr-FR')} % = ${euros(calcule)}`;

    const montants = { items: totalArticles, discount: globale.montant, delivery: livraison, assembly: montage, ttc, ht, tva: ttc - ht, deposit: acompte, balance: ttc - acompte };
    Object.entries(montants).forEach(([cle, montant]) => {
        cellule(cle).textContent = cle in options
            ? (!champDevis(cle).checked ? options[cle][1] : (montant ? euros(montant) : options[cle][0]))
            : (cle === 'discount' && montant ? '-' : '') + euros(montant);
    });
    form.querySelector('[data-part-acompte]').textContent = `${saisi ? pourcent(acompte, ttc) : taux.toLocaleString('fr-FR')} %`;
    form.querySelector('[data-moment-solde]').textContent = champDevis('delivery').checked ? 'à la livraison' : 'au retrait';
    return problemes;
}

// Un changement : les montants, et leur annonce
function apresChangement() {
    recalculer();
    annoncer();
}


// ===== Recherche avec liste de résultats (motif « combobox ») =====
// On tape 2 lettres au moins, la liste s'ouvre sous le champ ; flèches haut et bas pour s'y déplacer, Entrée pour
// choisir, Échap pour fermer. Les résultats viennent du serveur (data-url).
const SOURCES = {
    produit: {
        texte: (produit) => `${produit.name} · ${euros(centimes(produit.price))}`,
        vide: 'Aucun produit du catalogue. Le texte tapé sera le nom imprimé (ligne libre).',
        choisir: (input, produit) => choisirProduit(input.closest('.article'), produit),
        quitter: quitterArticle,
    },
};
const sourceDe = (input) => SOURCES[input.dataset.source];
const listeDe = (input) => document.getElementById(input.getAttribute('aria-controls'));

function fermer(input) {
    listeDe(input).hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
}

function ouvrir(input, elements) {
    const liste = listeDe(input);
    liste.elements = elements;   // l'élément d'une option se retrouve par son rang
    liste.replaceChildren(...(elements.length ? elements : [null]).map((element, rang) => {
        const option = document.createElement('li');
        option.setAttribute('role', 'option');
        if (element) {
            Object.assign(option, { id: `${liste.id}_${rang}`, textContent: sourceDe(input).texte(element) });
            option.dataset.rang = rang;
            option.setAttribute('aria-selected', 'false');
        } else {
            option.textContent = sourceDe(input).vide;
            option.setAttribute('aria-disabled', 'true');
        }
        return option;
    }));
    liste.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    input.removeAttribute('aria-activedescendant');
    annoncer(elements.length ? `${elements.length} résultat${elements.length > 1 ? 's' : ''}.` : 'Aucun résultat.');
}

function choisir(input, rang) {
    const element = listeDe(input).elements[rang];
    fermer(input);
    sourceDe(input).choisir(input, element);
}

let pauseFrappe;
function chercher(input) {
    clearTimeout(pauseFrappe);
    const texte = input.value.trim();
    if (texte.length < 2) {
        fermer(input);
        return;
    }
    pauseFrappe = setTimeout(async () => {
        try {
            const elements = await (await fetch(`${input.dataset.url}?q=${encodeURIComponent(texte)}`)).json();
            if (input.value.trim() === texte && document.activeElement === input) ouvrir(input, elements);   // une réponse dépassée ne remplace pas la suivante
        } catch {
            fermer(input);   // session expirée ou réseau coupé
        }
    }, 250);
}


// ===== Les gestes du vendeur =====
document.addEventListener('input', (e) => {
    if (!e.target.closest('#form-devis')) return;
    if (e.target.matches('[role="combobox"]')) chercher(e.target);
    else apresChangement();
});

document.addEventListener('change', (e) => {
    const cible = e.target;
    if (!cible.closest('#form-devis') || cible.matches('[role="combobox"]')) return;
    const article = cible.closest('.article');
    if (cible.matches('.c-remise input[type=radio]')) cible.closest('.c-remise').querySelector('.c-rv input').value = valeurNeutre(cible.closest('.c-remise'));
    if (cible.matches('[data-taille]')) {
        remplirMatieres(article, article.querySelector('[data-matiere]').value);
        appliquerVariante(article);
    }
    if (cible.matches('[data-matiere]')) appliquerVariante(article);
    apresChangement();
});

document.addEventListener('focusout', (e) => {
    const cible = e.target;
    if (!cible.closest?.('#form-devis')) return;
    if (cible.matches('[role="combobox"]')) {
        fermer(cible);
        sourceDe(cible).quitter(cible);
    }
    // Une remise vidée reprend la valeur neutre de son type
    if (cible.matches('.c-rv input') && cible.value === '') {
        cible.value = valeurNeutre(cible.closest('.c-remise'));
        apresChangement();
    }
});

document.addEventListener('keydown', (e) => {
    const input = e.target.closest?.('#form-devis [role="combobox"]');
    if (!input || input.getAttribute('aria-expanded') !== 'true') return;

    const options = [...listeDe(input).querySelectorAll('[data-rang]')];
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && options.length) {
        e.preventDefault();
        const actuelle = options.findIndex((option) => option.id === input.getAttribute('aria-activedescendant'));
        const pas = e.key === 'ArrowDown' ? 1 : -1;
        const suivante = options[actuelle === -1 ? (pas > 0 ? 0 : options.length - 1) : (actuelle + pas + options.length) % options.length];
        options.forEach((option) => option.setAttribute('aria-selected', String(option === suivante)));
        input.setAttribute('aria-activedescendant', suivante.id);
        suivante.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();   // Entrée choisit, sans envoyer le formulaire
        const active = document.getElementById(input.getAttribute('aria-activedescendant') || '');
        if (active) choisir(input, Number(active.dataset.rang));
    } else if (e.key === 'Escape') {
        e.preventDefault();
        fermer(input);
    }
});

// Un clic sur un résultat le choisit. mousedown et non click : le champ garde le focus, la liste ne se ferme pas avant.
document.addEventListener('mousedown', (e) => {
    const option = e.target.closest('#form-devis .resultats [data-rang]');
    if (!option) return;
    e.preventDefault();
    choisir(document.querySelector(`[aria-controls="${option.parentElement.id}"]`), Number(option.dataset.rang));
});

document.addEventListener('click', (e) => {
    const bouton = e.target.closest('#form-devis [data-action]');
    if (!bouton) return;
    const article = bouton.closest('.article');
    ({
        ajouter: ajouterArticle,
        retirer: () => retirer(article),
        annuler: () => retablir(article),
        revenir: () => revenir(article),
    })[bouton.dataset.action]();
});

// Au premier affichage, et après un enregistrement refusé que Turbo affiche à la place de la page
function demarrer() {
    if (!formulaire()) return;
    articles().forEach(preparer);
    recalculer();
    derniersMontants = texteMontants();
}
document.addEventListener('turbo:load', demarrer);
document.addEventListener('turbo:render', demarrer);
