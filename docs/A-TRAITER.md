# 📋 À TRAITER — points mis de côté

> Tout ce qu'on a volontairement **reporté** pour ne pas se disperser.
> On y revient **si le temps le permet**, dans l'ordre de priorité.
>
> Règle : quand un point est traité → le déplacer dans `AVANCEMENT.md` et le supprimer d'ici.
> Quand un point est **abandonné** → le passer dans « Décisions actées » en bas, avec la raison.

---

## 🔴 Priorité haute — à faire avant la démo si possible

### 1. Slug automatique — ✅ FAIT sur Product (21/07)
- Reste à appliquer la même recette à **`Category`** et **`SubCategory`** (5 min chacune, aucune migration).
- ⏭️ **Slug éditable en admin (IMPORTANT — confirmé nécessaire le 24/07)** : aujourd'hui, renommer une catégorie/produit ne met pas à jour le slug (généré à la création seulement) → obligée de corriger EN BASE (SQL), pas viable. Solution retenue = **remettre le champ `slug` dans les formulaires, mais FACULTATIF** : vide → généré auto (callback) ; rempli → on respecte la saisie (comme WordPress/Shopify). Garde la stabilité des URLs tout en laissant la main à l'admin. À faire avant la mise en prod. (Cas rencontré : catégorie « Tête de lit » renommée « Literie » gardait le slug `tete-de-lit`, corrigé en SQL.)

### 1bis. Médias — points d'architecture (décidés le 22/07)
- **Pas de `show` ni d'`index` pour Media** : une photo n'a pas de détail à consulter seule (déjà visible sur la fiche produit). On ne crée que les actions utiles : ajouter / afficher / supprimer.
- **Edit Media = métadonnées seulement** (décidé par Serife le 22/07) : on modifie `alt` / `isMain` / `position`, **pas le fichier** (le fichier se remplace par supprimer + ré-uploader). Réutiliser `MediaType` mais rendre `imageFile` **facultatif en mode edit** via une **option de formulaire** (Vich garde l'image existante si aucun nouveau fichier n'est envoyé). À faire après le delete.
- **✅ Fichiers orphelins — RÉGLÉ et TESTÉ (22/07)** : suppression d'une photo → le fichier physique part avec la ligne (Vich). **Remplacement** d'une photo (edit avec nouveau fichier) → l'ancien fichier est aussi supprimé (vérifié : 5 lignes = 5 fichiers, noms concordants, 0 orphelin). NB : il a fallu rendre `Media::setUrl(?string $url)` nullable (Vich remet url à null). ⚠️ Reste à vérifier le seul cas « suppression d'un PRODUIT entier » (cascade orphanRemoval) — s'assurer que chaque Media passe bien par remove (pas un DELETE SQL en masse).
- **✅ Règle métier « une seule photo principale »** : gérée par la méthode privée `MediaController::ensureSingleMainPhoto()` (appelée dans new + edit avant le flush : décoche les autres photos du produit). Tri d'affichage par `#[ORM\OrderBy(['position' => 'ASC'])]` sur `Product::$media`. Validation `position` ≥ 1 (`Assert\Positive` + `min:1` HTML5, défaut entité = 1).
- **⏭️ Ordonnancement des photos (position) — à améliorer** : aujourd'hui l'admin **saisit un numéro à la main** → risque de doublons (deux photos « rang 1 ») et de trous. Approche MVP visée : **auto-assignation à l'upload** (nouvelle photo = position max+1, on retire le champ du formulaire), puis **flèches ↑↓** pour réordonner (2 actions contrôleur qui échangent la position avec la voisine). Drag & drop (Sortable.js + AJAX) = V2. À reprendre si le temps le permet.

### 1ter. Dépréciation LiipImagine (vue le 22/07)
- Warning : `Liip\ImagineBundle\Templating\FilterExtension deprecated since 2.7`. → fix en 1 ligne : dans `config/packages/liip_imagine.yaml`, mettre `liip_imagine.twig.mode: lazy`. À faire en Phase C (config LiipImagine). Sans gravité.

### 2. Pipeline médias — ce qui RESTE (l'upload est FAIT ✅ le 22/07, cf. AVANCEMENT.md)

**2a. LiipImagine — ✅ BASE FAITE (24/07)** : driver gd + WebP + filter set `vignette` (400px) opérationnel (2,2 Mo → 8 Ko). Dépréciation corrigée. RESTE :
- filter set **`galerie`** (1600px) pour la fiche produit + **`og`** (1200×630) pour le partage social.
- **srcset** : `vignette` en plusieurs tailles (400/800/1200) pour le responsive.
- **Macro Twig `image()`** : `srcset` + `width`/`height` obligatoires (évite le décalage de mise en page, CLS=0) + `alt`. Centralise l'affichage image → 1 seul endroit à changer.
- Appliquer partout au **front** (pour l'instant seulement sur `product/show`).
- Alternative à garder en tête : **limiter les dimensions max à l'upload** (ex. refuser > 6000px) plutôt que de monter `memory_limit` — les deux se défendent.
- **Peaufinage Content-Type WebP** (24/07) : le cache LiipImagine garde l'extension `.jpg` alors que le contenu est du WebP → nginx renvoie `Content-Type: image/jpeg` (l'inspecteur affiche « jpeg »). Cosmétique : le navigateur décode par le contenu, le gain de poids est réel (2,2 Mo → 8 Ko). Pour un en-tête correct `image/webp`, configurer le résolveur de cache / la négociation. Pas bloquant.

**2b. Conversion HEIC (photos iPhone)**
- Aujourd'hui : **refusé** proprement par la validation (message FR clair). L'admin convertit (Aperçu → Exporter JPEG, ou iPhone → Réglages/Appareil photo/Formats/« Le plus compatible »).
- Version costaude si le temps le permet : **accepter et convertir automatiquement** → nécessite **Imagick + libheif** dans le Dockerfile.

**2c. Upload de vidéos**
- Reporté volontairement (décidé le 22/07 : « après le front, pas urgent »).
- Même mécanisme VichUploader ; `Media.type` prévoit déjà `photo|video`. Prévoir un mapping séparé + validation de format/poids adaptée (les vidéos sont lourdes → revoir les limites nginx/PHP).

### 2quater. Peaufinage bandeau (28/07)
- Fond bandeau = `var(--txt)` (noir, cohérent footer) + texte blanc. `.band-promo` en `#FF3B30` (uppercase) tire vers l'orange → rouge plus franc = `#E01E1E`/`#D50000` si voulu.
- **Mobile** : messages longs (ex. « Showroom à Bordeaux · 12 bis rue Suffren ») serrés sur petit écran malgré le mobile-first. → garder des **messages courts**, ou clipper. Mineur.
- Mécanisme `type: 'promo'` conservé (colore + majuscules) même si pas forcément utilisé pour l'instant.

### 2ter. Carte Google Maps du showroom — mauvais point (24/07)
- L'iframe Maps (`_showroom.html.twig`, requête `?q=12+bis+rue+Suffren...`) pointe sur « 12 rue Suffren » et pas « 12 bis rue Suffren, Bordeaux Lac ». Google ignore le « bis » / trouve le mauvais Suffren. → Fix : récupérer l'**embed officiel** depuis maps.google.com (Partager → Intégrer une carte → copier l'`<iframe src="...maps/embed?pb=...">` qui encode les **coordonnées GPS exactes**) et remplacer le src actuel. À faire.

### 3. Navigation du back-office
- Aucun menu ni **bouton Déconnexion** sur `/admin` (il faut taper `/logout` à la main).
- À faire en même temps que le stylage admin.

---

## 🟡 Priorité moyenne — confort et qualité

### ⭐ Réordonner les produits en admin par glisser-déposer (demandé fort par Serife 28/07)
- **Base déjà là** : `Product.position` (l'admin saisit un numéro, tri par position ASC dans les requêtes front). → fonctionne mais saisie manuelle.
- **Version voulue** : **drag & drop** dans la liste admin `/admin/product` pour réordonner à la souris (façon Shopify). Nécessite **Sortable.js** (ou lib JS) + un **endpoint AJAX** (`POST /admin/product/reorder`) qui reçoit le nouvel ordre et met à jour les `position` en base. Beau point jury (« l'admin pilote l'ordre de son catalogue »). Même besoin que le réordonnancement des **photos** (mutualiser l'approche). → À faire en **finition back-office** / quand le catalogue est peuplé.

### ⭐ Ajouter `position` à Category (ordre des catégories — pas encore fait, 28/07)
- L'ordre du menu/rangée univers sort par `id` → pas l'ordre voulu (Canapés, Literie, Tables, Chaises, Fauteuils, Rangements). **À faire** : champ `position` (int, défaut 0) sur `Category` + migration + champ IntegerType dans `CategoryType` + trier `AppExtensionRuntime::getNavCategories()` et `HomeController` par `['position' => 'ASC']`. Puis Serife règle les positions dans l'admin. *(Idem produits : drag&drop admin plus tard.)*

### 🎤 ORAL — points JS / déploiement / perf (à retenir + questions jury type)
**Principe JS** : natif d'abord (CSS pour le bandeau, `<details>` pour la FAQ = 0 JS), **JavaScript seulement pour l'interactivité/état** (burger, galerie produit F4, configurateur F4). Moins de code = plus maintenable.
**Déploiement JS** : Symfony **AssetMapper** (importmap) = pas de Node/webpack → JS servi statique. En prod : `php bin/console asset-map:compile`. Aucun souci sur mutualisé o2switch.
**Perf** : JS burger minimal + chargé en **module différé** (`type=module`) → ne bloque pas le rendu. Vrais leviers perf = **images WebP LiipImagine** (fait) + self-host polices/FA (V2).
**Questions jury type à préparer** : « Pourquoi ce choix CSS vs JS pour le bandeau/burger ? » · « Comment ton JS est-il déployé sans build ? » · « Qu'est-ce qui pèse sur le temps de chargement et comment tu l'optimises ? » · « C'est quoi `addEventListener` / un module JS différé ? » · « Pourquoi le burger a besoin d'un état alors que le bandeau non ? »

### Image du hero de l'accueil
- Aujourd'hui : **dégradé placeholder** (`.hero` linear-gradient). À remplacer par une **vraie image lifestyle** (intérieur meublé, ~1920px paysage) en `background-image` + voile sombre (`linear-gradient(rgba(...)) , url()`) + texte passé en blanc.
- ⚠️ **PAS dans la table `media`** (réservée aux photos produits, liées à un Product). Le hero = **asset de design du site** → fichier statique `public/images/hero.jpg` pour le MVP. Version admin-éditable = entité `SiteSetting`/`HomeSection` (cf. vision Mentions/HomeSection) = V2.
- À faire quand Serife a choisi une belle photo (c'est LE visuel principal).

### 📢 CRUD Annonce — bandeau défilant géré en admin (demandé 28/07)
Aujourd'hui : bandeau (`.band` dans `front/base.html.twig`) avec **messages en dur**. Voulu : l'admin écrit/modifie les annonces, les active/désactive, et les programme sur une **période**.
- **Entité `Annonce`** : `text` · `isActive` (bool) · `dateStart` (datetime nullable) · `dateEnd` (datetime nullable) · `position` (int). + migration.
- **CRUD admin** (`make:crud` ou codé main, sous `/admin/annonce`) + réflexes habituels.
- **Affichage bandeau** : requête `AnnonceRepository::findActiveNow()` → `isActive = true AND (dateStart IS NULL OR dateStart <= NOW) AND (dateEnd IS NULL OR dateEnd >= NOW)` triées par `position`. Boucle Twig dans le bandeau (dupliquer la liste pour la boucle marquee sans couture). Dispo dans le layout → via extension Twig (comme `nav_categories()`) ou globals.
- Bénéfice : promos programmées qui apparaissent/disparaissent seules. Beau point jury (contenu piloté par l'admin).
- **Regrouper avec les autres features « admin pilote le contenu »** : Mentions/HomeSection + réordonnancement produits drag&drop → **phase « back-office dynamique » après le cœur du front (F3/F4)**.

### 🎯 VISION SERIFE (24/07) — Mentions + gestion admin de l'accueil (fin de projet si temps)
Objectif : un mini-CMS de vitrine, piloté depuis l'admin (choisir quelles sections, dans quel ordre, activer/désactiver une section pour une durée). Deux briques :
1. **Entité `Mention`** (Nouveauté, Best-seller, Coup de cœur, Mis en avant…) en **ManyToMany avec Product** (table de jonction `product_mention`) → étiquette les produits (badges cartes + contenu des sections).
   - ⚠️ Nuance : « Nouveauté » (date) et « Promo » (prix `actual<initial`) sont **automatiques/calculées** — pas besoin de les cocher. `Mention` sert surtout aux étiquettes **manuelles** (Best-seller, Sélection). OU tout gérer via Mention si contrôle total voulu (choix à faire).
2. **Entité `HomeSection`** (config accueil) : quelle section (liée à une Mention ou une requête), `position` (ordre), `isActive`, période `du`/`au` (activer/désactiver pour une durée). → l'admin réorganise/active la home sans toucher au code. C'est le « 3 sections produits admin » du PLAN-DE-MATCH complet.
Admin CRUD pour les deux. **V2 / fin de projet si le temps le permet.** En attendant : sections en dur dans le template (Nouveautés auto).

### Sections accueil : Promos + Sélection (quand + de produits) — version simple intermédiaire
- **Factoriser** la section produits en fragment `templates/front/_product_section.html.twig` (titre + sous-titre + liste `products`) → réutilisé pour Nouveautés / Promos / Sélection (markup écrit 1 fois).
- **Promos** : `ProductRepository::findOnSale()` → `WHERE actualPrice < initialPrice AND isActive`. Automatique.
- **Sélection / mis en avant** : ajouter champ **`isFeatured`** (bool, défaut false) à `Product` (+ migration) + case dans `ProductType` (« Mettre en avant sur l'accueil ») + `findFeatured()` (`WHERE isFeatured = true`). L'admin choisit sa vitrine (façon « coups de cœur »). *(Vrais best-sellers calculés = V2, quand il y aura des commandes.)*
- Reporté car aujourd'hui seulement 2 produits → sections vides. À faire quand le catalogue est fourni.

### ⭐ Toolbar de filtres complète (prix / matière / couleur + tri) — APRÈS F4, quand le catalogue sera fourni
La maquette `~/Downloads/files_maquettes/desktop/categorie.html` prévoit, en plus des puces « Par type » (**déjà faites** — filtres sous-catégorie, cf. AVANCEMENT « F3+ ») : une **toolbar** avec bouton **« Filtres »** (panneau prix/matière/couleur), bouton **« Tri : Pertinence »**, et un compteur « X produits ». Elle prévoit aussi un **affichage par blocs** (un bloc titré par sous-catégorie + « Voir tous ») au lieu de la grille unique paginée actuelle.
- **Technique = EXACTEMENT celle du filtre sous-catégorie (F3+), répétée par critère** (un `andWhere` conditionnel de plus par filtre) :
  | Filtre | Implémentation |
  |---|---|
  | Prix (min/max) | 2 `andWhere` : `p.actualPrice >= :min` / `<= :max` |
  | Matière (tissu) | `join('p.fabrics','f')` + `andWhere` |
  | Couleur | `join('p.colors','c')` + `andWhere` |
  | Tri | `orderBy` variable (prix ASC/DESC, date, position) |
- **Ce qui coûte** (pas la requête, mais l'UI/UX) : panneau « Filtres » (tiroir sur mobile), **combiner plusieurs filtres dans l'URL** (`?sous-categorie=…&prix_min=…&matiere=…&tri=…`) + les préserver dans la pagination, compteur de résultats.
- **Pourquoi APRÈS F4 / plus tard** : (1) F4 (fiche produit) = cœur du parcours, prioritaire ; (2) un filtre prix/couleur ne se démontre pas sur 4 produits → attendre un catalogue fourni ; (3) le panneau multi-filtres = une vraie session. Beau point jury quand ce sera fait (« filtre à facettes multiples, généralisation du pattern `andWhere` conditionnel »).
- **Affichage par blocs** (maquette) vs grille paginée (actuel) = choix de design à retrancher à ce moment-là (les deux se défendent).

### Variante montrée sur la photo — `shownFabric` / `shownColor` (reporté le 31/07)
- **Distinction repérée** (bonne idée modélisation) : la **variante exposée sur la photo** (1 tissu + 1 couleur précis) VS **toutes les options** commandables (les ManyToMany `fabrics`/`colors`). Aujourd'hui le modèle ne stocke que « toutes les options » ; rien n'indique « celle de la photo ».
- **Solution propre (approche B)** : ajouter sur `Product` deux **ManyToOne** `shownFabric` + `shownColor` = la variante de la photo. Le configurateur la pré-sélectionne, les ManyToMany affichent toutes les options autour. Coût : 2 champs + migration + 2 champs `EntityType` dans `ProductType`.
- **Pourquoi reporté** : jugé trop lourd pour le MVP (31/07). En attendant, le configurateur **pré-sélectionne juste la 1ʳᵉ option par défaut** (sans prétendre que c'est celle de la photo) → le devis WhatsApp n'est jamais vide.
- **Aussi V2** : changer la **photo** selon la couleur cliquée = nécessite **une photo par couleur** (variante visuelle) → lourd, reporté. MVP = photo fixe, le clic alimente seulement le devis.
- Bon point jury quand ce sera fait : « je distingue la variante exposée des options disponibles ».

### ⭐ Section « Le produit chez vous » (photos d'ambiance / chez les clients) — si le temps (idée Serife 31/07)
Sur la fiche produit, une section montrant le produit **installé chez de vrais clients** (preuve sociale, très vendeur pour du meuble), **en plus** de la galerie studio.
- **Plan (réutilise le pipeline média existant Vich + LiipImagine)** :
  1. Champ **`isAmbiance`** (bool, défaut false) sur `Media` + migration → distingue photo studio / photo d'ambiance.
  2. Case à cocher dans le form média admin (« Photo d'ambiance / chez le client »).
  3. **Filtrer la galerie** studio : `{% for media in product.media|filter(m => not m.isAmbiance) %}` (Twig 3 : `|filter`, pas `for...if` déprécié).
  4. **Nouvelle section** « Le produit chez vous » : `product.media|filter(m => m.isAmbiance)`, masquée si vide.
- **Coût** ~1 h, 90 % réutilise l'existant. **Pas MVP-critique** → fin de projet si le temps. Recommandée (la plus distinctive des idées « réseaux/photos »).

### Réseaux sociaux — publier / partager (V2, avec réserves — discuté 31/07)
- **Auto-post vers l'Insta/FB de l'entreprise depuis le site** : ❌ **déconseillé**. = intégration **Meta Graph API** (compte pro, OAuth, tokens qui expirent, **validation d'app par Meta = semaines**), lourd et fragile. L'admin poste en 2 min depuis son téléphone. Bon argument jury : « auto-post réseaux = intégration API tierce hors périmètre MVP ».
- **Fil Instagram de la marque embarqué sur le site** (bloc « Suivez-nous ») : possible mais **script externe + RGPD** (cf. self-host FA/fonts) → **V2**.
- **Boutons de partage visiteur** (FB/Pinterest/WhatsApp/X — ⚠️ **pas Instagram**, aucun lien de partage web) : peu coûteux (~20 min, liens de partage, 0 backend) mais faible priorité. Pinterest pertinent pour du meuble.

### ❓ Limiter le nb de tissus/couleurs affichés sur la fiche + note « et bien plus en boutique » (décision en attente — 31/07)
- Idée : sur la fiche produit, n'afficher que N tissus / N couleurs (`|slice(0, N)` + `{% set maxFabrics/maxColors %}`) et ajouter « et bien plus en boutique » si `length > N` (nudge showroom, cohérent maquette « nuancier complet en showroom »). Évite que la colonne droite s'allonge trop.
- **Statut** : Serife n'a **pas encore décidé** (nombre à fixer). Reporté. En attendant : on affiche **toutes** les options. Ne se teste vraiment qu'avec un catalogue fourni (données actuelles : 1 tissu, 2 couleurs).

### 🎨 Bouton WhatsApp flottant → icône Font Awesome (passe design)
- Le bouton `.wa-float` (dans `front/base.html.twig`) utilise actuellement un SVG inline. Le remplacer par l'icône **Font Awesome** `<i class="fa-brands fa-whatsapp"></i>` (FA déjà chargé pour les réseaux) → cohérence visuelle. À faire dans la **passe design CSS**.

### 4. `createdAt` sur `AdminUser`
- **Jugé important par Serife** (traçabilité : « ce compte a été créé le… »), mis de côté le 21/07.
- Table quasi vide → migration sans risque, ~10 min.
- Décision du jour : on n'horodate **que `Product`** pour l'instant.

### 5. Champ `modules` du formulaire Product
- Actuellement, un produit **apparaît dans sa propre liste de modules** (incohérent).
- Fix : `query_builder` sur le champ → ne proposer que les produits `is_modular = module`, **en excluant le produit courant**.

### 6. Libellés des enums en français
- Les listes déroulantes affichent `No / Yes / Module` et `None / Left / Right` (noms des cas PHP).
- Fix : option `choice_label` sur les champs `EnumType` → *Non / Oui / Module* et *Sans objet / Gauche / Droite*.

### 7. Message « Invalid credentials » en anglais
- À traduire sur la page de login (fichier de traduction ou message personnalisé).

### 8. `seoText` de Category affiché en `<input>` court
- C'est un champ TEXT long → le passer en `TextareaType` dans `CategoryType`.

### 8bis. Relire/adapter les textes SEO des catégories (F6)
- Les metaTitle/metaDescription/seoText proposés le 24/07 mentionnent « fabriqué sur commande », « bois massif »… → **Serife doit vérifier/adapter à sa réalité commerciale** (ce qu'elle vend vraiment). À faire en phase SEO. Idem baseline et textes du front.
- **Passe « contenu SEO » (à faire avec Serife, comme la session juriste)** : rédiger à la main les **meta descriptions** importantes (accueil, grandes catégories, produits phares), ~150-160 car., mots-clés + CTA. En F6 (31/07) on a posé la **STRUCTURE** (bloc `meta_description` + fallback avec `?:`) mais le **fallback par défaut est générique** (pas ciselé). Les vrais textes = passe contenu.

### 8ter. Sauts de ligne description/dimensions (à traiter en F4)
- La saisie multi-lignes de `description`/`dimension` est bien enregistrée (avec ses `\n`), mais `{{ product.description }}` les affiche collés (le HTML écrase les retours à la ligne). Constaté le 30/07 sur `product/show` (admin).
- Fix = filtre Twig `{{ product.description|nl2br }}` (convertit `\n` en `<br>`) OU CSS `white-space: pre-line`. **À appliquer côté FRONT en F4** (accordéons description/dimensions de la fiche produit) — l'affichage admin n'est pas le livrable.

### 9. Tests fonctionnels Symfony
- `symfony/test-pack` est **déjà installé**, rien à ajouter.
- Objectif : 1 ou 2 tests `WebTestCase` sur le CRUD Product (la page répond, la création fonctionne).
- ⚠️ **Pas Postman** : l'application repose sur des formulaires HTML, pas sur une API JSON.
- En attendant : **recette manuelle** documentée + captures (suffisant pour le TP DWWM).

---

## 📝 À produire pour Serife (demandé le 22/07)
- **Session « prépa oral CSS » (demandée 28/07)** : reprendre `assets/styles/app.css` **bloc par bloc**, avec les **questions que le jury peut poser** + réponse pédagogique une par une. Concepts à couvrir : variables CSS (`:root`/`var()`), **flexbox** (`display:flex`, `align-items`, `justify-content`, `flex:1`, `flex-direction`), **grid** (`grid-template-columns: repeat()`), **media queries mobile-first** (`@media (min-width)`), **animations** (`@keyframes` + `transform: translateX` + `animation` du bandeau), `object-fit: cover`, `aspect-ratio`, `background-clip: text` (dégradé Insta), `position: sticky/fixed`, `overflow: hidden`, `-webkit-text-stroke`, `text-shadow`, le sticky footer (`min-height:100dvh`+flex+`.main{flex:1}`), le patron `.section`(extérieur)/`.wrap`(intérieur). + Rappeler le choix Tailwind (pipeline+Preflight, mais classes sémantiques custom, pas d'utilitaires). → À faire en prépa soutenance.
- **Récap de tous ses questionnements** : compiler les questions « pourquoi / comment » posées pendant le dev (findAll vs projection, lazy loading / N+1, callbacks Doctrine, slug AsciiSlugger, MoneyType, EnumType, EntityType, CSRF, multipart/form-data, les 3 couches de limite d'upload, VichUploader, persist vs flush, option de formulaire `require_image`, méthode privée vs service, LiipImagine…) avec la réponse-clé + la phrase pour l'oral. → Matériel direct pour le dossier et la soutenance. À faire quand elle le demande.
- ⚠️ **PAGINATION + objet `Query` = PAS ENCORE DIGÉRÉ** (dit par Serife le 29/07, F3). Elle sait recopier le geste `$paginator->paginate($repo->findXxxQuery(), $request->query->getInt('page',1), N)` mais le **pourquoi** ne coule pas de source. → **La quizzer à l'oral** sur : (1) pourquoi `getQuery()` renvoie une **Query non exécutée** et pas un `getResult()` ? (2) qui ajoute le `LIMIT/OFFSET` et à quel moment ? (3) que se passerait-il si on paginait un `array` déjà chargé (charger toute la table en mémoire) ? (4) d'où vient le n° de page (`?page=` dans l'URL) ? (5) rôle du service `PaginatorInterface` injecté. Reprendre avec l'image « recette pas encore cuisinée ». → À traiter en prépa orale + lui reposer la question spontanément lors des prochaines pages paginées (catégorie F3, recherche F5).

## 🟢 Plus tard / V2

### ⚖️ Pages légales + RGPD — STRUCTURE posée en F5, CONTENU à rédiger plus tard (cadrage 31/07)
- **Décision** : on pose d'abord la **structure** (routes + templates vides + bandeau cookies simple), le **contenu juridique se rédige ensuite ensemble** — Claude en posture « juriste expérimenté » + **recherches web** sur les textes en vigueur (LCEN, CNIL/RGPD, service-public.fr). Objectif : « se mettre à l'abri au max ».
- **Pages MVP** : **Mentions légales** (obligatoire LCEN : identité + SIRET + adresse + contact + hébergeur o2switch + directeur publication) · **Politique de confidentialité** (RGPD, car tiers : Google Maps, Font Awesome CDN, Google Fonts).
- **CGV** = ❌ hors MVP (pas de vente en ligne / paiement) → **V2** avec l'e-commerce. **CGU** = facultatif. Bon point jury : « CGV encadrent la vente en ligne, hors périmètre vitrine ».
- **Bandeau cookies** : version **simple posée dès le début** (bandeau + consentement accepter/refuser stocké). ⚠️ Conformité **totale** (bloquer réellement Maps/FA/fonts tant que pas de consentement) = plus lourd → lié au **self-host FA/fonts** (ci-dessous) et au chargement conditionnel de Maps → à finaliser avec la passe RGPD. Le contenu exact + le gating fin = session « juriste » à venir.
- ⚠️ Rappel : Claude n'est pas juriste — valider les textes finaux (générateurs officiels service-public.fr / CNIL).

### Héberger Font Awesome + Google Fonts en local (RGPD/perf) — APRÈS l'examen (confirmé Serife 24/07 : pas le temps pour le MVP)
- Icônes réseaux via **Font Awesome CDN cloudflare** (`<link>` dans `front/base.html.twig`) + **Montserrat via Google Fonts CDN**. → requêtes vers des tiers (RGPD) + poids. Pour la prod : **télécharger et servir en local** depuis `public/` (ou SVG officiels simple-icons pour les réseaux). À mentionner dans le bandeau cookies / politique de confidentialité. **Non bloquant pour le MVP/examen.**

### 10. Horodatage des autres tables
- `Category` : un `updatedAt` serait utile pour le `<lastmod>` du sitemap → à voir **avec le sitemap**.
- Tables de référence (Fabric, Color, Family, SubCategory, Media) : aucun usage identifié.

### 11. Renommer la capture historique
- Fait ✅ (`env-symfony-accueil-docker.png`).

### 12. Contraintes de validation métier (jamais posées)
- `actualPrice <= initialPrice` (via `Assert\Expression`).
- Un produit `is_modular = module` ne peut pas lui-même avoir des modules.
- Une seule photo `is_main` par produit.

---

## ✅ Décisions actées — ne pas y revenir

| Sujet | Décision | Raison |
|---|---|---|
| **Gedmo** (Sluggable/Timestampable) | ❌ **abandonné** au profit du **natif** | Callbacks Doctrine + `SluggerInterface` : pas de dépendance externe pour quelques lignes, et 100 % explicable au jury. Cohérent avec la démarche « je code et j'explique tout ». |
| **Trait `TimestampableTrait`** | ❌ écarté | Serife préfère éviter cette abstraction. |
| **Horodatage général** | ✅ **`Product` uniquement** | Seule entité dont la fraîcheur sert (nouveautés, tri admin, `lastmod` sitemap). Horodater des tables de référence figées = du bruit. |
| **`findAll()`** | ❌ **banni du projet** | Remplacé par des `findForIndex()` au QueryBuilder avec projection. Vérif : `grep -rn "findAll()" src/` → 0. |
| **`make:crud` / `make:form` pour Product** | ❌ refusés | Codé à la main pour maîtriser et défendre chaque ligne. |
| **Postman** | ❌ écarté | Outil d'API JSON, inadapté à une application à formulaires. |
