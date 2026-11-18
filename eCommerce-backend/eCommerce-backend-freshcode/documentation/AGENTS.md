# AGENTS.md — Hypercommerce Documentation

Guidance for working on the Hypercommerce documentation site. Read this before editing
any of the three core files.

## What this is

A self-contained, static documentation site for **Hypercommerce** — a multi-vendor
ecommerce platform sold on Envato / CodeCanyon. It is the install & setup guide for the
product's five surfaces: the Laravel backend (admin + seller panel), the Flutter customer
app, the seller app, and the customer web.

No framework, no build step, no dependencies to install. Open `hypercommerce-docs.html`
in a browser and it runs.

## File layout

```
hypercommerce-documentation/
├── hypercommerce-docs.html   Markup: shell + a few fully-authored "showcase" pages
├── hypercommerce-docs.css    Design tokens + all component styles (~950 lines)
├── hypercommerce-docs.js     App logic: nav model, routing, search, theming (vanilla)
├── assets/                   hyper-logo-onlight.png / hyper-logo-ondark.png
├── screenshots/              Real product screenshots (01-welcome … 06-brand)
├── uploads/                  Pasted/working images
└── _ds/taskify-design-system-…/   Source design system (read-only reference)
```

## Architecture — how pages work

The site is a hashless single-page app driven entirely by `hypercommerce-docs.js`.

**Navigation model.** `NAV` (top of the JS) is the source of truth — four groups
(`Getting Started`, `Backend · Admin · Seller Panel`, `Customer App`, `Other Platforms`),
each with items `{ id, label, icon, soon?, hidden? }`. `FLAT` flattens it for prev/next and
search. **To add or reorder a page, edit `NAV` — not the HTML.**

**Three ways a page renders** (see `navigate(id)`):
1. **Static showcase page** — if `#pages` contains a `<section data-page="{id}">`, it is
   shown as-authored. Only Introduction, Server Requirements, Backend Installation, and
   Hostinger Setup are hand-written this way. These are the canonical examples of correct
   markup — copy their patterns.
2. **Generated "Soon" page** — ids in the `SOON` map render via `buildSoon()` (coming-soon
   hero + roadmap + notify form).
3. **Generated generic page** — everything else renders via `buildGeneric()` from the
   `GENERIC` content map (`{ lead, outline, images }`). These are placeholders meant to be
   promoted to real static pages over time.

**Other behaviors, all hand-rolled in vanilla JS:**
- ⌘K **command palette** (`renderPalette`, keyboard nav, group headers)
- **On this page** TOC + scrollspy, built from `[data-toc]` headings (`buildTOC`)
- breadcrumbs, prev/next, copy-to-clipboard code blocks, tabbed panels
- light/dark theme toggle, mobile slide-in sidebar
- current page + theme persisted to `localStorage` (`hc-docs-page`, `hc-docs-theme`)

**Icons** are inline SVG path strings in the `ICON` map. Add a key there and reference it
via `svg("name", size)` — don't paste raw `<svg>` into JS-generated markup.

## Authoring a new static page

1. Add (or unhide) the entry in `NAV`.
2. Add `<section class="doc-page" data-page="{id}" hidden>…</section>` inside `#pages`.
3. Give every section heading you want in the right-hand TOC an `id` and
   `data-toc="Label"` attribute (`data-toc-sub` indents it). The TOC and scrollspy are
   generated from these — no manual TOC editing.
4. Use the existing component classes (below). Match an existing showcase page rather than
   inventing markup.
5. Inline navigation uses `data-go="{page-id}"` on any element; the delegated click handler
   routes it. Use this, not `href`.

## Design system & standards

The visual language is **Taskify / Metronic v9.2.8** — clean, dense, restrained color,
8px-grid spacing. The source lives in `_ds/taskify-design-system-…/` (read-only reference;
manifest, bundle, `colors_and_type.css`, fonts, ui_kits). Treat it as the spec.

**Everything is a CSS variable. Never hardcode a color, radius, shadow, or font size** —
use a token. Two token families exist:

- `--tk-*` — the Metronic foundation: neutral surfaces (`--tk-bg-page/card/muted/soft/hover`),
  borders, a 6-step text scale (`--tk-fg-1` darkest → `--tk-fg-6` ghost), semantic colors
  (primary blue, success green, warning amber, danger red, info purple — each with
  `-hover/-light/-fg/-light-border`), spacing (`--tk-space-1…16`), radii
  (`--tk-radius-xs…2xl/full`), shadows (`--tk-shadow-xs…pop/focus`), and the type scale
  (`--tk-fs-*`, `--tk-fw-*`, Inter + JetBrains Mono).
- `--hc-accent*` — the **Hypercommerce brand layer**: brand yellow `#FFD000`. The accent and
  its derived variants (`-hover/-contrast/-fg/-light/-soft/-border`) are recomputed in JS by
  `applyAccent()` for each theme, so light/dark contrast is handled automatically. Buttons,
  active nav, badges, and outline-item tiles all key off `--hc-accent*`. **To rebrand, change
  `ACCENT` in the JS — not scattered CSS.**

**Theming.** Dark mode is `[data-theme="dark"]` on `<html>`, which overrides the `--tk-*`
neutrals and re-derives the accent. Any new color you reference must resolve correctly in
both themes — prefer existing tokens or `color-mix(in srgb, var(--hc-accent) N%, …)` as the
existing dark rules do. Logos swap automatically via `.on-light` / `.on-dark`.

**Type & spacing.** Inter for UI, JetBrains Mono for code/numerics. Stay on the 8px grid and
the `--tk-fs-*` scale; don't introduce off-scale pixel values.

## Component vocabulary (reuse, don't reinvent)

| Pattern | Class |
|---|---|
| Callouts (tip / info / warn / danger) | `.doc-callout.{tip\|info\|warn\|danger}` + `.co-ic` / `.co-body` (`.co-title`, `.co-text`) |
| Numbered steps | `.doc-steps` › `.doc-step` › `.doc-step-num` + `.doc-step-body` |
| Code block w/ copy | `.doc-code` › `.doc-code-bar` (`.fname`, `.doc-code-lang`, `.doc-copy`) › `<pre><code>` of `.cl` lines (`.tk-c` = comment) |
| Spec / KV grid | `.doc-kv` › `.doc-kv-item` (`.kl` label, `.kvv` value) |
| Requirement table | `.doc-table-card` › `.doc-table` + `.doc-pill.{ok\|opt}` |
| Checklist | `.doc-checklist` › `.doc-check-row` (`.ck`, `.cb`) |
| Browser-frame screenshot | `.doc-shot` › `.doc-shot-frame` (`.doc-shot-bar` traffic-light dots, `.doc-shot-url`, `.doc-shot-canvas`) + `figcaption.doc-shot-cap` — JS helper `shotHTML()` |
| Product / outline cards | `.doc-prod-card`, `.doc-outline-item` |
| Tabs | `.doc-tabs` › `.doc-tabs-bar` › `.doc-tab` + `.doc-tab-pane` |
| "Soon" tag | `.doc-soon-tag` |

Code-block lines are spans with class `.cl`; mark comment lines with `.tk-c`. The copy button
strips non-breaking spaces automatically.

## Conventions & gotchas

- **Vanilla only.** No frameworks, no npm, no bundler. Keep it that way unless asked.
- Fonts load from Google Fonts CDN via `@import` in the CSS.
- The PHP-extensions grid is data-driven from a small inline `<script>` at the bottom of the
  HTML — edit the `ext` array there.
- `NAV` items support `hidden: true` (reachable by `data-go` but not listed, e.g. Hostinger /
  cPanel) and `soon: true` (renders the coming-soon template).
- Screenshot placeholders in generic/soon pages are intentional — replace `shotHTML(...)`
  callsites or the `images` arrays in `GENERIC` with real screenshots from `screenshots/`.
- Edit content through the model: `NAV` for structure, `GENERIC` for placeholder copy, a
  static `<section>` for finished pages. Don't fight the router by toggling `hidden` by hand.

## Changelog & versioning workflow (important)

The **Changelog** page renders from the `rows` array in the
`node.id === "changelog"` block of `hypercommerce-docs.js` (newest first; the
`true` flag marks the "Latest" entry). The site **version** lives in
`config.js` and `seller/config.js` (`"version"`), shown in the sidebar badge.

- Accumulate shipped-but-unreleased features in
  `docs-internal/changelog-unreleased.md` under **Pending** as they land —
  plain, customer-facing language.
- **Only when the user explicitly says "update the log with version X"**: fold
  the Pending bullets into a new `rows` entry (mark it Latest), bump `"version"`
  in **both** config files to X, then clear the Pending list.
- Never bump the version or cut a new changelog entry on your own initiative.
```

