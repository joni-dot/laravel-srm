import { createSupplier, expect, test } from '../support/test';

test('a rating sets the overall score and spend can be added', async ({
    page,
}) => {
    await createSupplier(page, 'Rated Supplier');

    await page.getByRole('button', { name: 'Add rating' }).click();
    const ratingDialog = page.getByRole('dialog');
    for (const criterion of ['Quality', 'Delivery', 'Price', 'Service']) {
        await ratingDialog.getByLabel(criterion).selectOption('5');
    }
    await ratingDialog.getByRole('button', { name: 'Save' }).click();

    await expect(ratingDialog).toHaveCount(0);
    await expect(
        page.getByText(/Latest overall score: 5(\.0+)? out of 5/),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Add spend' }).click();
    const spendDialog = page.getByRole('dialog');
    await spendDialog.getByLabel('Amount').fill('4321');
    await spendDialog.getByRole('button', { name: 'Save' }).click();

    await expect(spendDialog).toHaveCount(0);
    await expect(page.getByText(/4,321/)).toBeVisible();
});

test('a risk level can be overridden with a reason and switched back', async ({
    page,
}) => {
    await createSupplier(page, 'Risk Supplier');
    const reason = 'Quality problems reported by the factory';

    await page.getByRole('button', { name: 'Change risk level' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Risk level').selectOption({ label: 'Low' });
    await dialog.getByLabel('Reason').fill(reason);
    await dialog.getByRole('button', { name: 'Save' }).click();

    await expect(dialog).toHaveCount(0);
    await expect(page.getByText(reason)).toBeVisible();

    await page.getByRole('button', { name: 'Use calculated level' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Use calculated level' })
        .click();

    await expect(page.getByText(reason)).toHaveCount(0);
});
