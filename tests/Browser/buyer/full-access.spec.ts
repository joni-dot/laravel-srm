import { seeded } from '../support/test-data';
import { expect, test } from '../support/test';

test('a buyer gets the contract and spend data a viewer does not', async ({
    page,
}) => {
    const body = await (await page.request.get(seeded.supplierUrl)).text();

    expect(body).toContain(seeded.contractTitle);
    expect(body).toContain(seeded.contractValue);
    expect(body).toContain(seeded.spendAmount);

    await page.goto(seeded.contractUrl);
    await expect(
        page.getByRole('heading', { name: seeded.contractTitle }),
    ).toBeVisible();

    const contractBody = await (
        await page.request.get(seeded.contractUrl)
    ).text();
    expect(contractBody).toContain(seeded.contractComment);
});

test('the dashboard shows a buyer the biggest suppliers by spend', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await expect(
        page.getByRole('heading', { name: /Biggest suppliers by spend/ }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: seeded.supplierName }).first(),
    ).toBeVisible();
});

for (const url of ['/admin/users', '/admin/categories', '/admin/settings']) {
    test(`${url} is forbidden for a buyer`, async ({ page }) => {
        const response = await page.request.get(url);

        expect(response.status()).toBe(403);
    });
}
