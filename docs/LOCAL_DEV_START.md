# Demarrage local AEGIS MAILING

La vraie application Laravel est dans `app/`.
Pas de Docker, pas de Sail, pas de conteneur.

## Commande unique

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

## URL locale

Ouvrir `http://127.0.0.1:8001/dashboard`

## Ce que fait le script

- verifie `php`, `composer` et `npm`
- verifie `app/vendor` et `app/node_modules`
- cree `app/.env` depuis `app/.env.example` si besoin
- genere `APP_KEY` si besoin
- cree `app/database/database.sqlite` si besoin
- lance `php artisan migrate --no-interaction`
- demarre Laravel sur `127.0.0.1:8001`
- demarre Vite sur `127.0.0.1:5173`
- enregistre les PID pour permettre un arret propre

## Arret propre

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action stop
```

## Verification de l'etat

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action status
```

## Apres reboot / fermeture VS Code

Relancer exactement la meme commande :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

## Services obligatoires et optionnels

- Obligatoire pour afficher l'interface : Laravel + Vite
- Optionnel pour simple consultation de l'interface : worker de queue
- Non requis en local pour l'UI : Redis, Docker, Sail, mail-gateway Node

## Erreurs frequentes

- `Commande introuvable`: installer l'outil manquant (`php`, `composer`, `npm`)
- `Dependances PHP absentes`: lancer `cd .\app ; composer install`
- `Dependances Node absentes`: lancer `cd .\app ; npm install`
- `Le port 8001 est deja utilise`: liberer le port ou arreter l'ancien environnement avec `powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action stop`
- `no such column` ou `no such table`: le schema SQLite local est desaligne — voir la section Reset ci-dessous

## Reset de la base SQLite locale

Si le schema local est desaligne (colonnes manquantes, migrations en attente), utiliser le script dedie :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1
```

Ce script :

- supprime `app/database/database.sqlite`
- recree le fichier vide
- reapplique toutes les migrations depuis zero
- verifie qu'aucune migration ne reste en attente

Optionnel avec seeding :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1 -Seed
```

**Quand l'utiliser :**

- Apres un `git pull` qui ajoute de nouvelles migrations
- Si l'application affiche une erreur de schema (`no such column`, `no such table`)
- Pour repartir d'une base propre sans donnees

**Ce que le script ne touche pas :**

- La base de test PHPUnit (`:memory:`, recree a chaque test)
- La base E2E (`database/e2e.sqlite`, gere par `e2e-serve.ps1`)
- Les fichiers `.env` ou la configuration

## Difference entre les bases locales

| Base            | Fichier                    | Gestion                     | Usage                |
| --------------- | -------------------------- | --------------------------- | -------------------- |
| Applicative     | `database/database.sqlite` | `dev.ps1` ou `reset-db.ps1` | Developpement local  |
| Tests unitaires | `:memory:`                 | `phpunit.xml`               | Recree a chaque test |
| E2E / smoke     | `database/e2e.sqlite`      | `e2e-serve.ps1`             | Tests Playwright     |
