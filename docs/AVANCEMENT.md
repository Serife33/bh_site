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

## Prochaine étape : CRUD back-office 🗂️

## Puis (dans l'ordre acté)
1. **CRUD back-office** : Fabric, Color, Family, SubCategory (make:crud simples) → Category → **Product** (form riche) — slug saisi à la main pour l'instant
2. **Gedmo** : Sluggable (+ Timestampable) — avant le front
3. **Fixtures** Foundry (données de démo)
4. **Front** (Tailwind + design system intégré) : accueil, catégorie, fiche produit + CTA WhatsApp
5. SEO de base → recette → mise en ligne (17/7 !)

## Notes de sécurité
- `.env.local` est **ignoré par git** (secrets protégés) ✅
- Ne jamais versionner un mot de passe / clé API.
