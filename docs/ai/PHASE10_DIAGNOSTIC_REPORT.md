# PHASE 10 — RAPPORT DIAGNOSTIC COMPLET

**Baseline** : commit `cfcc260` (Phase 9) · branche `main`
**Tests** : 138 passed, 1711 assertions
**DB live** : Campaign 3 = `sent`, Recipient 4 = `clicked`, 0 recipients stuck, 61 events

---

## 1. RÉSUMÉ EXÉCUTIF

Le code backend d'AEGIS MAILING est **fonctionnellement correct** sur tous les axes critiques. L'investigation Phase 10 n'a trouvé **aucun bug bloquant** ni incohérence de données active. Les constats sont :

- **Machine à états** : ✅ Conforme aux 15 statuts gelés, aucune transition interdite
- **Chaîne queue/SMTP** : ✅ Transactions, idempotence, gardes — tout correct
- **Tracking** : ✅ Code correct, MAIS non-opérationnel en local (par design)
- **Stats/compteurs** : ✅ Mécaniquement corrects, quelques incohérences cosmétiques inter-pages
- **Timestamps** : ✅ Affichage correct partout, code mort inoffensif dans DiagnosticController
- **SMTP2GO** : ⏸️ Problème SSL/STARTTLS documenté, reporté (non-bloquant, OVH MX Plan est le provider V1)

**Aucun patch d'urgence n'est nécessaire.** Seules des améliorations de propreté sont recommandées.

---

## 2. BASELINE CONFIRMÉE

| Élément           | Valeur                                             |
| ----------------- | -------------------------------------------------- |
| Commit            | `cfcc260` (Phase 9)                                |
| Branche           | `main`                                             |
| Tests             | 138 passed, 1711 assertions                        |
| Migration         | `2026_03_15_140000_create_aegis_mailing_tables`    |
| Timezone          | `Europe/Paris` (config/app.php)                    |
| APP_URL           | `http://127.0.0.1:8001`                            |
| Public base URL   | `https://aegisnetwork.fr` (setting DB)             |
| Tracking base URL | `null` → cascade vers `aegisnetwork.fr`            |
| Gateway driver    | OVH MX Plan (vérifié fonctionnel)                  |
| DB                | SQLite, pas de recipients stuck, 0 erreurs actives |

---

## 3. AXE 1 — MACHINE À ÉTATS (STATUTS)

### Méthode

Audit exhaustif de chaque entité et de chaque transition de statut dans le code.

### Résultats

| Entité        | Statuts utilisés                                                         | Conforme ? |
| ------------- | ------------------------------------------------------------------------ | ---------- |
| MailDraft     | `draft`, `scheduled`                                                     | ✅         |
| MailCampaign  | `draft`, `scheduled`, `queued`, `sending`, `sent`, `failed`, `cancelled` | ✅         |
| MailRecipient | Les 15 statuts gelés                                                     | ✅         |
| MailMessage   | Pas de colonne `status` (utilise `classification`)                       | ✅ N/A     |

### Transitions vérifiées

```
Draft: draft → scheduled (schedule())
       scheduled → draft (unschedule() — Phase 9 guarded)

Campaign: draft → scheduled (createFromDraft)
          scheduled → queued (queueCampaign)
          queued → sending (dispatchQueuedMessage)
          sending → sent|failed (refreshCampaignStatus)

Recipient: draft → queued (queueCampaign, dans transaction)
           queued → sending (dispatchQueuedMessage, garde canDispatchQueuedMessage)
           sending → sent|failed (markSent/markFailed, dans transaction)
           sent → opened → clicked → replied (via tracking events)
           sent → soft_bounced|hard_bounced (via classification inbound)
```

### Gardes vérifiées

- ✅ `canDispatchQueuedMessage()` : vérifie `status === 'queued'`
- ✅ `shouldAutoStop()` : arrêt automatique si seuil d'échecs atteint
- ✅ Idempotency key `'dispatch.'.$message->id` : empêche double-envoi
- ✅ `unschedule()` Phase 9 : bloque si recipients déjà dispatched
- ✅ `syncAutosavedCampaign()` : préserve statut si `in_array($campaign->status, ['scheduled', 'queued', 'sending', 'sent'])`

### Constats

- **`delivered_if_known` n'est jamais persisté** comme statut recipient — c'est une valeur d'affichage calculée uniquement dans `MailboxActivityService` pour les messages inbound avec classification `unknown` (ligne 141). Ce n'est pas un bug, c'est le design. Le scoring l'inclut dans `sentRecipientStatuses()`.
- **Aucune violation de statut gelé trouvée** dans aucun fichier.

### Verdict : ✅ CONFORME

---

## 4. AXE 2 — CHAÎNE QUEUE/WORKER/SMTP

### Flux complet tracé

```
DraftService.schedule()
  → CampaignService.createFromDraft()  [transaction]
    → OutboundMailService.queueCampaign()  [transaction]
      → Recipients: draft → queued
      → Campaign: → queued
      → DispatchMailMessageJob dispatched (queue: mail-outbound)

DispatchMailMessageJob.handle()
  → OutboundMailService.dispatchQueuedMessage()
    → canDispatchQueuedMessage() [garde]
    → shouldAutoStop() [check seuil]
    → Recipient: queued → sending
    → gatewayClient.dispatchMessage()
      ├── Succès → markSent() [transaction] → Recipient: → sent
      └── Échec  → markFailed() [transaction] → Recipient: → failed
    → refreshCampaignStatus() [après chaque recipient]
      ├── remaining > 0 → Campaign: sending
      └── remaining === 0 → Campaign: sent|failed + completed_at
```

### Sécurité vérifiée

- ✅ Toutes les transitions sont dans des `DB::transaction()`
- ✅ Le job utilise `UniqueJob` avec clé `'dispatch.'.$message->id`
- ✅ `refreshCampaignStatus()` est appelé après CHAQUE `markSent()` et `markFailed()` — pas de risque de campaign "oubliée" en `sending`
- ✅ Le diagnostic détecte les recipients stuck (>30 min dans `queued`/`sending`)

### Risque opérationnel identifié

- ⚠️ **Crash du worker entre le `queued → sending` et le `markSent/markFailed`** : le recipient reste bloqué en `sending`. Le diagnostic le détecte mais il n'y a **pas de recovery automatique**. C'est un risque acceptable en V1 (opérateur surveille le diagnostic), mais un job de recovery pourrait être ajouté en V2.

### Verdict : ✅ CORRECT — 1 risque opérationnel documenté, non-bloquant

---

## 5. AXE 3 — TRACKING (OPENS/CLICKS)

### Architecture

```
Envoi:
  MailTrackingService.injectOpenPixel()   → <img src="https://aegisnetwork.fr/t/o/{token}.gif">
  MailTrackingService.rewriteTrackedLinks() → href → https://aegisnetwork.fr/t/c/{token}

Réception tracking:
  GET /t/o/{token}.gif → TrackingController → MailTrackingService.registerOpen()
    → MailMessage.opened_first_at = now()
    → MailRecipient.status: sent|delivered_if_known → opened
    → MailRecipient.score_bucket = 'warm'
    → MailEvent logged

  GET /t/c/{token}   → TrackingController → MailTrackingService.registerClickAndResolveRedirect()
    → MailMessage.clicked_first_at = now()
    → MailRecipient.status: sent|delivered_if_known|opened → clicked
    → MailRecipient.score_bucket = 'interested'
    → MailEvent logged
    → 302 redirect vers URL originale
```

### Cascade de résolution d'URL

```
1. settings.deliverability.tracking_base_url   (DB) → null actuellement
2. env MAIL_TRACKING_BASE_URL                  → non défini
3. settings.deliverability.public_base_url     (DB) → https://aegisnetwork.fr
4. null (si tout échoue)
```

→ URL tracking résolue = `https://aegisnetwork.fr`

### Validation preflight

| Scénario                                         | Preflight             | Envoi     | Tracking opérationnel                                          |
| ------------------------------------------------ | --------------------- | --------- | -------------------------------------------------------------- |
| Local dev, HTTP only                             | ❌ BLOQUÉ (pas HTTPS) | ❌        | —                                                              |
| Local dev + `aegisnetwork.fr` comme tracking URL | ✅ OK                 | ✅ ENVOYÉ | ❌ NON — aegisnetwork.fr ne pointe pas vers l'app locale       |
| Production `https://aegisnetwork.fr`             | ✅ OK                 | ✅ ENVOYÉ | ✅ OUI — uniquement si le domaine route vers cette app Laravel |

### CONSTAT CRITIQUE

**Le tracking n'est pas et ne peut pas être opérationnel en développement local.** Les emails envoyés contiennent des URLs tracking valides (`https://aegisnetwork.fr/t/o/{token}.gif`) mais les requêtes HTTP des clients mail n'atteignent jamais l'application locale. C'est le comportement attendu — le tracking ne fonctionnera qu'en production lorsque `aegisnetwork.fr` sera routé vers le serveur qui exécute cette application Laravel.

**Ce n'est pas un bug.** Les compteurs montrent correctement 0 opens/clicks car aucun événement tracking n'est enregistré. Le recipient 4 affiche `clicked` parce que ce statut a été défini manuellement (repair DB Phase 9).

### Disable tracking

Si `tracking_opens_enabled = false` et `tracking_clicks_enabled = false` :

- Pas d'injection de pixel ni de réécriture de liens à l'envoi
- Les routes `/t/o/` et `/t/c/` rejettent les requêtes
- Pas de mise à jour de statut

### Verdict : ✅ CODE CORRECT — non-opérationnel en local (par design, pas un bug)

---

## 6. AXE 4 — STATS ET COMPTEURS

### Inventaire complet

| Compteur          | Localisation                                         | Logique                                                                          | Entité        |
| ----------------- | ---------------------------------------------------- | -------------------------------------------------------------------------------- | ------------- |
| `sentToday`       | CrmPageDataService L97 + ComposerPageDataService L92 | `MailMessage.direction=out AND sent_at today`                                    | MailMessage   |
| `bounceRate`      | CrmPageDataService L53-64, L101-103                  | `(soft_bounced + hard_bounced) / sentRecipients * 100` — ALL-TIME                | MailRecipient |
| `activeCampaigns` | CrmPageDataService L104-106                          | `MailCampaign.status IN (scheduled, queued, sending)`                            | MailCampaign  |
| `scheduledCount`  | CrmPageDataService L107                              | `MailDraft.status=scheduled AND scheduled_at NOT NULL`                           | MailDraft     |
| `progressPercent` | CampaignService L355-372                             | `completed / total * 100` — completed = tous sauf queued/sending/scheduled/draft | MailRecipient |
| `openCount`       | CampaignService L214                                 | `recipients.status IN (opened, clicked, replied, auto_replied)`                  | MailRecipient |
| `replyCount`      | CampaignService L215                                 | `recipients.status = replied`                                                    | MailRecipient |
| `bounceCount`     | CampaignService L216                                 | `recipients.status IN (soft_bounced, hard_bounced)`                              | MailRecipient |

### Incohérences identifiées

| #   | Incohérence                                                                                                                                                           | Sévérité | Impact                                                                                            |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------- |
| S1  | `sentToday` compte des `MailMessage`, `bounceRate` compte des `MailRecipient` — entités différentes                                                                   | Basse    | Latent — diverge uniquement si retries créent plusieurs messages par recipient (pas le cas V1)    |
| S2  | `bounceRate` est ALL-TIME, affiché à côté de `sentToday` (quotidien) — confusion possible                                                                             | Basse    | Cosmétique — l'utilisateur pourrait penser que c'est le taux du jour                              |
| S3  | `progressPercent` inclut `failed` et `cancelled` dans "completed", `sentRecipientStatuses()` les exclut — définition différente de "envoyé"                           | Basse    | Logiquement défendable (progress = completion, bounce rate = deliverability) mais peut surprendre |
| S4  | Pas de `clickCount` dans le serializer campaign — les clics ne sont pas remontés sur la page détail campaign                                                          | Info     | Manque cosmétique                                                                                 |
| S5  | `auto_replied` compté dans `openCount` — une auto-réponse n'implique pas forcément qu'un humain a lu l'email                                                          | Basse    | Légère inflation de l'open rate                                                                   |
| S6  | Activity page : un open/click peut apparaître deux fois (MailMessage entry + MailEvent entry)                                                                         | Basse    | Duplication visuelle, pas fonctionnelle                                                           |
| S7  | Dashboard `activeCampaigns` = niveau campaign, Diagnostic `stuckRecipients` = niveau recipient — un campaign peut être "active" au dashboard et "stuck" au diagnostic | Moyenne  | Pas de signal croisé entre les deux vues                                                          |

### Vérification anti-faux-positifs

- ✅ Tous les compteurs tracking (opens, clicks) sont basés sur des événements réels reçus via les routes `/t/o/` et `/t/c/`
- ✅ Impossible d'avoir des opens/clicks "fantômes" — ils n'existent que si le tracking est fonctionnel et qu'une requête HTTP a été reçue
- ✅ Les 0 opens/clicks actuels sont corrects (tracking non-opérationnel en local)

### Verdict : ✅ MÉCANIQUEMENT CORRECT — 7 points cosmétiques/mineurs

---

## 7. AXE 5 — TIMESTAMPS / TIMEZONE / DATES

### Lifecycle complet de `2026-03-21T09:38:11.000000Z`

| Étape                                   | Valeur                        | Timezone                             |
| --------------------------------------- | ----------------------------- | ------------------------------------ |
| `Carbon::now()` au moment du clic       | `2026-03-21 10:38:11`         | Europe/Paris (CET, UTC+1)            |
| Stocké SQLite                           | `2026-03-21 10:38:11`         | Paris implicite (pas de marqueur TZ) |
| Relu via cast `immutable_datetime`      | `CarbonImmutable 10:38:11`    | Europe/Paris                         |
| Eloquent `serializeDate()` → `toJSON()` | `2026-03-21T09:38:11.000000Z` | Converti en UTC                      |
| Frontend `new Date()`                   | Instant UTC 09:38:11          | UTC                                  |
| Affichage `formatDateFR()`              | `21/03/2026 - 10h38`          | Europe/Paris ✅                      |

### CONSTAT : Le "fix" Phase 9 dans DiagnosticController est un NO-OP

```php
// DiagnosticController::events() — ligne 72-73
$event->occurred_at = $event->occurred_at?->timezone($tz);
```

**Pourquoi c'est un no-op :**

1. `$event->occurred_at` est déjà un `CarbonImmutable` en `Europe/Paris` (via le cast)
2. `->timezone('Europe/Paris')` sur une valeur déjà en Paris = rien ne change
3. L'assignation repasse par le cast (re-format en `Y-m-d H:i:s`)
4. `response()->json()` → `toArray()` → `serializeDate()` → `toJSON()` → **toujours UTC avec `Z`**

La même chose pour `health()` ligne 159 : `$lastEvent?->timezone(config('app.timezone'))` est aussi un no-op.

### Deux formats de sérialisation coexistent

| Source                                             | Format                                                     | Correct ?                |
| -------------------------------------------------- | ---------------------------------------------------------- | ------------------------ |
| Services manuels (Activity, Mails, CRM, Templates) | `->timezone('Europe/Paris')->toIso8601String()` → `+01:00` | ✅                       |
| DiagnosticController (Eloquent auto-serialization) | `serializeDate()` → `toJSON()` → `Z` (UTC)                 | ✅ Techniquement correct |

### Pourquoi ça marche quand même

`formatDateFR()` (frontend) gère les deux formats :

- `new Date('2026-03-21T09:38:11.000000Z')` → instant UTC → affiché en Paris ✅
- `new Date('2026-03-21T10:38:11+01:00')` → même instant → affiché en Paris ✅

**L'utilisateur voit la bonne heure dans tous les cas.**

### Verdict : ✅ AFFICHAGE CORRECT PARTOUT — code mort à nettoyer (non-bloquant)

---

## 8. AXE 6 — SMTP2GO

| Élément   | État                                        |
| --------- | ------------------------------------------- |
| Problème  | SSL/STARTTLS mismatch sur port 587          |
| Documenté | Phase 8, confirmé Phase 9                   |
| Impact V1 | Aucun — OVH MX Plan est le seul provider V1 |
| Action    | Reporté post-V1                             |

### Verdict : ⏸️ REPORTÉ — non-bloquant pour V1

---

## 9. VÉRITÉ DB vs AFFICHAGE — ÉTAT ACTUEL

| Donnée             | DB        | Affichage attendu             | Status |
| ------------------ | --------- | ----------------------------- | ------ |
| Campaign 3 status  | `sent`    | Envoyée                       | ✅     |
| Recipient 4 status | `clicked` | Cliqué                        | ✅     |
| Recipients stuck   | 0         | Aucun problème affiché        | ✅     |
| Events count       | 61        | 61 events dans diagnostic     | ✅     |
| Campaigns count    | 1         | 1 campaign (+ drafts)         | ✅     |
| Recipients count   | 2         | 2 recipients pour la campaign | ✅     |

---

## 10. FICHIERS POUVANT ÊTRE MODIFIÉS (RECOMMANDATIONS)

### Priorité BASSE — Nettoyage code mort

| Fichier                                                    | Modification                                                           | Raison                                                                         |
| ---------------------------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `app/Http/Controllers/Api/DiagnosticController.php` L72-73 | Supprimer `$event->occurred_at = $event->occurred_at?->timezone($tz);` | No-op — la conversion timezone n'a aucun effet car Eloquent resérialise en UTC |
| `app/Http/Controllers/Api/DiagnosticController.php` L159   | Supprimer `?->timezone(config('app.timezone'))`                        | Même raison                                                                    |

### Priorité BASSE — Cohérence de sérialisation

| Fichier                                                      | Modification                                                              | Raison                                                                 |
| ------------------------------------------------------------ | ------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `app/Http/Controllers/Api/DiagnosticController.php` events() | Utiliser `formatDate()` manuel comme les services (→ `toIso8601String()`) | Aligner le format de date avec le reste de l'app (offset au lieu de Z) |

### Priorité BASSE — Amélioration stats

| Fichier                          | Modification                                                | Raison                                               |
| -------------------------------- | ----------------------------------------------------------- | ---------------------------------------------------- |
| `CampaignService.php` serializer | Ajouter `clickCount` (recipients status = clicked, replied) | Manque actuel — clics non remontés sur page campaign |

---

## 11. PLAN DE CORRECTION CIBLÉ

**Aucune correction urgente n'est nécessaire.** Liste de nettoyages optionnels :

### Micro-correction 1 : Supprimer code mort DiagnosticController

- **Fichier** : `DiagnosticController.php`
- **Lignes** : 72-73, 159
- **Risque** : Zéro — supprime du code qui n'a aucun effet
- **Test** : Les tests existants passent sans changement

### Micro-correction 2 : Unifier le format de sérialisation (optionnel)

- **Fichier** : `DiagnosticController.php`
- **Lignes** : events() transform
- **Approche** : Ajouter un `formatDate()` helper comme dans les autres services
- **Test** : Ajouter un test unitaire vérifiant le format ISO avec offset

### Amélioration 3 : Ajouter `clickCount` au serializer campaign (optionnel)

- **Fichier** : `CampaignService.php`
- **Ligne** : ~216
- **Approche** : `$recipients->whereIn('status', ['clicked', 'replied'])->count()`
- **Contract** : Ajouter `clickCount: number` au payload campaign

---

## 12. TESTS À AJOUTER (OPTIONNEL)

| Test                                               | Fichier                         | Objectif                                                 |
| -------------------------------------------------- | ------------------------------- | -------------------------------------------------------- |
| `test_diagnostic_events_dates_are_iso8601`         | `DiagnosticControllerTest.php`  | Vérifier le format de date retourné par l'API            |
| `test_campaign_refresh_status_transitions_to_sent` | `OutboundMailServiceTest.php`   | Prouver que `refreshCampaignStatus` termine correctement |
| `test_tracking_url_requires_https_public_domain`   | `PublicEmailUrlServiceTest.php` | Vérifier la cascade et le rejet localhost/privé          |

---

## 13. DOCUMENTATION MIS À JOUR

**Aucune mise à jour de documentation nécessaire** — les docs existantes sont à jour post-Phase 9.

---

## 14. RAPPORT CHATGPT FORMAT

```
DIAGNOSTIC COMPLET PHASE 10 — AEGIS MAILING
Commit: cfcc260 (Phase 9) | 138 tests, 1711 assertions | DB: SQLite

MACHINE À ÉTATS : ✅ CONFORME
- 15 statuts gelés respectés, aucune transition interdite
- delivered_if_known = valeur d'affichage calculée, jamais persisté comme statut recipient

QUEUE/SMTP : ✅ CORRECT
- Transactions, idempotence, gardes, refreshCampaignStatus après chaque recipient
- Risque opérationnel : crash worker → recipient stuck (détecté par diagnostic, pas d'auto-recovery)

TRACKING : ✅ CODE CORRECT, NON-OPÉRATIONNEL EN LOCAL
- URLs tracking pointent vers aegisnetwork.fr
- En local, les requêtes tracking n'atteignent jamais l'app → 0 opens/clicks (correct)
- En production, fonctionnera si aegisnetwork.fr route vers cette app Laravel

STATS/COMPTEURS : ✅ MÉCANIQUEMENT CORRECTS
- 7 incohérences mineures/cosmétiques identifiées (détail dans rapport)
- Principale : bounceRate all-time vs sentToday quotidien, pas de clickCount campaign

TIMESTAMPS : ✅ AFFICHAGE CORRECT PARTOUT
- Le "fix" Phase 9 DiagnosticController = NO-OP (2 lignes de code mort)
- Deux formats coexistent (Z vs +01:00), les deux gérés par formatDateFR
- Utilisateur voit toujours l'heure Paris correcte

SMTP2GO : ⏸️ REPORTÉ (OVH MX Plan = seul provider V1)

ACTIONS : Aucun patch urgente. 3 nettoyages optionnels proposés.
- Supprimer code mort DiagnosticController (2 lignes)
- Unifier format sérialisation dates (cosmétique)
- Ajouter clickCount au serializer campaign (cosmétique)
```
