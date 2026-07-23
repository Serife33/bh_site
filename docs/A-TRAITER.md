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
- ⏭️ Un jour : permettre de **personnaliser** un slug en back-office (aujourd'hui, une faute dans le nom à la création reste dans l'URL — on ne régénère pas à la modification, volontairement, pour ne pas casser les URLs publiées).

### 1bis. Médias — points d'architecture (décidés le 22/07)
- **Pas de `show` ni d'`index` pour Media** : une photo n'a pas de détail à consulter seule (déjà visible sur la fiche produit). On ne crée que les actions utiles : ajouter / afficher / supprimer.
- **Edit Media = métadonnées seulement** (décidé par Serife le 22/07) : on modifie `alt` / `isMain` / `position`, **pas le fichier** (le fichier se remplace par supprimer + ré-uploader). Réutiliser `MediaType` mais rendre `imageFile` **facultatif en mode edit** via une **option de formulaire** (Vich garde l'image existante si aucun nouveau fichier n'est envoyé). À faire après le delete.
- **✅ Fichiers orphelins — RÉGLÉ (22/07)** : la suppression via `MediaController::delete` (remove + flush sur l'entité) déclenche bien VichUploader → le fichier physique est supprimé en même temps que la ligne. Confirmé au test. NB : il a fallu rendre `Media::setUrl(?string $url)` nullable, car Vich remet `url` à null en supprimant le fichier. ⚠️ Reste à vérifier le cas « suppression d'un PRODUIT entier » (cascade orphanRemoval) — s'assurer que chaque Media passe bien par remove (pas un DELETE SQL en masse).
- **Règle métier** : une seule photo `is_main` par produit → à gérer dans `MediaController` (décocher les autres quand on en coche une).

### 1ter. Dépréciation LiipImagine (vue le 22/07)
- Warning : `Liip\ImagineBundle\Templating\FilterExtension deprecated since 2.7`. → fix en 1 ligne : dans `config/packages/liip_imagine.yaml`, mettre `liip_imagine.twig.mode: lazy`. À faire en Phase C (config LiipImagine). Sans gravité.

### 2. Pipeline médias — moitié « upload »
- **État** : l'entité `Media` existe, l'**affichage** se fera avec le front. Il manque l'**upload**.
- **Quoi** : VichUploader (réception + stockage `public/uploads/products/`) + LiipImagine (WebP, vignettes, srcset).
- **⚠️ HEIC** : refuser les photos iPhone avec une validation claire —
  `Assert\File(mimeTypes: ['image/jpeg','image/png','image/webp'])` + message « convertis en JPEG ».
  Conversion auto HEIC→JPEG (Imagick + libheif) = V2.
- **Contournement en attendant** : les fixtures créent des lignes `Media` pointant vers des photos de démo posées à la main dans `public/uploads/products/` → **le front est complet et démontrable sans l'upload**.
- **Astuce prévue** : centraliser l'affichage des images dans **un seul composant Twig** (vignette produit) → le jour où l'upload arrive, on change une ligne.

### 3. Navigation du back-office
- Aucun menu ni **bouton Déconnexion** sur `/admin` (il faut taper `/logout` à la main).
- À faire en même temps que le stylage admin.

---

## 🟡 Priorité moyenne — confort et qualité

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

### 9. Tests fonctionnels Symfony
- `symfony/test-pack` est **déjà installé**, rien à ajouter.
- Objectif : 1 ou 2 tests `WebTestCase` sur le CRUD Product (la page répond, la création fonctionne).
- ⚠️ **Pas Postman** : l'application repose sur des formulaires HTML, pas sur une API JSON.
- En attendant : **recette manuelle** documentée + captures (suffisant pour le TP DWWM).

---

## 🟢 Plus tard / V2

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
