import { seeded } from '../support/test-data';
import { createSupplier, expect, test } from '../support/test';

test('a buyer can post, edit and delete a comment', async ({ page }) => {
    await createSupplier(page, 'Comment Supplier');

    // Typed markup must show as plain text, never run as HTML.
    const text = '<b>Called</b> them about the late order.';
    await page.getByLabel('New comment').fill(text);
    await page.getByRole('button', { name: 'Post comment' }).click();

    const comment = page.getByRole('listitem').filter({ hasText: text });
    await expect(comment).toBeVisible();
    await expect(comment.locator('b')).toHaveCount(0);
    await expect(page.getByLabel('New comment')).toHaveValue('');

    await comment.getByRole('button', { name: 'Edit comment' }).click();
    await page
        .getByLabel('Comment', { exact: true })
        .fill('They promised delivery on Friday.');
    await page.getByRole('button', { name: 'Save' }).click();

    const edited = page
        .getByRole('listitem')
        .filter({ hasText: 'They promised delivery on Friday.' });
    await expect(edited).toContainText('edited');

    await edited.getByRole('button', { name: 'Delete comment' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete' })
        .click();

    await expect(page.getByText('No comments yet.')).toBeVisible();
});

test('a comment on a contract stays on the contract page', async ({ page }) => {
    const name = await createSupplier(page, 'Contract Comment Supplier');
    const title = `${name} agreement`;
    await page.getByRole('link', { name: 'Add contract' }).click();
    await page.getByLabel('Title').fill(title);
    await page.getByLabel('Type').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create contract' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    const text = 'Legal is reviewing clause 7.';
    await page.getByLabel('New comment').fill(text);
    await page.getByRole('button', { name: 'Post comment' }).click();
    await expect(
        page.getByRole('listitem').filter({ hasText: text }),
    ).toBeVisible();

    await page.getByRole('link', { name, exact: true }).first().click();
    await expect(page.getByRole('heading', { name })).toBeVisible();
    await expect(page.getByText('No comments yet.')).toBeVisible();
    await expect(page.getByText(text)).toHaveCount(0);
});

test('older comments load on request without reloading the page', async ({
    page,
}) => {
    await page.goto(seeded.busySupplierUrl);
    const comments = page.getByRole('listitem').filter({
        hasText: 'Comment number',
    });
    await expect(comments).toHaveCount(20);
    await page.evaluate(() => {
        document.documentElement.dataset.notReloaded = 'true';
    });

    await page.getByRole('button', { name: 'Show older comments' }).click();

    await expect(comments).toHaveCount(seeded.busySupplierCommentCount);
    // Oldest at the top, newest at the bottom next to the new comment box.
    await expect(comments.first()).toContainText(
        `Comment number ${seeded.busySupplierCommentCount - 1}`,
    );
    await expect(comments.last()).toHaveText(/Comment number 0$/);
    await expect(page.locator('html')).toHaveAttribute(
        'data-not-reloaded',
        'true',
    );
    await expect(
        page.getByRole('button', { name: 'Show older comments' }),
    ).toHaveCount(0);
});
