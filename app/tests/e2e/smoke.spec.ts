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
  await expect(page.getByRole('columnheader', { name: 'Contact' })).toBeVisible();
  await page.getByRole('link', { name: 'Fiche' }).first().click();
  await expect(page).toHaveURL(/\/contacts\/\d+$/);
  await expect(page.locator('h1')).toContainText(/Alice Martin|Bruno Leroy|Carla Durand/);

  await page.goto('/organizations');
  await expect(page.getByText('Ajouter une organisation')).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Organisation' })).toBeVisible();
  await page.getByRole('link', { name: 'Fiche' }).first().click();
  await expect(page).toHaveURL(/\/organizations\/\d+$/);
  await expect(page.locator('h1')).toContainText(/Acme Labs|Beta Logistics/);

  await page.goto('/mails');
  await expect(page.getByRole('columnheader', { name: 'Destinataire' })).toBeVisible();
  await page.getByRole('link', { name: 'Voir' }).first().click();
  await expect(page).toHaveURL(/\/threads\/\d+$/);
  await expect(page.locator('h1')).toContainText(/Relance Q2|Prospection Mars|Delivery failure/);

  await page.goto('/campaigns');
  await expect(page.getByText('Préparer une campagne')).toBeVisible();
  await page.getByRole('link', { name: 'Détails' }).first().click();
  await expect(page).toHaveURL(/\/campaigns\/\d+$/);
  await expect(page.locator('h1')).toContainText(/Relance Q2 Batch|Prospection Mars/);

  assertNoClientErrors();
});

test('smoke template and draft flow supports save, preflight and schedule', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);
  const templateName = `Smoke Template ${Date.now()}`;
  const recipientEmail = 'prospect-smoke@example.test';
  const scheduledAt = new Date(Date.now() + 15 * 60 * 1000);

  await page.goto('/templates');
  await page.getByRole('button', { name: 'Nouveau modèle' }).click();
  await expect(page.getByRole('heading', { name: 'Nouveau modèle', exact: true }).first()).toBeVisible();
  await page.getByPlaceholder('Ex : Premier contact').fill(templateName);
  await page.getByPlaceholder('Objet du message').fill('Relance smoke');
  await page.getByPlaceholder('Rédigez votre message ici, en texte simple (sans balises HTML)…').fill('Bonjour depuis le smoke test');
  await page.getByRole('button', { name: 'Créer le modèle' }).click();
  await expect(page.getByText(templateName)).toBeVisible();

  await page.goto('/drafts');
  await page.getByRole('button', { name: 'Nouveau brouillon' }).click();
  await expect(page.getByRole('heading', { name: 'Corps du message' })).toBeVisible();
  await page.getByPlaceholder('adresse@exemple.fr').first().fill(recipientEmail);
  await page.getByPlaceholder('Objet du message').fill('Draft smoke schedule');
  await page.getByPlaceholder('Rédigez votre message ici, en texte simple (sans balises HTML)…').fill('Bonjour smoke');
  await page.getByRole('button', { name: 'Sauvegarder brouillon' }).click();
  await expect(page.getByText(/Sauvegardé/i).first()).toBeVisible();

  await page.getByRole('button', { name: 'Vérification avant envoi' }).click();
  await expect(page.getByText('Prêt à planifier')).toBeVisible();
  await expect(page.getByText('1/1 destinataire(s) exploitable(s)')).toBeVisible();

  // Verify "send now" and "schedule" buttons appear after successful preflight
  await expect(page.getByRole('button', { name: 'Envoyer maintenant' })).toBeVisible();

  await page.getByRole('button', { name: 'Planifier' }).first().click();
  await page.locator('input[type="datetime-local"]').fill(scheduledAt.toISOString().slice(0, 16));
  await page.getByRole('button', { name: 'Confirmer la planification' }).click();

  const scheduledRow = page.locator('tr', { hasText: 'Draft smoke schedule' }).first();
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
test('template deletion removes the template from the list', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);
  const templateName = `Delete Me ${Date.now()}`;

  // Create a template first
  await page.goto('/templates');
  await page.getByRole('button', { name: 'Nouveau modèle' }).click();
  await page.getByPlaceholder('Ex : Premier contact').fill(templateName);
  await page.getByPlaceholder('Objet du message').fill('Objet suppression');
  await page.getByPlaceholder('Rédigez votre message ici, en texte simple (sans balises HTML)…').fill('Corps temporaire');
  await page.getByRole('button', { name: 'Créer le modèle' }).click();
  await expect(page.getByText(templateName)).toBeVisible();

  // Delete it via confirm dialog
  page.on('dialog', (dialog) => dialog.accept());
  const row = page.locator('tr', { hasText: templateName });
  await row.getByRole('button', { name: 'Supprimer' }).click();

  // Template should be gone
  await expect(page.getByText(templateName)).not.toBeVisible();

  assertNoClientErrors();
});

test('campaign detail page shows recipients and statuses', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);

  // Navigate to the sent campaign "Prospection Mars"
  await page.goto('/campaigns');
  await page.getByRole('link', { name: 'Détails' }).first().click();
  await expect(page).toHaveURL(/\/campaigns\/\d+$/);

  // Verify campaign header metadata cards
  await expect(page.getByRole('paragraph').filter({ hasText: 'Statut' })).toBeVisible();
  await expect(page.getByText('Destinataires et statuts')).toBeVisible();

  // Verify recipient table exists
  await expect(page.getByRole('columnheader', { name: 'Destinataire' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Statut' })).toBeVisible();

  assertNoClientErrors();
});

test('thread detail page shows messages and classification', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);

  // Navigate to a thread from the mails page
  await page.goto('/mails');
  await page.getByRole('link', { name: 'Voir' }).first().click();
  await expect(page).toHaveURL(/\/threads\/\d+$/);

  // Verify thread structure
  await expect(page.getByText('Contact', { exact: true })).toBeVisible();
  await expect(page.getByText('Organisation', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Dernière activité')).toBeVisible();

  assertNoClientErrors();
});

test('activity page supports filtering by event type', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);

  await page.goto('/activity');
  await expect(page.getByRole('heading', { name: 'Activité', exact: true })).toBeVisible();

  // Verify filter dropdown is present
  const filterSelect = page.locator('select').first();
  await expect(filterSelect).toBeVisible();
  await expect(filterSelect).toContainText('Tous les événements');

  // Filter by replies
  await filterSelect.selectOption({ label: 'Réponses' });
  // Page should still load without errors
  await expect(page.getByRole('heading', { name: 'Activité', exact: true })).toBeVisible();

  assertNoClientErrors();
});

test('mails page shows quota bar and status filters', async ({ page }) => {
  const assertNoClientErrors = captureClientErrors(page);

  await page.goto('/mails');
  await expect(page.getByRole('heading', { name: 'Mails', exact: true })).toBeVisible();

  // Verify send buttons
  await expect(page.getByRole('button', { name: 'Mail simple' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Envoi multiple' })).toBeVisible();

  // Verify quota display
  await expect(page.getByText(/envoyés aujourd/)).toBeVisible();

  // Verify status filter dropdown
  const statusFilter = page.locator('select').first();
  await expect(statusFilter).toBeVisible();
  await expect(statusFilter).toContainText('Tous les statuts');

  assertNoClientErrors();
});
