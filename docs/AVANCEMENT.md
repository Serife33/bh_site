# 📌 Brillance Home — Avancement du projet

> Journal de bord pour reprendre le fil rapidement (mis à jour au fil de l'eau).

## Contexte
- E-commerce ameublement **Brillance Home** (vitrine + devis WhatsApp), projet d'examen **TP DWWM** (niveau 5).
- Deadlines : **appli le 17 juillet 2026** · **dossier le 7 août 2026**.
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
**RESTE F2** : (1) **menu header dynamique** (catégories BDD dans le layout → extension Twig ou `render(controller())`) ; (2) sections contenu accueil optionnelles (réassurance, showroom, CTA WhatsApp). Liens produits/catégories = `front_home` provisoire → à brancher sur `front_product`/`front_category` en F3/F4.
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
  - Étapes : créer BDD MySQL (cPanel) → envoyer le code (git/SSH) → `composer install --no-dev --optimize-autoloader` → `.env.local` prod (`APP_ENV=prod` + `DATABASE_URL` o2switch) → document root sur `public/` → migrations + `cache:clear --env=prod` → permissions (`var/`, `public/uploads/`, `public/media/cache/`).
  - ⚠️ **Tailwind** : le binaire télécharge depuis internet → prévoir de **compiler le CSS en local** et l'uploader (ne pas dépendre du build sur le mutualisé). Idem, `.env.local` de prod jamais commité.
- **Alternative si Docker en prod voulu** : VPS (Hetzner ~4 €, OVH) avec droits root. Plus de travail, pas nécessaire pour le MVP.
- 🎤 Argument jury : « Docker en dev pour un environnement reproductible ; déploiement sur mutualisé natif car l'appli est portable — Docker n'est pas une dépendance de prod. »

## ⚡ OPTIMISATION IMAGES — LiipImagine TERMINÉ ✅ (24 juillet)
- Config `config/packages/liip_imagine.yaml` : driver **gd**, `twig.mode: lazy` (corrige la dépréciation), filter set **`vignette`** (`format: webp`, `quality: 82`, `thumbnail size:[400,400] mode:inset` = ratio préservé).
- Affichage : `{{ vich_uploader_asset(media,'imageFile') | imagine_filter('vignette') }}` dans `product/show.html.twig` (width="200" retiré, LiipImagine gère la taille). Cache dans `public/media/cache/` (ignoré par git).
- **2 galères d'environnement résolues (Dockerfile)** :
  1. GD compilé **sans WebP** → erreur « Creating an image in webp not supported ». Fix : `libwebp-dev` + `--with-webp` sur `docker-php-ext-configure gd` + rebuild.
  2. Images 22-30 MP → GD sature la RAM (bitmap = L×H×4 octets ≈ 121 Mo pour du 5500² > 128M). Fix : `memory_limit = 512M` dans `docker/php/uploads.ini`.
- **Résultat mesuré** : litcoffre 2,2 Mo → vignette WebP **8 Ko** (~275×). Réflexe débogage : lire `var/log/dev.log` a donné l'erreur exacte à chaque fois.
- ⏭️ Reste (A-TRAITER) : filter sets `galerie` (1600) + `og` (1200×630), macro Twig `image()` avec srcset + width/height (CLS=0), appliquer au front. Alternative envisagée : limiter les dimensions max à l'upload plutôt que monter la mémoire.

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
