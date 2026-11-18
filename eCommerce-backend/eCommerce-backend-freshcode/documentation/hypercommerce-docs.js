/* =====================================================================
   Hypercommerce Documentation — behaviour (vanilla JS, no framework)
   Handles: page routing, sidebar nav, breadcrumb, on-this-page TOC +
   scrollspy, prev/next, command-palette search, copy buttons, tabs,
   light/dark theme, mobile sidebar.
   ===================================================================== */
(function () {
  "use strict";

  /* ---------------- Localization ---------------- */
  var CFG = window.HC_DOCS_CONFIG || {};
  var I18N = window.HC_DOCS_I18N || {};
  var LANG_KEY = "hc-docs-lang";

  function availableLangs() {
    var have = Object.keys(I18N);
    var ordered = (CFG.langs || []).map(function (item) { return item.code; }).filter(function (code) {
      return code === "en" || have.indexOf(code) >= 0;
    });
    return ordered.length ? ordered : ["en"];
  }
  function langLabel(code) {
    var match = (CFG.langs || []).filter(function (item) { return item.code === code; })[0];
    return match ? match.label : code;
  }
  var LANG = (function () {
    var requested;
    try { requested = new URLSearchParams(location.search).get("lang"); } catch (e) {}
    var saved;
    try { saved = localStorage.getItem(LANG_KEY); } catch (e) {}
    var wanted = requested || saved || CFG.defaultLang || "en";
    var available = availableLangs();
    return available.indexOf(wanted) >= 0 ? wanted : "en";
  })();
  function tr(value) {
    if (value == null || LANG === "en") return value;
    return (I18N[LANG] && I18N[LANG][value]) || value;
  }
  function translateTree(root) {
    if (!root) return;
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var nodes = [], current;
    while ((current = walker.nextNode())) nodes.push(current);
    nodes.forEach(function (node) {
      var parent = node.parentElement;
      if (!parent || parent.closest("script, style, pre, code, svg")) return;
      if (node._hcDocsEnglish == null) node._hcDocsEnglish = node.nodeValue;
      var source = node._hcDocsEnglish;
      var leading = (source.match(/^\s*/) || [""])[0];
      var trailing = (source.match(/\s*$/) || [""])[0];
      var key = source.trim();
      node.nodeValue = key ? leading + tr(key) + trailing : source;
    });
    ["placeholder", "aria-label", "title", "data-toc"].forEach(function (attr) {
      var elements = root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll("[" + attr + "]")) : [];
      if (root.nodeType === 1 && root.hasAttribute(attr)) elements.unshift(root);
      elements.forEach(function (el) {
        var prop = "_hcDocsEnglish_" + attr.replace(/-/g, "_");
        if (el[prop] == null) el[prop] = el.getAttribute(attr);
        el.setAttribute(attr, tr(el[prop]));
      });
    });
    document.documentElement.setAttribute("lang", LANG);
  }
  function buildSwitcher() {
    var host = $("#langSwitch");
    if (!host) return;
    host.innerHTML = availableLangs().map(function (code) {
      return '<option value="' + code + '"' + (code === LANG ? " selected" : "") + ">" + langLabel(code) + "</option>";
    }).join("");
    host.setAttribute("aria-label", tr("Language"));
  }
  function setLang(code) {
    if (availableLangs().indexOf(code) < 0 || code === LANG) return;
    LANG = code;
    try { localStorage.setItem(LANG_KEY, code); } catch (e) {}
    try {
      var url = new URL(location.href);
      if (code === "en") url.searchParams.delete("lang"); else url.searchParams.set("lang", code);
      history.replaceState(null, "", url.toString());
    } catch (e) {}
    buildSwitcher();
    translateTree(document.body);
    buildSidebar();
    navigate(CUR_ID || "introduction");
  }

  /* ---------------- Navigation model ---------------- */
  var NAV = [
    { group: "Getting Started", icon: "rocket", items: [
      { id: "introduction", label: "Introduction", icon: "book" },
      { id: "server-requirements", label: "Server Requirements", icon: "server" },
      { id: "changelog", label: "Changelog", icon: "git-branch" },
    ]},
    { group: "Backend · Admin · Seller Panel", icon: "layers", items: [
      { id: "installation", label: "Installation", icon: "download" },
      { id: "install-hostinger", label: "Hostinger Setup", icon: "server", hidden: true },
      { id: "install-cpanel", label: "cPanel Setup", icon: "folder", hidden: true },
      { id: "firebase", label: "Firebase Setup", icon: "flame" },
      { id: "google-cloud", label: "Google Cloud & Maps", icon: "cloud" },
      { id: "shipping", label: "Shipping", icon: "truck" },
      { id: "support-chat", label: "Support Chat", icon: "message" },
    ]},
    { group: "Customer App", icon: "smartphone", items: [
      { id: "app-prerequisites", label: "Prerequisites", icon: "list" },
      { id: "app-config", label: "App Configuration", icon: "settings" },
      { id: "app-firebase", label: "Firebase & Push", icon: "flame" },
      { id: "build-release", label: "Build & Release", icon: "package" },
    ]},
    { group: "Customer Web", icon: "globe", items: [
      { id: "web-prerequisites", label: "Prerequisites", icon: "list" },
      { id: "web-seller-landing", label: "Seller Landing Page", icon: "store" },
      { id: "web-shared-hosting", label: "Shared Hosting (Static)", icon: "folder" },
      { id: "web-vps", label: "VPS Install (SSR)", icon: "server" },
    ]},
    { group: "Seller App", icon: "store", items: [
      { id: "seller-app", label: "Configuration", icon: "settings" },
    ]},
  ];
  var FLAT = [];
  NAV.forEach(function (g) { g.items.forEach(function (it) { FLAT.push({ id: it.id, label: it.label, soon: it.soon, group: g.group }); }); });
  function byId(id) { return FLAT.filter(function (x) { return x.id === id; })[0]; }
  function indexOf(id) { for (var i = 0; i < FLAT.length; i++) if (FLAT[i].id === id) return i; return -1; }
  var SOON = {};

  /* ---------------- Generic-section content ---------------- */
  var GENERIC = {
    "changelog": { lead: "Release notes for each Hypercommerce version." },
  };

  /* ---------------- Icons (inline SVG strings) ---------------- */
  var ICON = {
    hash: '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
    terminal: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M13 15h4"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
    "life-buoy": '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="m5 5 3.5 3.5M15.5 15.5 19 19M19 5l-3.5 3.5M8.5 15.5 5 19"/>',
    check: '<path d="M5 12.5 10 17 19 7"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
    "arrow-right": '<path d="M5 12h14M14 6l6 6-6 6"/>',
    "arrow-left": '<path d="M19 12H5M10 6l-6 6 6 6"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    store: '<path d="M4 9.5 5 4h14l1 5.5"/><path d="M4 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 4 0"/><path d="M5 11v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><path d="M9 20v-5h6v5"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 3 4 6 4 9s-1.5 6-4 9c-2.5-3-4-6-4-9s1.5-6 4-9Z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
    play: '<path d="M7 4l13 8-13 8V4Z"/>',
    circle: '<circle cx="12" cy="12" r="9"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/>',
  };
  function svg(name, size) {
    size = size || 18;
    return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' + (ICON[name] || ICON.circle) + '</svg>';
  }

  /* ---------------- Accent derivation (brand yellow + theme) ---------------- */
  var ACCENT = "#FFD000";
  function hexToRgb(h) { h = h.replace("#", ""); if (h.length === 3) h = h.split("").map(function (c) { return c + c; }).join(""); var n = parseInt(h, 16); return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 }; }
  function toHex(r, g, b) { return "#" + [r, g, b].map(function (x) { return Math.max(0, Math.min(255, Math.round(x))).toString(16).padStart(2, "0"); }).join(""); }
  function mix(hex, t, amt) { var a = hexToRgb(hex), b = hexToRgb(t); return toHex(a.r + (b.r - a.r) * amt, a.g + (b.g - a.g) * amt, a.b + (b.b - a.b) * amt); }
  function lum(hex) { var c = hexToRgb(hex); return 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b; }
  function applyAccent(hex, dark) {
    var c = hexToRgb(hex), L = lum(hex), light = L > 150, r = document.documentElement;
    r.style.setProperty("--hc-accent", hex);
    r.style.setProperty("--hc-accent-contrast", L > 140 ? "#1B1C22" : "#FFFFFF");
    r.style.setProperty("--hc-accent-hover", mix(hex, "#000000", light ? 0.10 : 0.14));
    r.style.setProperty("--hc-accent-fg", dark ? (light ? mix(hex, "#fff", 0.12) : mix(hex, "#fff", 0.45)) : (light ? mix(hex, "#1a1200", 0.62) : mix(hex, "#15131f", 0.18)));
    r.style.setProperty("--hc-accent-light", dark ? "rgba(" + c.r + "," + c.g + "," + c.b + ",0.20)" : mix(hex, "#ffffff", 0.90));
    r.style.setProperty("--hc-accent-soft", dark ? "rgba(" + c.r + "," + c.g + "," + c.b + ",0.12)" : mix(hex, "#ffffff", 0.95));
    r.style.setProperty("--hc-accent-border", dark ? "rgba(" + c.r + "," + c.g + "," + c.b + ",0.42)" : "rgba(" + c.r + "," + c.g + "," + c.b + ",0.30)");
  }

  /* ---------------- DOM refs ---------------- */
  var $ = function (s, ctx) { return (ctx || document).querySelector(s); };
  var $$ = function (s, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(s)); };
  var sidebarNav, pagesHost, crumbRoot, crumbCur, tocList, prevEl, nextEl, sidebar;

  /* ---------------- Theme ---------------- */
  function currentTheme() { return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light"; }
  function setTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    applyAccent(ACCENT, theme === "dark");
    try { localStorage.setItem("hc-docs-theme", theme); } catch (e) {}
    var btn = $("#themeToggle");
    if (btn) btn.innerHTML = svg(theme === "dark" ? "sun" : "moon", 17);
  }
  ICON.sun = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>';
  ICON.moon = '<path d="M21 13a9 9 0 0 1-11-11 9 9 0 1 0 11 11Z"/>';
  ICON.menu = '<path d="M3 6h18M3 12h18M3 18h18"/>';
  ICON.github = '<path d="M9 19c-4 1.5-4-2-6-2.5M15 21v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.3 4.3 0 0 0-.1-3.2s-1-.3-3.4 1.3a11.6 11.6 0 0 0-6 0C6.3 1.6 5.3 1.9 5.3 1.9a4.3 4.3 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 8.3c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/>';
  ICON.sparkles = '<path d="m12 4 1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6L12 4Z"/><path d="M18 16l.7 1.8L20.5 18l-1.8.7L18 20.5l-.7-1.8L15.5 18l1.8-.7L18 16Z"/>';
  ICON["git-branch"] = '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8.5v7M18 10.5c0 4-6 2-6 6"/>';
  ICON.zap = '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>';
  ICON["check-circle"] = '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5L16 9"/>';
  ICON.image = '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>';
  ICON["chevron-right"] = '<path d="M9 6l6 6-6 6"/>';
  // sidebar nav icons
  ICON.book = '<path d="M4 5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2V5Z"/><path d="M4 18a2 2 0 0 1 2-2h12"/>';
  ICON.rocket = '<path d="M14 4c4 0 6 2 6 6 0 5-7 9-7 9s-7-4-7-9c0-4 2-6 6-6h2Z"/><circle cx="13" cy="10" r="2"/><path d="M9 17l-3 3 3-1Z"/>';
  ICON.server = '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>';
  ICON.database = '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.66 3.58 3 8 3s8-1.34 8-3V6"/><path d="M4 12v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>';
  ICON.download = '<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/>';
  ICON.flame = '<path d="M12 3c1 4 5 5 5 9a5 5 0 0 1-10 0c0-1.5.6-2.5 1.3-3.4C9 10 9.5 9 9.3 7.5 11 8 11.7 5.5 12 3Z"/>';
  ICON.cloud = '<path d="M7 18a4 4 0 0 1-.5-7.97A5.5 5.5 0 0 1 17 9.5a3.5 3.5 0 0 1-.5 8.5H7Z"/>';
  ICON["credit-card"] = '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>';
  ICON.settings = '<circle cx="12" cy="12" r="3"/><path d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.7l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 13H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.3 6L4.2 6a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.3V4a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-1.2 2.9H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>';
  ICON.package = '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>';
  ICON.layers = '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5M3 17l9 5 9-5"/>';
  ICON.smartphone = '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>';
  ICON.folder = '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>';
  ICON.truck = '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>';
  ICON.message = '<path d="M21 12a8 8 0 0 1-8 8H6l-4 3v-7a8 8 0 1 1 19-4Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>';

  /* ---------------- Sidebar nav ---------------- */
  function buildSidebar() {
    var html = "";
    NAV.forEach(function (g) {
      html += '<div class="doc-nav-grp">' + svg(g.icon, 13).replace("<svg", '<svg class="gi"') + tr(g.group) + "</div>";
      g.items.forEach(function (it) {
        if (it.hidden) return;
        html += '<div class="doc-nav-item' + (it.soon ? " is-soon" : "") + '" data-nav="' + it.id + '">' +
          svg(it.icon, 17).replace("<svg", '<svg class="doc-nav-ic"') +
          '<span class="lbl">' + tr(it.label) + "</span>" +
          (it.soon ? '<span class="doc-soon-tag">' + tr("Soon") + "</span>" : "") + "</div>";
      });
    });
    sidebarNav.innerHTML = html;
  }

  /* ---------------- Generic + Soon page builders ---------------- */
  function shotHTML(url, label, dims, caption) {
    return '<figure class="doc-shot"><div class="doc-shot-frame"><div class="doc-shot-bar">' +
      '<span class="dots"><i style="background:#F8285A"></i><i style="background:#F6B100"></i><i style="background:#17C653"></i></span>' +
      '<span class="doc-shot-url">' + url + '</span><span style="width:44px"></span></div>' +
      '<div class="doc-shot-canvas">' + svg("image", 42).replace("<svg", '<svg class="pi"') +
      '<span class="pl">' + label + '</span><span class="pd">' + dims + '</span></div></div>' +
      (caption ? '<figcaption class="doc-shot-cap"><b>Fig.</b> ' + caption + "</figcaption>" : "") + "</figure>";
  }
  function buildGeneric(node) {
    var info = GENERIC[node.id] || { lead: "This section of the setup guide is part of the full Hypercommerce documentation.", outline: [["Step one", "Overview of this section"], ["Step two", "Configuration details"], ["Step three", "Verification"]] };
    var html = '<div class="doc-eyebrow">' + svg("hash", 14) + node.group + "</div>" +
      '<h1 class="doc-h1">' + node.label + "</h1>" +
      '<p class="doc-lead">' + info.lead + "</p>";
    if (node.id === "changelog") {
      var rows = [
        ["v3.4.0 — 31 Oct 2026", "<strong>What's New</strong><br>[+ Added] Recently Viewed Products on the Customer Website and Customer App<br>[+ Added] Tile view for product lists in the Admin and Seller Panels<br>[+ Added] Combo Packs and Build Your Own Bundle tools for Admins and Sellers<br>[+ Added] Live Reel Preview on Admin and Seller create/edit forms<br>[+ Added] Custom order-number prefixes for new orders<br>[+ Added] Custom Invoice Builder for order invoices<br>[+ Added] Country-specific dynamic Seller Form Fields for bank details and business documents<br>[+ Added] Custom Landing Page Builder for standalone storefront pages<br>[+ Added] WhatsApp Cloud API integration with approved-template mapping, test messages, and delivery-status callbacks", true],
        ["v3.3.0 — 19 Oct 2026", "<strong>What's New</strong><br>[+ Added] Admin-only blog creation, management, categories, and settings under Social &amp; Content<br>[+ Added] Dynamic Splash Screen Builder for the Customer App and Seller App<br>[+ Added] Onboarding Builder for the Customer App and Seller App<br>[+ Added] Automated abandoned-cart notifications<br>[+ Added] Shareable carts with options to merge items into an existing cart or replace it<br>[+ Added] New-order popup notifications in the Admin and Seller Panels<br>[+ Added] Google Fonts typography settings for the Admin Panel, Seller Panel, Customer Website, Customer App, and Seller App", false],
        ["v3.2.5 — 26 Sep 2026", "<strong>What's New</strong><br>[+ Added] Separate language management for the Panel, Customer Website, Customer App, and Seller App<br>[+ Added] CSV, Excel, and PDF exports on supported Admin and Seller tables<br>[+ Added] Admin reports for categories, brands, products, sellers, stores, earnings, and taxes<br>[+ Added] Seller reports for sales, products, settlements, returns, stores, and taxes<br>[+ Added] Session re-login that returns users to the page where they were working<br><br><strong>Changed workflows</strong><br>[+ Updated] Order, fulfilment, cancellation, return, refund, and customer-facing status screens<br>[+ Updated] Product variants, inventory, attributes, FAQs, tax context, moderation, and reapproval flows<br>[+ Updated] Website SEO and social-sharing controls<br>[+ Moved] Firebase credentials and connection tests into Authentication settings", false],
        ["v3.2.0 — 08 Sep 2026", "<strong>What's New</strong><br>[+ Added] Watch &amp; Buy with product-based reels<br>[+ Added] Like, Share, Profile Management, and Reel Management<br>[+ Added] Multiple product attachments in Watch &amp; Buy reels<br>[+ Added] Admin Reel Management with reel creation and product attachments<br>[+ Added] One Day Story with image, video, and text support<br>[+ Added] Product linking in Stories with automatic story expiration<br>[+ Added] Improved and redesigned Invoice Template<br>[+ Added] Mercado Pago Payment Gateway<br>[+ Added] Xendit Payment Gateway<br>[+ Added] User Guide / Introductory Walkthrough<br>[+ Added] Customer-to-Admin Chat Support<br>[+ Added] Dynamic Seller Landing Page", false],
        ["v3.1.0 — 19 Aug 2026", "<strong>What's New</strong><br>[+ Added] Advanced Home Builder with Website and App Preview<br>[+ Added] Custom Tab Bar and Header Styling for both App and Website<br>[+ Added] New Play Image Section in Home Builder<br>[+ Added] Multiple design options for Product, Category, and Brand Cards<br><br><strong>Improvements</strong><br>[+ Improved] UI across Admin, Seller, and other panels<br>[+ Improved] UI/UX across Website and Mobile Apps<br>[+ Improved] Overall system performance, stability, and internal system processes<br><br><strong>Bug Fixes</strong><br>[&#10003; Fixed] Various UI and functional bugs across the platform<br>[&#10003; Fixed] Minor issues and stability-related bugs across the system", false],
        ["v3.0.0 — 12 Aug 2026", "<strong>What's New</strong><br>[+ Added] Seller App for managing products, orders, inventory, and store operations on mobile<br><br><strong>Improvements</strong><br>[+ Improved] Overall system performance and platform stability", false],
        ["v2.0.0 — 05 Aug 2026", "<strong>What's New</strong><br>[+ Added] Customer Website with a modern, responsive shopping experience<br><br><strong>Improvements</strong><br>[+ Improved] Overall system performance and platform stability", false],
        ["v1.0.0 — Initial release", "First public release of Hypercommerce: a multi-vendor marketplace with admin &amp; seller panels, a Flutter customer app, multiple payment gateways (Stripe, Razorpay, Paystack &amp; Flutterwave), multi-currency markets, manual shipping with zones &amp; rates, automated shipping via Shiprocket, and a customer wallet.", false]
      ];
      html += '<h2 id="releases" data-toc="Release history" class="doc-h2head">' + svg("git-branch", 20) + "Release history</h2><div class=\"doc-checklist\">";
      rows.forEach(function (r) {
        html += '<div class="doc-check-row" style="align-items:center"><span class="ck"' + (r[2] ? ' style="background:var(--hc-accent-light);color:var(--hc-accent-fg)"' : "") + ">" + svg(r[2] ? "zap" : "check", 13) + "</span>" +
          '<span class="cb"><b>' + r[0] + "</b><br><span style=\"color:var(--tk-fg-4)\">" + r[1] + "</span></span>" +
          (r[2] ? '<span class="doc-soon-tag" style="background:var(--hc-accent-light);color:var(--hc-accent-fg);border-color:var(--hc-accent-border);margin-left:auto">Latest</span>' : "") + "</div>";
      });
      html += "</div>";
    } else {
      html += '<div class="doc-callout info"><span class="co-ic">' + svg("info", 20) + '</span><div class="co-body"><div class="co-title">Part of the full guide</div><div class="co-text">In the live documentation this page contains the detailed walkthrough for <strong>' + node.label.toLowerCase() + "</strong>.</div></div></div>";
      html += '<h2 id="in-section" data-toc="In this section" class="doc-h2head">' + svg("list", 20) + "In this section</h2><div class=\"doc-outline\">";
      (info.outline || []).forEach(function (o, i) {
        html += '<div class="doc-outline-item"><span class="oi">' + svg("hash", 16) + "</span><span><span class=\"ot\">" + o[0] + '</span><div class="od">' + o[1] + "</div></span><span class=\"on\">" + String(i + 1).padStart(2, "0") + "</span></div>";
      });
      html += "</div>";
      if (info.images && info.images.length) {
        html += '<h2 id="screens" data-toc="Screenshots">' + svg("image", 20).replace("<svg", '<svg style="color:var(--hc-accent-fg)"') + "Screenshots</h2>";
        html += '<div class="doc-callout info"><span class="co-ic">' + svg("info", 20) + '</span><div class="co-body"><div class="co-title">Screenshots coming soon</div><div class="co-text">Step-by-step images for this section will be added here.</div></div></div>';
        info.images.forEach(function (label) { html += shotHTML("console.hypercommerce.app", label, "1440 × 760", label); });
      }
    }
    return html;
  }
  function buildSoon(node) {
    return '<div class="doc-soon"><span class="doc-soon-art">' + svg(SOON[node.id] || "store", 42) + "</span>" +
      '<div><span class="badge">' + svg("clock", 13) + "Coming Soon</span></div>" +
      "<h1>" + node.label + "</h1>" +
      "<p>The " + node.label + " is in active development. Setup documentation will land here when it ships.</p>" +
      '<div class="doc-roadmap"><h4>Development roadmap</h4>' +
      [["Design & architecture", "Complete", "done"], ["Core development", "In progress", "active"], ["Beta & documentation", "Q3 2026", "todo"], ["Public release", "Q4 2026", "todo"]].map(function (r) {
        return '<div class="doc-rm-row ' + r[2] + '"><span class="rm-ic">' + svg(r[2] === "done" ? "check" : r[2] === "active" ? "play" : "circle", 14) + "</span><span class=\"rm-t\">" + r[0] + '</span><span class="rm-d">' + r[1] + "</span></div>";
      }).join("") + "</div></div>";
  }

  /* ---------------- TOC + scrollspy ---------------- */
  var spyHandler = null;
  function buildTOC(pageEl) {
    var heads = $$("[data-toc]", pageEl);
    if (!heads.length) { tocList.parentElement.style.visibility = "hidden"; return; }
    tocList.parentElement.style.visibility = "visible";
    tocList.innerHTML = heads.map(function (h) {
      return '<a class="doc-toc-link' + (h.hasAttribute("data-toc-sub") ? " sub" : "") + '" data-spy="' + h.id + '" href="#' + h.id + '">' + h.getAttribute("data-toc") + "</a>";
    }).join("");
    $$(".doc-toc-link", tocList).forEach(function (a) {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        var el = document.getElementById(a.getAttribute("data-spy"));
        if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 88, behavior: "smooth" });
      });
    });
    if (spyHandler) window.removeEventListener("scroll", spyHandler);
    spyHandler = function () {
      var cur = heads[0].id, top = 110;
      for (var i = 0; i < heads.length; i++) {
        if (heads[i].getBoundingClientRect().top - top <= 0) cur = heads[i].id; else break;
      }
      $$(".doc-toc-link", tocList).forEach(function (a) { a.classList.toggle("active", a.getAttribute("data-spy") === cur); });
    };
    window.addEventListener("scroll", spyHandler, { passive: true });
    spyHandler();
  }

  /* ---------------- Image lightbox (fslightbox) ---------------- */
  function setupLightbox() {
    $$(".doc-shot img").forEach(function (img) {
      var parent = img.parentNode;
      if (parent && parent.tagName === "A" && parent.hasAttribute("data-fslightbox")) return;
      var page = img.closest(".doc-page");
      var group = page ? (page.getAttribute("data-page") || "docs") : "docs";
      var a = document.createElement("a");
      a.setAttribute("data-fslightbox", "gallery-" + group);
      a.setAttribute("href", img.getAttribute("src"));
      a.style.display = "block";
      parent.insertBefore(a, img);
      a.appendChild(img);
    });
    if (typeof window.refreshFsLightbox === "function") window.refreshFsLightbox();
  }

  /* ---------------- Routing ---------------- */
  var CUR_ID = "", CUR_TITLE = "";
  function navigate(id) {
    var node = byId(id);
    if (!node) return;
    CUR_ID = id; CUR_TITLE = node.label;
    // hide all pages
    $$(".doc-page", pagesHost).forEach(function (p) { p.hidden = true; });
    var staticEl = $('.doc-page[data-page="' + id + '"]', pagesHost);
    var shown;
    if (staticEl) {
      staticEl.hidden = false; shown = staticEl;
    } else if (SOON[id]) {
      var soonHost = $('.doc-page[data-page="__soon"]', pagesHost);
      soonHost.innerHTML = buildSoon(node); soonHost.hidden = false; shown = soonHost;
    } else {
      var genHost = $('.doc-page[data-page="__generic"]', pagesHost);
      genHost.innerHTML = buildGeneric(node); genHost.hidden = false; shown = genHost;
    }
    // breadcrumb
    translateTree(shown);
    crumbRoot.textContent = tr(node.group);
    crumbCur.textContent = tr(node.label);
    // page title (company name from config)
    document.title = tr(node.label) + " · " + ((window.HC_DOCS_CONFIG && window.HC_DOCS_CONFIG.brandName) || "Hypercommerce") + " — " + tr("Documentation");
    // active nav
    $$(".doc-nav-item", sidebarNav).forEach(function (n) { n.classList.toggle("active", n.getAttribute("data-nav") === id); });
    // prev / next
    var idx = indexOf(id);
    setPN(prevEl, idx > 0 ? FLAT[idx - 1] : null, "prev");
    setPN(nextEl, idx < FLAT.length - 1 ? FLAT[idx + 1] : null, "next");
    var isSoon = shown.getAttribute("data-page") === "__soon";
    var pn = $(".doc-prevnext"); if (pn) pn.style.display = isSoon ? "none" : "";
    var fbRow = $(".doc-feedback"); if (fbRow) fbRow.style.display = isSoon ? "none" : "";
    // toc
    buildTOC(shown);
    // image lightbox
    setupLightbox();
    // reset feedback widget
    if (fbRow) {
      var q = fbRow.querySelector("span"); if (q) q.style.display = "";
      var bt = fbRow.querySelector(".fb-btns"); if (bt) bt.style.display = "";
      var dn = fbRow.querySelector(".doc-fb-done"); if (dn) dn.style.display = "none";
    }
    // persist + reflect in URL + scroll + close overlays
    try { localStorage.setItem("hc-docs-page", id); } catch (e) {}
    try { if (location.hash.slice(1) !== id) location.hash = id; } catch (e) {}
    window.scrollTo(0, 0);
    closeMenu(); closePalette();
  }
  function setPN(el, node, dir) {
    if (!el) return;
    if (!node) { el.style.visibility = "hidden"; el.removeAttribute("data-go"); return; }
    el.style.visibility = "visible";
    el.setAttribute("data-go", node.id);
    el.innerHTML = (dir === "prev"
      ? '<span class="dir">' + svg("arrow-left", 14) + tr("Previous") + "</span>"
      : '<span class="dir">' + tr("Next") + svg("arrow-right", 14) + "</span>") +
      '<span class="ttl">' + tr(node.label) + "</span>";
  }

  /* ---------------- Command palette ---------------- */
  var palette, paletteInput, paletteList, paletteSel = 0, paletteResults = [];
  function openPalette() { palette.hidden = false; paletteInput.value = ""; renderPalette(""); paletteInput.focus(); }
  function closePalette() { if (palette) palette.hidden = true; }
  function renderPalette(q) {
    paletteResults = FLAT.filter(function (x) { return !q || x.label.toLowerCase().indexOf(q.toLowerCase()) >= 0 || x.group.toLowerCase().indexOf(q.toLowerCase()) >= 0 || tr(x.label).toLowerCase().indexOf(q.toLowerCase()) >= 0 || tr(x.group).toLowerCase().indexOf(q.toLowerCase()) >= 0; });
    paletteSel = 0;
    if (!paletteResults.length) { paletteList.innerHTML = '<div class="doc-cmd-empty">' + tr("No results for") + ' “' + q + '”.</div>'; return; }
    var html = "", lastGroup = null, idx = -1;
    paletteResults.forEach(function (r) {
      if (r.group !== lastGroup) { html += '<div class="doc-cmd-grp">' + tr(r.group) + "</div>"; lastGroup = r.group; }
      idx++;
      html += '<div class="doc-cmd-item' + (idx === 0 ? " active" : "") + '" data-go="' + r.id + '" data-i="' + idx + '">' +
        '<span class="ci">' + svg("arrow-right", 17) + '</span><span class="ct">' + tr(r.label) + "</span>" +
        (r.soon ? '<span class="cg">' + tr("Coming soon") + "</span>" : '<span class="ci">' + svg("arrow-right", 14) + "</span>") + "</div>";
    });
    paletteList.innerHTML = html;
  }
  function moveSel(d) {
    var items = $$(".doc-cmd-item", paletteList);
    if (!items.length) return;
    paletteSel = Math.max(0, Math.min(items.length - 1, paletteSel + d));
    items.forEach(function (it, i) { it.classList.toggle("active", i === paletteSel); });
    items[paletteSel].scrollIntoViewIfNeeded ? items[paletteSel].scrollIntoViewIfNeeded() : null;
  }

  /* ---------------- Mobile sidebar ---------------- */
  function openMenu() { sidebar.classList.add("open"); var b = $("#sbBackdrop"); if (b) b.hidden = false; }
  function closeMenu() { sidebar.classList.remove("open"); var b = $("#sbBackdrop"); if (b) b.hidden = true; }

  /* ---------------- "Was this helpful?" feedback ---------------- */
  function sendFeedback(value) {
    var cfg = window.HC_DOCS_CONFIG || {};
    if (!cfg.feedbackUrl) return;
    try {
      fetch(cfg.feedbackUrl, {
        method: "POST",
        mode: "no-cors",
        body: new URLSearchParams({ page: CUR_ID || "unknown", title: CUR_TITLE || CUR_ID || "unknown", feedback: value })
      });
    } catch (e) {}
  }

  /* ---------------- Copy buttons + tabs (delegated) ---------------- */
  function wireDelegates() {
    document.addEventListener("click", function (e) {
      // copy
      var copyBtn = e.target.closest(".doc-copy");
      if (copyBtn) {
        var pre = copyBtn.closest(".doc-code").querySelector("pre");
        var text = pre.innerText.replace(/\u00A0/g, " ");
        var done = function () { copyBtn.classList.add("copied"); copyBtn.innerHTML = svg("check", 13) + tr("Copied"); setTimeout(function () { copyBtn.classList.remove("copied"); copyBtn.innerHTML = svg("copy", 13) + tr("Copy"); }, 1600); };
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, done); else done();
        return;
      }
      // tabs
      var tab = e.target.closest(".doc-tab");
      if (tab) {
        var bar = tab.closest(".doc-tabs");
        $$(".doc-tab", bar).forEach(function (t) { t.classList.remove("active"); });
        tab.classList.add("active");
        var idx = $$(".doc-tab", bar).indexOf(tab);
        $$(".doc-tab-pane", bar).forEach(function (p, i) { p.hidden = i !== idx; });
        return;
      }
      // nav
      var nav = e.target.closest(".doc-nav-item");
      if (nav) { navigate(nav.getAttribute("data-nav")); return; }
      // inline links / cards with data-go
      var go = e.target.closest("[data-go]");
      if (go) { navigate(go.getAttribute("data-go")); return; }
      // feedback
      var fbBtn = e.target.closest(".doc-fb-btn");
      if (fbBtn) {
        var fb = fbBtn.closest(".doc-feedback");
        var value = fbBtn.getAttribute("data-feedback") || "No";
        sendFeedback(value);
        fb.querySelector("span").style.display = "none";
        fb.querySelector(".fb-btns").style.display = "none";
        fb.querySelector(".doc-fb-done").style.display = "inline";
        return;
      }
    });
  }

  /* ---------------- Site settings (config.js) ---------------- */
  function applyConfig() {
    var cfg = window.HC_DOCS_CONFIG || {};
    // version
    if (cfg.version) {
      var badge = $(".doc-ver-badge"); if (badge) badge.textContent = "v" + cfg.version;
      var hero = $("#heroVersion"); if (hero) hero.textContent = tr("Version") + " " + cfg.version;
    }
    // logo
    var logoLight = $(".doc-logo-img.on-light");
    if (logoLight && cfg.logo) { logoLight.setAttribute("src", cfg.logo); if (cfg.brandName) logoLight.setAttribute("alt", cfg.brandName); }
    var logoDark = $(".doc-logo-img.on-dark");
    if (logoDark && cfg.logoDark) { logoDark.setAttribute("src", cfg.logoDark); if (cfg.brandName) logoDark.setAttribute("alt", cfg.brandName); }
    // favicon
    if (cfg.favicon) { var fav = $('link[rel="icon"]'); if (!fav) { fav = document.createElement("link"); fav.setAttribute("rel", "icon"); document.head.appendChild(fav); } fav.setAttribute("href", cfg.favicon); }
    // brand footer
    var who = $(".doc-sb-foot .who");
    if (who) {
      var b = who.querySelector("b"); if (b && cfg.brandName) b.textContent = cfg.brandName;
      var sub = who.querySelector("span"); if (sub && cfg.brandSub) sub.textContent = cfg.brandSub;
    }
    // purchase button
    var buy = $(".doc-buy");
    if (buy) {
      if (cfg.purchaseUrl) buy.addEventListener("click", function () { window.open(cfg.purchaseUrl, "_blank", "noopener"); });
      else buy.style.display = "none";
    }
    // support links (header span + TOC anchor)
    $$(".doc-support-link").forEach(function (el) {
      if (!cfg.supportUrl) { el.style.display = "none"; return; }
      if (el.tagName === "A") { el.setAttribute("href", cfg.supportUrl); }
      else { el.style.cursor = "pointer"; el.addEventListener("click", function () { window.open(cfg.supportUrl, "_blank", "noopener"); }); }
    });
  }

  /* ---------------- Init ---------------- */
  function setupMobileControls() {
    var hdr = $(".doc-hdr"), sb = $(".doc-sb"), foot = $(".doc-sb-foot");
    if (!hdr || !sb) return;
    var tray = document.createElement("div");
    tray.className = "doc-sb-actions";
    if (foot) sb.insertBefore(tray, foot); else sb.appendChild(tray);
    var items = [].slice.call(hdr.querySelectorAll(".doc-buy, #langSwitch, .doc-support-link, .doc-hdr-link")).map(function (el) {
      return { el: el, parent: el.parentNode, next: el.nextSibling };
    });
    if (!items.length) return;
    var mq = window.matchMedia("(max-width: 1240px)");
    var state = null;
    function apply() {
      var small = mq.matches;
      if (small === state) return;
      state = small;
      if (small) {
        items.forEach(function (it) { tray.appendChild(it.el); });
      } else {
        items.forEach(function (it) {
          if (it.next && it.next.parentNode === it.parent) it.parent.insertBefore(it.el, it.next);
          else it.parent.appendChild(it.el);
        });
      }
    }
    apply();
    if (mq.addEventListener) mq.addEventListener("change", apply);
    else if (mq.addListener) mq.addListener(apply);
    window.addEventListener("resize", apply);
  }

  function init() {
    sidebar = $(".doc-sb");
    sidebarNav = $("#sidebarNav");
    pagesHost = $("#pages");
    crumbRoot = $("#crumbRoot");
    crumbCur = $("#crumbCur");
    tocList = $("#tocList");
    prevEl = $("#pnPrev");
    nextEl = $("#pnNext");
    palette = $("#palette");
    paletteInput = $("#paletteInput");
    paletteList = $("#paletteList");

    // theme
    var saved = "light";
    try { saved = localStorage.getItem("hc-docs-theme") || "light"; } catch (e) {}
    setTheme(saved);

    applyConfig();
    buildSwitcher();
    translateTree(document.body);
    buildSidebar();
    wireDelegates();
    setupMobileControls();

    // header buttons
    $("#themeToggle").addEventListener("click", function () { setTheme(currentTheme() === "dark" ? "light" : "dark"); });
    var ls = $("#langSwitch"); if (ls) ls.addEventListener("change", function () { setLang(ls.value); });
    var mb = $("#menuBtn"); if (mb) mb.addEventListener("click", openMenu);
    var bd = $("#sbBackdrop"); if (bd) bd.addEventListener("click", closeMenu);
    $$("[data-open-search]").forEach(function (b) { b.addEventListener("click", openPalette); });

    // palette events
    palette.addEventListener("mousedown", function (e) { if (e.target === palette) closePalette(); });
    paletteInput.addEventListener("input", function () { renderPalette(paletteInput.value); });
    paletteList.addEventListener("mouseover", function (e) { var it = e.target.closest(".doc-cmd-item"); if (it) { paletteSel = +it.getAttribute("data-i"); $$(".doc-cmd-item", paletteList).forEach(function (x, i) { x.classList.toggle("active", i === paletteSel); }); } });
    paletteList.addEventListener("click", function (e) { var it = e.target.closest(".doc-cmd-item"); if (it) navigate(it.getAttribute("data-go")); });
    document.addEventListener("keydown", function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); palette.hidden ? openPalette() : closePalette(); return; }
      if (palette.hidden) return;
      if (e.key === "Escape") closePalette();
      else if (e.key === "ArrowDown") { e.preventDefault(); moveSel(1); }
      else if (e.key === "ArrowUp") { e.preventDefault(); moveSel(-1); }
      else if (e.key === "Enter") { e.preventDefault(); var r = paletteResults[paletteSel]; if (r) navigate(r.id); }
    });

    // deep-linking: back/forward + shareable URLs via the hash
    window.addEventListener("hashchange", function () {
      var hid = decodeURIComponent((location.hash || "").slice(1));
      if (hid && hid !== CUR_ID && byId(hid)) navigate(hid);
    });

    // landing page — prefer the URL hash, then last-visited, then default
    var start = "introduction";
    var hid = decodeURIComponent((location.hash || "").slice(1));
    if (hid && byId(hid)) start = hid;
    else { try { var s = localStorage.getItem("hc-docs-page"); if (s && byId(s)) start = s; } catch (e) {} }
    navigate(start);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
