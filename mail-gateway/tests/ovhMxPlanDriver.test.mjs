import assert from 'node:assert/strict';
import test from 'node:test';
import nodemailer from 'nodemailer';
import { OvhMxPlanDriver } from '../dist/ovhMxPlanDriver.js';

class CaptureDriver extends OvhMxPlanDriver {
  rawMessage = '';

  createTransport() {
    const transport = nodemailer.createTransport({
      streamTransport: true,
      buffer: true,
      newline: 'unix',
    });

    return {
      verify: async () => true,
      sendMail: async (options) => {
        const info = await transport.sendMail(options);
        this.rawMessage = info.message.toString('utf8');

        return {
          messageId: info.messageId,
          accepted: Array.isArray(info.envelope?.to) ? info.envelope.to : [info.envelope?.to].filter(Boolean),
          rejected: [],
          envelope: info.envelope,
          response: 'stream',
        };
      },
    };
  }
}

test('dispatchMessage builds a multipart alternative MIME with unsubscribe headers and no internal tracking header', async () => {
  const driver = new CaptureDriver();

  const result = await driver.dispatchMessage({
    mailbox_account_id: 1,
    mail_message_id: 1,
    provider: 'ovh_mx_plan',
    email: 'ops@example.com',
    username: 'ops@example.com',
    password: 'secret',
    smtp_host: 'smtp.mail.ovh.net',
    smtp_port: 465,
    smtp_secure: true,
    from_email: 'ops@example.com',
    from_name: 'AEGIS Ops',
    to_emails: ['alice@example.com'],
    subject: 'MIME verification',
    html_body: '<p>Bonjour <a href="https://www.example.com/offer">offre</a></p>',
    text_body: 'Bonjour https://www.example.com/offer',
    message_id_header: '<message-id@example.com>',
    headers_json: {
      'X-Aegis-Tracking-Id': 'track-123',
      'List-Unsubscribe': '<https://mail.example.com/u/1.signature>',
      'List-Unsubscribe-Post': 'List-Unsubscribe=One-Click',
      tracking: {
        open: {
          token: 'track-123',
        },
      },
    },
    attachments: [],
  });

  assert.equal(result.success, true);
  assert.match(driver.rawMessage, /multipart\/alternative/i);
  assert.match(driver.rawMessage, /Content-Type:\s*text\/plain;/i);
  assert.match(driver.rawMessage, /Content-Type:\s*text\/html;/i);
  assert.match(driver.rawMessage, /List-Unsubscribe:\s*<https:\/\/mail\.example\.com\/u\/1\.signature>/i);
  assert.match(driver.rawMessage, /List-Unsubscribe-Post:\s*List-Unsubscribe=One-Click/i);
  assert.doesNotMatch(driver.rawMessage, /\ntracking:/i);
});

test('dispatchMessage returns the selected SMTP provider as driver metadata', async () => {
  const driver = new CaptureDriver();

  const result = await driver.dispatchMessage({
    mailbox_account_id: 1,
    mail_message_id: 1,
    provider: 'smtp2go',
    email: 'ops@example.com',
    username: 'smtp2go-user',
    password: 'secret',
    smtp_host: 'mail.smtp2go.com',
    smtp_port: 2525,
    smtp_secure: false,
    from_email: 'ops@example.com',
    to_emails: ['alice@example.com'],
    subject: 'Provider metadata',
    text_body: 'Bonjour',
    message_id_header: '<message-id@example.com>',
    attachments: [],
  });

  assert.equal(result.success, true);
  assert.equal(result.driver, 'smtp2go');
  assert.match(result.message, /smtp2go/i);
});
