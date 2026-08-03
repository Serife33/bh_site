# 📌 Brillance Home — Avancement du projet

> Journal de bord pour reprendre le fil rapidement (mis à jour au fil de l'eau).

---
## 🚀 POUR REPRENDRE (nouvelle conversation Claude Code) — LIRE EN PREMIER
0. ⚠️⚠️ **TOUT PREMIER GESTE** : dans `src/Controller/CatalogController.php` ligne 16, `PRODUCTS_PER_PAGE` est **resté à `1`** (valeur de test de la démo pagination laissée en place le 29/07). **LA REMETTRE À `12`** avant toute chose, sinon la page catégorie n'affiche qu'1 produit par page.
1. **Lire ce fichier en entier** + `docs/A-TRAITER.md` (points reportés) + `~/.claude/plans/ticklish-juggling-stroustrup.md` (feuille de route FRONT détaillée, mobile-first).
2. **Mode de travail impératif** : **guidé pas à pas** — Serife TAPE le code elle-même, pose beaucoup de « pourquoi/comment », doit pouvoir tout défendre au jury. **NE PAS coder à sa place** ; expliquer avant/après, la laisser coder, relire.
3. **Où on en est** : back-office + médias + LiipImagine ✅ · **FRONT F1 (fondations) ✅** · **FRONT F2 (accueil + finition layout : menu header dynamique, burger mobile, bandeau défilant) ✅** · **FRONT F3 (page catégorie) ✅** · **FRONT F3+ (filtres sous-catégorie) ✅ (30/07)**. **PROCHAIN PAS = F4 : FICHE PRODUIT** (la + grosse : galerie médias, configurateur affiché tissus/couleurs/variantes/modules, devis WhatsApp pré-rempli, accordéons, produits similaires, SEO metaTitle/metaDescription). Puis **F5** (recherche/contact/légales/404), **F6** (SEO sitemap/robots/JSON-LD).
4. **Rappels front** : mobile-first partout · CSS dans `assets/styles/app.css` compilé par `tailwind:build --watch` (⚠️ **le watch s'arrête souvent** → si « rien ne change » : relancer `tailwind:build` + `Cmd+Shift+R`) · layout front = `templates/front/base.html.twig` (séparé de l'admin) · piège récurrent CSS : ne pas mettre `.section`/`.wrap` (2 paddings) sur le même élément → `.section` (padding vertical+fond) sur l'extérieur, `.wrap` (largeur+padding horizontal) sur un div intérieur.
5. **Après le front** : **DÉPLOIEMENT** (o2switch natif, sans Docker — voir section dédiée) puis **DOSSIER PROJET** (voir section dédiée ci-dessous). Deadline **20 août 2026**.
6. **Réflexe captures** : rappeler à Serife de screenshoter chaque étape pertinente → `docs/captures/` + légende dans `docs/captures/LEGENDES.md` (matière pour le dossier).
---

## Contexte
- E-commerce ameublement **Brillance Home** (vitrine + devis WhatsApp), projet d'examen **TP DWWM** (niveau 5).
- Deadline : **20 août 2026** (appli + dossier). *(Anciennes dates 17/07 et 07/08 obsolètes.)*
- Mode de travail : **guidé pas à pas** — Serife tape le code elle-même et doit pouvoir tout expliquer au jury. Ne pas coder à sa place.
- Projet local : `~/bh_site`
- Docs de conception (MCD/MLD/plans) : `~/Downloads/files_maquettes/` (mvp-mcd.html, mvp-mld.html, mvp-mld.sql, PLAN_DE_MATCH_MVP.md, PLANNING-EXAM.md).

## Décisions techniques actées
- **Docker** : toute l'appli est conteneurisée (choix assumé, bon point jury/portfolio).
- **Symfony 7.4 LTS** (créé avec `--webapp`).
- **MySQL 8.4 LTS** (image Docker).
- BDD MVP = **12 tables** (source de vérité : `mvp-mld.sql`), noms de tables en **anglais**.
- Périmètre MVP : vitrine + WhatsApp, **FR seul**, pas de panier/paiement (→ V2).

## Environnement Docker — FAIT ✅
4 conteneurs orchestrés par `compose.yaml` :

| Service (conteneur) | Image | Rôle | Accès navigateur |
|---|---|---|---|
| `bh_php` | build `docker/php/Dockerfile` (php:8.4-fpm) | exécute Symfony | — |
| `bh_nginx` | nginx:alpine | serveur web | http://localhost:8080 |
| `bh_database` | mysql:8.4 | base de données | (port 3306) |
| `bh_phpmyadmin` | phpmyadmin | admin visuel de la BDD (login `bh_user`/`bh_pass`) | http://localhost:8081 |

**Identifiants MySQL** (dev local, définis dans `compose.yaml`) :
`base = bh_site` · `user = bh_user` · `pass = bh_pass` · `root pass = root` · hôte depuis les conteneurs = **`database`** (nom du service).

**Fichiers Docker créés** :
- `compose.yaml` — le chef d'orchestre (a remplacé le compose PostgreSQL généré par Symfony)
- `docker/php/Dockerfile` — image PHP 8.4 + extensions (pdo_mysql, intl, zip, gd, opcache) + Composer
- `docker/nginx/default.conf` — config nginx → php-fpm, racine sur `public/`

## Checklist d'avancement
- [x] Prérequis Mac (PHP 8.5, Composer, Symfony CLI, Node, Git) — déjà présents
- [x] Ménage disque (Docker supprimé → réinstallé, ~14-16 Go libérés)
- [x] Docker Desktop installé et fonctionnel (Docker 29.x, Compose v5.x)
- [x] Projet Symfony 7.4 créé (`symfony new . --webapp --version=lts`)
- [x] Stack Docker écrite + `docker compose up -d --build` → 4 conteneurs OK
- [x] Vérifié : Symfony sur :8080, Adminer sur :8081
- [x] `.env.local` créé (DATABASE_URL) + connexion testée → **MySQL 8.4.10 OK**
- [x] `.DS_Store` ignoré + commit `chore: configuration Docker` (3e commit)
- [x] Branche renommée `master` → `main`
- [x] **GitHub OK** → dépôt poussé sur https://github.com/Serife33/bh_site (auth par token PAT, mémorisé dans le trousseau Mac). Push suivants : `git push` seul.
- [x] **Dépendances MVP installées** (via `docker compose exec php composer require`) : stof/doctrine-extensions (Gedmo), vich/uploader, liip/imagine, knp-paginator, symfonycasts/tailwind, symfony/rate-limiter + (dev) doctrine-fixtures + zenstruck/foundry. *(NB : hoquet bind-mount Mac pendant l'install → réglé avec `composer install`.)*
- [x] **SOCLE TERMINÉ** ✅

## 📅 Session du 7 juillet — Phase 3 : entités (EN COURS)
- [x] **7 entités créées** (classes PHP) via `make:entity` :
  - `Fabric` (name) · `Color` (name, hex) · `Family` (name) · `SubCategory` (name, slug) · `Category` (name, slug, seoText, metaTitle, metaDescription)
  - `AdminUser` (via **`make:user`** → email/roles/password + `security.yaml` configuré)
  - `Product` (tous les champs scalaires : name, slug, description, dimension **TEXT**, initialPrice/actualPrice **DECIMAL(10,2)**, stock, isCustomMade, isModular, sideLr, leadMin/MaxWeeks, metaTitle, metaDescription, position, isActive)
- [x] **2 enums PHP** créés dans `src/Enum/` : `ProductModular` (no/yes/module) + `ProductSide` (none/left/right) → branchés dans Product (`enumType` + getters/setters + `use`)
- [x] `dimension` passé en **TEXT** (mvp-mld.sql mis à jour aussi) · `actualPrice` corrigé en DECIMAL(10,2)
- [x] **1ʳᵉ migration faite** → seule la table **`fabric`** existe en base (+ `messenger_messages` technique). ⚠️ Les **6 autres entités NE sont PAS encore migrées** (pas de table).
- [x] Visualiseur BDD : **Adminer remplacé par phpMyAdmin** (compose.yaml + force-recreate) → http://localhost:8081
- [x] Commité + poussé ✅

## 📅 Session du 8 juillet — Relations + Media + LES 12 TABLES ✅
- [x] **Relations de `Product`** (via `make:entity Product`) — les 6 :
  - `category` **ManyToOne** (nullable no, inverse `products`, orphanRemoval no)
  - `family` **ManyToOne** (nullable **yes**, inverse `products`)
  - `subCategories` / `fabrics` / `colors` **ManyToMany** (inverses `products`)
  - `modules` **ManyToMany réflexive** (→ `Product` self, **unidirectionnelle** : pas d'inverse)
- [x] **Entité `Media`** : url, alt(nullable), type(string photo/video), isMain(bool), position(int) + **ManyToOne → Product** (nullable no, inverse `medias`, **orphanRemoval yes**)
- [x] **Migration groupée** `Version20260708141607` relue puis appliquée → **LES 12 TABLES EXISTENT** 🎉
  (8 entités + jonctions `product_sub_category`, `product_fabric`, `product_color`, `product_product`)
  ⚠️ Note : la jonction modules s'appelle `product_product` (nom auto Doctrine, colonnes product_source/product_target) au lieu de `product_module` du MLD — fonctionnellement identique.
- [x] Commit + push : `feat: relations Product, entité Media et migration du schéma complet (12 tables)`

## 🧭 Décisions d'ordre (actées le 8/7)
- **Gedmo repoussé** (pas V2, mais APRÈS auth + CRUD) : slug rempli à la main (ou SluggerInterface dans les contrôleurs CRUD) en attendant ; dates gérées au moment voulu. Gedmo Sluggable sera posé AVANT le front (URLs propres).
- **Ordre de la suite : Auth admin → CRUD back-office → Gedmo → fixtures → front.**
- Retard global ~2 jours vs PLANNING-EXAM (setup + design system décalé) ; rythme réel ≈ 1,3× le prévu. Design system sera fait avec le front.

## Mémo commandes (réflexe Docker !)
- **Toute** commande Symfony/Composer se lance DANS le conteneur :
  - `docker compose exec php php bin/console ...`
  - `docker compose exec php composer ...`
- Démarrer les conteneurs : `docker compose up -d`
- Les arrêter : `docker compose down`
- Voir l'état : `docker compose ps`
- Voir les logs : `docker compose logs -f [service]`
- Reconstruire après modif du Dockerfile : `docker compose up -d --build`

## ✅ AUTH ADMIN TERMINÉE (Phase 5) — 13 juillet
Faite **à la main** (pas de `make:security:form-login`) :
- `security.yaml` : firewall `main` + `form_login` (login_path/check_path `app_login`, `enable_csrf`) + `logout` + `access_control ^/admin → ROLE_ADMIN`
- `SecurityController` (routes `/login` app_login, `/logout` app_logout) + `templates/security/login.html.twig` (form HTML manuel : `_username`/`_password`/`_csrf_token`)
- **Commande console `app:create-admin`** (`src/Command/CreateAdminCommand.php`) : injecte EntityManager + UserPasswordHasher → crée un AdminUser haché
- `AdminController` (route `/admin`) — page de test « Hello AdminController »
- ✅ **Testé** : compte créé, login OK, `/admin` accessible avec ROLE_ADMIN (confirmé dans le profiler). Compte actuel : `serifekaragur@gmail.com`.
- Détour résolu : setup **Tailwind** (`tailwind.config.js` + `config/packages/symfonycasts_tailwind.yaml` avec `binary_version: v3.4.17` épinglé pour éviter GitHub 403 + `assets/styles/app.css` en `@tailwind` + `tailwind:build`).
- [ ] 🔴 **À COMMITTER** : `git add . && git commit -m "feat: authentification admin (login, firewall, commande create-admin, page /admin)" && git push`

## 🗂️ CRUD back-office (EN COURS) — 13 juillet

### ⚙️ Les 2 RÉFLEXES à appliquer sur CHAQUE `make:crud` d'entité
1. **Protéger sous `/admin`** : dans le contrôleur généré, changer le préfixe de classe `#[Route('/fabric')]` → `#[Route('/admin/fabric')]` (ligne ~14) → verrouillé par `access_control ^/admin`.
2. **Supprimer le champ `products` parasite** du `...Type.php` généré (c'est le côté **inverse** ManyToMany — on assigne les tissus/couleurs **depuis** le formulaire Product) → garder seulement les vrais champs (+ retirer les `use App\Entity\Product` / `EntityType` devenus inutiles).
   *(Sinon : champ « products » required + vide → au clic Save, le navigateur bloque en silence = « rien ne se passe ».)*

### 🧠 3ᵉ réflexe ajouté : `__toString()`
Sur chaque entité de référence, ajouter `public function __toString(): string { return $this->name ?? ''; }` — indispensable pour l'affichage dans les listes déroulantes du futur form Product (sinon « could not be converted to string »).
NB : le champ `products` parasite n'apparaît **que** sur les entités en **ManyToMany** (Fabric, Color, SubCategory). Les entités en **OneToMany** (Family, Category) n'en ont pas → réflexe 2 inutile pour elles.

- [x] **Fabric** ✅ : route `/admin/fabric`, champ `products` retiré. Testé OK.
- [x] **Color** ✅ : route `/admin/color`, champ `products` retiré, `__toString`. Testé OK.
- [x] **Family** ✅ : route `/admin/family`, `__toString` (pas de champ `products` : OneToMany). Testé OK.
- [x] **SubCategory** ✅ : route `/admin/sub-category`, champ `products` retiré, `__toString`. Testé OK (name+slug saisis main).
- [x] **Category** ✅ : route `/admin/category`, `__toString` (pas de champ `products` : OneToMany). Testé OK. *(seoText affiché en input court — à passer en textarea au stylage admin.)*
- [ ] Plus tard : liens de navigation admin + bouton **Déconnexion** sur `/admin`, traduire « Invalid credentials », seoText Category en textarea
- [ ] Plus tard (peaufinage form Product) : champ `modules` → ajouter un `query_builder` pour ne proposer que les produits `is_modular = module` **et exclure le produit courant** (constaté le 21/07 : un produit s'affiche dans sa propre liste de modules). Idem, franciser les libellés des enums via `choice_label` (No/Yes/Module → Non/Oui/Module, None → Sans objet).

## ⏸️ REPRISE — **FRONT F2 : accueil quasi fait**
**Accueil dynamique FAIT** ✅ : 6 catégories créées · `HomeController` passe `categories`+`produits` (`ProductRepository::findLatestActive`) · `home/index.html.twig` = HERO (dégradé beige, baseline, boutons `.btn`/`.btn-primary`/`.btn-wa`) + rangée **Nos univers** (boucle catégories, ronds `.cat-ring`, grille 3/6 cols) + grille **Nouveautés** (composant `templates/front/_product_card.html.twig` avec photo LiipImagine `vignette`, promo si `actualPrice<initialPrice`, badge stock, `Product::getMainMedia()`). CSS `.section`/`.cats`/`.grid-produits`/`.pcard` en place.
**Accueil ÉTOFFÉ** ✅ : sections dans l'ordre de la maquette — Hero · Nos univers (catégories) · **Nouveautés** · **Réassurance** (`_reassurance`, 4 cartes 2×2 mobile) · **Promotions** · **Expertise** (`_expertise`, bloc sombre + WhatsApp) · **En stock** (masquée si vide) · **Showroom** (`_showroom` : vraie carte Google Maps iframe + réseaux sociaux). Sections produits factorisées dans `_product_section` (titre+sous-titre+products, masquée si vide). `.eyebrow` global. Sticky footer OK.
**Icônes réseaux** : **Font Awesome via CDN** (`<link>` dans `front/base.html.twig`) — `<i class="fa-brands fa-...">`, couleurs de marque en CSS (Insta = dégradé via `background-clip:text`, TikTok = écho cyan/magenta via `text-shadow`, Snapchat = tuile jaune + ghost blanc `-webkit-text-stroke`, WhatsApp vert). ⚠️ CDN → self-host RGPD APRÈS examen (A-TRAITER). Macros `_icons.html.twig` abandonnées au profit de FA.
**BANDEAU défilant FAIT** ✅ : fragment `templates/front/_band.html.twig` (`{% set annonces = [...] %}` liste en dur + boucle ×2), inclus dans `base.html.twig`. CSS marquee : chaque `<span>` en `width:100vw` + `height:34px` (un message à la fois, centré) + `white-space:nowrap`, `animation: band-scroll 55s linear infinite` (translateX 0→-50%, seamless via duplication). ⏭️ deviendra dynamique avec le **CRUD Annonce** (A-TRAITER) : remplacer le `{% set %}` par les annonces actives BDD.
**FINITION LAYOUT FAITE** ✅ : (1) **menu header dynamique** — extension Twig `src/Twig/Extension/AppExtension.php` (déclare `nav_categories`) + `src/Twig/Runtime/AppExtensionRuntime.php` (injecte `CategoryRepository`, `getNavCategories()` = `findBy([], ['id'=>'ASC'])` — passer à `position` quand la colonne sera ajoutée). Header boucle `{% for categorie in nav_categories() %}`. ⚠️ piège rencontré : les 2 fichiers Extension/Runtime avaient leur contenu inversé (namespace doit matcher le dossier). (2) **Burger mobile** : bouton `.burger` + `.nav` en tiroir absolu (mobile) / horizontal (desktop ≥768px), JS dans `assets/app.js` en **délégation d'événement** (`document` addEventListener + `e.target.closest('.burger')`) — robuste (survit aux re-renders, corrige le « marche une fois puis plus »). ferme au clic sur un lien.
**→ F2 (accueil + layout) 100% TERMINÉ.** Liens produits/catégories = `front_home` provisoire → à brancher sur `front_product`/`front_category`.
**FRONT F3 — PAGE CATÉGORIE TERMINÉE ✅ (29 juillet)** :
- **`src/Controller/CatalogController.php`** : `category(string $slug, Request, CategoryRepository, ProductRepository, PaginatorInterface)` — route `/categorie/{slug}` nom `front_category`. Récupère la catégorie via `findOneBy(['slug'=>$slug])`, **404** propre si `null` (`createNotFoundException`). Pagine via KnpPaginator. Constante `PRODUCTS_PER_PAGE` = **doit valoir 12** (multiple de 2/3/4 → grille propre). ⚠️ **ACTUELLEMENT ENCORE À 1** (valeur de test laissée le 29/07 pour la démo pagination) → **à remettre à 12 en début de prochaine session** (voir point 0 du bloc REPRENDRE).
- **`ProductRepository::findActiveByCategoryQuery(Category): Query`** : QueryBuilder `isActive=true` + `p.category = :category` (paramètre lié, anti-injection) + `orderBy position ASC` + `getQuery()` (Query NON exécutée → le paginator ajoute le `LIMIT/OFFSET`). `use App\Entity\Category`.
- **`templates/front/category.html.twig`** (étend `front/base`) : block `title` = `{{ category.metaTitle ?? (category.name ~ ' — Brillance Home') }}` — ⚠️ **parenthèses obligatoires** : en Twig `??` est prioritaire sur `~`, sans parenthèses le suffixe se collait AUSSI au metaTitle (doublon « Brillance Home »). Fil d'Ariane `<nav class="breadcrumb" aria-label>` (Accueil › nom, chevron `aria-hidden`). `<h1>` = nom cat + texte SEO si présent. Grille `.grid-produits` avec boucle `{% for product in pagination %}` + `{% include '_product_card' %}`, `{% else %}` = état vide `.empty`. `{{ knp_pagination_render(pagination) }}`.
- **CSS ajouté** (`assets/styles/app.css`) : `.breadcrumb` (flex, `--txt2`, hover `--accent`), `.empty` (`grid-column: 1/-1`), `.pagination` (pastilles `a`/`span`, `.current` fond noir/blanc = page active). Compilé via `tailwind:build`.
- **Liens câblés** : header `nav_categories()` + rangée « Nos univers » (`home/index.html.twig`) → `path('front_category', { slug: categorie.slug })` (avant : `front_home` provisoire).
- **Vérifié navigateur** : `/categorie/canapes` = état vide OK (0 produit actif) ; `/categorie/literie` = 2 cartes WebP + promo ; pagination testée (`?page=2` → 2ᵉ produit, page active bascule). **Capture dossier** : `docs/captures/front-categorie-pagination.png` (+ légende) générée en headless Chrome.
- ⚠️ **Point à retravailler (oral)** : la **pagination + l'objet `Query`** ne sont pas encore parfaitement digérés par Serife (noté dans A-TRAITER § prépa orale) → la quizzer sur les prochaines listes paginées (F5 recherche).

## 🏷️ FRONT F3+ — FILTRES SOUS-CATÉGORIE ✅ TERMINÉ (30/07)
**FAIT ✅** : puces sous-catégorie sur la page catégorie — n'affiche que les sous-cat *utiles* (`SubCategoryRepository::findUsedInCategory` avec join ManyToMany, exclut les vides + celles d'autres catégories) · clic → filtre la grille (`?sous-categorie=slug` → `CatalogController` lit le param via `get()` → `findOneBy(slug)` → passé en 2ᵉ arg optionnel à `ProductRepository::findActiveByCategoryQuery($category, ?SubCategory)` qui ajoute `join('p.subCategories','sc')` + `andWhere` **conditionnel**, motif pagination préservé) · puce active en noir (`.chip.is-active`, ternaire Twig `currentSubCategory.id == sc.id`) · « Tous » = reset (lien sans param, actif si `currentSubCategory is null`) · robustesse slug bidon (`findOneBy` null → pas de filtre). **Testé OK sur Canapés** (3 places → 1, D'angle → 1, Tous → 2). 🕗 RESTE (non testable avec 2 produits) : vérifier que KnpPaginator préserve `?sous-categorie=` sur page 2 (préservation par défaut attendue) — retester quand catalogue fourni. ⏭️ Explications détaillées + matériel oral dans les sous-sections ci-dessous (DQL→SQL, voyage d'un clic).
**Décision (29/07)** : on a ajouté le **filtrage par sous-catégorie** sur la page catégorie **avant** la fiche produit. Raison de Serife : attaquer le morceau « long » tant qu'il reste du temps (deadline 20/08 large), pas en fin de projet.

**Pourquoi les sous-catégories comptent ICI (à verbaliser jury)** : `Category` = **ManyToOne** (obligatoire, 1 seule, l'ossature du menu). `SubCategory` = **ManyToMany** (optionnel, plusieurs par produit, = étiquettes de raffinement type « 3 places », « convertible », « d'angle »). Serife refuse les titres à la Amazon (« Canapé Oslo 3 places convertible ») → noms courts et élégants (« Canapé Oslo ») cohérents avec sa DA (Micadoni/Japandi). Du coup la spec « 3 places » NE vit PAS dans le nom → elle vit soit dans la **description** (trouvable par la recherche F5), soit en **sous-catégorie** (filtre cliquable, fiable). Serife veut le filtre cliquable.

**État de départ** : entité `SubCategory` (name+slug, slug auto) ✅ · CRUD admin `/admin/sub-category` ✅ · `Product.subCategories` ManyToMany ✅ · **rien côté front** (à faire).

**Le plan (4 niveaux — on ajoute un filtre à l'entonnoir F3)** : slug → produits de la catégorie → **FILTRE sous-catégorie (nouveau)** → paginés. Même motif `Query` + paginator que F3, avec un `andWhere` conditionnel en plus.

**Choix actés (simplicité MVP)** :
1. Filtre = **paramètre d'URL** `?sous-categorie=slug` (à côté de `?page`) → pagination + filtre cohabitent, URL partageable/SEO.
2. **Une seule** sous-catégorie à la fois (clic simple) ; multi-sélection = V2.
3. N'afficher que les **puces utiles** (sous-cat ayant ≥1 produit actif dans cette catégorie) — option : simplifier au début (toutes les sous-cat) puis raffiner.

**Ordre de construction (incrémental, chaque étape testable)** :
- **Étape 0** — données : créer 2-3 sous-cat en admin + les cocher sur les produits (sinon rien à afficher ; catégorie `literie` a des produits = bon terrain).
- **Étape 1** — AFFICHER les puces (sans filtrer) : contrôleur passe les sous-cat, template les dessine.
- **Étape 2** — FILTRER : puces cliquables (`?sous-categorie=`), contrôleur lit le param, repository ajoute `andWhere`.
- **Étape 3** — FINITIONS : puce active en noir, bouton « Tous » (reset), **pagination qui PRÉSERVE le filtre** (piège : sinon « page 2 » perd le filtre).

**Estimation guidée** : ≈ **2 h 30 – 3 h** (1 session sérieuse). Bien plus petit que F4. ⚠️ Démo peu spectaculaire tant que le catalogue = 2 produits (ne pas être déçue du rendu ; le code marchera).
**Point oral** : réutilise le motif pagination (Query non exécutée + `andWhere` conditionnel) → bonne occasion de re-quizzer Serife sur la pagination (point pas encore digéré, cf. A-TRAITER).

### 🎤 ORAL — DQL → SQL : le `join` sur un ManyToMany (méthode `SubCategoryRepository::findUsedInCategory`)
Méthode qui liste les sous-catégories **utiles** d'une catégorie (pour les puces de filtre). DQL :
```php
$this->createQueryBuilder('sc')
    ->join('sc.products', 'p')            // traverse le ManyToMany SubCategory↔Product
    ->andWhere('p.category = :category')
    ->andWhere('p.isActive = true')
    ->setParameter('category', $category)
    ->distinct()
    ->orderBy('sc.name', 'ASC')
    ->getQuery()->getResult();            // exécuté direct (peu de lignes) → PAS de pagination ici
```
**SQL réellement exécuté** :
```sql
SELECT DISTINCT s.id, s.name, s.slug
FROM sub_category s
INNER JOIN product_sub_category ps ON s.id = ps.sub_category_id   -- ①
INNER JOIN product p             ON p.id = ps.product_id          -- ②
WHERE p.category_id = 4 AND p.is_active = 1
ORDER BY s.name ASC;
```
**LE point clé à dire au jury** : `Category` et `SubCategory` ne sont **pas liées directement** — seulement via `Product`. Donc pour lister les sous-cat d'une catégorie, on **traverse la relation ManyToMany** avec un `join` → c'est **structurellement nécessaire**, pas juste pour écarter les sous-cat vides (bonus).
**Ce qui coince souvent** : **1 seul `join('sc.products','p')` en DQL = 2 `INNER JOIN` en SQL**, car un ManyToMany est stocké dans une **table de jonction cachée** (`product_sub_category`, colonnes `product_id`/`sub_category_id`). Doctrine masque cette table → tu raisonnes en objets, il traduit en 2 sauts (sub_category → jonction → product). C'est tout l'intérêt de l'ORM.
**Détails vocabulaire** : `p.category` (objet) → `p.category_id` (colonne FK) en SQL. `INNER JOIN` = ne garde que les lignes ayant une correspondance des 2 côtés → c'est ça qui exclut « Convertible » (0 produit → rien à joindre). `DISTINCT` = 1 sous-cat une seule fois même si plusieurs produits.
**Vérifié sur données réelles (30/07)** : catégorie Canapés (id 4) → renvoie « 3 places », « D'angle », « méridienne » ; PAS « Convertible » (créée mais rattachée à aucun produit). ✅
**Contraste pagination (à verbaliser)** : ici `getResult()` direct (sous-cat = peu nombreuses, bornées) VS produits paginés `getQuery()` non exécutée (peuvent être nombreux). On choisit la technique selon la volumétrie attendue.

### 📖 RÉVISION — Le voyage d'un clic (filtre sous-catégorie, bout en bout)
Ce qu'on construit à l'étape 2 = **brancher un clic (URL) à une requête filtrée**. Trajet quand le visiteur clique la puce « 3 places » :
```
① NAVIGATEUR : clic « 3 places » → URL = /categorie/canapes?sous-categorie=3-places
② CONTRÔLEUR (CatalogController) — lit DEUX infos dans l'URL :
     • slug "canapes"        → findOneBy → objet Category « Canapés »
     • ?sous-categorie "3-places" → findOneBy → objet SubCategory « 3 places »
   puis commande au repository : « produits actifs de Canapés, filtrés 3 places »
③ REPOSITORY (findActiveByCategoryQuery) — base isActive+category ; comme une sous-cat
   est fournie → AJOUTE join('p.subCategories','sc') + andWhere('sc = :subCategory')
   → renvoie la Query NON exécutée
④ PAGINATOR — ajoute LIMIT 12, exécute → 1 seul produit
⑤ TEMPLATE — affiche la grille filtrée
```
**Rôle du contrôleur** : il ne filtre pas lui-même, il ne parle pas SQL. Il **traduit l'URL en objets** (`get('sous-categorie')` → slug → `findOneBy` → objet SubCategory) et **passe la commande** au repository qui, lui, sait filtrer.
**Le pivot `null` = "pas de filtre"** (LE point clé) :
| Situation | ?sous-categorie | $currentSubCategory | Repository |
|---|---|---|---|
| arrivée normale / clic « Tous » | absent | **null** | PAS de filtre → tous les produits |
| clic « 3 places » | 3-places | objet SubCategory | join + andWhere → produits étiquetés |
Le contrôleur envoie soit `null` soit un objet ; le `if ($subCategory !== null)` du repository décide d'ajouter le filtre. → **c'est pour ça que le param est facultatif `?SubCategory $subCategory = null`** : le pivot entre « tout montrer » et « filtrer ».
**Robustesse** : `?sous-categorie=nawak` → `findOneBy` renvoie null → pas de filtre, on montre tout (pas de plantage ; même philosophie que le 404 : ne jamais faire confiance à l'URL).
**`get()` vs `getInt()`** : la page est un nombre (`getInt('page',1)`), la sous-cat est un texte/slug (`get('sous-categorie')`) → outil adapté au type.
**Résumé 1 phrase** : on transforme un clic (URL) en requête filtrée, sans jamais charger plus que la page affichée.

## 🛋️ FRONT F4 — FICHE PRODUIT (PROCHAIN PAS — plan arbitré le 30/07)
Route `/produit/{slug}` nom `front_product`. **La + grosse brique du front.**

**⚠️ RÉCONCILIATION MVP** : la maquette `~/Downloads/files_maquettes/desktop/produit.html` a été dessinée pour un **e-commerce complet** (panier, favoris, paiement Alma). Le MVP = **vitrine + devis WhatsApp**, SANS panier/paiement. Le travail de plan = adapter :
- ❌ **Retirés** : « Ajouter au panier », « Favoris », mensualités Alma.
- ✅ **Remplacés par** : **CTA WhatsApp pré-rempli** = LE bouton principal (`wa.me/{{ whatsapp_number }}?text=Bonjour, je suis intéressée par le {{ product.name }}…`). C'est la conversion, à la place du panier.

**Sections (maquette → donnée → MVP)** :
| Section maquette | Donnée | MVP |
|---|---|---|
| Galerie photos | relation `medias` | filtre LiipImagine **`galerie`** (1600px) à créer (comme `vignette`, plus grand) |
| Titre + prix (barré si promo) | `name`, `initialPrice`/`actualPrice` | direct |
| Tissu & coloris (swatches) | `fabrics` + `colors` (ManyToMany) | **configurateur — niveau à décider APRÈS le squelette** (voir ci-dessous) |
| Dimensions (2/3/4 places) | `family` (variantes) | = les frères de famille |
| ~~Panier/Favoris/Alma~~ | — | ❌ retirés → CTA WhatsApp |
| Fabriqué sur commande + délai | `isCustomMade`, `leadMin/MaxWeeks` | direct |
| Savoir-faire (4 cartes) | — | contenu **statique** (comme l'accueil) |
| Accordéons (desc/technique/livraison) | `description`, `dimension` + texte fixe | balise native **`<details>` = 0 JS** ; ⚠️ appliquer `\|nl2br` sur description/dimension (cf. A-TRAITER 8ter) |
| Vous aimerez aussi (similaires) | voir ci-dessous | réutilise `_product_card` |

**DÉCISION configurateur (30/07)** : niveau **à trancher une fois le squelette en place** (option « on voit plus tard »). 3 niveaux possibles : (a) affiché seul 0 JS + WhatsApp pré-rempli avec le nom ; (b) interactif = cliquer tissu/couleur enrichit le message WhatsApp (« Oslo, Bouclé, Ivoire ») + un peu de JS ; (c) décidé plus tard. → on reverra après avoir vu la fiche de base.

**DÉCISION similaires (30/07, choix Serife)** : section « Vous aimerez aussi » = produits de la **même sous-catégorie OU de la même famille**, en **excluant le produit courant**. (Risque connu accepté : vide si le produit est seul dans sa sous-cat ET sa famille → repli « même catégorie » possible plus tard, pas pour le MVP.)

**À construire** : `ProductRepository::findOneActiveBySlug()` + `findSimilar()` · filtre LiipImagine `galerie` · `CatalogController::product()` (slug → produit actif ou 404 → similaires → render) · `templates/front/product.html.twig` · **brancher le lien de `_product_card`** (aujourd'hui `front_home` provisoire) → `front_product` · SEO metaTitle/metaDescription avec fallback.

**Ordre de construction (incrémental, testable à chaque étape)** :
1. **Squelette** ✅ (31/07) : route `/produit/{slug}` + `CatalogController::product()` (`findOneBy(slug+isActive)` → 404) + `templates/front/product.html.twig` (fil d'Ariane 3 niveaux, titre, prix promo, photo). Lien `_product_card` branché sur `front_product` (avant : `front_home` provisoire). Rappel URL FR `/produit` (SEO/UX) vs code EN.
2. **Galerie** ✅ (31/07) : filtre LiipImagine **`galerie`** (1600px, WebP q85, **auto_rotate**) créé. Galerie riche construite en 4 couches : (1) carrousel CSS `scroll-snap` (swipe, 0 JS) ; (2) miniatures cliquables `.gal-thumbs` (filtre vignette) + JS délégué (`scrollTo` offsetLeft) ; (3) flèches ‹ › `.gal-arrow` dans `.gal-stage` (position relative) + JS `scrollBy(clientWidth)` ; (4) synchro miniature active sur `scroll` du viewport (`Math.round(scrollLeft/clientWidth)`, listener direct car scroll ne bouille pas). ⚠️ getter photos = `product.media` (propriété `$media` SINGULIER, `getMedia()`), PAS `medias` (piège rencontré → 500). Piège auto_rotate : chaque filter_set a son PROPRE bloc `filters:` (galerie n'hérite pas de vignette).
3. **Configurateur** (trancher le niveau ici — a/b) : swatches tissus/couleurs.
4. **CTA WhatsApp pré-rempli** (cœur MVP).
5. **Accordéons `<details>`** + savoir-faire statique.
6. **Similaires + variantes famille** + branchement lien `_product_card`.

**PROGRESS F4 (31/07)** : squelette ✅ · galerie ✅ · **configurateur AFFICHAGE ✅** (couche 1 : `.config` avec tissus `product.fabrics` en boutons + couleurs `product.colors` en pastilles `style="background: hex"`, rendu tolérant `hex starts with '#' ? hex : '#' ~ hex` car données parfois sans `#` → valider le hex sur Color = V2) · **CTA WhatsApp ✅** (`btn-devis`, `wa.me/{{ whatsapp_number }}?text={{ (…)|url_encode }}`, pré-rempli nom produit) · **accordéons ✅** (`<details>` natif 0 JS, description/dimension avec `|nl2br`, livraison statique) · **savoir-faire ✅** (statique) · **similaires EN COURS** (bloc 5). RESTE : similaires · **Phase B mise en page 2 colonnes** (`.product` grid, galerie gauche/infos droite en desktop, empilé mobile — content-first puis layout, choix Serife) · **Phase C interactivité** configurateur (sélection au clic → devis WhatsApp dynamique) · SEO metaTitle fallback. DÉCISIONS : pas de « variante montrée sur photo » (shownFabric/shownColor) → V2 (cf. A-TRAITER) ; configurateur = afficher toutes options, 1ʳᵉ pré-sélectionnée par défaut.

### 🎤 ORAL — Doctrine QueryBuilder (méthode `ProductRepository::findSimilar`, à réviser fin de projet)
Méthode : produits similaires = **même sous-catégorie OU même famille**, en s'excluant, actifs, max 4.
- **`$qb`** = le **QueryBuilder**, objet renvoyé par `$this->createQueryBuilder('p')`, qu'on **assemble morceau par morceau** (`andWhere`, `leftJoin`, `setParameter`, `getQuery`). Gardé en **variable** parce qu'on ajoute des morceaux **conditionnellement** (dans des `if`) — même raison que le filtre sous-catégorie F3+.
- **`$qb->expr()`** = le **constructeur d'expressions** (boîte à outils qui fabrique des **bouts de condition** sans écrire de chaîne : `orX`, `andX`, `in`, `eq`, `like`). Analogie : `$qb` = le chef qui assemble la requête ; `expr()` = son plan de travail qui fabrique des fragments qu'on réinjecte via `andWhere`.
- **`orX()`** = groupe de conditions en **OU** → produit `AND ( … OR … )` (parenthèses importantes : le `AND` est prioritaire sur le `OR`, il faut grouper). `andX()` = groupe ET. On l'**alimente dynamiquement** (`->add(...)`) selon ce que le produit possède ; d'où le garde-fou `if ($ou->count() === 0) return []`.
- **`leftJoin` vs `join` (LE point clé)** : `join` (INNER) = « garde SEULEMENT les produits qui ONT une sous-catégorie » → jetterait les produits similaires **par la famille seule** (sans sous-cat). `leftJoin` (LEFT) = « garde TOUT le monde » (sc = null si absent), et c'est le **OU** qui décide. **Règle** : dès qu'une branche du OU (la famille) n'a pas besoin de la jointure, la jointure ne doit pas être obligatoire → `leftJoin`. Contraste avec `findUsedInCategory` (puces F3+) qui utilise `join` INNER car là le lien à la sous-catégorie EST obligatoire.
- **`sc.id IN (:sousCatIds)`** = « la sous-cat du candidat est parmi celles du produit courant » (ids extraits via `$product->getSubCategories()->map(fn($sc) => $sc->getId())->toArray()`). **`p != :current`** = exclure le produit de ses propres similaires.
**Accueil = COMPLET** ✅ (hero, catégories, 3 sections produits, réassurance, expertise, showroom+maps+réseaux, FAQ, CTA WhatsApp, footer).
**Vrais liens réseaux** : href="#" provisoires (WhatsApp OK via global) → Serife met ses URLs.
**Note style** : Serife aime bien le rendu actuel ; option « plus Micadoni » (tuiles-photos catégories, photo de couverture Category) = évoquée, reportée.
Ancien libellé ci-dessous (F2 initial) :
Back-office ✅ + médias/LiipImagine ✅ + **FRONT F1 TERMINÉ** ✅. On reprend à **F2 : page d'accueil dynamique** (row catégories, best-sellers, nouveautés) + composant `_product_card` + sections contenu + menu header dynamique.
📄 Feuille de route front : `~/.claude/plans/ticklish-juggling-stroustrup.md`. Points reportés : `docs/A-TRAITER.md`.

## 🎨 FRONT F1 — FONDATIONS TERMINÉES ✅ (24 juillet)
- **HomeController** : route `/` (nom `front_home`). Front et admin partagent `src/Controller/` (distingués par route/sécurité).
- **Pipeline CSS** compris : on écrit dans `assets/styles/app.css` → importé par `assets/app.js` → compilé par `tailwind:build --watch` → chargé via `{{ importmap('app') }}`. Réflexe : `Cmd+Shift+R` (cache navigateur) quand « rien ne change ».
- **DA** dans `app.css` : tokens (variables CSS `:root` : `--bg`, `--accent`… + `--r`), police **Montserrat** (choix client validé accessibilité : taille 16px/contraste/poids), `.wrap` (conteneur centré mobile-first).
- **Layout `templates/front/base.html.twig`** (séparé de l'admin) : `<head>` (fonts Google Montserrat + `importmap`), header (logo + nav provisoire), `<main class="main">`, footer sombre, bouton WhatsApp flottant. Blocks `title`/`body`.
- **Sticky footer** : `body { min-height:100dvh; display:flex; flex-direction:column }` + `.main { flex:1 }` → footer collé en bas même page vide.
- **Global Twig `whatsapp_number`** (`config/packages/twig.yaml` globals ← `%env(WHATSAPP_NUMBER)%`, valeur dans `.env`) → dispo dans tous les templates (layout compris). Lien WhatsApp : `https://wa.me/{{ whatsapp_number }}`.
- **Infos boutique validées** (à mettre en globals plus tard, réutilisées footer/contact/showroom/SEO) : `12 bis rue Suffren, 33300 Bordeaux` · `Lun–Sam 11h–18h · Dim 14h–17h` · WhatsApp `33781071071`. Baseline : « Spécialiste de l'ameublement intérieur à Bordeaux — salon, salle à manger, chambre et literie ».
- ⏭️ F2 : menu header dynamique (catégories BDD), `_product_card`, grilles best-sellers/nouveautés, sections contenu.
- ❓ À décider en F2 : catalogue organisé **par pièce** (Salon/Salle à manger/Chambre) ou **par type** (Canapés/Tables/Lits) → impacte le nommage des catégories en base.

## 🗺️ ÉTAT GLOBAL DU PROJET (au 24 juillet)
**FAIT** : env Docker · BDD 12 tables · auth admin · 6 CRUD (dont Product codé main) · zéro findAll (projections) · horodatage + slug auto (natif) · **médias complets** (upload VichUploader + validation + suppression + LiipImagine WebP).
**RESTE avant le 20 août** (3 gros chantiers) :
1. **FRONT** (~16-21 h guidé) — vitrine, voir feuille de route.
2. **DÉPLOIEMENT** (~2-3 j) — voir section ci-dessous.
3. **DOSSIER** projet ~30-40 p. (~1-1,5 sem.) — matière déjà accumulée (ce journal + A-TRAITER + captures/LEGENDES + futur récap questionnements).
Ordre conseillé : **front → déploiement → dossier** (le déploiement donne l'URL live pour le dossier). Deadline 20 août = large.

## 🎨 PLAN FRONT — l'essentiel (détail dans la feuille de route)
- **Direction artistique** : benchmark Serife (Micadoni, Bobochic, **Japandi**) + tokens de ses maquettes (`~/Downloads/files_maquettes/desktop|mobile/*.html`). Neutres chauds, Playfair+Inter, **photo produit reine**. Objectif : rendu **authentique, pas "IA"**.
- **⚠️ MOBILE-FIRST** intégré dès le début (base = mobile, header burger/tiroir, `@media min-width` pour desktop ; 44px tactile, inputs 16px). Le responsive n'est PAS une phase finale.
- **MVP** : pas de panier/favoris/paiement → **devis WhatsApp pré-rempli** (`wa.me/NUMÉRO?text=…`, `WHATSAPP_NUMBER` en env/global Twig). FR seul.
- **Layout séparé** : créer `templates/front/base.html.twig` (NE PAS écraser le `base.html.twig` de l'admin).
- **Routes front** préfixées `front_` (évite collision avec l'admin) : `/` `/categorie/{slug}` `/produit/{slug}` `/recherche` `/contact` `/mentions-legales` `/sitemap.xml`.
- **Réutilise** : LiipImagine (ajouter filtres `card`/`galerie`/`og`), KnpPaginator, entités/repos existants (+ méthodes `findLatestActive`, `findActiveByCategoryQuery`, `findOneActiveBySlug`, `findSimilar`, `searchActiveQuery`, `CategoryRepository::findOneBySlug`/`findAllForNav`).
- **Périmètre** : tout (accueil, catégorie, produit, recherche, contact WhatsApp-first, légales, 404, SEO sitemap/robots/JSON-LD).
- Phasage F1→F6. Répartition : CSS/markup = porté de ses maquettes (elle relit) ; câblage dynamique = pas-à-pas.

## 🚀 DÉPLOIEMENT — à préparer (points clés)
- **Docker = DEV uniquement**, pas une dépendance de prod. L'appli Symfony est portable → tourne sur du PHP+MySQL natifs.
- **o2switch** (mutualisé, ~7 €/mois, FR) = **ne gère PAS Docker** (pas de root), MAIS convient très bien : on y déploie l'appli en **natif** (PHP + MySQL fournis). Choix simple/pas cher pour un MVP d'examen.
  - ⚠️ À vérifier avant : **PHP ≥ 8.2** dispo (Symfony 7.4), **GD compilé avec WebP** (pour LiipImagine), config PHP ajustable (memory_limit 512M, upload 16M).
  - Étapes : créer BDD MySQL (cPanel) → envoyer le code (git/SSH) → `composer install --no-dev --optimize-autoloader` → `.env.local` prod (`APP_ENV=prod` + `DATABASE_URL` o2switch) → **`php bin/console asset-map:compile`** (AssetMapper : dump JS/CSS dans `public/assets/`, pas de Node/build nécessaire → le JS burger etc. marche en mutualisé) → document root sur `public/` → migrations + `cache:clear --env=prod` → permissions (`var/`, `public/uploads/`, `public/media/cache/`).
  - 💡 JS = **AssetMapper natif** (pas de build webpack/npm), servi statique, chargé en module différé → **aucun souci déploiement ni perf**.
  - ⚠️ **Tailwind** : le binaire télécharge depuis internet → prévoir de **compiler le CSS en local** et l'uploader (ne pas dépendre du build sur le mutualisé). Idem, `.env.local` de prod jamais commité.
- **Alternative si Docker en prod voulu** : VPS (Hetzner ~4 €, OVH) avec droits root. Plus de travail, pas nécessaire pour le MVP.
- 🎤 Argument jury : « Docker en dev pour un environnement reproductible ; déploiement sur mutualisé natif car l'appli est portable — Docker n'est pas une dépendance de prod. »

## 📄 DOSSIER PROJET — à rédiger (deadline 20 août)
Dossier écrit ~30-40 p. pour le **TP DWWM**, à présenter/soutenir. **Ordre conseillé : front → déploiement → dossier** (le déploiement donne l'URL live + captures finales). La rédaction se fait **avec la matière déjà accumulée** (ne pas partir d'une page blanche).

**Matière première déjà constituée** :
- `docs/AVANCEMENT.md` (ce journal = tout l'historique technique + les « pourquoi »)
- `docs/A-TRAITER.md` (décisions actées + arbitrages + perspectives V2 → parfait pour la partie « choix techniques » et « évolutions »)
- `docs/captures/` + `docs/captures/LEGENDES.md` (captures **déjà légendées** : montre / prouve / partie du dossier + phrases pour l'oral)
- Les **maquettes** `~/Downloads/files_maquettes/` + MCD/MLD (`mvp-mld.sql`, `mvp-mcd.html`).

**À PRODUIRE pour Serife (demandé, pas encore fait)** : un **récap de tous ses questionnements** (les « pourquoi/comment » du dev) avec réponse-clé + phrase pour l'oral → matière directe pour le dossier et la soutenance. Liste des sujets déjà dans `A-TRAITER.md` (section « À produire pour Serife »). **À faire quand elle le demande.**

**Structure type d'un dossier DWWM** (à adapter au réel de Serife) :
1. Présentation (contexte, entreprise/client, expression du besoin, périmètre MVP vitrine+WhatsApp).
2. Gestion de projet (planning, choix du MVP, priorisation, outils : Git/GitHub, Docker).
3. Spécifications & conception : maquettes (mobile-first), **MCD/MLD** (12 tables), règles métier (stock 0 = sur commande, promo, module=produit…).
4. Réalisation **front-end** (bloc 1 DWWM) : intégration mobile-first, composants Twig réutilisables, responsive, accessibilité (Montserrat/contraste/alt), CTA WhatsApp.
5. Réalisation **back-end** (bloc 2 DWWM) : Symfony (contrôleurs, routes, entités Doctrine, formulaires), **CRUD Product codé main**, accès aux données (projections, pas de findAll, pagination), **sécurité** (auth admin, CSRF, validation des uploads, access_control), **médias** (VichUploader + LiipImagine WebP, les 3 couches de limite d'upload).
6. **Sécurité** (transversal) : hachage mots de passe, CSRF, validation MIME réelle des fichiers, protection `/admin`.
7. **Jeu d'essai / recette** : cahier de recette manuel + captures (+ 1-2 tests fonctionnels WebTestCase si le temps — PAS Postman car app à formulaires).
8. **Déploiement** (o2switch) + URL live.
9. **Veille / perspectives** (V2) : Mentions+HomeSection (mini-CMS), i18n EN, paiement, slug éditable, self-host FA/fonts (RGPD), etc. → tout est dans `A-TRAITER.md`.
10. Bilan / compétences DWWM couvertes.

⚠️ **Adapter tous les textes à la réalité commerciale de Serife** (ce qu'elle vend vraiment) — cf. `A-TRAITER` point 8bis.

## ⚡ OPTIMISATION IMAGES — LiipImagine TERMINÉ ✅ (24 juillet)
- Config `config/packages/liip_imagine.yaml` : driver **gd**, `twig.mode: lazy` (corrige la dépréciation), filter set **`vignette`** (`format: webp`, `quality: 82`, `thumbnail size:[400,400] mode:inset` = ratio préservé).
- Affichage : `{{ vich_uploader_asset(media,'imageFile') | imagine_filter('vignette') }}` dans `product/show.html.twig` (width="200" retiré, LiipImagine gère la taille). Cache dans `public/media/cache/` (ignoré par git).
- **2 galères d'environnement résolues (Dockerfile)** :
  1. GD compilé **sans WebP** → erreur « Creating an image in webp not supported ». Fix : `libwebp-dev` + `--with-webp` sur `docker-php-ext-configure gd` + rebuild.
  2. Images 22-30 MP → GD sature la RAM (bitmap = L×H×4 octets ≈ 121 Mo pour du 5500² > 128M). Fix : `memory_limit = 512M` dans `docker/php/uploads.ini`.
- **Résultat mesuré** : litcoffre 2,2 Mo → vignette WebP **8 Ko** (~275×). Réflexe débogage : lire `var/log/dev.log` a donné l'erreur exacte à chaque fois.
- ⏭️ Reste (A-TRAITER) : filter sets `galerie` (1600) + `og` (1200×630), macro Twig `image()` avec srcset + width/height (CLS=0), appliquer au front. Alternative envisagée : limiter les dimensions max à l'upload plutôt que monter la mémoire.

## 🔄 AUTO-ROTATION PHOTOS iPhone (EXIF) — RÉSOLU ✅ (30 juillet)
**Symptôme** : une photo iPhone (parmi 10) s'affichait couchée sur le front (vignette LiipImagine), les autres OK.
**Cause** : le fichier avait `Orientation EXIF = 6` (= « pivoter 90° »), les autres `= 1` (normale). GD ignore l'EXIF → photo non redressée.
**Fix = 4 pièces qui s'emboîtent (BON MATÉRIEL ORAL/DOSSIER — l'environnement fait partie du code)** :
1. **Filtre `auto_rotate: ~`** dans `config/packages/liip_imagine.yaml`, placé **AVANT** `thumbnail` (l'ordre compte : redresser puis redimensionner) → dit « redresse selon l'EXIF ».
2. **Extension `exif`** ajoutée au `docker/php/Dockerfile` (ligne `docker-php-ext-install ... exif`) + **`docker compose up -d --build php`** → sans `exif`, PHP ne sait même pas *lire* l'orientation (même famille de problème que le WebP au début du projet : une capacité manquante dans l'image Docker).
3. **`php bin/console cache:clear`** → LiipImagine choisit son lecteur d'orientation (ExifMetadataReader vs Default) à la **compilation du conteneur de services** ; tant que l'ancien conteneur compilé (sans `exif`) était en cache, la nouvelle extension était ignorée. **C'est ce maillon qui manquait au début** (exif chargée mais rotation toujours absente).
4. **`liip:imagine:cache:remove`** (+ `Cmd+Shift+R`) → régénérer la vignette déjà en cache et forcer le navigateur.
**Diagnostic objectif utilisé** : mesurer les dimensions de la vignette générée — original brut 4032×3024 (paysage) → vignette 400×300 (KO, pas tourné) puis **300×400 (portrait = rotation OK)**. Prouve la rotation sans se fier à l'œil. `exif_read_data()` en CLI pour lire l'`Orientation` de chaque fichier uploadé (1 seul en 6, le reste en 1).
**Phrase jury** : « Redresser une photo n'était pas un réglage unique mais une chaîne : configurer le filtre, doter l'image Docker de l'extension `exif`, puis vider le cache de services pour que Symfony redétecte cette capacité. J'ai diagnostiqué chaque maillon en mesurant les dimensions de la vignette. »

## 🖼️ PIPELINE MÉDIAS — UPLOAD PHOTOS TERMINÉ ✅ (22 juillet)
Décision : pipeline **costaud** (délai repoussé au 20 août). Upload photos fait ; LiipImagine (WebP/miniatures/srcset) et vidéos = plus tard (avec le front / après).

**VichUploader (upload + stockage)**
- Config `config/packages/vich_uploader.yaml` : mapping `product_media` (uri_prefix `/uploads/products`, upload_destination `public/uploads/products`, `SmartUniqueNamer` = noms uniques anti-collision).
- Entité `Media` rendue **`#[Vich\Uploadable]`** (namespace **Attribute**, pas Annotation qui est dépréciée). Ajout : `imageFile` (champ virtuel `#[Vich\UploadableField(mapping:'product_media', fileNameProperty:'url')]`, PAS une colonne) + `updatedAt` (colonne, migration `Version20260722135409`). `setImageFile()` touche `updatedAt` → sinon Doctrine ne détecte pas le remplacement de fichier. Valeurs par défaut sur l'entité : `type='photo'`, `isMain=false`, `position=0` (factorisé, pas dans le contrôleur).
- `public/uploads/products/.gitignore` = `*` + `!.gitignore` (garde le dossier, ignore les fichiers uploadés).

**MediaController (codé main, section dédiée)** : `new` (`/admin/product/{id}/media/new` — produit dans l'URL) + `delete` (`/admin/media/{id}/delete`, POST + CSRF). Pas de show/index/edit (décidé). Affichage des photos dans `product/show.html.twig` via `vich_uploader_asset(media,'imageFile')` + fragment `media/_delete_form.html.twig`.

**MediaType** : `imageFile` en **VichImageType** + validation `Assert\Image` (maxSize 8M, mimeTypes jpeg/png/webp → **rejette le HEIC** avec message FR). alt / isMain / position. Champs `url`/`type`/`product` remplis par le contrôleur.

**⚠️ Les 3 couches de limite d'upload (gros point de debug résolu)** : un upload traverse **nginx → PHP → app**. Fallait aligner les 3 à 16M :
- nginx `client_max_body_size 16M;` dans `docker/nginx/default.conf` (défaut 1M ! → bloquait avant tout) + `docker compose restart nginx`.
- PHP `upload_max_filesize`/`post_max_size` = 16M via `docker/php/uploads.ini` copié par le Dockerfile (`COPY uploads.ini /usr/local/etc/php/conf.d/`) + rebuild. Config dans le Dockerfile = reproductible (un conteneur est jetable).
- La vraie limite = la plus petite des trois.

**Suppression = nettoie aussi le fichier physique** ✅ (Vich, via remove+flush sur l'entité). A nécessité `Media::setUrl(?string $url)` nullable (Vich remet url à null). Pas de fichiers orphelins.

## ⚙️ Automatismes NATIFS (21 juillet) — Gedmo écarté
**Décision** : pas de bundle Gedmo, tout en **natif Doctrine/Symfony** (pas de dépendance pour quelques lignes, et 100 % explicable).

### Horodatage — callbacks de cycle de vie Doctrine
- `Product` seulement (`createdAt` + `updatedAt`, `datetime_immutable`, non nullables) — migration `Version20260721120350` appliquée ✅.
- `#[ORM\HasLifecycleCallbacks]` sur la classe + `#[ORM\PrePersist] initTimestamps()` et `#[ORM\PreUpdate] refreshUpdatedAt()`.
- **Setters supprimés** (getters conservés) → les dates ne peuvent pas être falsifiées depuis le code métier, seul Doctrine les écrit.
- **Pourquoi un callback** : sans lui il faudrait écrire la date dans chaque contrôleur/fixture/commande → un oubli = date nulle = crash (colonne NOT NULL). Le callback se déclenche quel que soit l'endroit qui crée l'objet.
- **Pourquoi Product seul** : seule entité dont la fraîcheur sert (nouveautés, tri admin, `lastmod` du sitemap). Tables de référence figées = du bruit.

### Slug automatique — `AsciiSlugger` (natif Symfony)
- `#[ORM\PrePersist] generateSlug()` : `$this->slug = (new AsciiSlugger())->slug($this->name)->lower();`
- Testé ✅ : « Canapé d'angle Méridienne 4 places » → `canape-d-angle-meridienne-4-places` (accents, apostrophe, espaces, majuscules).
- Champ `slug` retiré de `ProductType`.
- **⚠️ Volontairement PAS sur `PreUpdate`** : une URL publiée doit rester **stable** — la régénérer au renommage casserait les liens entrants et le référencement.
- Reste à appliquer à `Category` et `SubCategory` (aucune migration, colonnes déjà présentes).

## 🚫 ZÉRO findAll() — refactor fait (21 juillet)
Choix de Serife, assumé et défendable au jury : **aucun `findAll()` dans le projet** (`grep -rn "findAll()" src/` → 0 résultat).
- Chaque repository a une méthode **`findForIndex()`** écrite au **QueryBuilder** avec **projection** (`->select(...)` = uniquement les colonnes affichées) + `orderBy` explicite + **`getArrayResult()`** (tableaux, pas d'objets).
- Colonnes par repo : Color `id,name,hex` · Fabric `id,name` · Family `id,name` · SubCategory `id,name,slug` · Category `id,name,slug,seoText,metaTitle,metaDescription` · Product `id,name,actualPrice,stock`.
- **Pourquoi les tableaux** : pas d'hydratation d'objets (plus léger) et surtout **aucun lazy loading possible** → le problème **N+1** est éliminé à la source, pas juste évité par discipline.
- En Twig, `{{ color.name }}` marche pareil sur un tableau que sur un objet → **templates inchangés**.
- ⚠️ `show`/`edit`/`delete` chargent l'entité **complète par id** (1 ligne) — c'est normal et voulu.

## 🏗️ CRUD Product — CODÉ À LA MAIN (EN COURS) — 14 juillet

> Choix de Serife : **PAS de make:crud** pour Product → tout coder **à la main et commenter**, pour le maîtriser et l'expliquer au jury. Coquille de départ via `make:controller` seulement.

### ✅ Fait
- **`make:controller ProductController`** (coquille) → route de classe passée à `#[Route('/admin/product')]` (protégée ^/admin), **sans `name:` sur la classe** (sinon préfixe collé au nom des actions).
- **Action `index` PAGINÉE** codée main : injecte `Request` + `ProductRepository` + `PaginatorInterface` (KnpPaginator, déjà installé). Constante `PRODUCTS_PER_PAGE = 20` (pas de nombre magique).
- **`ProductRepository::findAllOrderedQuery()`** : renvoie la **Query non exécutée** (`orderBy position ASC`) → c'est le paginator qui ajoute le `LIMIT 20` → **on ne charge jamais toute la table**.
- **Décision archi (à verbaliser jury)** : on **pagine** les tables qui grossissent (product) ; `findAll()` reste OK pour les **petites tables de référence bornées** (color, fabric, family, sub_category, category ≈ 10 lignes).
- **`templates/product/index.html.twig`** : tableau + boucle `{% for product in pagination %}` + `{% else %}` (liste vide) + `knp_pagination_render(pagination)`.

### ✅ `ProductType` TERMINÉ (codé main, 21 champs, commenté)
`src/Form/ProductType.php` — les 5 groupes sont faits :
1. **Infos** : name (TextType), description + dimension (TextareaType, required:false).
2. **Prix/stock** : initialPrice + actualPrice (**MoneyType**, currency EUR — jamais de float pour l'argent, DECIMAL en base), stock (IntegerType).
3. **Options** : isCustomMade (CheckboxType, **required:false obligatoire** sinon la case devrait être cochée), isModular + sideLr (**EnumType** + `class` → choix issus des enums PHP `src/Enum/`), leadMin/MaxWeeks (IntegerType, required:false).
4. **Relations** (**EntityType** = choix lus en base) : category (single, obligatoire, placeholder), family (single, required:false), subCategories/fabrics/colors (`multiple:true` + `expanded:true` = cases à cocher), modules (multiple, expanded:false = liste, réflexif sur Product). ⚠️ Toujours `choice_label => 'name'` (sinon erreur « could not be converted to string »).
5. **SEO/publication** : slug (TextType + `help`), metaTitle/metaDescription (required:false → fallback SeoResolver), position (IntegerType), isActive (CheckboxType required:false).
- ⏭️ **Quand Gedmo arrivera** : retirer le champ `slug` de `ProductType`, `CategoryType`, `SubCategoryType` (généré via `#[Gedmo\Slug(fields:['name'])]`) ; et **ajouter** createdAt/updatedAt aux entités (+migration) pour Timestampable.
- Pas de `make:form` (générerait des champs nus à réécrire à 80 %).
- Pourquoi PAS `make:form` : il génère des champs nus (prix sans MoneyType, relations en `choice_label:id` illisibles, labels en anglais) → à réécrire à 80 %, on perd le bénéfice « je maîtrise/commente ».

### 🗺️ Comment on continue (plan ProductType, groupe par groupe)
1. **Prix + stock** : `initialPrice`/`actualPrice` en **MoneyType** (devise EUR), `stock` en IntegerType.
2. **Options** : `isCustomMade` (CheckboxType), `isModular` + `sideLr` en **EnumType** (enums PHP `ProductModular`/`ProductSide`), `leadMinWeeks`/`leadMaxWeeks` (IntegerType, required:false).
3. **Relations** : `category` (**EntityType** single, required — `choice_label:name` grâce aux `__toString`), `family` (EntityType nullable), `subCategories`/`fabrics`/`colors` (EntityType `multiple`), `modules` (EntityType multiple réflexif).
4. **SEO + publication** : `slug` (TextType, saisi **à la main** en attendant Gedmo), `metaTitle`/`metaDescription`, `position` (IntegerType), `isActive` (CheckboxType).
5. ⏭️ **Médias exclus** du form pour l'instant → Phase 4 (VichUploader, cf. section « À garder en tête »).

### ✅ Actions + templates TERMINÉS et TESTÉS (21 juillet)
Les 5 actions codées main dans `ProductController` : `index` (paginée), `new`, `show`, `edit`, `delete`. Templates : `index/new/show/edit/_delete_form`. **Testé OK : créer, modifier, supprimer un produit.**

Notions posées (bon matériel pour l'oral) :
- **`createForm(ProductType::class, $product)`** : liaison **bidirectionnelle** form ↔ objet → `new` (objet vide = form vierge) et `edit` (objet existant = form pré-rempli) partagent **le même** FormType. `handleRequest()` remplit l'entité via ses setters (d'où l'importance que le 1er arg de `->add()` soit le nom exact de la propriété).
- **`edit` n'appelle PAS `persist()`** : l'objet vient de la base, Doctrine le surveille déjà (unit of work) → `flush()` suffit pour l'UPDATE.
- **`show`/`edit`/`delete` : `Product $product` en paramètre** → Symfony fait le `find($id)` tout seul depuis `{id}` (Entity Value Resolver) + 404 auto si inexistant.
- **`requirements: ['id' => '\d+']`** → `{id}` limité aux chiffres : lève l'ambiguïté avec `/new` sans dépendre de l'ordre, et 404 propre sur URL invalide.
- **`delete` en POST uniquement + jeton CSRF** (`isCsrfTokenValid('delete'.$id, payload '_token')`) : une action destructrice ne passe jamais par un lien GET (robots/préchargement videraient la base), et le jeton bloque les requêtes forgées depuis un autre site.
- ⚠️ **Piège rencontré** : `if (...);` — le `;` ferme le `if` → le bloc s'exécutait **toujours** (vérif CSRF neutralisée). Toujours une accolade.

### ⏭️ Après Product
Gedmo (Sluggable/Timestampable) → Fixtures Foundry → Front (Tailwind + design) → SEO → recette → mise en ligne.
Tests : plus tard 1-2 **tests fonctionnels Symfony** (WebTestCase, déjà installé — PAS Postman : app à formulaires, pas API JSON).

## ⏳ À garder en tête pour la Phase 4 (pipeline médias)
- **Upload** = VichUploader (mapping sur `Media`, stockage `public/uploads/products/`, on stocke juste le nom/chemin en base, jamais l'image).
- **Affichage** = LiipImagine (filtres WebP + miniatures/srcset).
- **⚠️ HEIC (photos iPhone)** : GD ne le lit pas + les navigateurs ne l'affichent pas. **Décision MVP = REJETER** via validation `Assert\File(mimeTypes: ['image/jpeg','image/png','image/webp'])` avec message clair « convertis en JPEG ». L'admin convertit (iPhone → Réglages/Appareil photo/Formats/« Le plus compatible », ou Aperçu Mac → Exporter JPEG). Conversion auto HEIC→JPEG (Imagick+libheif) = **V2** si besoin.

## Puis (dans l'ordre acté)
1. **Gedmo** : Sluggable (+ Timestampable) — avant le front
2. **Fixtures** Foundry (données de démo)
3. **Front** (Tailwind + design system intégré) : accueil, catégorie, fiche produit + CTA WhatsApp
4. SEO de base → recette → mise en ligne

## Notes de sécurité
- `.env.local` est **ignoré par git** (secrets protégés) ✅
- Ne jamais versionner un mot de passe / clé API.
