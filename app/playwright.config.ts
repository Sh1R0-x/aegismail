import { defineConfig, devices } from '@playwright/test';

const smokePort = process.env.AEGIS_E2E_PORT ?? '8811';
const smokeBaseUrl = `http://127.0.0.1:${smokePort}`;

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: smokeBaseUrl,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: `powershell -ExecutionPolicy Bypass -File ../scripts/e2e-serve.ps1 -E2ePort ${smokePort}`,
    url: `${smokeBaseUrl}/dashboard`,
    reuseExistingServer: !!(process.env.AEGIS_E2E_REUSE_SERVER),
    timeout: 180_000,
  },
});
