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

## Pipeline médias (upload)

### `env-uploads-dossier.png`
- **Montre** : le contenu de `public/uploads/products/` après un upload (le fichier image physiquement rangé, renommé).
- **Prouve** : le fichier est stocké sur le **système de fichiers**, avec un nom **unique** généré par le SmartUniqueNamer de VichUploader (pas d'écrasement possible). Le fichier n'est PAS en base.
- **Partie du dossier** : *Gestion des fichiers / upload*.

### `admin-upload-media-base.png`
- **Montre** : le résultat d'une requête SQL sur la table `media` (`url`, `type`, `is_main`, `product_id`).
- **Prouve** : en base, on ne stocke que le **nom du fichier** (colonne `url`), pas le fichier lui-même ; le média est bien **rattaché au produit** (`product_id`), le `type` vaut `photo` (valeur par défaut de l'entité) et `is_main` reflète le choix de l'admin.
- **Partie du dossier** : *Gestion des fichiers / persistance*.
- 💬 Phrase associée : « Je ne stocke jamais un fichier en base, seulement son nom ; le fichier vit dans le système de fichiers, la base reste légère. »

### `admin-upload-formulaire-photo.png`
- **Montre** : le formulaire d'ajout d'une photo à un produit (`/admin/product/{id}/media/new`).
- **Prouve** : interface d'upload dédiée (champ fichier VichImageType, texte alternatif, photo principale, position). Le produit est passé par l'URL, pas par un champ.
- **Partie du dossier** : *Gestion des fichiers / interface d'upload*.

### `admin-fiche-produit-photo-hd.png`
- **Montre** : la fiche produit du back-office affichant une vraie photo haute résolution uploadée.
- **Prouve** : la chaîne complète upload → stockage → affichage fonctionne (`vich_uploader_asset` construit l'URL publique depuis le nom stocké en base).
- **Partie du dossier** : *Gestion des fichiers / affichage*.

### `admin-photo-bouton-supprimer.png`
- **Montre** : une photo avec son bouton « Supprimer » sous la fiche produit.
- **Prouve** : suppression depuis l'interface (formulaire POST + jeton CSRF, fragment `_delete_form` réutilisable). La suppression retire la ligne en base **et** le fichier physique (via VichUploader).
- **Partie du dossier** : *Gestion des fichiers / CRUD médias*.

### `admin-upload-erreur-validation.png`
- **Montre** : le message d'erreur affiché quand on tente d'uploader un fichier non conforme (ici un fichier texte renommé en .jpg).
- **Prouve** : la validation inspecte le **type MIME réel** (le contenu), pas l'extension → un fichier déguisé est rejeté avec un message clair en français. Protection contre l'upload de fichiers malveillants.
- **Partie du dossier** : *Sécurité / validation des entrées*.
- 💬 Phrase : « Ma validation vérifie le type MIME réel du fichier, pas son extension : un exécutable ou un texte renommé en .jpg serait rejeté. »

### `config-limites-upload-nginx-php.png`
- **Montre** : la configuration des limites d'upload (nginx `client_max_body_size`, PHP `uploads.ini`, `COPY` dans le Dockerfile).
- **Prouve** : maîtrise des **3 couches de limite** qu'un upload traverse (serveur web → PHP → application). La limite réelle est la plus petite des trois ; elles sont alignées à 16M. Config placée dans le Dockerfile = reproductible.
- **Partie du dossier** : *Infrastructure / configuration serveur*.
- 💬 Phrase : « Un upload franchit trois limites successives : nginx, puis les deux directives PHP. Je les aligne, et je place la config dans le Dockerfile pour un environnement reproductible. »

### `bdd-media-apres-suppression.png`
- **Montre** : la table `media` et le contenu du dossier `uploads/products/` après une suppression.
- **Prouve** : cohérence **base ↔ système de fichiers** — autant de fichiers que de lignes, aucun fichier orphelin. VichUploader supprime le fichier physique en même temps que l'entité.
- **Partie du dossier** : *Gestion des fichiers / intégrité des données*.

---

## Optimisation / performance

### `front-liipimagine-poids-original-vs-webp.png`
- **Montre** : la comparaison de poids entre l'image originale et sa vignette générée par LiipImagine (`du -h` sur les deux fichiers).
- **Prouve** : l'optimisation d'images fonctionne — l'original de **2,2 Mo** devient une vignette **WebP de 8 Ko** (~275× plus léger), redimensionnée à 400px et mise en cache. L'admin uploade l'original ; le site sert une version légère.
- **Partie du dossier** : *Optimisation / performance / expérience utilisateur*.
- 💬 Phrase : « Sans optimisation, une page catalogue de 12 vignettes ferait ~26 Mo. Avec LiipImagine (redimensionnement + WebP + cache), la même page fait moins de 100 Ko. »

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
