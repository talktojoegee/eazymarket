/* =====================================================================
   Hypercommerce Knowledge Base — engine (vanilla JS, no framework)
   Reads its content from window.KB_NAV (set by a kb-data-*.js file) and
   window.KB_AUDIENCE. Articles can be hand-written HTML strings on each
   nav item (`html`), a generated preview (`lead`+`outline`), or a
   roadmap page (`soon`). Storage is namespaced per audience so the Admin
   and (separate) Seller deployments never share state or search results.
   Requires kb-helpers.js (window.kbIcon) loaded first.
   ===================================================================== */
(function () {
  "use strict";

  var AUDIENCE = (window.KB_AUDIENCE || "admin");
  var SKEY = "hc-kb-" + AUDIENCE;
  var svg = window.kbIcon;
  var CFG = window.HC_DOCS_CONFIG || {};
  var I18N = window.KB_I18N || {};

  // Languages that actually have content, ordered by config.langs.
  function availableLangs() {
    var have = Object.keys(I18N);
    var ordered = (CFG.langs || []).map(function (l) { return l.code; }).filter(function (c) { return have.indexOf(c) >= 0; });
    have.forEach(function (c) { if (ordered.indexOf(c) < 0) ordered.push(c); });
    return ordered.length ? ordered : ["en"];
  }
  function langLabel(code) { var m = (CFG.langs || []).filter(function (x) { return x.code === code; })[0]; return m ? m.label : code; }
  function dataFor(code) { return I18N[code] || I18N.en || { nav: (window.KB_NAV || []), ui: {} }; }

  var LANG = (function () {
    var avail = availableLangs();
    var q; try { q = new URLSearchParams(location.search).get("lang"); } catch (e) {}
    var saved; try { saved = localStorage.getItem(SKEY + "-lang"); } catch (e) {}
    var want = q || saved || CFG.defaultLang || "en";
    return avail.indexOf(want) >= 0 ? want : (avail.indexOf("en") >= 0 ? "en" : avail[0]);
  })();

  var NAV = [], UI = {}, FLAT = [];
  function loadLang(code) {
    var d = dataFor(code);
    NAV = d.nav || [];
    UI = d.ui || {};
    FLAT = [];
    NAV.forEach(function (g) { g.items.forEach(function (it) { it.group = g.group; FLAT.push({ id: it.id, label: it.label, icon: it.icon, soon: it.soon, group: g.group }); }); });
  }
  loadLang(LANG);
  function t(key, fallback) { return (UI && UI[key]) || fallback; }
  function byId(id) { for (var i = 0; i < FLAT.length; i++) if (FLAT[i].id === id) return FLAT[i]; return null; }
  function nodeOf(id) { for (var i = 0; i < NAV.length; i++) for (var j = 0; j < NAV[i].items.length; j++) if (NAV[i].items[j].id === id) return NAV[i].items[j]; return null; }
  function indexOf(id) { for (var i = 0; i < FLAT.length; i++) if (FLAT[i].id === id) return i; return -1; }

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
  var sidebarNav, pagesHost, crumbRoot, crumbCur, tocList, prevEl, nextEl, sidebar, relatedHost;

  /* ---------------- Theme ---------------- */
  function currentTheme() { return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light"; }
  function setTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    applyAccent(ACCENT, theme === "dark");
    try { localStorage.setItem(SKEY + "-theme", theme); } catch (e) {}
    var btn = $("#themeToggle");
    if (btn) btn.innerHTML = svg(theme === "dark" ? "sun" : "moon", 17);
  }

  /* ---------------- Sidebar nav ---------------- */
  function buildSidebar() {
    var html = "";
    NAV.forEach(function (g) {
      html += '<div class="doc-nav-grp">' + svg(g.icon, 13).replace("<svg", '<svg class="gi"') + g.group + "</div>";
      g.items.forEach(function (it) {
        html += '<div class="doc-nav-item' + (it.soon ? " is-soon" : "") + '" data-nav="' + it.id + '">' +
          svg(it.icon, 17).replace("<svg", '<svg class="doc-nav-ic"') +
          '<span class="lbl">' + it.label + "</span>" +
          (it.soon ? '<span class="doc-soon-tag">' + t("soon", "Soon") + "</span>" : "") + "</div>";
      });
    });
    sidebarNav.innerHTML = html;
  }

  /* ---------------- Builders for generated pages ---------------- */
  function buildGeneric(node) {
    var html = '<div class="doc-eyebrow">' + svg("hash", 14) + node.group + "</div>" +
      '<h1 class="doc-h1">' + node.label + "</h1>" +
      '<p class="doc-lead">' + (node.lead || "This article is part of the knowledge base.") + "</p>";
    html += '<div class="doc-callout info"><span class="co-ic">' + svg("info", 20) + '</span><div class="co-body"><div class="co-title">Article preview</div><div class="co-text">The full how-to for <strong>' + node.label + "</strong> will be written out here.</div></div></div>";
    html += '<h2 id="in-article" data-toc="In this article">' + svg("list", 20).replace("<svg", '<svg style="color:var(--hc-accent-fg)"') + "In this article</h2><div class=\"doc-outline\">";
    (node.outline || []).forEach(function (o, i) {
      html += '<div class="doc-outline-item"><span class="oi">' + svg("hash", 16) + '</span><span><span class="ot">' + o[0] + '</span><div class="od">' + o[1] + "</div></span><span class=\"on\">" + String(i + 1).padStart(2, "0") + "</span></div>";
    });
    return html + "</div>";
  }
  function buildSoon(node) {
    var rm = node.roadmap || [["On the roadmap", "Coming in a future update", "todo"]];
    return '<div class="doc-soon"><span class="doc-soon-art">' + svg("clock", 42) + "</span>" +
      '<div><span class="badge">' + svg("clock", 13) + t("roadmapBadge", "Roadmap") + "</span></div>" +
      "<h1>" + node.label + "</h1>" +
      "<p>" + (node.lead || "These features exist on the roadmap and will appear in a future update.") + "</p>" +
      '<div class="doc-roadmap"><h4>' + t("roadmapHeading", "On the roadmap") + "</h4>" +
      rm.map(function (r) { return '<div class="doc-rm-row ' + r[2] + '"><span class="rm-ic">' + svg("circle", 14) + "</span><span class=\"rm-t\">" + r[0] + '</span><span class="rm-d">' + r[1] + "</span></div>"; }).join("") +
      "</div></div>";
  }

  /* ---------------- Related articles ---------------- */
  function buildRelated(node) {
    if (!node || !node.related || !node.related.length) { relatedHost.innerHTML = ""; relatedHost.style.display = "none"; return; }
    var cards = node.related.map(function (rid) {
      var n = nodeOf(rid); if (!n) return "";
      var desc = n.lead ? n.lead.split(".")[0] + "." : "";
      return '<div class="kb-rel-card" data-go="' + rid + '">' +
        '<span class="kb-rel-ic">' + svg(n.icon || "book", 19) + "</span>" +
        '<span class="kb-rel-body"><span class="kb-rel-cat">' + n.group + '</span><span class="kb-rel-title">' + n.label + "</span>" +
        (desc ? '<span class="kb-rel-desc">' + desc + "</span>" : "") + "</span>" +
        '<span class="kb-rel-arrow">' + svg("arrow-right", 16) + "</span></div>";
    }).join("");
    relatedHost.innerHTML = '<div class="kb-related"><div class="kb-related-h">' + svg("compass", 16) + "Related articles</div><div class=\"kb-related-grid\">" + cards + "</div></div>";
    relatedHost.style.display = "";
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
      a.addEventListener("click", function (e) { e.preventDefault(); var el = document.getElementById(a.getAttribute("data-spy")); if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 88, behavior: "smooth" }); });
    });
    if (spyHandler) window.removeEventListener("scroll", spyHandler);
    spyHandler = function () {
      var cur = heads[0].id, top = 110;
      for (var i = 0; i < heads.length; i++) { if (heads[i].getBoundingClientRect().top - top <= 0) cur = heads[i].id; else break; }
      $$(".doc-toc-link", tocList).forEach(function (a) { a.classList.toggle("active", a.getAttribute("data-spy") === cur); });
    };
    window.addEventListener("scroll", spyHandler, { passive: true });
    spyHandler();
  }

  /* ---------------- Image lightbox ---------------- */
  function setupLightbox() {
    $$(".doc-shot img").forEach(function (img) {
      var parent = img.parentNode;
      if (parent && parent.tagName === "A" && parent.hasAttribute("data-fslightbox")) return;
      var page = img.closest(".doc-page");
      var group = page ? (page.getAttribute("data-page") || "kb") : "kb";
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
    var node = nodeOf(id);
    if (!node) return;
    CUR_ID = id; CUR_TITLE = node.label;
    $$(".doc-page", pagesHost).forEach(function (p) { p.hidden = true; });
    var host = $('.doc-page[data-page="__article"]', pagesHost);
    if (node.soon) host.innerHTML = buildSoon(node);
    else if (node.html) host.innerHTML = node.html;
    else host.innerHTML = buildGeneric(node);
    host.hidden = false;
    var shown = host;

    crumbRoot.textContent = node.group;
    crumbCur.textContent = node.label;
    // page title (company name from config)
    document.title = node.label + " · " + ((window.HC_DOCS_CONFIG && window.HC_DOCS_CONFIG.brandName) || "Hypercommerce") + " — " + t("siteLabel", (AUDIENCE === "seller" ? "Seller Knowledge Base" : "Admin Knowledge Base"));
    $$(".doc-nav-item", sidebarNav).forEach(function (n) { n.classList.toggle("active", n.getAttribute("data-nav") === id); });

    var idx = indexOf(id);
    setPN(prevEl, idx > 0 ? FLAT[idx - 1] : null, "prev");
    setPN(nextEl, idx < FLAT.length - 1 ? FLAT[idx + 1] : null, "next");

    var pn = $(".doc-prevnext"); if (pn) pn.style.display = node.soon ? "none" : "";
    var fbRow = $(".doc-feedback"); if (fbRow) fbRow.style.display = node.soon ? "none" : "";

    buildTOC(shown);
    buildRelated(node.soon ? null : node);
    setupLightbox();

    if (fbRow) {
      var q = fbRow.querySelector("span"); if (q) q.style.display = "";
      var bt = fbRow.querySelector(".fb-btns"); if (bt) bt.style.display = "";
      var dn = fbRow.querySelector(".doc-fb-done"); if (dn) dn.style.display = "none";
    }
    try { localStorage.setItem(SKEY + "-page", id); } catch (e) {}
    try { if (location.hash.slice(1) !== id) location.hash = id; } catch (e) {}
    window.scrollTo(0, 0);
    closeMenu(); closePalette();
  }
  function setPN(el, node, dir) {
    if (!el) return;
    if (!node) { el.style.visibility = "hidden"; el.removeAttribute("data-go"); return; }
    el.style.visibility = "visible";
    el.setAttribute("data-go", node.id);
    el.innerHTML = (dir === "prev" ? '<span class="dir">' + svg("arrow-left", 14) + t("prev", "Previous") + "</span>" : '<span class="dir">' + t("next", "Next") + svg("arrow-right", 14) + "</span>") +
      '<span class="ttl">' + node.label + "</span>";
  }

  /* ---------------- Command palette (audience-scoped) ---------------- */
  var palette, paletteInput, paletteList, paletteSel = 0, paletteResults = [];
  function openPalette() { palette.hidden = false; paletteInput.value = ""; renderPalette(""); paletteInput.focus(); }
  function closePalette() { if (palette) palette.hidden = true; }
  function renderPalette(q) {
    paletteResults = FLAT.filter(function (x) { return !q || x.label.toLowerCase().indexOf(q.toLowerCase()) >= 0 || x.group.toLowerCase().indexOf(q.toLowerCase()) >= 0; });
    paletteSel = 0;
    if (!paletteResults.length) { paletteList.innerHTML = '<div class="doc-cmd-empty">' + t("noResults", "No results for") + " “" + q + "”.</div>"; return; }
    var html = "", lastGroup = null, idx = -1;
    paletteResults.forEach(function (r) {
      if (r.group !== lastGroup) { html += '<div class="doc-cmd-grp">' + r.group + "</div>"; lastGroup = r.group; }
      idx++;
      html += '<div class="doc-cmd-item' + (idx === 0 ? " active" : "") + '" data-go="' + r.id + '" data-i="' + idx + '">' +
        '<span class="ci">' + svg(r.icon || "arrow-right", 17) + '</span><span class="ct">' + r.label + "</span>" +
        (r.soon ? '<span class="cg">Roadmap</span>' : '<span class="ci">' + svg("arrow-right", 14) + "</span>") + "</div>";
    });
    paletteList.innerHTML = html;
  }
  function moveSel(d) {
    var items = $$(".doc-cmd-item", paletteList);
    if (!items.length) return;
    paletteSel = Math.max(0, Math.min(items.length - 1, paletteSel + d));
    items.forEach(function (it, i) { it.classList.toggle("active", i === paletteSel); });
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

  /* ---------------- Delegated clicks ---------------- */
  function wireDelegates() {
    document.addEventListener("click", function (e) {
      var copyBtn = e.target.closest(".doc-copy");
      if (copyBtn) {
        var pre = copyBtn.closest(".doc-code").querySelector("pre");
        var text = pre.innerText.replace(/\u00A0/g, " ");
        var done = function () { copyBtn.classList.add("copied"); copyBtn.innerHTML = svg("check", 13) + "Copied"; setTimeout(function () { copyBtn.classList.remove("copied"); copyBtn.innerHTML = svg("copy", 13) + "Copy"; }, 1600); };
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, done); else done();
        return;
      }
      var faqQ = e.target.closest(".kb-faq-q");
      if (faqQ) { faqQ.parentElement.classList.toggle("open"); return; }
      var nav = e.target.closest(".doc-nav-item");
      if (nav) { navigate(nav.getAttribute("data-nav")); return; }
      var go = e.target.closest("[data-go]");
      if (go) { navigate(go.getAttribute("data-go")); return; }
      var fbBtn = e.target.closest(".doc-fb-btn");
      if (fbBtn) {
        var fb = fbBtn.closest(".doc-feedback");
        var value = fbBtn.textContent.trim().toLowerCase().indexOf("yes") >= 0 ? "Yes" : "No";
        sendFeedback(value);
        fb.querySelector("span").style.display = "none";
        fb.querySelector(".fb-btns").style.display = "none";
        fb.querySelector(".doc-fb-done").style.display = "inline";
        return;
      }
    });
  }

  /* ---------------- Localization ---------------- */
  function applyI18n() {
    $$("[data-i18n]").forEach(function (el) { var k = el.getAttribute("data-i18n"); if (UI[k]) el.textContent = UI[k]; });
    $$("[data-i18n-ph]").forEach(function (el) { var k = el.getAttribute("data-i18n-ph"); if (UI[k]) el.setAttribute("placeholder", UI[k]); });
  }
  function buildSwitcher() {
    var host = $("#langSwitch"); if (!host) return;
    var avail = availableLangs();
    if (avail.length < 2) { host.style.display = "none"; return; }
    host.style.display = "";
    host.innerHTML = avail.map(function (c) { return '<option value="' + c + '"' + (c === LANG ? " selected" : "") + ">" + langLabel(c) + "</option>"; }).join("");
  }
  function setLang(code) {
    if (!code) return;
    LANG = code;
    try { localStorage.setItem(SKEY + "-lang", code); } catch (e) {}
    try { var u = new URL(location.href); u.searchParams.set("lang", code); history.replaceState(null, "", u.toString()); } catch (e) {}
    loadLang(code);
    buildSidebar();
    buildSwitcher();
    applyI18n();
    var target = (CUR_ID && byId(CUR_ID)) ? CUR_ID : (FLAT[0] && FLAT[0].id);
    if (target) navigate(target);
  }

  /* ---------------- Site settings (config.js) ---------------- */
  function applyConfig() {
    var cfg = window.HC_DOCS_CONFIG || {};
    if (cfg.version) { var badge = $(".doc-ver-badge"); if (badge) badge.textContent = "v" + cfg.version; }
    // logo
    var logoLight = $(".doc-logo-img.on-light");
    if (logoLight && cfg.logo) { logoLight.setAttribute("src", cfg.logo); if (cfg.brandName) logoLight.setAttribute("alt", cfg.brandName); }
    var logoDark = $(".doc-logo-img.on-dark");
    if (logoDark && cfg.logoDark) { logoDark.setAttribute("src", cfg.logoDark); if (cfg.brandName) logoDark.setAttribute("alt", cfg.brandName); }
    // company name (sidebar footer)
    if (cfg.brandName) { var who = $(".doc-sb-foot .who b"); if (who) who.textContent = cfg.brandName; }
    // favicon
    if (cfg.favicon) { var fav = $('link[rel="icon"]'); if (!fav) { fav = document.createElement("link"); fav.setAttribute("rel", "icon"); document.head.appendChild(fav); } fav.setAttribute("href", cfg.favicon); }
    var buy = $(".doc-buy");
    if (buy) { if (cfg.purchaseUrl) buy.addEventListener("click", function () { window.open(cfg.purchaseUrl, "_blank", "noopener"); }); else buy.style.display = "none"; }
    $$(".doc-support-link").forEach(function (el) {
      if (!cfg.supportUrl) { el.style.display = "none"; return; }
      if (el.tagName === "A") { el.setAttribute("href", cfg.supportUrl); }
      else { el.style.cursor = "pointer"; el.addEventListener("click", function () { window.open(cfg.supportUrl, "_blank", "noopener"); }); }
    });
  }

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

  /* ---------------- Init ---------------- */
  function init() {
    sidebar = $(".doc-sb");
    sidebarNav = $("#sidebarNav");
    pagesHost = $("#pages");
    crumbRoot = $("#crumbRoot");
    crumbCur = $("#crumbCur");
    tocList = $("#tocList");
    prevEl = $("#pnPrev");
    nextEl = $("#pnNext");
    relatedHost = $("#kbRelated");
    palette = $("#palette");
    paletteInput = $("#paletteInput");
    paletteList = $("#paletteList");

    var saved = "light";
    try { saved = localStorage.getItem(SKEY + "-theme") || "light"; } catch (e) {}
    setTheme(saved);

    buildSidebar();
    buildSwitcher();
    applyI18n();
    applyConfig();
    wireDelegates();
    setupMobileControls();

    var ls = $("#langSwitch"); if (ls) ls.addEventListener("change", function () { setLang(this.value); });
    $("#themeToggle").addEventListener("click", function () { setTheme(currentTheme() === "dark" ? "light" : "dark"); });
    var mb = $("#menuBtn"); if (mb) mb.addEventListener("click", openMenu);
    var bd = $("#sbBackdrop"); if (bd) bd.addEventListener("click", closeMenu);
    $$("[data-open-search]").forEach(function (b) { b.addEventListener("click", openPalette); });

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

    // landing — prefer the URL hash, then last-visited, then first article
    var start = FLAT.length ? FLAT[0].id : null;
    var hid = decodeURIComponent((location.hash || "").slice(1));
    if (hid && byId(hid)) start = hid;
    else { try { var s = localStorage.getItem(SKEY + "-page"); if (s && byId(s)) start = s; } catch (e) {} }
    if (start) navigate(start);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
