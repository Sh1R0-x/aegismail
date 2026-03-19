# OVH Deployment Runbook

## Scope

Ce runbook prépare une mise en ligne réaliste d'AEGIS MAILING sur une infrastructure simple type OVH, sans Docker, sans Sail et sans multi-provider.

Règles figées gardées :

- une seule boîte OVH MX Plan
- IMAP pour la réception
- SMTP pour l’envoi
- une seule queue sortante
- statuts gelés inchangés

## Compatibilité constatée

### Ce que le repo supporte bien aujourd’hui

- déploiement Laravel classique sur PHP >= 8.2
- build frontend statique Vite
- PostgreSQL comme cible production réaliste
- Redis recommandé pour queue, cache et sessions
- scheduler Laravel avec cron
- worker queue Laravel séparé
- stockage local `storage/`

### Ce qui impose une contrainte d’hébergement

- la V1 complète nécessite des processus long-lived :
    - `php artisan queue:work`
    - `php artisan schedule:run` via cron
    - un mail-gateway HTTP si on veut un envoi/sync réels hors stub

### Conclusion honnête

- **OVH VPS basique** : compatible et recommandé pour une V1 réelle
- **OVH mutualisé** : acceptable seulement pour un mode dégradé ou une démo applicative sans flux mail temps réel complet

Raisons :

- pas de process manager durable sur mutualisé
- Redis généralement absent
- mail-gateway Node non hébergeable proprement sur un mutualisé simple

## État réel du mail-gateway

Le repo Laravel sait déjà parler à un client :

- `stub`
- `http`

Le dossier `mail-gateway/` embarque maintenant un serveur Node minimal compatible avec la V1 :

- `POST /v1/tests/imap`
- `POST /v1/tests/smtp`
- `POST /v1/messages/send`
- `POST /v1/mailboxes/sync`

Conséquence pratique :

- **déploiement applicatif prêt** : oui
- **mise en ligne complète avec SMTP/IMAP réels via gateway HTTP** : oui, si ce gateway Node est effectivement lancé et supervisé sur le serveur cible

## Prérequis serveur OVH recommandés

### Minimum réaliste

- OVH VPS Linux
- PHP 8.2 ou 8.3 CLI + FPM
- Composer
- Node.js 18+
- Nginx ou Apache
- PostgreSQL
- Redis
- cron système
- accès SSH

### Extensions PHP à vérifier

- `mbstring`
- `openssl`
- `pdo_pgsql`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`
- `tokenizer`

## Variables d’environnement

Point de départ fourni :

- [app/.env.ovh.example](/c:/Dev/Aegis%20mail/app/.env.ovh.example)

Variables obligatoires à ajuster :

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- `MAIL_PUBLIC_BASE_URL`
- `MAIL_TRACKING_BASE_URL` si tracking sur un sous-domaine dédié
- `APP_KEY`
- `DB_*`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `SESSION_DRIVER`
- `REDIS_*` si Redis est utilisé
- `MAIL_GATEWAY_DRIVER`
- `MAIL_GATEWAY_BASE_URL` si mode `http`
- `MAIL_GATEWAY_SHARED_SECRET` si mode `http`
- `MAIL_OUTBOUND_QUEUE`
- `MAIL_SYNC_QUEUE`

Règles impératives pour les URLs publiques email :

- `APP_URL`, `MAIL_PUBLIC_BASE_URL` et `MAIL_TRACKING_BASE_URL` doivent être des URLs absolues en `https://`
- aucune de ces URLs ne doit pointer vers `localhost`, `127.0.0.1`, une IP privée ou un hostname interne
- `MAIL_TRACKING_BASE_URL` peut être omise seulement si `MAIL_PUBLIC_BASE_URL` ou `APP_URL` fournit déjà une base publique HTTPS valide
- `MAIL_GATEWAY_BASE_URL` est différent : c’est une URL interne entre Laravel et le service Node. Elle peut rester en `http://127.0.0.1:3001` sur le serveur et n’est jamais injectée dans les emails sortants

### Valeurs recommandées pour une vraie prod simple

- `DB_CONNECTION=pgsql`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `MAIL_GATEWAY_DRIVER=http` quand le gateway Node est déployé
- `APP_URL=https://mailing.example.com`
- `MAIL_PUBLIC_BASE_URL=https://mailing.example.com`
- `MAIL_TRACKING_BASE_URL=https://track.example.com` si vous isolez le tracking, sinon laisser vide pour réutiliser `MAIL_PUBLIC_BASE_URL`

## Procédure de déploiement

### 1. Préparer le serveur

Créer l’arborescence, par exemple :

- `/var/www/aegis-mailing/app`
- `/var/www/aegis-mailing/shared`

Créer les répertoires persistants :

- `storage/app`
- `storage/framework`
- `storage/logs`
- `bootstrap/cache`

Vérifier les permissions de l’utilisateur web sur :

- `storage/`
- `bootstrap/cache/`

### 2. Déployer le code

Exemple :

```bash
cd /var/www/aegis-mailing
git clone <repo> app
cd app/app
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 3. Préparer l’environnement

```bash
cp .env.ovh.example .env
php artisan key:generate --force
```

Puis renseigner :

- URL publique
- URL publique email/tracking
- base PostgreSQL
- Redis
- mode gateway

### 4. Migrer et optimiser Laravel

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Configurer le scheduler

Cron recommandé :

```cron
* * * * * cd /var/www/aegis-mailing/app/app && php artisan schedule:run >> /dev/null 2>&1
```

Ce cron couvre notamment :

- `mailbox:poll` toutes les 5 minutes via le scheduler Laravel

### 6. Configurer le worker queue

Commande recommandée :

```bash
php artisan queue:work --queue=mail-outbound,mail-sync --tries=1 --sleep=1 --timeout=120
```

Lancer ce worker via `systemd` ou Supervisor sur VPS.

### 7. Configurer le serveur web

Servir le dossier Laravel public :

- `/var/www/aegis-mailing/app/app/public`

Vérifier :

- HTTPS actif
- `APP_URL` aligné avec le domaine réel
- `MAIL_PUBLIC_BASE_URL` aligné avec le domaine public réellement joignable depuis une boîte externe
- `MAIL_TRACKING_BASE_URL` aligné avec le domaine public réellement joignable depuis une boîte externe, ou vide pour fallback
- redirection HTTP -> HTTPS

## Exemple systemd

### Worker Laravel

Fichier type `/etc/systemd/system/aegis-queue.service` :

```ini
[Unit]
Description=AEGIS Mailing Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/aegis-mailing/app/app/artisan queue:work --queue=mail-outbound,mail-sync --tries=1 --sleep=1 --timeout=120
WorkingDirectory=/var/www/aegis-mailing/app/app

[Install]
WantedBy=multi-user.target
```

Activation :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now aegis-queue
sudo systemctl status aegis-queue
```

## Build frontend

Le frontend Inertia/Vue est buildé statiquement :

```bash
npm run build
```

Le dossier utilisé en prod est :

- `app/public/build`

Pas de Node runtime requis pour servir le frontend buildé.

## PostgreSQL / Redis

### PostgreSQL

Checks minimum :

- connexion OK
- migrations passées
- timezone cohérente
- sauvegarde planifiée

### Redis

Checks minimum :

- connexion OK
- queue jobs visible
- locks fonctionnels
- mémoire surveillée

Si Redis n’est pas disponible :

- `database` reste possible
- mais moins robuste pour cadence, queue et locks en production

## SMTP / IMAP

### Ce que l’application peut vérifier

- `POST /api/settings/mail/test-smtp`
- `POST /api/settings/mail/test-imap`

### Avant ouverture réelle

Vérifier :

- credentials OVH MX Plan
- `send_enabled = true`
- `sync_enabled = true`
- fenêtre d’envoi cohérente
- daily/hourly limits cohérents
- `APP_URL` / `MAIL_PUBLIC_BASE_URL` / `MAIL_TRACKING_BASE_URL` cohérents et publics
- qu’aucune signature HTML n’embarque d’image en `http://`, `localhost` ou chemin relatif non résoluble publiquement

## Logs et surveillance

À surveiller :

- `storage/logs/laravel.log`
- table `jobs`
- table `failed_jobs`
- table `mail_events`

Événements clés :

- `settings.mail.updated`
- `mail_message.queued`
- `mail_message.dispatch_skipped`
- `mail_message.sent`
- `mail_message.failed`
- `mailbox.sync_skipped_locked`
- `mail_campaign.auto_stopped`

## Assets statiques publics (images de signature, etc.)

### Mapping URL ↔ filesystem — OVH mutualisé

Sur OVH mutualisé avec le dossier racine OVH Multisite configuré à `www` :

- **Le web root servi publiquement est `/home/<login>/www/`**
- **Ce dossier correspond exactement à `app/public/` dans le repo**
- `www/` est soit un lien symbolique vers `app/public/`, soit `app/public/` est déployé directement dedans

Preuve : `robots.txt` (situé dans `app/public/robots.txt` dans le repo) est accessible à `https://aegisnetwork.fr/robots.txt` → `www/robots.txt` → `app/public/robots.txt`.

Il n'existe **pas** de `.htaccess` racine dans `app/` : le seul `.htaccess` est `app/public/.htaccess`. Il ne redirige vers `index.php` que les requêtes pointant vers des fichiers qui n'existent pas physiquement. Tout fichier réel dans `app/public/` est servi directement par Apache sans passer par Laravel.

### Dossier dédié pour les images de signature

Le dossier `app/public/signatures/` est réservé aux images statiques destinées aux signatures email.

**Placement serveur :**

```
/home/<login>/www/signatures/<nom-image>.png
```

**URL résultante :**

```
https://aegisnetwork.fr/signatures/<nom-image>.png
```

**Exemple concret :**

- Image : `aegis-logo-compact-512.png`
- Chemin serveur : `/home/aegisno/www/signatures/aegis-logo-compact-512.png`
- URL publique : `https://aegisnetwork.fr/signatures/aegis-logo-compact-512.png`

### Erreur fréquente à éviter

Ne **pas** placer les fichiers dans `/home/<login>/www/public/`. Ce chemin crée un sous-dossier `public/` à l'intérieur de `app/public/`, ce qui n'est ni référencé par le repo ni servi correctement :

- `/public/aegis-logo-compact-512.png` → 404 (sous-dossier non géré ou bloqué par Apache)
- Ce design crée une confusion entre le dossier `public/` du repo et un sous-dossier ad hoc

### Convention pour les futurs assets

Pour publier d'autres images de signature ou assets statiques :

1. Ajouter le fichier dans `app/public/signatures/` dans le repo (ou le déposer directement en SFTP dans `www/signatures/`)
2. L'URL résultante est toujours `https://aegisnetwork.fr/signatures/<fichier>`
3. Aucune route Laravel, aucun controller, aucun middleware n'intervient : Apache sert le fichier directement

### Validation après placement

```bash
# Vérifier HTTP 200 + Content-Type image/png
curl -I https://aegisnetwork.fr/signatures/aegis-logo-compact-512.png

# Résultat attendu :
# HTTP/2 200
# content-type: image/png

# Vérifier que robots.txt est toujours intact
curl -I https://aegisnetwork.fr/robots.txt
# HTTP/2 200
# content-type: text/plain
```

### Usage dans une signature HTML

```html
<img
    src="https://aegisnetwork.fr/signatures/aegis-logo-compact-512.png"
    alt="AEGIS Network"
    width="120"
    style="display:block;"
/>
```

---

## Contrôles post-déploiement

1. Ouvrir `/dashboard`, `/drafts`, `/templates`, `/campaigns`, `/settings`
2. Vérifier que le build frontend charge sans 500
3. Vérifier `php artisan about`
4. Vérifier `php artisan migrate:status`
5. Vérifier `php artisan queue:work` lancé
6. Vérifier le cron `schedule:run`
7. Vérifier un `test-smtp`
8. Vérifier un `test-imap`
9. Sauvegarder un draft
10. Faire un preflight
11. Planifier un draft futur
12. Vérifier la création des jobs et recipients
13. Vérifier la source brute d’un email réel :
    - aucun `localhost`, `127.0.0.1` ou IP privée
    - pixel open en `https://`
    - lien tracké en `https://`
    - présence de `text/plain`
    - présence de `List-Unsubscribe` sur une campagne bulk

Checklist détaillée :

- `docs/ai/GO_LIVE_CHECKLIST.md`

## Redéploiement sobre

Ordre recommandé :

```bash
cd /var/www/aegis-mailing/app
git pull
cd app
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart aegis-queue
```

## Rollback simple

1. Revenir au commit ou tag précédent
2. Relancer :

```bash
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate:status
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart aegis-queue
```

3. Vérifier immédiatement :

- page `/dashboard`
- état du worker queue
- logs Laravel

Checklist détaillée :

- `docs/ai/GO_LIVE_CHECKLIST.md`

## Risques restants

- le repo embarque maintenant un gateway Node minimal, mais il faut toujours le superviser proprement en production
- un mutualisé OVH n’est pas une cible réaliste pour la V1 complète si on veut queue worker + scheduler + gateway
- Redis reste recommandé pour une vraie cadence de production, même si `database` peut dépanner
- Microsoft/Hotmail peut toujours classer un message en indésirable malgré SPF/DKIM passants si la réputation domaine/IP, le contenu ou le volume sont faibles ; ce patch corrige les défauts techniques évitables, pas la réputation intrinsèque
