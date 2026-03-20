import fs from 'node:fs';
import path from 'node:path';
import { ImapFlow } from 'imapflow';
import { simpleParser, type AddressObject, type Attachment } from 'mailparser';
import nodemailer from 'nodemailer';
import type {
  DispatchMessagePayload,
  GatewayResult,
  MailboxProbePayload,
  SyncMailboxMessage,
  SyncMailboxPayload,
} from './contracts.js';

const DRIVER = 'ovh_mx_plan';
const PROTECTED_HEADERS = new Set(['message-id', 'in-reply-to', 'references']);
const INTERNAL_HEADERS = new Set(['tracking', 'gateway', 'gateway_error']);

export class OvhMxPlanDriver {
  async testImap(payload: MailboxProbePayload): Promise<GatewayResult> {
    try {
      this.assertImapPayload(payload);

      const client = this.createImapClient(payload);

      try {
        await client.connect();
        const lock = await client.getMailboxLock('INBOX');
        lock.release();
      } finally {
        await this.safeLogout(client);
      }

      return {
        success: true,
        driver: DRIVER,
        protocol: 'imap',
        message: 'OVH MX Plan IMAP connection succeeded.',
        accepted_at: new Date().toISOString(),
      };
    } catch (error) {
      return this.failure('imap', error);
    }
  }

  async testSmtp(payload: MailboxProbePayload): Promise<GatewayResult> {
    try {
      this.assertSmtpPayload(payload);

      const transporter = this.createTransport(payload);
      await transporter.verify();
      const provider = payload.provider ?? DRIVER;

      return {
        success: true,
        driver: provider,
        protocol: 'smtp',
        message: `${provider} SMTP connection succeeded.`,
        accepted_at: new Date().toISOString(),
      };
    } catch (error) {
      return this.failure('smtp', error, payload.provider ?? DRIVER);
    }
  }

  async dispatchMessage(payload: DispatchMessagePayload): Promise<GatewayResult> {
    try {
      this.assertDispatchPayload(payload);

      const transporter = this.createTransport(payload);
      const provider = payload.provider ?? DRIVER;
      const info: {
        messageId?: string;
        accepted?: string[];
        rejected?: string[];
        envelope?: unknown;
        response?: string;
      } = await (transporter.sendMail({
        from: this.formatMailbox(payload.from_email, payload.from_name),
        to: payload.to_emails,
        subject: payload.subject,
        html: payload.html_body ?? undefined,
        text: payload.text_body ?? undefined,
        messageId: payload.message_id_header,
        inReplyTo: payload.in_reply_to_header ?? undefined,
        references: this.normalizeReferences(payload.references_header),
        headers: this.composeHeaders(payload.headers_json),
        attachments: (payload.attachments ?? []).map((attachment) => ({
          filename: attachment.original_name,
          path: this.resolveAttachmentPath(attachment.storage_disk, attachment.storage_path),
          contentType: attachment.mime_type,
          cid: attachment.content_id ?? undefined,
          contentDisposition: attachment.disposition === 'inline' ? 'inline' : 'attachment',
        })),
      }) as Promise<{
        messageId?: string;
        accepted?: string[];
        rejected?: string[];
        envelope?: unknown;
        response?: string;
      }>);

      return {
        success: true,
        driver: provider,
        message: `Outbound message accepted by ${provider} SMTP.`,
        accepted_at: new Date().toISOString(),
        message_id_header: info.messageId ?? payload.message_id_header,
        headers_json: {
          accepted: info.accepted,
          rejected: info.rejected,
          envelope: info.envelope,
          response: info.response,
        },
      };
    } catch (error) {
      return this.failure(undefined, error, payload.provider ?? DRIVER);
    }
  }

  async syncMailbox(payload: SyncMailboxPayload): Promise<{
    success: boolean;
    driver: typeof DRIVER;
    message: string;
    accepted_at: string;
    folder: 'INBOX' | 'SENT';
    from_uid: number;
    highest_uid: number;
    messages: SyncMailboxMessage[];
  }> {
    this.assertSyncPayload(payload);

    const client = this.createImapClient(payload);
    const folder = payload.folder;
    const fromUid = Math.max(0, Number(payload.from_uid ?? 0));

    try {
      await client.connect();

      const lock = await client.getMailboxLock(folder);

      try {
        const messages: SyncMailboxMessage[] = [];
        const range = `${Math.max(fromUid + 1, 1)}:*`;

        for await (const fetched of client.fetch(
          range,
          { uid: true, source: true, envelope: true, internalDate: true },
          { uid: true },
        )) {
          if (!Buffer.isBuffer(fetched.source)) {
            continue;
          }

          const parsed = await simpleParser(fetched.source);

          messages.push({
            uid: fetched.uid,
            message_id_header: this.firstString(parsed.messageId, fetched.envelope?.messageId),
            in_reply_to_header: this.firstString(parsed.inReplyTo),
            references_header: this.referencesHeader(parsed.references),
            aegis_tracking_id: this.headerString(parsed.headers.get('x-aegis-tracking-id')),
            from_email: this.firstAddress(parsed.from),
            to_emails: this.addresses(parsed.to),
            cc_emails: this.addresses(parsed.cc),
            bcc_emails: this.addresses(parsed.bcc),
            subject: parsed.subject ?? fetched.envelope?.subject ?? '',
            html_body: typeof parsed.html === 'string' ? parsed.html : null,
            text_body: typeof parsed.text === 'string' ? parsed.text : null,
            headers_json: this.objectJsonValue(Object.fromEntries(parsed.headers)),
            sent_at: folder === 'SENT' ? this.isoDate(parsed.date ?? fetched.envelope?.date ?? fetched.internalDate) : null,
            received_at: folder === 'INBOX' ? this.isoDate(fetched.internalDate ?? parsed.date ?? fetched.envelope?.date) : null,
            attachments: parsed.attachments.map((attachment: Attachment) => ({
              original_name: attachment.filename ?? 'attachment.bin',
              mime_type: attachment.contentType,
              size_bytes: attachment.size,
              content_id: attachment.contentId ?? attachment.cid ?? null,
              disposition: attachment.contentDisposition ?? undefined,
            })),
          });
        }

        const highestUid = messages.reduce((carry, message) => Math.max(carry, message.uid), fromUid);

        return {
          success: true,
          driver: DRIVER,
          message: `OVH MX Plan IMAP sync completed for ${folder}.`,
          accepted_at: new Date().toISOString(),
          folder,
          from_uid: fromUid,
          highest_uid: highestUid,
          messages,
        };
      } finally {
        lock.release();
      }
    } catch (error) {
      throw new Error(this.errorMessage(error));
    } finally {
      await this.safeLogout(client);
    }
  }

  private createTransport(payload: MailboxProbePayload | DispatchMessagePayload) {
    return nodemailer.createTransport({
      host: payload.smtp_host,
      port: payload.smtp_port,
      secure: payload.smtp_secure,
      auth: {
        user: payload.username,
        pass: payload.password,
      },
    });
  }

  private createImapClient(payload: MailboxProbePayload | SyncMailboxPayload) {
    return new ImapFlow({
      host: payload.imap_host!,
      port: payload.imap_port!,
      secure: payload.imap_secure ?? true,
      auth: {
        user: payload.username!,
        pass: payload.password!,
      },
    });
  }

  private composeHeaders(input?: Record<string, unknown>): Record<string, string | string[]> | undefined {
    if (!input) {
      return undefined;
    }

    const headers: Record<string, string | string[]> = {};

    for (const [key, value] of Object.entries(input)) {
      if (
        PROTECTED_HEADERS.has(key.toLowerCase())
        || INTERNAL_HEADERS.has(key.toLowerCase())
        || value === null
        || value === undefined
      ) {
        continue;
      }

      if (Array.isArray(value)) {
        const flattened = value
          .filter((entry) => ['string', 'number', 'boolean'].includes(typeof entry))
          .map((entry) => this.stringifyHeaderValue(entry));

        if (flattened.length > 0) {
          headers[key] = flattened;
        }

        continue;
      }

      if (typeof value === 'object') {
        continue;
      }

      headers[key] = this.stringifyHeaderValue(value);
    }

    return Object.keys(headers).length > 0 ? headers : undefined;
  }

  private resolveAttachmentPath(storageDisk: string, storagePath: string): string {
    if (path.isAbsolute(storagePath) && fs.existsSync(storagePath)) {
      return storagePath;
    }

    const laravelAppRoot = process.env.LARAVEL_APP_ROOT
      ? path.resolve(process.env.LARAVEL_APP_ROOT)
      : path.resolve(process.cwd(), '..', 'app');

    const candidates = [
      path.resolve(laravelAppRoot, storagePath),
      path.resolve(laravelAppRoot, 'storage', 'app', storagePath),
      path.resolve(laravelAppRoot, 'storage', 'app', 'private', storagePath),
    ];

    if (storageDisk === 'local') {
      candidates.push(path.resolve(laravelAppRoot, 'storage', 'app', storagePath));
    }

    const resolved = candidates.find((candidate) => fs.existsSync(candidate));

    if (!resolved) {
      throw new Error(`Attachment not found for storage path "${storagePath}".`);
    }

    return resolved;
  }

  private normalizeReferences(value?: string | null): string | string[] | undefined {
    if (!value) {
      return undefined;
    }

    const parts = value
      .split(/\s+/)
      .map((part) => part.trim())
      .filter(Boolean);

    return parts.length <= 1 ? parts[0] : parts;
  }

  private referencesHeader(value: unknown): string | string[] | null {
    if (Array.isArray(value)) {
      return value.map((entry) => String(entry));
    }

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    return null;
  }

  private formatMailbox(email?: string, name?: string): string | undefined {
    if (!email) {
      return undefined;
    }

    if (!name || name.trim() === '') {
      return email;
    }

    return `"${name.replace(/"/g, '\\"')}" <${email}>`;
  }

  private addresses(input: AddressObject | AddressObject[] | undefined): string[] {
    const normalized = Array.isArray(input) ? input : input ? [input] : [];

    return normalized
      .flatMap((entry) => entry.value ?? [])
      .map((entry) => entry.address?.trim().toLowerCase())
      .filter((entry): entry is string => Boolean(entry));
  }

  private firstAddress(input: AddressObject | AddressObject[] | undefined): string {
    return this.addresses(input)[0] ?? '';
  }

  private headerString(value: unknown): string | undefined {
    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    return undefined;
  }

  private firstString(...values: Array<unknown>): string | undefined {
    for (const value of values) {
      if (typeof value === 'string' && value.trim() !== '') {
        return value;
      }
    }

    return undefined;
  }

  private isoDate(value: Date | string | null | undefined): string | null {
    if (value instanceof Date) {
      return value.toISOString();
    }

    if (typeof value === 'string' && value.trim() !== '') {
      const parsed = new Date(value);

      if (!Number.isNaN(parsed.getTime())) {
        return parsed.toISOString();
      }
    }

    return null;
  }

  private stringifyHeaderValue(value: unknown): string {
    if (typeof value === 'string') {
      return value;
    }

    return JSON.stringify(this.toJsonValue(value));
  }

  private toJsonValue(value: unknown): unknown {
    if (value instanceof Date) {
      return value.toISOString();
    }

    if (Array.isArray(value)) {
      return value.map((entry) => this.toJsonValue(entry));
    }

    if (value instanceof Map) {
      return this.toJsonValue(Object.fromEntries(value));
    }

    if (value && typeof value === 'object') {
      return Object.fromEntries(
        Object.entries(value).map(([key, entry]) => [key, this.toJsonValue(entry)]),
      );
    }

    return value;
  }

  private objectJsonValue(value: unknown): Record<string, unknown> | undefined {
    const normalized = this.toJsonValue(value);

    if (normalized && typeof normalized === 'object' && !Array.isArray(normalized)) {
      return normalized as Record<string, unknown>;
    }

    return undefined;
  }

  private assertImapPayload(payload: MailboxProbePayload | SyncMailboxPayload): void {
    if (!payload.username || !payload.password || !payload.imap_host || !payload.imap_port) {
      throw new Error('Incomplete IMAP configuration.');
    }
  }

  private assertSmtpPayload(payload: MailboxProbePayload | DispatchMessagePayload): void {
    if (!payload.username || !payload.password || !payload.smtp_host || !payload.smtp_port) {
      throw new Error('Incomplete SMTP configuration.');
    }
  }

  private assertDispatchPayload(payload: DispatchMessagePayload): void {
    this.assertSmtpPayload(payload);

    if (!payload.from_email || !payload.to_emails?.length || !payload.subject || !payload.message_id_header) {
      throw new Error('Incomplete dispatch payload.');
    }
  }

  private assertSyncPayload(payload: SyncMailboxPayload): void {
    this.assertImapPayload(payload);

    if (!payload.folder) {
      throw new Error('Missing IMAP folder.');
    }
  }

  private failure(protocol: 'imap' | 'smtp' | undefined, error: unknown, driver: GatewayResult['driver'] = DRIVER): GatewayResult {
    return {
      success: false,
      driver,
      protocol,
      message: this.errorMessage(error),
      accepted_at: new Date().toISOString(),
    };
  }

  private errorMessage(error: unknown): string {
    if (error instanceof Error) {
      return error.message;
    }

    return String(error);
  }

  private async safeLogout(client: ImapFlow): Promise<void> {
    try {
      if (client.usable) {
        await client.logout();
      }
    } catch {
      // Ignore disconnect errors while unwinding the request.
    }
  }
}
