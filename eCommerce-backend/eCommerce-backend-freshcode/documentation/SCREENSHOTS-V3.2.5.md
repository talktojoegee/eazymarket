# Hypercommerce v3.2.5 screenshot upload guide

Upload each image to the exact path shown below. The knowledge-base viewer shows
that path automatically while a file is missing. Use PNG, capture at normal
browser zoom on a desktop viewport around 1440 px wide, and crop to the browser
content area needed to understand the task. Keep menus, page title, important
filters and action buttons visible.

Before capturing, replace or mask names, emails, phone numbers, addresses,
tokens, API keys, project IDs, purchase codes, order/customer identifiers and
real financial information. Use realistic sample data; do not leave destructive
confirmation dialogs open.

## Admin images

### `documentation/assets/admin/languages.png`

- Open **Admin → Languages Management**.
- Show the navigation for Panel, Website, Customer App and Seller App.
- Keep one surface's language table visible with default, active, order/RTL and
  action controls.
- If practical, include Quick Add without covering the table.

### `documentation/assets/admin/datatable-export.png`

- Open a supported Admin table with a harmless filter applied.
- Open **Export** and show CSV, Excel and PDF plus the available scope controls.
- Include a completed background export/download state in the same capture, or
  compose two clearly separated crops in one PNG.

### `documentation/assets/admin/reports.png`

- Open the Reports menu and a representative report such as Products or
  Earnings.
- Show the selected date range, market/resource filters, summary values and the
  first rows of the report table.
- Use sample totals and mask seller/store identities.

### `documentation/assets/admin/authentication-firebase.png`

- Open **Settings → Authentication → Firebase**.
- Show the Firebase section and connection/identity test actions.
- Mask every credential, key, client ID, project ID and service-account value;
  do not reveal even partial production secrets.

### `documentation/assets/admin/seo-settings.png`

- Open **Settings → Web** at the SEO/social-sharing controls.
- Show title, description, keywords and social image using non-sensitive sample
  content.
- Include the save action and image preview when available.

### `documentation/assets/admin/product-approval.png` (replace existing)

- Open a product currently awaiting verification after a seller edit.
- Show the verification state and current approve/reject actions.
- Keep enough product context to make reapproval understandable; hide seller and
  product identifiers if they belong to real data.

### `documentation/assets/admin/order-detail.png` (replace existing)

- Open a sample order containing fulfilment/shipment information and a return or
  refund state.
- Show the customer-facing status, payment and fulfilment state, plus the Admin
  cancellation, return and refund areas.
- Mask all customer data, order numbers, addresses, tracking numbers and real
  amounts.

## Seller images

### `documentation/seller/screenshots/reports.png`

- Open **Seller → Reports** and a representative Sales or Products report.
- Show the selected store/date filters, summary cards or chart, and report rows.
- Use sample amounts and mask store/customer identities.

### `documentation/seller/screenshots/datatable-export.png`

- Open a supported Seller list with a harmless filter applied.
- Show CSV, Excel and PDF, scope selection and a completed background export.
- Keep the active-store context visible.

### `documentation/seller/screenshots/product-faqs.png`

- Show the Product FAQs list and the create/edit form with a safe sample question
  and answer.
- Include the relevant product selector/context and save action.

### `documentation/seller/screenshots/attribute.png` (replace existing)

- Show an attribute with several values and its current display-style controls.
- Use simple sample values and include the save action.

### `documentation/seller/screenshots/product-form.png` (replace existing)

- Show the current product editor with selected attributes, generated variants,
  per-location inventory and tax context.
- If the entire workflow does not fit legibly, combine two labelled crops in one
  PNG rather than shrinking text.

### `documentation/seller/screenshots/order-details.png` (replace existing)

- Show fulfilment, seller cancellation, shipment and return information for a
  sample order.
- Mask customer details, addresses, order/tracking identifiers and real amounts.

### `documentation/seller/screenshots/session-relogin.png`

- Let a Seller session expire while a non-destructive page is open.
- Show the re-login modal over the original page so it is clear that work resumes
  in place.
- Mask the email/account identity and never capture a password.

The same English UI screenshots are shared by all six language guides. Do not
create translated duplicates unless the product later ships localized demo data
and a separate screenshot set is explicitly requested.
