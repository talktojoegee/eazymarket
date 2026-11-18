# Hypercommerce Documentation — Audit & To-Do

What the docs site is: a static SPA (`hypercommerce-docs.html` + `.js` + `.css`) that is
the install/setup guide for the platform. Most of it currently renders **placeholder
content and fake data** from the `GENERIC`, `SOON`, and changelog maps in
`hypercommerce-docs.js`. Below is what to fix, grouped by priority.

---

## 1. Replace fake / placeholder data (highest priority)

These are invented values shipping in the live docs today.

- **Changelog is fabricated.** `GENERIC.changelog` hardcodes `v2.4.0 — June 2026`,
  `v2.3.1`, `v2.3.0`, `v2.2.0` with made-up feature lists. The app's real version default
  is `1.0.0` (`config/app.php` → `APP_VERSION`). Replace with the actual release history.
- **Version badge mismatch.** The header shows `v2.4` and the hero says
  *"Version 2.4 · Updated June 2026"* — not backed by anything in the code. Tie this to the
  real `APP_VERSION` (currently `1.0.0`).
- **11 pages are empty outlines, not real guides.** Everything in the `GENERIC` map renders
  a generic "In this section" stub: `firebase`, `google-cloud`, `cron-queue`, `go-live`,
  `app-prerequisites`, `app-config`, `app-firebase`, `build-release` (plus dead
  `install-hostinger`/`install-cpanel` generic entries that the static pages already
  override). Each needs to be promoted to a real static page (see authoring steps in
  `CLAUDE.md`).
- **Screenshot placeholders everywhere.** `shotHTML()` renders fake browser frames labelled
  "Attach screenshot here" pointing at invented domains (`console.hypercommerce.app`,
  `admin.hypercommerce.app`, `1440 × 760`). Real screenshots already exist in
  `screenshots/` (`01-welcome` … `06-brand`) but are never used. Wire them in.
- **"Coming soon" roadmap dates are invented** (`Q3 2026`, `Q4 2026`) for Seller App and
  Customer Web, and the "Notify me" email form does nothing. Use real dates or remove.
- **Dead links.** "Edit this page on GitHub", the GitHub icon, "Get support", and the
  thumbs-up/down feedback all go nowhere. Point them at real URLs or remove them.

## 2. Fix accuracy against the actual code

- **Installer steps don't match the real installer.** The docs describe a vague
  "web installer → requirements → purchase code → migrations" flow. The real wizard
  (`routes/installer.php`) is a 7-step flow: **license → license/verify → environment →
  super-admin → requirements → permissions → database → final**. Rewrite the installer
  walkthrough to match these actual steps and screens.
- **Tech-stack claims that are correct (keep):** `Laravel 13` and `PHP 8.4` match
  `composer.json` (`laravel/framework ^13.0`, `php ^8.4`). Good.
- **Re-verify** the "MySQL 8.0 / MariaDB 10.6" minimums against what you actually test on,
  and the Customer App "Flutter 3" / Customer Web "Next.js" labels.

## 3. Add missing pages (the product does far more than the docs cover)

The migrations and `app/` code show large features with **zero documentation**:

- **Payment gateways.** Code supports **Stripe, Razorpay, Paystack, and Flutterwave**
  (`app/Enums/Payment/*`, `app/Types/Settings/PaymentSettingType.php`). The docs mention
  payments only once and have **no setup page**. Add one — this is a top buyer need.
- **Post-install admin configuration.** No guidance for: Markets / multi-currency, POS,
  Shipping (providers, zones, profiles, rates), Subscriptions & seller plans, Ad campaigns,
  Referrals, Gift cards & wallet, Delivery boys, Reviews/badges, Home sections / CMS.
- **Seller Panel guide.** The nav group is "Backend · Admin · Seller Panel" but there is no
  seller-specific page.
- **API reference.** `dedoc/scramble` is installed — it auto-generates API docs. Link to
  the generated reference.
- **Updating the product.** There's a `documentation/update-packages/` mechanism and a
  `system_updates` table, but no "How to update / upgrade" page.
- **Environment variables reference** from `.env.example`.

## 4. Structure & polish

- Promote `GENERIC` placeholders to real `<section data-page>` static pages and remove the
  dead generic entries — follow the "Authoring a new static page" steps in `CLAUDE.md`.
- Replace `shotHTML(...)` placeholder calls with the real `screenshots/` images.
- Consolidate the standalone "Cron & Queue Workers" page with the cron content already in
  the Installation page (currently duplicated/placeholder).

---

### Quick wins (do these first)
1. Real changelog + correct version number.
2. Rewrite the installer page to the real 7-step wizard.
3. Add a Payment Gateways page (Stripe / Razorpay / Paystack / Flutterwave).
4. Drop real screenshots into Installation & Hostinger pages.
5. Fix or remove dead GitHub / support / feedback links.
