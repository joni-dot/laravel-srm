/// <reference types="node" />

import { seeded } from '../support/test-data';
import { createSupplier, daysFromToday, expect, test } from '../support/test';

const pdf = {
    name: 'certificate.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from(
        '%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n',
    ),
};

test('uploading a required certificate clears the warning and can be downloaded', async ({
    page,
}) => {
    await createSupplier(page, 'Certificate Supplier');
    const warning = page.getByRole('alert').filter({
        hasText: `Missing a valid required certificate: ${seeded.requiredCertificate}`,
    });
    await expect(warning).toBeVisible();

    await page.getByRole('button', { name: 'Add certificate' }).click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByLabel('Type')
        .selectOption({ label: seeded.requiredCertificate });
    await dialog.getByLabel('Expiry date').fill(daysFromToday(365));
    await dialog.getByLabel('File').setInputFiles(pdf);
    await dialog.getByRole('button', { name: 'Save' }).click();

    await expect(dialog).toHaveCount(0);
    await expect(warning).toHaveCount(0);

    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: pdf.name }).click();
    expect((await download).suggestedFilename()).toBe(pdf.name);
});

test('a file that is not really a PDF is rejected', async ({ page }) => {
    await createSupplier(page, 'Rejected File Supplier');

    await page.getByRole('button', { name: 'Add certificate' }).click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByLabel('Type')
        .selectOption({ label: seeded.requiredCertificate });
    await dialog.getByLabel('File').setInputFiles({
        name: 'pretending.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('this is plain text'),
    });
    await dialog.getByRole('button', { name: 'Save' }).click();

    await expect(dialog.getByText(/must be a file of type/)).toBeVisible();
});
