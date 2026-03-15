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

Contrat sortant minimal V1 pour `POST /v1/messages/send` :
- identité mailbox OVH MX Plan
- SMTP host/port/secure + username/password
- `mail_message_id`
- `aegis_tracking_id`
- `Message-ID`
- `In-Reply-To` et `References` si disponibles
- `from_email`, `from_name`, `to_emails`
- `subject`, `html_body`, `text_body`
- `headers_json`
- `attachments[]`

Réponse minimale attendue :
- `success`
- `driver`
- `message`
- `accepted_at`
- `message_id_header`
- `headers_json`

Contrat IMAP minimal V1 pour `POST /v1/mailboxes/sync` :
- une seule mailbox OVH MX Plan
- dossier `INBOX` ou `SENT`
- `from_uid` pour reprise sûre
- identité IMAP (`email`, `username`, `password`, `imap_host`, `imap_port`, `imap_secure`)
- réponse `messages[]` triables par UID

Shape minimal de `messages[]` :
- `uid`
- `message_id_header`
- `in_reply_to_header`
- `references_header`
- `from_email`
- `to_emails[]`
- `cc_emails[]`
- `bcc_emails[]`
- `subject`
- `html_body`
- `text_body`
- `headers_json`
- `received_at` pour `INBOX`
- `sent_at` pour `SENT`
- `attachments[]` avec métadonnées de stockage

Réponse minimale attendue pour `POST /v1/mailboxes/sync` :
- `success`
- `driver`
- `message`
- `accepted_at`
- `folder`
- `from_uid`
- `highest_uid`
- `messages[]`

Le backend Laravel expose déjà ces appels via un client `stub|http`.
La prochaine phase consistera à implémenter ici le serveur TypeScript et les workers IMAP/SMTP réels.
