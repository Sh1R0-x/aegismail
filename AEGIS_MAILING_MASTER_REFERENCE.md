# AEGIS MAILING — Référence projet V1

## 1. Objet

AEGIS MAILING est un outil interne de démarchage et de suivi e-mail pour AEGIS NETWORK.

Le produit ne reprend pas la logique Gmail de l’ancien projet OppoLES.
La cible V1 est exclusivement basée sur **une seule boîte OVH MX Plan**.

L’objectif principal est simple :
- envoyer des mails propres et lisibles
- suivre tous les échanges sans perdre le fil
- centraliser les actions dans une seule interface
- préparer une phase 2 avec IA sans dépendre d’elle pour la V1

## 2. Périmètre V1 figé

### Inclus
- une seule boîte mail OVH MX Plan
- envoi SMTP
- lecture/synchronisation IMAP
- mails simples
- mails multiples
- brouillons éditables
- modèles réutilisables
- pièces jointes
- tracking opens / clicks
- détection de réponses humaines
- détection d’auto-réponses / absences / accusés automatiques
- détection soft bounce / hard bounce
- timeline unifiée par thread / contact / organisation
- scoring simple sans IA
- réglages administrables depuis l’application
- gestion utilisateur minimale

## 3. Stack retenue

### Produit métier
- Laravel 12
- PHP 8.3+
- PostgreSQL
- Redis
- Inertia + Vue 3
- Tailwind CSS

### Moteur mail dédié
- Node.js LTS
- TypeScript strict
- IMAPFlow
- Nodemailer
- PostalMime

## 4. Principe d’architecture

### Répartition
- **Laravel** = métier, auth, utilisateurs, contacts, organisations, campagnes, envois, brouillons, scoring, timeline, réglages, API interne
- **Node/TypeScript** = moteur mail, SMTP, IMAP, parsing MIME, tracking, threading, classification technique des messages, cadence d’envoi
- **Redis** = queue, scheduling, locks, débit d’envoi
- **PostgreSQL** = vérité métier et historique

### Règle impérative
Le moteur mail est un composant interne spécialisé.
Le produit ne doit jamais réintroduire une dépendance Gmail.

## 5. Cible messagerie

### Provider
- OVH MX Plan uniquement

### Réception
- IMAP

### Envoi
- SMTP

### Mode de synchronisation
- polling régulier Inbox / Sent
- pas de webhook provider natif en V1

### File d’envoi
- même queue pour mails simples et mails multiples
- pas de traitement séparé V1

## 6. Contraintes métier validées

- une seule boîte mail
- signature globale unique
- brouillon éditable avant planification
- modèles réutilisables dès la V1
- pièces jointes supportées
- scoring simple sans IA
- réponses automatiques gérées dès la V1
- état exploitable de chaque mail envoyé
- possibilité de reprogrammer une action après lecture d’une auto-réponse
- interface CRM avec sidebar à gauche
- réglages accessibles dans l’application
- volume par défaut : 100 e-mails / jour
- plafond journalier modifiable par l’administrateur
- envoi progressif obligatoire

## 7. Règle produit prioritaire

Le cœur du projet est la qualité d’envoi et le suivi du fil des échanges.

La logique prioritaire n’est pas “faire joli”, mais :
- arriver en boîte
- rester lisible partout
- suivre les réponses
- détecter les anomalies
- permettre une action rapide ensuite

## 8. Identité des messages et threading

Chaque mail sortant doit avoir plusieurs identifiants :
- `Message-ID` standard
- `aegis_tracking_id` interne
- `mail_thread_id` interne
- UID technique IMAP si disponible côté dossier

### Rattachement entrant
Ordre de priorité :
1. `In-Reply-To`
2. `References`
3. corrélation sur `Message-ID`
4. heuristique sujet + participants + fenêtre temporelle
5. création d’un nouveau thread si confiance insuffisante

## 9. États à gérer

### États d’envoi / suivi
- draft
- scheduled
- queued
- sending
- sent
- delivered_if_known
- opened
- clicked
- replied
- auto_replied
- soft_bounced
- hard_bounced
- unsubscribed
- failed
- cancelled

### Types de réponses automatiques
- out_of_office
- auto_acknowledgement
- system_auto_reply
- probable_auto_reply

## 10. Délivrabilité

### Exigences minimales
- SPF valide
- DKIM valide
- DMARC publié
- version texte obligatoire
- HTML e-mail sobre et compatible
- liens propres
- images secondaires seulement
- alt sur les images
- poids maîtrisé
- pas de JavaScript
- pas de polices exotiques critiques

### Préflight avant envoi
Avant lancement, l’application doit vérifier :
- configuration SMTP/IMAP
- destinataires exclus / invalides
- présence version texte
- poids estimé
- nombre de liens
- présence d’images distantes
- alertes structure HTML
- état d’authentification domaine

## 11. Algorithme d’envoi progressif

L’envoi ne doit jamais partir en rafale.

### Règles V1
- tous les messages passent par une queue Redis
- consommation par workers dédiés
- cadence configurable depuis les réglages
- jitter léger entre messages
- plafonds horaires et journaliers configurables
- fenêtres d’envoi configurables
- arrêt automatique si trop d’erreurs ou trop de hard bounces
- mode ralenti activable

### Valeurs de départ recommandées
- plafond journalier par défaut : 100
- plafond horaire initial conservateur : 10 à 15
- délai minimal entre messages : configurable

## 12. Scoring simple sans IA

But : donner un niveau de chaleur opérationnel.

### Signaux
- envoyé
- ouvert
- cliqué
- répondu
- auto-répondu
- soft bounce
- hard bounce
- désinscription
- dernière activité

### Lecture métier
- froid
- tiède
- intéressé
- engagé
- à exclure

Le scoring doit être explicable et modifiable depuis les réglages.

## 13. Données principales

### Entités métier
- users
- mailbox_accounts
- organizations
- contacts
- contact_emails
- mail_templates
- mail_drafts
- mail_campaigns
- mail_recipients
- mail_threads
- mail_messages
- mail_attachments
- mail_events
- settings
- audit_logs

## 14. Paramètres administrables dans l’application

### Mail
- adresse d’envoi
- nom d’expéditeur
- signature globale
- fenêtre d’envoi
- plafond journalier
- plafond horaire
- délai minimal entre envois
- mode ralenti
- tracking opens/clicks
- paramètres IMAP/SMTP si autorisés

### Délivrabilité
- vérifications SPF / DKIM / DMARC
- seuils d’alerte bounce
- règles d’arrêt automatique

### Produit
- rôles utilisateur minimum
- statuts visibles
- scoring simple
- catégories d’auto-réponses
- modèles réutilisables

## 15. UX attendue

### Layout global
- sidebar gauche permanente
- zone contenu centrale
- actions secondaires et réglages accessibles en haut à droite selon les écrans
- ergonomie CRM simple, lisible, dense mais propre

### Sections principales
- Dashboard
- Contacts
- Organisations
- Mails
- Brouillons
- Modèles
- Campagnes
- Timeline / Activité
- Réglages
- Utilisateurs

## 16. Phase 2 préparée mais non bloquante

La V1 doit stocker les données utiles à une future couche IA :
- texte nettoyé
- langue détectée
- délais de réponse
- signaux opens/clicks
- classification technique des messages
- tags manuels opérateur

L’IA ne doit pas être nécessaire au fonctionnement V1.

## 17. Définition de fini V1

Le produit est considéré prêt V1 si :
- un utilisateur peut configurer la boîte MX Plan
- envoyer un mail simple
- envoyer un mail multiple
- sauvegarder un brouillon
- réutiliser un modèle
- voir chaque message dans une timeline
- rattacher correctement une réponse normale
- distinguer une auto-réponse
- distinguer un bounce
- suivre ouvertures/clics quand disponibles
- reprogrammer une action depuis l’interface
- modifier les paramètres clés depuis les réglages

