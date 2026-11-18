# Complete documentation screenshot inventory

Use this as the upload and replacement checklist for the v3.2.5 documentation.
Paths are relative to the repository root. Keep the filename exactly as listed so
all six language guides receive the replacement automatically.

Capture panel images as PNG at normal browser zoom and approximately 1440 px
wide. Keep the page title, relevant navigation, filters and actions visible.
Mask names, emails, phone numbers, addresses, order/tracking identifiers,
financial data, API keys, project IDs, tokens and all credentials.

Status meanings:

- **Current v3.2.5** — supplied with the September 26 documentation update.
- **Replace** — an older Admin/Seller UI capture that should be recaptured.
- **Upload** — referenced by the guide but the file is not present yet.
- **Review external UI** — setup screenshot for Firebase/Google or the installer;
  replace only when that exact setup screen has changed.
- **Obsolete** — do not reuse because the documented workflow no longer exists.

## Admin guide

### Current v3.2.5 images

- [ ] `documentation/assets/admin/languages.png` — Languages Management showing
  Panel, Website, Customer App and Seller App surfaces plus one language list.
- [ ] `documentation/assets/admin/datatable-export.png` — CSV/Excel/PDF format,
  export scope and completed background-export state.
- [ ] `documentation/assets/admin/reports.png` — Reports menu and a filtered
  report with summary and table.
- [ ] `documentation/assets/admin/authentication-firebase.png` — Authentication
  → Firebase and its connection/identity tests, with credentials masked.
- [ ] `documentation/assets/admin/seo-settings.png` — Web SEO title,
  description, keywords and social-sharing image.
- [ ] `documentation/assets/admin/product-approval.png` — current product
  verification and reapproval controls.
- [ ] `documentation/assets/admin/order-detail.png` — current customer/payment/
  fulfilment states and cancellation, return and refund controls.

### Older Admin UI images to replace

- [ ] `documentation/assets/admin/dashboard.png` — Admin Dashboard KPIs, charts
  and date filter.
- [ ] `documentation/assets/admin/settings.png` — Settings overview showing the
  current Payment, Authentication and Notification composition.
- [ ] `documentation/assets/admin/market.png` — Create Market form with currency,
  number formatting, default language, countries, priority and default state.
- [ ] `documentation/assets/admin/store-detail.png` — Store detail, verification,
  recommended state and locations.
- [ ] `documentation/assets/admin/commissions.png` — Commissions with maturity,
  settlement status and settle actions.
- [ ] `documentation/assets/admin/cron-monitor.png` — Cron Monitor health,
  history, Run Now and copyable cron/queue commands.
- [ ] `documentation/assets/admin/home-builder.png` — legacy translated-guide
  Home Builder overview. Replace with the current workspace or remove after all
  translated articles use the five step images below.

### Home Builder images used by the Admin guide

- [ ] `documentation/assets/homebuilder/step1.png` — App, Website and Header &
  Navbar workspace chooser.
- [ ] `documentation/assets/homebuilder/step2.png` — App live preview with
  section/design controls.
- [ ] `documentation/assets/homebuilder/step3.png` — Header & Navbar appearance
  editor.
- [ ] `documentation/assets/homebuilder/step4.png` — hero layout and item-style
  controls.
- [ ] `documentation/assets/homebuilder/step5.png` — Website product-card design
  selector and responsive preview.

## Seller guide

### Referenced older images to replace

- [ ] `documentation/seller/screenshots/dashboard.png` — Seller Dashboard with
  current KPIs, charts, date filter and store context.
- [ ] `documentation/seller/screenshots/location-form.png` — Add Location form
  with address and stock/inventory fields.
- [ ] `documentation/seller/screenshots/attribute.png` — current attribute
  values, preview and display-style controls.
- [ ] `documentation/seller/screenshots/product-form.png` — current product
  editor with attributes, variants, per-location inventory and tax context.
- [ ] `documentation/seller/screenshots/product-form-price.png` — current base-
  currency pricing area.
- [ ] `documentation/seller/screenshots/product-bulk.png` — bulk-product upload,
  template area and image-upload status.
- [ ] `documentation/seller/screenshots/add-ons-stock.png` — store add-on item
  price, cost, stock and availability.
- [ ] `documentation/seller/screenshots/product-addons-form.png` — product add-on
  matrix by variant and group.
- [ ] `documentation/seller/screenshots/shipping-profile.png` — shipping-profile
  products/locations, zone geography and rate fields.
- [ ] `documentation/seller/screenshots/package-form.png` — package presets with
  dimensions, weight and default package.
- [ ] `documentation/seller/screenshots/order-details.png` — current order detail
  with fulfilment, item cancellation, shipments and shipment-linked returns.
- [ ] `documentation/seller/screenshots/parcel-form.png` — current manual and/or
  Shiprocket parcel creation fields.
- [ ] `documentation/seller/screenshots/update-shipment-form.png` — current
  manual shipment status controls and Shiprocket Sync/NDR actions where relevant.
- [ ] `documentation/seller/screenshots/withdrawal-form.png` — withdrawal request
  and request list with current statuses.

### Referenced Seller images still to upload

- [ ] `documentation/seller/screenshots/reports.png` — Reports navigation and a
  filtered Sales or Products report.
- [ ] `documentation/seller/screenshots/datatable-export.png` — CSV/Excel/PDF
  selection, scope and completed background export.
- [ ] `documentation/seller/screenshots/product-faqs.png` — Product FAQ list and
  create/edit form.
- [ ] `documentation/seller/screenshots/session-relogin.png` — expired-session
  re-login modal over the original Seller page; do not capture a password.

### Obsolete Seller image

- `documentation/seller/screenshots/order-item-accept-reject.png` — **do not
  replace or upload**. It is no longer referenced and shows the retired seller
  accept/reject flow. Current orders have no seller acceptance gate.

## Installation and setup documentation

These images describe installers or third-party consoles. Review them against
their current screens; they do not need replacement merely because the Admin or
Seller panel theme changed.

### Backend installer

- [ ] `documentation/assets/installation/step1.png`
- [ ] `documentation/assets/installation/step2.png`
- [ ] `documentation/assets/installation/step3.png`
- [ ] `documentation/assets/installation/step4.png`
- [ ] `documentation/assets/installation/step5.png`
- [ ] `documentation/assets/installation/step6.png`
- [ ] `documentation/assets/installation/step7.png`

Capture the seven current installer stages in their documented order: license,
environment, super-admin, requirements, permissions, database and final screen.

### Backend Firebase setup

- [ ] `documentation/assets/firebase/firebase-1.png`
- [ ] `documentation/assets/firebase/firebase-2.png`
- [ ] `documentation/assets/firebase/firebase-3.png`
- [ ] `documentation/assets/firebase/firebase-4.png`
- [ ] `documentation/assets/firebase/firebase-5.png`
- [ ] `documentation/assets/firebase/firebase-6.png`
- [ ] `documentation/assets/firebase/firebase-7.png`
- [ ] `documentation/assets/firebase/firebase-8.png`
- [ ] `documentation/assets/firebase/firebase-9.png`

Review the Firebase project/service-account sequence and mask project IDs,
private keys and account identifiers.

### Customer App Firebase setup

- [ ] `documentation/assets/app-firebase/create-project-firebase.png`
- [ ] `documentation/assets/app-firebase/android-app-1.png`
- [ ] `documentation/assets/app-firebase/android-app-2.png`
- [ ] `documentation/assets/app-firebase/android-service-file.png`
- [ ] `documentation/assets/app-firebase/add-sha-keys-firebase.png`
- [ ] `documentation/assets/app-firebase/firebase-auth-1.png`
- [ ] `documentation/assets/app-firebase/firebase-auth-provider-2.png`
- [ ] `documentation/assets/app-firebase/firebase_client_id.png`
- [ ] `documentation/assets/app-firebase/ios-app-1.png`
- [ ] `documentation/assets/app-firebase/ios-app-2.png`
- [ ] `documentation/assets/app-firebase/ios-service-file.png`
- [ ] `documentation/assets/app-firebase/ios-auth-config.png`

Review Android/iOS registration, service files, SHA keys and authentication
provider screens. Never expose downloadable service-file contents or secrets.

### Google Cloud and Maps

- [ ] `documentation/assets/google-cloud/step-1.png`
- [ ] `documentation/assets/google-cloud/step-2.png`
- [ ] `documentation/assets/google-cloud/step-3.png`
- [ ] `documentation/assets/google-cloud/step-4.png`
- [ ] `documentation/assets/google-cloud/step-5.png`

Review project selection, API enablement, credentials and restriction screens;
mask the API key and project identity.

### Customer Support Chat

- [ ] `documentation/assets/support-chat/01-broadcast-driver-settings.png` —
  broadcast driver settings.
- [ ] `documentation/assets/support-chat/02-broadcast-connection-test.png` —
  successful connection test.
- [ ] `documentation/assets/support-chat/03-support-inbox.png` — Admin support
  inbox, queues and filters.
- [ ] `documentation/assets/support-chat/04-support-conversation.png` — assigned
  Admin conversation and composer.
- [ ] `documentation/assets/support-chat/05-support-guidance.png` — guidance and
  quick-reply management.
- [ ] `documentation/assets/support-chat/06-support-dashboard.png` — support
  dashboard, activity and ratings.
- [ ] `documentation/assets/support-chat/07-customer-guided-start.png` — customer
  guided support start.
- [ ] `documentation/assets/support-chat/08-customer-live-chat.png` — active
  customer conversation.
- [ ] `documentation/assets/support-chat/09-customer-rating-reopen.png` — rating,
  reopening and start-again controls.

The Support images contain both Admin and Customer Web UI. Replace any whose
layout or actions changed in v3.2.5.

## Not UI screenshots

Logos, favicons, app icons and files under `documentation/uploads/` are not part
of the UI-recapture checklist. Do not replace them unless branding itself
changes.
