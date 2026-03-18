import http from 'node:http';
import type { IncomingMessage, ServerResponse } from 'node:http';
import {
  type DispatchMessagePayload,
  type MailboxProbePayload,
  type SyncMailboxPayload,
} from './contracts.js';
import { OvhMxPlanDriver } from './ovhMxPlanDriver.js';

const JSON_HEADERS = {
  'Content-Type': 'application/json; charset=utf-8',
};

export function createServer(driver = new OvhMxPlanDriver()) {
  return http.createServer(async (request, response) => {
    try {
      if (request.method !== 'POST') {
        return sendJson(response, 405, {
          success: false,
          driver: 'gateway',
          message: 'Method Not Allowed.',
        });
      }

      if (!authorize(request)) {
        return sendJson(response, 401, {
          success: false,
          driver: 'gateway',
          message: 'Unauthorized gateway request.',
        });
      }

      const body = await parseJsonBody(request);

      switch (request.url) {
        case '/v1/tests/imap':
          return sendJson(response, 200, await driver.testImap(body as MailboxProbePayload));

        case '/v1/tests/smtp':
          return sendJson(response, 200, await driver.testSmtp(body as MailboxProbePayload));

        case '/v1/messages/send': {
          const result = await driver.dispatchMessage(body as DispatchMessagePayload);

          return sendJson(response, result.success ? 200 : 422, result);
        }

        case '/v1/mailboxes/sync':
          return sendJson(response, 200, await driver.syncMailbox(body as SyncMailboxPayload));

        default:
          return sendJson(response, 404, {
            success: false,
            driver: 'gateway',
            message: 'Unknown mail-gateway route.',
          });
      }
    } catch (error) {
      return sendJson(response, 500, {
        success: false,
        driver: 'gateway',
        message: error instanceof Error ? error.message : String(error),
      });
    }
  });
}

export function startServer() {
  const port = Number(process.env.PORT ?? 3001);
  const host = process.env.HOST ?? '127.0.0.1';
  const server = createServer();

  server.listen(port, host, () => {
    process.stdout.write(`mail-gateway listening on http://${host}:${port}\n`);
  });

  return server;
}

async function parseJsonBody(request: IncomingMessage): Promise<unknown> {
  const chunks: Buffer[] = [];

  for await (const chunk of request) {
    chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
  }

  if (chunks.length === 0) {
    return {};
  }

  return JSON.parse(Buffer.concat(chunks).toString('utf-8'));
}

function authorize(request: IncomingMessage): boolean {
  const expected = process.env.MAIL_GATEWAY_SHARED_SECRET;

  if (!expected) {
    return true;
  }

  return request.headers['x-aegis-gateway-secret'] === expected;
}

function sendJson(response: ServerResponse, status: number, payload: unknown) {
  response.writeHead(status, JSON_HEADERS);
  response.end(JSON.stringify(payload));
}
