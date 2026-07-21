# 📸 Légendes des captures d'écran

> À remplir **au fil de l'eau**, juste après chaque capture.
> Objectif : en août, quand je rédigerai le dossier, savoir immédiatement **ce que chaque
> image démontre** et **dans quelle partie** la placer — sans avoir à me souvenir.

**Convention de nommage** : `<zone>-<ce-que-ça-montre>.png` — minuscules, sans accent, avec des tirets.
Zones : `env-` (environnement) · `bdd-` (base de données) · `admin-` (back-office) · `front-` (site public) · `profiler-` (preuves techniques) · `secu-` (sécurité).

---

## Environnement / installation

### `env-symfony-accueil-docker.png`
- **Montre** : la page d'accueil par défaut de Symfony 7.4 servie via Docker sur `localhost:8080`.
- **Prouve** : l'environnement conteneurisé fonctionne (php-fpm + nginx + MySQL orchestrés par `compose.yaml`).
- **Partie du dossier** : *Mise en place de l'environnement de développement*.

---

## Back-office (CRUD)

### `admin-liste-produits.png`
- **Montre** : la liste des produits du back-office (`/admin/product`), avec colonnes Nom / Prix / Stock / Actions.
- **Prouve** : CRUD fonctionnel, liste **paginée**, colonnes ciblées, actions Voir / Modifier.
- **Partie du dossier** : *Back-office — gestion du catalogue*.

### `admin-formulaire-produit.png`
- **Montre** : le formulaire de création d'un produit (`/admin/product/new`), 21 champs.
- **Prouve** : formulaire riche **codé à la main** (pas de génération automatique), avec des types adaptés à chaque donnée :
  - `MoneyType` (symbole €) pour les prix,
  - listes déroulantes alimentées par des **enums PHP** (modulable, côté),
  - cases à cocher pour les relations **ManyToMany** (types, tissus, couleurs),
  - textes d'aide (`help`) pour guider la saisie du slug.
- **Partie du dossier** : *Développement des composants d'interface — formulaires*.
- ⚠️ Capture **avant mise en forme** (aucun style). En reprendre une après l'intégration Tailwind.

---

## Preuves techniques (profiler)

### `profiler-doctrine-liste-produits-pagination.png`
- **Montre** : le panneau **Doctrine** du profiler Symfony sur `/admin/product` — 4 requêtes, 4,19 ms.
- **Prouve** (trois choses d'un coup) :
  1. **Pagination** : `... ORDER BY position ASC LIMIT 20` → on ne charge jamais toute la table.
  2. **Projection** : seules les colonnes affichées sont sélectionnées (id, name, actual_price, stock).
  3. **Aucun problème N+1** : le nombre de requêtes est **constant** quel que soit le nombre de produits affichés (pas une requête par produit).
- **Partie du dossier** : *Accès aux données / optimisation des requêtes*.
- 💬 Phrase associée : « J'ai vérifié mes requêtes au profiler : la liste exécute 4 requêtes constantes, avec un LIMIT et une sélection de colonnes ciblée. »

---

## Base de données

### `bdd-slug-timestamps-automatiques.png`
- **Montre** : le résultat d'une requête SQL sur la table `product` — colonnes `name`, `slug`, `created_at`.
- **Prouve** : deux automatismes que je n'ai codés **nulle part dans les contrôleurs** —
  1. **Slug généré automatiquement** depuis le nom, via l'`AsciiSlugger` natif de Symfony :
     « Canapé d'angle Méridienne 4 places » → `canape-d-angle-meridienne-4-places`
     (accents supprimés, apostrophe et espaces transformés en tirets, tout en minuscules).
  2. **Horodatage automatique** via les **callbacks de cycle de vie Doctrine** (`PrePersist` / `PreUpdate`).
- **Partie du dossier** : *Persistance des données — automatisation et intégrité*.
- 💬 Phrases associées :
  - « Le slug est généré une seule fois, à la création. Je ne le régénère pas à la modification pour garder des URLs stables : changer une URL publiée casserait les liens entrants et le référencement. »
  - « L'horodatage passe par un callback Doctrine plutôt que par une ligne dans chaque contrôleur : impossible de l'oublier, quel que soit l'endroit du code qui crée le produit. Et sans setter public, ces dates ne peuvent pas être falsifiées. »
  - « J'ai comparé avec le bundle Gedmo, mais pour deux besoins simples j'ai préféré les composants natifs : pas de dépendance supplémentaire, et je maîtrise ce qui s'exécute. »

---

## À capturer plus tard (penser-y !)

- [ ] `secu-login.png` — la page de connexion admin
- [ ] `profiler-securite-role-admin.png` — onglet Security du profiler (utilisateur authentifié + ROLE_ADMIN)
- [ ] `secu-acces-refuse.png` — tentative d'accès à `/admin` sans être connecté → redirection vers le login
- [ ] `bdd-schema-12-tables.png` — phpMyAdmin avec les 12 tables du MVP
- [ ] `admin-crud-couleurs.png` — un CRUD simple (généré par `make:crud`), pour contraster avec Product codé main
- [ ] `front-accueil.png`, `front-categorie.png`, `front-fiche-produit.png` — le site vitrine
- [ ] `front-whatsapp-devis.png` — le CTA WhatsApp avec message pré-rempli
- [ ] `front-mobile-375.png` — le rendu responsive sur mobile
