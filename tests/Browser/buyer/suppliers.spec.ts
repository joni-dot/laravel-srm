import { createSupplier, expect, test } from '../support/test';

test('moving between pages does not reload the whole page', async ({
    page,
}) => {
    await page.goto('/dashboard');
    await page.evaluate(() => {
        document.documentElement.dataset.notReloaded = 'true';
    });

    await page.getByRole('link', { name: 'Suppliers' }).first().click();
    await expect(
        page.getByRole('heading', { name: 'Suppliers', exact: true }),
    ).toBeVisible();

    await expect(page.locator('html')).toHaveAttribute(
        'data-not-reloaded',
        'true',
    );
});

test('a required field left empty keeps the user on the form', async ({
    page,
}) => {
    await page.goto('/suppliers/create');
    await page.getByRole('button', { name: 'Create supplier' }).click();

    await expect(page).toHaveURL(/\/suppliers\/create$/);
});

test('a buyer can create, find, edit, archive and restore a supplier', async ({
    page,
}) => {
    const name = await createSupplier(page, 'Lifecycle Supplier');

    await page.getByRole('link', { name: 'Edit' }).click();
    await page.getByLabel('City').fill('Tampere');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Tampere')).toBeVisible();

    await page.goto('/suppliers');
    await page.getByLabel('Search suppliers').fill(name);
    // Wait for the search to finish: it runs 300 ms after typing stops.
    await expect(page).toHaveURL(/search=/);
    await page.getByRole('link', { name }).click();

    await page.getByRole('button', { name: 'Archive' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Archive' })
        .click();
    await expect(page.getByText('This supplier was archived')).toBeVisible();

    await page.goto('/suppliers');
    await page.getByLabel('Search suppliers').fill(name);
    await expect(page.getByText('No suppliers')).toBeVisible();

    await page.getByLabel('Show archived').click();
    await page.getByRole('link', { name }).click();
    await page.getByRole('button', { name: 'Restore' }).click();
    await expect(page.getByText('This supplier was archived')).toHaveCount(0);
});
