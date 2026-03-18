export * from './contracts.js';
export * from './ovhMxPlanDriver.js';
export * from './server.js';

import { fileURLToPath } from 'node:url';
import { startServer } from './server.js';

const executedAsEntrypoint = process.argv[1] === fileURLToPath(import.meta.url);

if (executedAsEntrypoint) {
  startServer();
}
