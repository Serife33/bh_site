# 📌 MÉMO COMMANDES — Brillance Home

> Antisèche du quotidien. Toujours se placer dans `~/bh_site` d'abord.

```bash
cd ~/bh_site
```

---

## 🐳 Docker — démarrer / arrêter

| Commande | Rôle |
|---|---|
| `docker compose up -d` | **démarrer** les 4 conteneurs (le matin) |
| `docker compose ps` | vérifier qu'ils tournent |
| `docker compose down` | **arrêter** (le soir) |
| `docker compose restart` | redémarrer |
| `docker compose logs -f php` | voir les logs PHP en direct (Ctrl+C pour sortir) |
| `docker compose up -d --build` | reconstruire après modif du `Dockerfile` |

⚠️ **Docker Desktop doit être lancé** (icône baleine dans la barre du haut) avant toute commande.

### 🌐 Les URLs
- Site / admin → **http://localhost:8080**
- phpMyAdmin → **http://localhost:8081** (login `bh_user` / `bh_pass`)

---

## ⚙️ Symfony — TOUJOURS via le conteneur

> Réflexe : PHP tourne **dans** Docker, donc toute commande commence par `docker compose exec php`.

```bash
docker compose exec php php bin/console <commande>
docker compose exec php composer <commande>
```

| Besoin | Commande |
|---|---|
| Vider le cache | `docker compose exec php php bin/console cache:clear` |
| Lister les routes | `docker compose exec php php bin/console debug:router` |
| Créer un admin | `docker compose exec php php bin/console app:create-admin` |
| Voir les comptes admin | `docker compose exec php php bin/console dbal:run-sql "SELECT id, email FROM admin_user"` |
| Créer une entité | `docker compose exec php php bin/console make:entity` |
| Créer un contrôleur | `docker compose exec php php bin/console make:controller` |
| Créer une migration | `docker compose exec php php bin/console make:migration` |
| Appliquer les migrations | `docker compose exec php php bin/console doctrine:migrations:migrate` |
| Vérifier le schéma | `docker compose exec php php bin/console doctrine:schema:validate` |
| Compiler le CSS Tailwind | `docker compose exec php php bin/console tailwind:build` |

---

## 🗄️ Base de données

```bash
# Exécuter une requête SQL directement
docker compose exec php php bin/console dbal:run-sql "SELECT * FROM color"

# Entrer dans MySQL en ligne de commande
docker compose exec database mysql -u bh_user -pbh_pass bh_site
```

**Identifiants dev** : base `bh_site` · user `bh_user` · pass `bh_pass` · hôte (depuis les conteneurs) `database`

---

## 🌿 Git

```bash
git status                    # voir ce qui a changé
git add .                     # tout préparer
git commit -m "feat: ..."     # enregistrer
git push                      # envoyer sur GitHub
git log --oneline -10         # les 10 derniers commits
```

**Préfixes de commit** : `feat:` (nouveauté) · `fix:` (correction) · `refactor:` (réécriture) · `chore:` (config/outillage) · `docs:` (documentation)

---

## 🔍 Vérifications utiles

```bash
grep -rn "findAll()" src/     # doit renvoyer ZÉRO résultat (choix projet)
```

**Le profiler** : barre noire en bas des pages en mode dev → onglet **Doctrine** = voir les requêtes SQL exécutées, onglet **Security** = voir l'utilisateur connecté et ses rôles.

---

## 🆘 Ça ne marche pas ?

| Symptôme | À essayer |
|---|---|
| « Cannot connect to the Docker daemon » | lancer **Docker Desktop** |
| Page blanche / erreur bizarre | `docker compose exec php php bin/console cache:clear` |
| Le CSS n'est pas à jour | `docker compose exec php php bin/console tailwind:build` |
| « Invalid CSRF token » au login | rafraîchir en forçant : **Cmd + Shift + R** |
| Route introuvable | `docker compose exec php php bin/console debug:router` |
| Conteneur mort | `docker compose ps` puis `docker compose up -d` |
