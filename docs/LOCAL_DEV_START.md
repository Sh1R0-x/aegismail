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
