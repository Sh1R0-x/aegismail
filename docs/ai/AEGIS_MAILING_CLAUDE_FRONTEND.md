# AEGIS MAILING — Spécification frontend pour Claude

## Rôle de ce document

Ce fichier est une annexe détaillée.
Les sources de vérité quotidiennes restent `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`, `docs/ai/FRONTEND_SCOPE.md`, `docs/ai/FRONTEND_CONTRACTS.md` et `docs/ai/AI_WORKFLOW_METHOD.md`.

## 1. Rôle

Tu prends en charge exclusivement le frontend, l’UX et l’intégration visuelle.
Tu ne modifies pas la logique métier backend sauf pour câbler les données nécessaires au front.

## 2. Contexte figé

- application interne type CRM
- une seule boîte OVH MX Plan
- aucun écran ni wording lié à Gmail
- sidebar à gauche
- réglages accessibles depuis l’application
- un seul produit regroupant mails simples et mails multiples
- design sobre, dense, lisible, professionnel

## 3. Principes UX

### Priorité

Le cœur du produit est l’envoi et le suivi des échanges.
L’UI doit servir l’action, pas la décoration.

### Style attendu

- layout CRM propre
- sidebar gauche fixe
- header léger en haut de la zone contenu
- actions importantes visibles
- filtres rapides
- tableaux lisibles
- timeline détaillée mais propre
- indicateurs d’état clairs
- densité raisonnable, sans vide inutile

## 4. Navigation principale

### Sidebar gauche

- Dashboard
- Contacts
- Organisations
- Mails
- Brouillons
- Modèles
- Campagnes
- Activité
- Réglages
- Utilisateurs

### Zone haute droite

- recherche globale
- bouton d’action contextuel selon l’écran
- accès rapide au profil / session
- accès rapide aux réglages si utile

## 5. Écrans minimum V1

### Dashboard

Doit montrer :

- volume envoyé du jour
- quota journalier actuel
- santé d’envoi
- dernières réponses
- auto-réponses récentes
- hard bounces récents
- campagnes actives
- prochains envois programmés

### Contacts

- listing
- filtres
- recherche
- fiche contact
- e-mails liés
- statut simple
- historique des échanges

### Organisations

- listing
- fiche organisation
- contacts liés
- historique global

### Mails

Écran unifié avec deux points d’entrée :

- mail simple
- mail multiple

Le même module doit rester cohérent visuellement.

### Brouillons

- liste des brouillons
- statut
- date planifiée si présente
- duplication
- édition

### Modèles

- liste des modèles
- aperçu
- duplication
- édition
- activation / archivage

### Campagnes

- liste
- statut
- progression
- volume
- destinataires
- exclusions
- dernières interactions

### Activité

- timeline globale
- filtres par événement
- recherche par email/contact/organisation

### Réglages

- paramètres mail
- délivrabilité
- cadence d’envoi
- scoring
- signature globale
- utilisateurs minimum

## 6. Pages et composants clés

### Composer mail

Un compositeur unique, utilisable en mode simple ou multiple.

Doit gérer :

- destinataires
- sujet
- contenu HTML
- version texte
- pièces jointes
- choix d’un modèle
- aperçu
- brouillon
- planification
- préflight avant lancement

### Bloc préflight

Doit afficher clairement :

- erreurs bloquantes
- warnings
- exclusions
- poids estimé
- qualité technique minimale
- état de la configuration mail

### Timeline de thread

Affiche :

- messages entrants / sortants
- type de message
- date/heure
- état ouvert/cliqué
- statut auto-réponse / bounce
- actions possibles
- pièces jointes

### Badges d’état

Prévoir des badges lisibles pour :

- envoyé
- ouvert
- cliqué
- répondu
- réponse automatique
- soft bounce
- hard bounce
- désinscrit
- erreur
- planifié

## 7. Comportements UX importants

### Auto-réponses

Une auto-réponse ne doit jamais ressembler à une réponse normale.
Elle doit être visuellement distincte.

### Bounces

Les hard bounces doivent être très visibles.
L’utilisateur doit comprendre immédiatement que l’adresse est à exclure.

### Reprogrammation

Depuis un thread ou un message auto-répondu, l’utilisateur doit pouvoir :

- reprogrammer une relance
- changer une date
- annoter
- ignorer

### Paramètres

Tout ce qui pilote la cadence et l’envoi doit être trouvable facilement dans les réglages.

## 8. Layout recommandé

### Structure

- sidebar fixe gauche
- contenu principal scrollable
- en-tête de page avec titre + actions
- cartes KPI en dashboard
- listes/tableaux pour les vues volumétriques
- panneau latéral ou page dédiée pour les détails selon densité

### Responsive

Responsive propre mais priorité desktop.
Pas de sophistication mobile inutile si elle ralentit ou complexifie.

## 9. Design system minimal

### Couleurs / ton

- neutre professionnel
- contrastes lisibles
- états couleur cohérents
- pas d’effet marketing excessif

### Typographie

- lisible
- compacte
- hiérarchie nette

### Inputs / forms

- validation claire
- erreurs visibles
- aides courtes
- labels explicites

## 10. DoD frontend

Le frontend V1 est valide si :

- la navigation est claire
- l’écran mails simple / multiple est cohérent
- le composer est utilisable sans ambiguïté
- le préflight est compréhensible
- les statuts sont visibles rapidement
- les réglages sont accessibles et lisibles
- la timeline est exploitable au quotidien
- l’ensemble donne un vrai ressenti CRM interne propre

## 11. Comportements UX validés en phase 3 (mars 2026)

### Réglages mail — feedback utilisateur

- Les résultats des tests SMTP/IMAP s'affichent dans un bloc visuel coloré (vert/rouge) avec icône, pas en simple span inline.
- Le résultat d'un test est effacé automatiquement si les champs liés changent (résultats périmés).
- La bannière de succès après sauvegarde se referme automatiquement après 5 secondes.
- L'indicateur du champ mot de passe précise "Laissez vide pour conserver le mot de passe enregistré."
- Les messages d'erreur de connexion proviennent du backend en français — le front ne réinterprète pas.

### Contacts et Organisations — CTA

- Le bouton "Ajouter un contact" et "Ajouter une organisation" sont actifs dès que `capabilities.canCreate` est `true`.
- Si `canCreate` est false, le bouton reste désactivé avec un tooltip neutre (pas "prochaine version").
- Cliquer sur "Ajouter un contact" ouvre une modale inline avec les champs : e-mail (requis), prénom, nom, poste, téléphone.
- Cliquer sur "Ajouter une organisation" ouvre une modale inline avec les champs : nom (requis), domaine, site web.
- Les erreurs de validation retournées par l'API s'affichent en rouge par champ.
- Après création réussie, la modale se ferme et la liste se recharge.
- L'état vide des tables inclut un CTA secondaire pour créer directement.
- Ces boutons dépendent du prop `capabilities.canCreate` fourni par le backend via `CrmPageDataService`.

### Campagnes — flow draft-first

- Le CTA en entête de la page Campagnes est "Préparer une campagne" (lien vers `creationFlow.entryHref`).
- L'état vide affiche le `creationFlow.helperText` et un lien CTA secondaire.
- Le composant accepte le prop `creationFlow` fourni par `ComposerPageDataService::campaigns()`.
- Il n'y a pas de création directe de campagne sans passer par un brouillon — c'est l'architecture choisie en V1.

### Dépendance Codex recommandée

- `ComposerPageDataService::campaigns()` → changer `actionLabel` de `'Nouveau brouillon'` en `'Préparer une campagne'` pour aligner le contrat avec l'UI.
