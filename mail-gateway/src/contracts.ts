export type GatewayProtocol = 'imap' | 'smtp';

export interface MailboxProbePayload {
  provider: 'ovh_mx_plan';
  email: string;
  username: string;
  password: string;
  imap_host?: string;
  imap_port?: number;
  imap_secure?: boolean;
  smtp_host?: string;
  smtp_port?: number;
  smtp_secure?: boolean;
}

export interface GatewayResult {
  success: boolean;
  driver: 'stub' | 'http' | 'gateway' | 'ovh_mx_plan';
  message: string;
  protocol?: GatewayProtocol;
  accepted_at?: string;
  message_id_header?: string;
  headers_json?: Record<string, unknown>;
}

export interface DispatchMessagePayload {
  mailbox_account_id: number;
  mail_message_id: number;
  thread_id?: number;
  campaign_id?: number;
  recipient_id?: number;
  idempotency_key?: string;
  provider?: 'ovh_mx_plan';
  email?: string;
  username?: string;
  password?: string;
  smtp_host?: string;
  smtp_port?: number;
  smtp_secure?: boolean;
  from_email?: string;
  from_name?: string;
  to_emails?: string[];
  subject?: string;
  html_body?: string | null;
  text_body?: string | null;
  message_id_header?: string;
  in_reply_to_header?: string | null;
  references_header?: string | null;
  aegis_tracking_id?: string;
  headers_json?: Record<string, unknown>;
  attachments?: Array<{
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    storage_disk: string;
    storage_path: string;
    content_id?: string | null;
    disposition?: string | null;
  }>;
}

export interface SyncMailboxPayload {
  mailbox_account_id: number;
  folder: 'INBOX' | 'SENT';
  from_uid?: number;
  provider?: 'ovh_mx_plan';
  email?: string;
  username?: string;
  password?: string;
  imap_host?: string;
  imap_port?: number;
  imap_secure?: boolean;
  idempotency_key?: string;
  stub_messages?: SyncMailboxMessage[];
}

export interface SyncMailboxMessage {
  uid: number;
  folder?: 'INBOX' | 'SENT';
  message_id_header?: string;
  in_reply_to_header?: string | null;
  references_header?: string | string[] | null;
  aegis_tracking_id?: string;
  from_email: string;
  to_emails?: string[];
  cc_emails?: string[];
  bcc_emails?: string[];
  subject?: string;
  html_body?: string | null;
  text_body?: string | null;
  headers_json?: Record<string, unknown>;
  sent_at?: string | null;
  received_at?: string | null;
  attachments?: Array<{
    original_name: string;
    mime_type: string;
    size_bytes: number;
    storage_disk?: string;
    storage_path?: string;
    content_id?: string | null;
    disposition?: string | null;
  }>;
}
