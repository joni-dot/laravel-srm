import { createSupplier, daysFromToday, expect, test } from '../support/test';

test('a contract ending soon appears under "Contracts needing action"', async ({
    page,
}) => {
    const name = await createSupplier(page, 'Contract Supplier');
    const title = `${name} agreement`;

    await page.getByRole('link', { name: 'Add contract' }).click();
    await page.getByLabel('Title').fill(title);
    await page.getByLabel('Type').selectOption({ index: 1 });
    await page.getByLabel('End date').fill(daysFromToday(10));
    await page.getByRole('button', { name: 'Create contract' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    await page.goto('/suppliers');
    await page.getByLabel('Search suppliers').fill(name);
    await expect(page.getByRole('link', { name })).toBeVisible();

    await page.getByLabel('Contracts needing action').click();
    await expect(page).toHaveURL(/contracts_action_soon=1/);
    await expect(page.getByRole('link', { name })).toBeVisible();
});
