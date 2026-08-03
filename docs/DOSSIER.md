# 📄 Dossier projet — Brillance Home (TP DWWM)

> Structure de travail du dossier écrit (~30-40 p.) à présenter/soutenir.
> **Ne pas partir de la page blanche** : la matière est déjà accumulée dans les fichiers ci-dessous.
> Statut de chaque section : 🔴 à écrire · 🟡 en cours · 🟢 rédigé.

## 🗂️ Matière première (où puiser)
- **`docs/AVANCEMENT.md`** — tout l'historique technique + les « pourquoi ». Sections clés : 🎤 ORAL, 📖 RÉVISION, auto-rotation EXIF, F3+ filtres, F4 fiche produit.
- **`docs/A-TRAITER.md`** — choix actés, arbitrages, perspectives V2 → parties « choix techniques » et « évolutions ».
- **`docs/captures/`** + `LEGENDES.md` — captures déjà légendées (preuves visuelles).
- **`~/Downloads/files_maquettes/`** — maquettes mobile/desktop + MCD/MLD (`mvp-mcd.html`, `mvp-mld.sql`).

---

## 1. Présentation du projet — 🔴
- Contexte : e-commerce ameublement **Brillance Home** (Bordeaux), spécialiste intérieur.
- Client / expression du besoin : *(adapter à la réalité commerciale de Serife — ce qu'elle vend vraiment)*.
- Périmètre **MVP** : vitrine + **devis WhatsApp** (pas de panier/paiement → V2), **FR seul**.
- Cible, objectifs, contraintes.

## 2. Gestion de projet — 🔴
- Planning, deadline 20 août, choix du MVP, priorisation (ex. sous-catégories avant fiche produit : choix assumé).
- Outils : **Git/GitHub**, **Docker** (env reproductible), journal de bord (`AVANCEMENT.md`).
- Méthode de travail : conception → back → médias → front → déploiement → dossier.

## 3. Spécifications & conception — 🔴
- **Maquettes** mobile-first (mobile/desktop).
- **MCD / MLD** : 12 tables (source `mvp-mld.sql`), noms EN. Expliquer les relations (ManyToOne, ManyToMany, réflexive modules).
- **Règles métier** : stock 0 = sur commande · promo (`actualPrice < initialPrice`) · module = produit · une seule photo principale.

## 4. Réalisation FRONT-END (bloc 1 DWWM) — 🔴
- Intégration **mobile-first** (base mobile, `@media min-width`, burger/tiroir, 44px tactile, inputs 16px).
- **Composants Twig réutilisables** : `_product_card`, `_product_section`, layout `front/base`.
- **Responsive**, **accessibilité** (Montserrat, contraste, `alt`, `aria-label`).
- **Interactivité** : natif d'abord (bandeau CSS, FAQ `<details>` = 0 JS) ; JS pour l'état (burger, **galerie carrousel/miniatures/flèches**, configurateur).
- **CTA WhatsApp** (à la place du panier).
- Pages : accueil, catégorie (+ filtres sous-catégorie), **fiche produit** (galerie, configurateur, devis), recherche, contact, légales, 404.

## 5. Réalisation BACK-END (bloc 2 DWWM) — 🔴
- **Symfony 7.4** : contrôleurs, routes (URL FR / code EN), entités Doctrine, formulaires.
- **CRUD Product codé à la main** (maîtrise/défense de chaque ligne).
- **Accès aux données** : zéro `findAll()` → projections `findForIndex()` + **pagination** (Query non exécutée + KnpPaginator), requêtes au QueryBuilder (`join` ManyToMany, `andWhere` conditionnel du filtre).
- **Médias** : VichUploader (upload) + **LiipImagine** (WebP, vignette/galerie, **auto_rotate** EXIF).
- Automatismes **natifs** (pas Gedmo) : slug `AsciiSlugger`, horodatage callbacks Doctrine.

## 6. Sécurité (transversal) — 🔴
- Hachage mots de passe, **auth admin** (firewall, `form_login`, `access_control ^/admin`).
- **CSRF** (formulaires, suppression POST).
- **Validation des uploads** : MIME réel (`Assert\Image`), rejet HEIC, les **3 couches de limite** (nginx/PHP/app).
- Anti-injection SQL (paramètres liés `setParameter`), produits inactifs non accessibles par URL.

## 7. Jeu d'essai / recette — 🔴
- **Cahier de recette manuel** (scénarios + résultats attendus) + **captures**.
- 1-2 **tests fonctionnels** `WebTestCase` si le temps (PAS Postman : app à formulaires).

## 8. Déploiement — 🔴
- **o2switch** (mutualisé natif, pas Docker en prod : l'appli est portable).
- Étapes : BDD MySQL cPanel → code (git/SSH) → `composer install --no-dev` → `.env.local` prod → compiler assets/CSS en local → migrations → doc root `public/` → permissions.
- **URL live** + captures finales.
- Argument jury : « Docker en dev pour la reproductibilité ; prod sur mutualisé natif car l'appli est portable. »

## 9. Veille / perspectives (V2) — 🔴
- Depuis `A-TRAITER.md` : toolbar filtres à facettes, slug éditable, CRUD Annonce (bandeau piloté admin), Mentions/HomeSection (mini-CMS), i18n EN, paiement, self-host FA/fonts (RGPD), conversion HEIC auto…

## 10. Bilan / compétences DWWM — 🔴
- Compétences des blocs 1 (front) et 2 (back) couvertes.
- Difficultés rencontrées et résolues (ex. WebP GD, auto_rotate EXIF, N+1, les 3 couches d'upload).
- Ce que le projet m'a appris.

---

## ⚠️ Rappels
- **Adapter tous les textes à la réalité commerciale de Serife** (ce qu'elle vend vraiment).
- Ordre conseillé : **finir le front → déployer → rédiger** (le déploiement donne l'URL + captures finales).
- À produire en support : **récap des questionnements** (pourquoi/comment de chaque notion) → matière directe oral + dossier.
