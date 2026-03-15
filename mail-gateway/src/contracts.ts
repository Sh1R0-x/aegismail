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
  driver: 'stub' | 'http' | 'gateway';
  message: string;
  protocol?: GatewayProtocol;
}

export interface DispatchMessagePayload {
  mailbox_account_id: number;
  mail_message_id: number;
  thread_id?: number;
  campaign_id?: number;
  recipient_id?: number;
  idempotency_key?: string;
}

export interface SyncMailboxPayload {
  mailbox_account_id: number;
  folder: 'INBOX' | 'SENT';
  idempotency_key?: string;
}
