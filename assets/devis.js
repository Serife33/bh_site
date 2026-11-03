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
const nombreDe = (nombre, mot) => `${nombre} ${mot}${nombre > 1 ? 's' : ''}`;   // « 1 article », « 3 articles »

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


// ===== Modifications non enregistrées : dites sous Enregistrer, et rappelées avant de quitter la page =====
let modifiee = false;

function marquerModifie() {
    if (modifiee) return;
    modifiee = true;
    resultat(formulaire().querySelector('[data-etat-enregistrement]'), 'Modifications non enregistrées.', 'attention');
    const lien = formulaire().querySelector('[data-sans-modifications]');
    if (lien) lien.textContent = ', sans les modifications en cours';
}

window.addEventListener('beforeunload', (e) => {
    if (formulaire() && modifiee) e.preventDefault();
});
document.addEventListener('turbo:before-visit', (e) => {
    if (formulaire() && modifiee && !confirm('Quitter sans enregistrer ? Les modifications de ce devis seront perdues.')) e.preventDefault();
});


// ===== Erreurs d'un enregistrement refusé : le résumé en haut, le message sous chaque champ =====
// Corriger un champ retire son message et sa ligne du résumé.
function effacerErreur(element) {
    if (element.getAttribute('aria-invalid') !== 'true') return;
    element.removeAttribute('aria-invalid');
    (element.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => /_error\d+$/.test(id)).forEach((id) => document.getElementById(id)?.remove());
    document.querySelectorAll(`[data-erreur-de="${element.id}"]`).forEach((ligne) => ligne.remove());
    majResume();
}

function majResume() {
    const resume = document.getElementById('resume-erreurs');
    if (!resume) return;
    const nombre = resume.querySelectorAll('li').length;
    resume.classList.toggle('corrige', !nombre);
    resume.querySelector('h2').textContent = nombre
        ? `Le devis n'est pas enregistré : ${nombreDe(nombre, 'point')} à corriger`
        : 'Tout est corrigé. Vous pouvez enregistrer.';
}

// Un lien du résumé ou d'« Avant d'enregistrer » mène au champ et y place le focus
document.addEventListener('click', (e) => {
    const lien = e.target.closest('#contenu [data-aller]');
    if (!lien) return;
    e.preventDefault();
    const cible = document.getElementById(lien.dataset.aller);
    cible?.scrollIntoView({ block: 'center' });
    cible?.focus({ preventScroll: true });
});

// La phrase sous le statut ; « Accepté » prévient qu'il verrouillera le devis
const STATUTS = {
    draft: 'Pas encore remis au client.',
    sent: 'Remis au client. Passe seul à Expiré après la date de validité.',
    accepted: "Le client a signé. À l'enregistrement, le devis est verrouillé.",
    refused: 'Le client ne donne pas suite.',
    expired: 'Date de validité dépassée.',
};

function majStatut() {
    const statut = champDevis('status');
    resultat(aideDe(statut), STATUTS[statut.value], statut.value === 'accepted' && formulaire().dataset.statutEnregistre !== 'accepted' ? 'attention' : '');
}

// Avant d'enregistrer un passage à « Accepté » : une confirmation, le focus sur le bouton prudent.
// L'envoi est celui du navigateur (data-turbo false) : un seul à la fois, même avec les deux boutons Enregistrer.
let verrouillageConfirme = false;
document.addEventListener('submit', (e) => {
    if (e.target.id !== 'form-devis') return;
    if (e.target.dataset.envoi) {
        e.preventDefault();
        return;
    }
    if (champDevis('status').value === 'accepted' && e.target.dataset.statutEnregistre !== 'accepted' && !verrouillageConfirme) {
        e.preventDefault();
        document.getElementById('dialogue-accepte').showModal();
        return;
    }
    e.target.dataset.envoi = '1';
    verrouillageConfirme = false;
    modifiee = false;
});
// Retour sur la page depuis le cache du navigateur : elle peut de nouveau être envoyée
window.addEventListener('pageshow', () => delete formulaire()?.dataset.envoi);

document.addEventListener('click', (e) => {
    const bouton = e.target.closest('#dialogue-accepte [data-dialogue]');
    if (!bouton) return;
    document.getElementById('dialogue-accepte').close();
    if (bouton.dataset.dialogue === 'oui') {
        verrouillageConfirme = true;
        formulaire().requestSubmit();
    } else {
        champDevis('status').focus();
    }
});


// ===== Le client : sa fiche, lue sur le fichier client =====
const sectionClient = () => document.querySelector('[data-client]');
const clientDe = () => lireJson(sectionClient().dataset.client);
const paragraphe = (texte, classe = '') => Object.assign(document.createElement('p'), { textContent: texte, className: classe });

function majFiche() {
    const client = clientDe();
    const fiche = document.querySelector('[data-fiche-client]');
    if (!client) {
        fiche.replaceChildren(paragraphe('Aucun client choisi.', 'vide'));
        return;
    }
    const nom = paragraphe('');
    nom.append(Object.assign(document.createElement('strong'), { textContent: client.name }));
    const lien = paragraphe('');
    lien.append(Object.assign(document.createElement('a'), { href: client.url, target: '_blank', textContent: 'Modifier la fiche (nouvel onglet)' }));
    fiche.replaceChildren(nom,
        paragraphe(`${client.phone || 'Pas de téléphone sur la fiche.'} · ${client.email || "Pas d'e-mail sur la fiche."}`),
        paragraphe(client.address || "Pas d'adresse sur la fiche."),
        lien);
}

function choisirClient(input, client) {
    sectionClient().dataset.client = JSON.stringify(client);
    champDevis('customer').value = client.id;
    input.value = client.name;
    majFiche();
    annoncer(`Client choisi : ${client.name}.`);
    apresChangement();
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
function remplirProduit(article, produit) {
    delete article.dataset.ancienProduit;
    delete article.dataset.ancienneVariante;
    lier(article, produit);
    champ(article, 'label').value = produit.name;
    champ(article, 'label').dataset.avant = produit.name;   // le texte remis si une recherche ouvre ensuite la composition
    champ(article, 'unitPrice').value = produit.price;
    champ(article, 'customMade').checked = produit.customMade;
}

function choisirProduit(article, produit) {
    remplirProduit(article, produit);
    annoncer(`${produit.name} choisi : prix ${euros(centimes(produit.price))} et case « fabriqué selon les choix du client » remplis.`);
    apresChangement();
}

// Quitter le champ sans choisir alors que le texte n'est plus le nom du produit, ni le texte enregistré (ancien devis,
// produit renommé depuis) : la ligne redevient libre, rien d'autre ne bouge
function quitterArticle(input) {
    const article = input.closest('.article');
    const produit = produitDe(article);
    if (!produit || [produit.name, input.defaultValue.trim()].includes(input.value.trim())) return;

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

// Un article ajouté à la fin : le modèle de carte de la collection, son numéro de ligne et son numéro d'article
function nouvelArticle() {
    const conteneur = document.getElementById('articles');
    const index = Number(conteneur.dataset.index);
    const numero = Math.max(0, ...articles().map((article) => Number(article.dataset.numero))) + 1;
    conteneur.insertAdjacentHTML('beforeend', conteneur.dataset.prototype.replace(/__name__/g, index).replace(/__numero__/g, numero));
    conteneur.dataset.index = index + 1;
    const article = conteneur.lastElementChild;
    preparer(article);
    return article;
}

function ajouterArticle() {
    const article = nouvelArticle();
    champ(article, 'label').focus();
    annoncer(`Article ${article.dataset.numero} ajouté.`);
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

    // Livraison et montage : un prix inutile est grisé (non envoyé, il vaut 0), une phrase dit pourquoi
    const livre = champDevis('delivery').value === '1';
    const monte = champDevis('assembly').checked;
    champDevis('deliveryFee').disabled = !livre;
    champDevis('assemblyFee').disabled = !monte;
    const livraison = livre ? centimes(champDevis('deliveryFee').value) : 0;
    const montage = monte ? centimes(champDevis('assemblyFee').value) : 0;
    resultat(aideDe(champDevis('deliveryFee')), !livre ? 'Retrait au showroom : pas de livraison.'
        : livraison ? `Livraison facturée ${euros(livraison)}.` : 'Livraison offerte. Tapez un prix si elle est payante.', livre && !livraison ? 'attention' : '');
    resultat(aideDe(champDevis('assemblyFee')), !monte ? 'Pas de montage.'
        : montage ? `Montage facturé ${euros(montage)}.` : "Montage offert. Tapez un prix s'il est payant.", monte && !montage ? 'attention' : '');
    const client = clientDe();
    resultat(form.querySelector('[data-adresse-livraison]'), !livre ? 'Sans objet pour un retrait.' : !client ? "Choisissez d'abord le client."
        : client.address ? `${client.address} (fiche client)` : 'Adresse absente de la fiche client. Complétez la fiche.', livre && client && !client.address ? 'attention' : '');
    majDelai();

    const ttc = totalArticles - globale.montant + livraison + montage;
    const ht = Math.round(ttc * 100 / (100 + Number(form.dataset.tva)));

    // L'acompte : le taux du devis (le montant suit le total en direct), ou un autre montant convenu, vérifié tout de suite
    const taux = Number(form.dataset.acompte);
    const calcule = Math.round(ttc * taux / 100);
    form.querySelector('[data-acompte-calcule]').textContent = euros(calcule);
    const champAcompte = champDevis('depositAmount');
    const autre = champDevis('depositMode').value === 'montant';
    champAcompte.disabled = !autre;
    const saisi = centimes(champAcompte.value);
    const acompte = autre && champAcompte.value !== '' && saisi > 0 ? saisi : calcule;
    const erreurAcompte = !autre || champAcompte.value === '' ? ''
        : saisi <= 0 ? `Indiquez un montant supérieur à 0, ou choisissez ${taux.toLocaleString('fr-FR')} % du total.`
        : saisi > ttc ? `L'acompte (${euros(saisi)}) dépasse le total TTC (${euros(ttc)}). Indiquez un montant plus petit.` : '';
    resultat(aideDe(champAcompte), erreurAcompte || (!autre ? 'Calculé sur le total TTC, livraison et montage compris.'
        : champAcompte.value === '' ? `Tapez le montant convenu avec le client. Vide, l'acompte reste à ${taux.toLocaleString('fr-FR')} %.`
        : `Soit ${pourcent(saisi, ttc)} % du total TTC.`), erreurAcompte ? 'faux' : '');
    if (erreurAcompte) problemes.push({ texte: `Acompte : ${saisi <= 0 ? 'indiquez un montant supérieur à 0' : 'il dépasse le total TTC'}`, cible: champAcompte.id });

    const montants = { items: totalArticles, discount: globale.montant, delivery: livraison, assembly: montage, ttc, ht, tva: ttc - ht, deposit: acompte, balance: ttc - acompte };
    Object.entries(montants).forEach(([cle, montant]) => { cellule(cle).textContent = (cle === 'discount' && montant ? '-' : '') + euros(montant); });
    cellule('delivery').textContent = !livre ? 'retrait au showroom' : livraison ? euros(livraison) : 'offerte';
    cellule('assembly').textContent = !monte ? 'sans montage' : montage ? euros(montage) : 'offert';
    form.querySelector('[data-part-acompte]').textContent = `${acompte !== calcule ? pourcent(acompte, ttc) : taux.toLocaleString('fr-FR')} %`;
    form.querySelector('[data-moment-solde]').textContent = livre ? 'à la livraison' : 'au retrait';
    ['ttc', 'deposit', 'balance'].forEach((cle) => { form.querySelector(`[data-barre="${cle}"]`).textContent = cellule(cle).textContent; });

    // Avant d'enregistrer : ce qui est prêt, ce qui reste à faire (en lien vers le champ)
    const nombre = articlesActifs().length;
    const points = [
        client ? { texte: `Client : ${client.name}` } : { texte: 'Choisissez le client', cible: `${champDevis('customer').id}_recherche` },
        nombre ? { texte: nombreDe(nombre, 'article') } : { texte: 'Ajoutez au moins un article', cible: 'ajouter-article' },
        ...problemes,
        champDevis('leadTime').value.trim() ? { texte: 'Délai de livraison indiqué' } : { texte: 'Indiquez le délai de livraison', cible: champDevis('leadTime').id },
        ...(erreurAcompte ? [] : [{ texte: `Acompte : ${euros(acompte)}` }]),
    ];
    form.querySelector('[data-verifs]').replaceChildren(...points.map(({ texte, cible }) => {
        const point = Object.assign(document.createElement('li'), { className: cible ? 'ko' : 'ok' });
        point.append(cible ? Object.assign(document.createElement('a'), { href: `#${cible}`, textContent: texte }) : texte);
        if (cible) point.firstChild.dataset.aller = cible;
        return point;
    }));
}

// Délai : ce que dit le catalogue des articles choisis, et un raccourci vers le plus long
function majDelai() {
    const delais = [];
    let mini = 0;
    let maxi = 0;
    articlesActifs().forEach((article) => {
        const produit = produitDe(article);
        if (!produit) return;
        if (!produit.leadTime) {
            delais.push(`${produit.name} : aucun délai indiqué`);
            return;
        }
        delais.push(`${produit.name} : ${produit.leadTime[0]} à ${produit.leadTime[1]} semaines`);
        mini = Math.max(mini, produit.leadTime[0]);
        maxi = Math.max(maxi, produit.leadTime[1]);
    });
    formulaire().querySelector('[data-delais-catalogue]').textContent = delais.length ? `Catalogue : ${delais.join(' ; ')}.` : "Aucun article du catalogue pour l'instant.";
    const bouton = formulaire().querySelector('[data-delai-catalogue]');
    bouton.disabled = !maxi;
    bouton.dataset.delai = maxi ? `${mini} à ${maxi} semaines après réception de l'acompte` : '';
    bouton.textContent = maxi ? `Utiliser : ${bouton.dataset.delai}` : 'Utiliser le délai du catalogue';
}

// Un changement : les montants, l'état « non enregistré », et l'annonce des montants sauf pendant la frappe
function apresChangement(dire = true) {
    marquerModifie();
    majStatut();
    recalculer();
    if (dire) annoncer();
}


// ===== Recherche avec liste de résultats (motif « combobox ») =====
// On tape 2 lettres au moins, la liste s'ouvre sous le champ ; flèches haut et bas pour s'y déplacer, Entrée pour
// choisir, Échap pour fermer. Les résultats viennent du serveur (data-url).
const SOURCES = {
    client: {
        texte: (client) => [client.name, client.phone].filter(Boolean).join(' · '),
        vide: 'Aucun client trouvé. Créez sa fiche avec « + Nouveau client ».',
        choisir: choisirClient,
        // Quitter sans choisir : ce qui est affiché redevient le client enregistré avec le devis
        quitter: (input) => { input.value = clientDe()?.name ?? ''; },
    },
    produit: {
        // En premier, une gamme modulable trouvée : « Composer un canapé LUMA (7 modules) »
        texte: (produit) => (produit.composition ? `Composer un canapé ${produit.name} (${nombreDe(produit.modules, 'module')})`
            : `${produit.name} · ${euros(centimes(produit.price))}`),
        etiquette: (produit) => produit.hidden && 'masqué',   // pas encore en ligne : chiffrable, mais à ne pas confondre
        vide: 'Aucun produit du catalogue. Le texte tapé sera le nom imprimé (ligne libre).',
        choisir: (input, produit) => (produit.composition ? ouvrirComposition(input, produit.url) : choisirProduit(input.closest('.article'), produit)),
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
            const etiquette = sourceDe(input).etiquette?.(element);
            if (etiquette) option.append(' ', Object.assign(document.createElement('span'), { className: 'etiquette', textContent: etiquette }));
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
    annoncer(elements.length ? `${nombreDe(elements.length, 'résultat')}.` : 'Aucun résultat.');
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


// ===== Composer un canapé modulable : une fenêtre, un article par module choisi =====
// Le contenu de la fenêtre vient du serveur (QuoteController::composition). Rien ne change dans le devis avant
// « Ajouter au devis » ; Échap ou Annuler ferment sans rien ajouter, le focus revient au champ Article.
const MODULES_MAX = 9;
const dialogueCompo = () => document.getElementById('dialogue-composition');
const modulesCompo = () => [...dialogueCompo().querySelectorAll('.compo-module')];
const nombreModule = (module) => Number(module.querySelector('[data-nombre]').value);
// Une carte encore vide, à l'endroit de la recherche : la composition la remplace
const estVide = (article) => !produitDe(article) && ['label', 'unitPrice', 'finish', 'details'].every((nom) => !champ(article, nom).value.trim());

async function ouvrirComposition(input, url) {
    input.value = input.dataset.avant ?? '';   // le texte d'avant la recherche : la carte reste telle qu'elle était
    const dialogue = dialogueCompo();
    try {
        const reponse = await fetch(url);
        if (!reponse.ok || reponse.redirected) throw new Error(reponse.statusText);   // session expirée : page de connexion
        dialogue.innerHTML = await reponse.text();   // gabarit du serveur, échappé
    } catch {
        annoncer('La composition ne se charge pas. Rechargez la page.');
        return;
    }
    dialogue.article = input.closest('.article');
    dialogue.showModal();
    majComposition();
    dialogue.querySelector('[data-pas="1"]').focus();
}

// Les boutons − et + (0 à 9), le nombre de modules et le total, dits dans la fenêtre
function majComposition(texte = '') {
    let nombre = 0;
    let total = 0;
    modulesCompo().forEach((module) => {
        const n = nombreModule(module);
        module.querySelector('[data-pas="-1"]').setAttribute('aria-disabled', String(n === 0));
        module.querySelector('[data-pas="1"]').setAttribute('aria-disabled', String(n === MODULES_MAX));
        nombre += n;
        total += n * centimes(lireJson(module.dataset.produit).price);
    });
    dialogueCompo().querySelector('[data-compo="ajouter"]').disabled = !nombre;
    // Le module changé n'est dit qu'au lecteur d'écran : à l'écran, son nombre est sous ses yeux
    dialogueCompo().querySelector('[data-compo-somme]').replaceChildren(Object.assign(document.createElement('span'), { className: 'sr-only', textContent: texte }),
        nombre ? `Composition : ${nombreDe(nombre, 'module')}, ${euros(total)}.` : 'Aucun module choisi.');
}

// Un article par module choisi, à la suite, à la fin du devis : produit lié, prix, nombre, tissu commun
function ajouterComposition() {
    const dialogue = dialogueCompo();
    const tissu = dialogue.querySelector('[data-tissu]').value.trim();
    if (estVide(dialogue.article)) dialogue.article.remove();
    const ajoutes = modulesCompo().filter(nombreModule).map((module) => {
        const article = nouvelArticle();
        remplirProduit(article, lireJson(module.dataset.produit));
        champ(article, 'quantity').value = nombreModule(module);
        champ(article, 'finish').value = tissu;
        return article;
    });
    dialogue.close();
    champ(ajoutes[0], 'label').focus();
    annoncer(`Composition ajoutée : ${nombreDe(ajoutes.length, 'article')}, à partir de l'article ${ajoutes[0].dataset.numero}.`);
    apresChangement();
}

document.addEventListener('click', (e) => {
    const bouton = e.target.closest('#dialogue-composition button');
    if (!bouton) return;
    if (bouton.dataset.compo) {
        bouton.dataset.compo === 'ajouter' ? ajouterComposition() : dialogueCompo().close();
        return;
    }
    if (bouton.getAttribute('aria-disabled') === 'true') return;
    const module = bouton.closest('.compo-module');
    const nombre = module.querySelector('[data-nombre]');
    nombre.value = nombreModule(module) + Number(bouton.dataset.pas);
    majComposition(`${module.querySelector('.compo-nom').textContent} : ${nombre.value}. `);
});


// ===== Les gestes du vendeur =====
// Le texte du champ Article quand on y entre : remis si la recherche ouvre la composition
document.addEventListener('focusin', (e) => {
    if (e.target.matches?.('#form-devis [data-source="produit"]')) e.target.dataset.avant = e.target.value;
});

document.addEventListener('input', (e) => {
    if (!e.target.closest('#form-devis')) return;
    effacerErreur(e.target);
    if (e.target.matches('[role="combobox"]')) chercher(e.target);
    else apresChangement(false);
});

document.addEventListener('change', (e) => {
    const cible = e.target;
    // Un champ caché ne change que par ce fichier, ou par le jeton CSRF de Symfony à l'envoi : pas une modification.
    // La recherche du client non plus : seul le choix d'un client compte.
    if (!cible.closest('#form-devis') || cible.matches('[data-source="client"], [type="hidden"]')) return;
    effacerErreur(cible);
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
    // Un autre acompte laissé vide : retour au taux du devis, dit à la même place
    if (cible === champDevis('depositAmount') && cible.value === '' && champDevis('depositMode').value === 'montant') {
        formulaire().querySelector('[name="quote[depositMode]"][value="taux"]').checked = true;
        apresChangement();
        resultat(aideDe(cible), `Montant vide : l'acompte reste à ${Number(formulaire().dataset.acompte).toLocaleString('fr-FR')} % du total.`);
    }
});

document.addEventListener('keydown', (e) => {
    // Entrée dans un champ n'envoie pas le formulaire : on enregistre avec le bouton
    if (e.key === 'Enter' && e.target.matches?.('#form-devis input') && e.target.getAttribute('aria-expanded') !== 'true') {
        e.preventDefault();
        return;
    }
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
        delai: () => {
            champDevis('leadTime').value = bouton.dataset.delai;
            champDevis('leadTime').focus();
            apresChangement();
        },
    })[bouton.dataset.action]();
});

// La molette ne change pas un prix pendant qu'on fait défiler la page : le champ perd le focus au premier cran
document.addEventListener('wheel', (e) => {
    if (e.target === document.activeElement && e.target.matches('#form-devis input[type="number"]')) e.target.blur();
}, { passive: true });

// À l'affichage, et après un enregistrement refusé : alors les modifications ne sont pas enregistrées, et le focus
// va sur le résumé des erreurs
function demarrer() {
    if (!formulaire()) return;
    const resume = document.getElementById('resume-erreurs');
    modifiee = !!resume;
    majFiche();
    articles().forEach(preparer);
    majStatut();
    recalculer();
    derniersMontants = texteMontants();
    majResume();
    resume?.focus();
}
document.addEventListener('turbo:load', demarrer);
