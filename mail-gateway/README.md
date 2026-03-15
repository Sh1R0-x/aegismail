# AEGIS MAILING mail-gateway

Ce dossier est le squelette du moteur mail dédié en Node.js + TypeScript.

Règles figées de la V1 :
- une seule boîte OVH MX Plan
- aucune logique Gmail, OAuth Google ou multi-provider
- SMTP pour l'envoi, IMAP pour la synchronisation
- même queue d'envoi pour les mails simples et multiples

Contrats HTTP attendus par Laravel :
- `POST /v1/tests/imap`
- `POST /v1/tests/smtp`
- `POST /v1/messages/send`
- `POST /v1/mailboxes/sync`

Le backend Laravel expose déjà ces appels via un client `stub|http`.
La prochaine phase consistera à implémenter ici le serveur TypeScript et les workers IMAP/SMTP réels.
