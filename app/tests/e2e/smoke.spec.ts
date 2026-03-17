import { expect, test, type Page } from '@playwright/test';

function captureClientErrors(page: Page) {
  const pageErrors: string[] = [];
  const consoleErrors: string[] = [];

  page.on('pageerror', (error) => {
    pageErrors.push(error.message);
  });

  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  return () => {
    expect(pageErrors, `Unexpected page errors: ${pageErrors.join(' | ')}`).toEqual([]);
    expect(consoleErrors, `Unexpected console errors: ${consoleErrors.join(' | ')}`).toEqual([]);
  };
}

test('smoke navigation covers supported pages and disabled placeholders', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);

  await page.goto('/dashboard');
  await expect(page.getByRole('heading', { name: 'Tableau de Bord' })).toBeVisible();
  await expect(page.getByText('AEGIS MAILING')).toBeVisible();

  const routes = [
    { label: 'Contacts', url: '/contacts', heading: 'Contacts' },
    { label: 'Organisations', url: '/organizations', heading: 'Organisations' },
    { label: 'Mails', url: '/mails', heading: 'Mails' },
    { label: 'Brouillons', url: '/drafts', heading: 'Brouillons' },
    { label: 'Modèles', url: '/templates', heading: 'Modèles' },
    { label: 'Campagnes', url: '/campaigns', heading: 'Campagnes' },
    { label: 'Activité', url: '/activity', heading: 'Activité' },
    { label: 'Réglages', url: '/settings', heading: 'Réglages' },
    { label: 'Utilisateurs', url: '/users', heading: 'Utilisateurs' },
  ];

  for (const route of routes) {
    await page.getByRole('link', { name: route.label }).click();
    await expect(page).toHaveURL(new RegExp(`${route.url.replace('/', '\\/')}$`));
    await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible();
  }

  await page.goto('/settings');
  await expect(page.getByRole('button', { name: 'Paramètres mail', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Signature' }).click();
  await expect(page.getByText('Signature globale')).toBeVisible();

  await page.goto('/contacts');
  await expect(page.getByText('Ajouter un contact')).toBeVisible();
  await expect(page.getByText('Fiche').first()).toBeVisible();

  await page.goto('/organizations');
  await expect(page.getByText('Ajouter une organisation')).toBeVisible();
  await expect(page.getByText('Historique').first()).toBeVisible();

  await page.goto('/mails');
  await expect(page.getByText('Voir').first()).toBeVisible();

  await page.goto('/campaigns');
  await expect(page.getByText('Détails').first()).toBeVisible();

  assertNoClientErrors();
});

test('smoke template and draft flow supports save, preflight and schedule', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);
  const templateName = `Smoke Template ${Date.now()}`;
  const recipientEmail = 'prospect-smoke@example.test';
  const scheduledAt = new Date(Date.now() + 15 * 60 * 1000);

  await page.goto('/templates');
  await page.getByRole('button', { name: 'Nouveau modèle' }).click();
  const templatePanel = page.locator('.fixed.inset-y-0.right-0.z-50').last();
  await expect(templatePanel.getByRole('heading', { name: 'Nouveau modèle', exact: true })).toBeVisible();
  await templatePanel.getByPlaceholder('Ex : Premier contact').fill(templateName);
  await templatePanel.getByPlaceholder('Objet du message').fill('Relance smoke');
  await templatePanel.getByPlaceholder('<p>Votre message…</p>').fill('<p>Bonjour depuis le smoke test</p>');
  await templatePanel.getByPlaceholder('Version texte brut du message (sans balises HTML)…').fill('Bonjour depuis le smoke test');
  await templatePanel.getByRole('button', { name: 'Créer le modèle' }).click();
  await expect(page.getByText(templateName)).toBeVisible();

  await page.goto('/drafts');
  await page.getByRole('button', { name: 'Nouveau brouillon' }).click();
  const draftPanel = page.locator('.fixed.inset-y-0.right-0.z-50').last();
  await draftPanel.getByPlaceholder('adresse@exemple.fr').fill(recipientEmail);
  await draftPanel.getByPlaceholder('Objet du message').fill('Draft smoke schedule');
  await draftPanel.getByPlaceholder('<p>Votre message…</p>').fill('<p>Bonjour smoke</p>');
  await draftPanel.getByPlaceholder('Version texte brut du message (sans balises HTML)…').fill('Bonjour smoke');
  await draftPanel.getByRole('button', { name: 'Sauvegarder brouillon' }).click();
  await expect(draftPanel.getByText(/Sauvegardé/i)).toBeVisible();

  await draftPanel.getByRole('button', { name: 'Vérifier (preflight)' }).click();
  await expect(draftPanel.getByText('Prêt à planifier')).toBeVisible();
  await expect(draftPanel.getByText('1/1 destinataire(s) exploitable(s)')).toBeVisible();

  await draftPanel.getByRole('button', { name: 'Planifier' }).click();
  await draftPanel.locator('input[type="datetime-local"]').fill(scheduledAt.toISOString().slice(0, 16));
  await draftPanel.getByRole('button', { name: 'Confirmer la planification' }).click();

  const scheduledRow = page.locator('tr', { hasText: 'Draft smoke schedule' });
  await expect(scheduledRow).toBeVisible();
  await expect(scheduledRow.getByText('Planifié', { exact: true })).toBeVisible();

  assertNoClientErrors();
});

test('settings smtp validation exposes a precise user-facing message', async ({ page }) => {
  await page.goto('/settings');

  await page.locator('input[type="email"]').fill('adresse-invalide');

  await page.getByRole('button', { name: 'Tester SMTP' }).click();

  await expect(page.getByText('Le champ l’adresse d’envoi doit être une adresse e-mail valide.')).toBeVisible();
});
