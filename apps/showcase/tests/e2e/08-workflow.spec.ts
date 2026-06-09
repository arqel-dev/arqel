import { expect, test } from './fixtures';

/**
 * TicketResource models an `arqelWorkflow()` over `status` and its form()
 * mounts both a plain Field::select('status') and a
 * Workflow\Fields\StateTransitionField::make('status')->showHistory().
 *
 * Real-DOM notes (verified against the dogfood stack):
 *  - The Tickets table renders plain "Edit"/"Delete" row buttons (no
 *    dropdown, unlike PostResource).
 *  - "Edit" opens the record form inline on the same /admin/tickets URL
 *    (a slideover) — the route does NOT change to /edit.
 *  - The Subject text field renders its <input> and the Save/Cancel actions
 *    render normally.
 *
 * KNOWN GAP (findings ledger CANDIDATE #7): the form declares TWO fields
 * named `status` (the plain Field::select + the StateTransitionField). On
 * the edit form BOTH render as a bare `<label>Status</label>` with NO control
 * (no <select>, no transition buttons, no history) — the duplicate field
 * name collides and the SelectField control is dropped, and the
 * StateTransitionField emits no transition UI without a bound record. So we
 * assert the editable form *surfaces* (Subject input + the Status labels +
 * Save) rather than a status control / transition UI that does not render.
 */
test.describe('Workflow resource (Tickets)', () => {
  test('the ticket edit form opens inline with the Subject field and Save action', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/tickets');
    await page.waitForLoadState('networkidle');

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Edit' }).click();

    // The editable form mounts inline (slideover) on the same URL.
    const subject = page.locator('[data-arqel-field="subject"]');
    await expect(subject).toBeVisible();
    await expect(subject.locator('input')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
  });

  test('the status field(s) are present in the form schema (label rendered)', async ({
    loggedInPage,
  }) => {
    const page = loggedInPage;
    await page.goto('/admin/tickets');
    await page.waitForLoadState('networkidle');

    await page.locator('table tbody tr').first().getByRole('button', { name: 'Edit' }).click();

    await expect(page.locator('[data-arqel-field="subject"]')).toBeVisible();

    // Both the plain Field::select('status') and the StateTransitionField are
    // serialised into the form, each surfacing a "Status" label. (Their
    // controls do not render — see CANDIDATE #7 — so we assert the labels.)
    await expect(page.locator('[data-arqel-field="status"]')).toHaveCount(2);
    await expect(page.getByText('Status', { exact: true }).first()).toBeVisible();
  });
});
