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
| `bh_adminer` | adminer | admin visuel de la BDD | http://localhost:8081 |

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
- [ ] **EN COURS** → créer `.env.local` (DATABASE_URL vers MySQL) + tester la connexion
- [ ] Premier commit + créer le dépôt GitHub
- [ ] Installer les dépendances MVP (vich, liip, gedmo, paginator, tailwind, fixtures…)
- [ ] Phase 2 design system → Phase 3 entités (12 tables) → etc. (voir PLAN_DE_MATCH_MVP.md)

## Mémo commandes (réflexe Docker !)
- **Toute** commande Symfony/Composer se lance DANS le conteneur :
  - `docker compose exec php php bin/console ...`
  - `docker compose exec php composer ...`
- Démarrer les conteneurs : `docker compose up -d`
- Les arrêter : `docker compose down`
- Voir l'état : `docker compose ps`
- Voir les logs : `docker compose logs -f [service]`
- Reconstruire après modif du Dockerfile : `docker compose up -d --build`

## Prochaine étape immédiate
1. Créer `.env.local` :
   `DATABASE_URL="mysql://bh_user:bh_pass@database:3306/bh_site?serverVersion=8.4&charset=utf8mb4"`
2. Tester : `docker compose exec php php bin/console dbal:run-sql "SELECT VERSION()"`
3. Premier commit Git + dépôt GitHub.

## Notes de sécurité
- `.env.local` est **ignoré par git** (secrets protégés) ✅
- Ne jamais versionner un mot de passe / clé API.
