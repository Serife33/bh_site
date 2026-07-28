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

### 2ter. Carte Google Maps du showroom — mauvais point (24/07)
- L'iframe Maps (`_showroom.html.twig`, requête `?q=12+bis+rue+Suffren...`) pointe sur « 12 rue Suffren » et pas « 12 bis rue Suffren, Bordeaux Lac ». Google ignore le « bis » / trouve le mauvais Suffren. → Fix : récupérer l'**embed officiel** depuis maps.google.com (Partager → Intégrer une carte → copier l'`<iframe src="...maps/embed?pb=...">` qui encode les **coordonnées GPS exactes**) et remplacer le src actuel. À faire.

### 3. Navigation du back-office
- Aucun menu ni **bouton Déconnexion** sur `/admin` (il faut taper `/logout` à la main).
- À faire en même temps que le stylage admin.

---

## 🟡 Priorité moyenne — confort et qualité

### Image du hero de l'accueil
- Aujourd'hui : **dégradé placeholder** (`.hero` linear-gradient). À remplacer par une **vraie image lifestyle** (intérieur meublé, ~1920px paysage) en `background-image` + voile sombre (`linear-gradient(rgba(...)) , url()`) + texte passé en blanc.
- ⚠️ **PAS dans la table `media`** (réservée aux photos produits, liées à un Product). Le hero = **asset de design du site** → fichier statique `public/images/hero.jpg` pour le MVP. Version admin-éditable = entité `SiteSetting`/`HomeSection` (cf. vision Mentions/HomeSection) = V2.
- À faire quand Serife a choisi une belle photo (c'est LE visuel principal).

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

### 9. Tests fonctionnels Symfony
- `symfony/test-pack` est **déjà installé**, rien à ajouter.
- Objectif : 1 ou 2 tests `WebTestCase` sur le CRUD Product (la page répond, la création fonctionne).
- ⚠️ **Pas Postman** : l'application repose sur des formulaires HTML, pas sur une API JSON.
- En attendant : **recette manuelle** documentée + captures (suffisant pour le TP DWWM).

---

## 📝 À produire pour Serife (demandé le 22/07)
- **Récap de tous ses questionnements** : compiler les questions « pourquoi / comment » posées pendant le dev (findAll vs projection, lazy loading / N+1, callbacks Doctrine, slug AsciiSlugger, MoneyType, EnumType, EntityType, CSRF, multipart/form-data, les 3 couches de limite d'upload, VichUploader, persist vs flush, option de formulaire `require_image`, méthode privée vs service, LiipImagine…) avec la réponse-clé + la phrase pour l'oral. → Matériel direct pour le dossier et la soutenance. À faire quand elle le demande.

## 🟢 Plus tard / V2

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
