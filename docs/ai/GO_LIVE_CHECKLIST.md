# Go-Live Checklist

## Scope

Checklist concrète de validation post-déploiement et de rollback pour AEGIS MAILING V1.

Règles gardées :

- une seule boîte OVH MX Plan
- réception IMAP
- envoi SMTP
- une seule queue sortante
- pas de Gmail
- pas de multi-provider

## Checklist post-déploiement

### 1. Santé applicative

- l’URL publique répond en HTTPS
- `/dashboard` charge sans erreur 500
- le build frontend est servi correctement
- `public/build` est présent
- aucune erreur manifeste dans `storage/logs/laravel.log`
- `APP_ENV=production`
- `APP_DEBUG=false`

### 2. Santé Laravel

Depuis le serveur :

```bash
php artisan about
php artisan migrate:status
php artisan route:list --compact
```

Vérifier :

- connexion DB OK
- migrations toutes appliquées
- routes web et API attendues présentes

### 3. Santé PostgreSQL

Vérifier :

- connexion PostgreSQL OK
- tables présentes
- écriture/lecture OK
- espace disque suffisant
- sauvegarde active ou planifiée

Contrôle minimal :

```bash
php artisan migrate:status
```

### 4. Santé Redis

Si Redis est utilisé :

- connexion Redis OK
- cache opérationnel
- queue opérationnelle
- mémoire disponible
- clé bloquante anormale non persistante

### 5. Queue worker

Vérifier :

- le worker queue tourne
- il consomme bien `mail-outbound` et `mail-sync`
- pas d’accumulation anormale dans `jobs`
- pas d’explosion dans `failed_jobs`

Contrôles utiles :

```bash
sudo systemctl status aegis-queue
php artisan queue:work --queue=mail-outbound,mail-sync --tries=1 --sleep=1 --timeout=120
```

La seconde commande est un contrôle manuel ponctuel, pas à laisser en doublon si le service tourne déjà.

### 6. Scheduler / cron

Vérifier :

- le cron `schedule:run` est installé
- il s’exécute chaque minute
- `mailbox:poll` est bien planifié toutes les 5 minutes

Contrôle manuel :

```bash
php artisan schedule:list
php artisan schedule:run
```

### 7. Santé mailbox/settings

Dans l’application :

- la boîte OVH est bien configurée dans Réglages
- `send_enabled` cohérent avec la stratégie go-live
- `sync_enabled` cohérent avec la stratégie go-live
- la fenêtre d’envoi est correcte
- la signature globale est présente si attendue

### 8. Tests IMAP/SMTP

Vérifier depuis l’UI ou l’API :

- `POST /api/settings/mail/test-smtp`
- `POST /api/settings/mail/test-imap`

Résultat attendu :

- succès explicite
- pas de warning bloquant sur la mailbox

### 9. Logs et événements métier

Surveiller :

- `storage/logs/laravel.log`
- table `mail_events`
- table `failed_jobs`

Événements importants :

- `settings.mail.updated`
- `mail_message.queued`
- `mail_message.dispatch_skipped`
- `mail_message.sent`
- `mail_message.failed`
- `mailbox.sync_skipped_locked`
- `mail_campaign.auto_stopped`

## Checklist fonctionnelle go-live

### Navigation

Vérifier que les pages suivantes chargent :

- `/dashboard`
- `/contacts`
- `/organizations`
- `/mails`
- `/drafts`
- `/templates`
- `/campaigns`
- `/activity`
- `/settings`
- `/users`

### Templates

- créer un template
- éditer un template
- dupliquer un template
- archiver / réactiver un template

### Drafts / Composer

- ouvrir le composer
- créer un draft simple
- créer un draft multiple
- sauvegarder un draft
- réouvrir un draft existant
- déprogrammer un draft planifié

### Preflight

- lancer un preflight sur un draft valide
- vérifier erreurs bloquantes si destinataires invalides
- vérifier warnings délivrabilité si cas présents

### Campaigns / Scheduling

- planifier un draft futur
- vérifier la création campaign + recipients
- vérifier que le statut reste cohérent (`scheduled` / `queued`)

### Envoi

Si l’envoi réel est activé dans l’environnement :

- planifier un envoi simple de test
- vérifier `mail_message.sent` ou `mail_message.failed`
- vérifier `Message-ID` et `aegis_tracking_id` persistés

Si le gateway réel n’est pas prêt :

- laisser `MAIL_GATEWAY_DRIVER=stub`
- limiter le go-live à la validation applicative et métier hors envoi réel

### Sync / Activity / Timeline

Si la sync réelle est activée :

- lancer `php artisan mailbox:poll`
- vérifier création ou mise à jour de messages
- vérifier distinction `replied` / `auto_replied`
- vérifier remontée `hard_bounced`
- vérifier l’alimentation de `/activity`

## Plan de rollback

### 1. Déclenchement

Rollback à envisager si :

- erreur 500 persistante
- build frontend cassé
- migration problématique
- queue worker instable
- tests IMAP/SMTP KO après déploiement

### 2. Rollback code

Revenir au commit ou tag stable précédent :

```bash
cd /var/www/aegis-mailing/app
git checkout <tag-ou-commit-stable>
cd app
composer install --no-dev --optimize-autoloader
npm run build
```

### 3. Migrations

Principe réaliste :

- ne rollback pas automatiquement une migration en production sans validation explicite
- commencer par vérifier si le rollback code suffit
- si une migration doit être annulée, le faire manuellement et avec sauvegarde confirmée

Contrôles :

```bash
php artisan migrate:status
```

### 4. Workers / scheduler

Après rollback code :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart aegis-queue
```

Le cron `schedule:run` n’a normalement pas besoin d’être modifié si le chemin de release reste identique.

### 5. Contrôles après rollback

Vérifier immédiatement :

- `/dashboard`
- `/settings`
- statut du worker
- logs Laravel
- absence d’erreurs 500
- `test-smtp`
- `test-imap`

## Décision go / no-go

### Go si

- app accessible
- build OK
- DB OK
- Redis OK si utilisé
- worker OK
- scheduler OK
- réglages mailbox présents
- tests IMAP/SMTP OK
- navigation et parcours critiques OK

### No-go si

- erreurs 500 récurrentes
- worker ou cron indisponible
- tests IMAP/SMTP KO
- queue bloquée
- logs applicatifs en erreur continue
