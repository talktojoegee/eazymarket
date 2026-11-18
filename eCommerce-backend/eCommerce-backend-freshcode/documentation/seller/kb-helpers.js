/* =====================================================================
   Hypercommerce Knowledge Base — shared helpers
   Centralises the icon set and the small HTML builders the data files
   use to compose articles. Loaded BEFORE the data file and the engine.
   Exposes:  window.KB_ICON, window.kbIcon(name,size,cls), window.kb (builders)
   ===================================================================== */
(function () {
  "use strict";

  var KB_ICON = {
    /* chrome / generic */
    hash: '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
    check: '<path d="M5 12.5 10 17 19 7"/>',
    "arrow-right": '<path d="M5 12h14M14 6l6 6-6 6"/>',
    "arrow-left": '<path d="M19 12H5M10 6l-6 6 6 6"/>',
    "chevron-right": '<path d="M9 6l6 6-6 6"/>',
    circle: '<circle cx="12" cy="12" r="9"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/>',
    warn: '<path d="M10.3 4 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    bulb: '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 1 4 10.5c-.7.7-1 1.3-1 2.5H9c0-1.2-.3-1.8-1-2.5A6 6 0 0 1 12 3Z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    play: '<path d="M7 4l13 8-13 8V4Z"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
    moon: '<path d="M21 13a9 9 0 0 1-11-11 9 9 0 1 0 11 11Z"/>',
    "help-circle": '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .8-1 1.7M12 17h.01"/>',
    compass: '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
    image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    zap: '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
    "refresh-cw": '<path d="M21 12a9 9 0 1 1-2.6-6.4M21 4v4h-4"/>',
    login: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>',
    dashboard: '<rect x="3" y="3" width="8" height="9" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="12" width="8" height="9" rx="1.5"/><rect x="3" y="16" width="8" height="5" rx="1.5"/>',
    "map-pin": '<path d="M12 21s7-5.7 7-11a7 7 0 0 0-14 0c0 5.3 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
    tag: '<path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>',
    percent: '<path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8M12 8v13"/><path d="M12 8S10.5 3 8 3a2.5 2.5 0 0 0 0 5h4Zm0 0s1.5-5 4-5a2.5 2.5 0 0 1 0 5h-4Z"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
    lock: '<rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="M11 12 20 3M17 6l2 2M14 9l2 2"/>',
    box: '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    layout: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
    megaphone: '<path d="M3 11v2a1 1 0 0 0 1 1h2l9 5V5L6 10H4a1 1 0 0 0-1 1Z"/><path d="M18 8a4 4 0 0 1 0 8"/>',
    ticket: '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M15 6v12"/>',
    "file-text": '<path d="M6 3h8l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v4h4M8 13h8M8 17h6"/>',
    scale: '<path d="M12 3v18M7 7h10M7 7 4 14a3 3 0 0 0 6 0L7 7Zm10 0-3 7a3 3 0 0 0 6 0l-3-7ZM7 21h10"/>',
    "rotate-ccw": '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"/>',
    "shield-check": '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
    "credit-card": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
    wallet: '<path d="M3 7a2 2 0 0 1 2-2h12v3M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2Z"/><circle cx="17" cy="13" r="1.2"/>',
    coins: '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.66 2.7 3 6 3s6-1.34 6-3"/><path d="M15 12.5c2.5.3 6 1.3 6 3.5 0 1.66-2.7 3-6 3s-6-1.34-6-3"/>',
    "trending-up": '<path d="M3 17l6-6 4 4 8-8M21 7v5h-5"/>',
    "bar-chart": '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    truck: '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
    clipboard: '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1H9V4Z"/><path d="M9 10h6M9 14h4"/>',
    rotate: '<path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 4v4h-4"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 20v-4h4"/>',
    package: '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    store: '<path d="M4 9.5 5 4h14l1 5.5"/><path d="M4 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 4 0"/><path d="M5 11v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><path d="M9 20v-5h6v5"/>',
    sparkles: '<path d="m12 4 1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6L12 4Z"/><path d="M18 16l.7 1.8L20.5 18l-1.8.7L18 20.5l-.7-1.8L15.5 18l1.8-.7L18 16Z"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21 20c0-2.6-1.6-4.8-4-5.7"/>',
    server: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
    shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.7l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 13H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.3 6L4.2 6a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.3V4a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-1.2 2.9H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 3 4 6 4 9s-1.5 6-4 9c-2.5-3-4-6-4-9s1.5-6 4-9Z"/>',
    layers: '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5M3 17l9 5 9-5"/>',
    book: '<path d="M4 5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2V5Z"/><path d="M4 18a2 2 0 0 1 2-2h12"/>',
    rocket: '<path d="M14 4c4 0 6 2 6 6 0 5-7 9-7 9s-7-4-7-9c0-4 2-6 6-6h2Z"/><circle cx="13" cy="10" r="2"/><path d="M9 17l-3 3 3-1Z"/>',
    flame: '<path d="M12 3c1 4 5 5 5 9a5 5 0 0 1-10 0c0-1.5.6-2.5 1.3-3.4C9 10 9.5 9 9.3 7.5 11 8 11.7 5.5 12 3Z"/>',
    sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    inbox: '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5L5 5Z"/>',
    "user-cog": '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.6 2.7-6.5 6-6.5"/><circle cx="18" cy="16" r="2.5"/><path d="M18 12.5v1M18 18.5v1M21 16h-1M16 16h-1M20 14l-.7.7M16.7 17.3l-.7.7M20 18l-.7-.7M16.7 14.7l-.7-.7"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  };

  function kbIcon(name, size, cls) {
    size = size || 18;
    var c = cls ? ' class="' + cls + '"' : "";
    return '<svg' + c + ' width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' + (KB_ICON[name] || KB_ICON.circle) + '</svg>';
  }
  function accentIcon(name) {
    return kbIcon(name, 20).replace("<svg", '<svg style="color:var(--hc-accent-fg)"');
  }

  /* ---- article builders (return HTML strings) ---- */
  var kb = {
    eyebrow: function (icon, text) { return '<div class="doc-eyebrow">' + kbIcon(icon, 14) + text + "</div>"; },
    h1: function (t) { return '<h1 class="doc-h1">' + t + "</h1>"; },
    lead: function (t) { return '<p class="doc-lead">' + t + "</p>"; },
    p: function (t) { return "<p>" + t + "</p>"; },
    h2: function (id, toc, text, icon) { return '<h2 id="' + id + '" data-toc="' + toc + '">' + accentIcon(icon || "hash") + text + "</h2>"; },
    h3sub: function (id, toc, text) { return '<h3 id="' + id + '" data-toc="' + toc + '" data-toc-sub>' + text + "</h3>"; },
    ul: function (items) { return "<ul>" + items.map(function (i) { return "<li>" + i + "</li>"; }).join("") + "</ul>"; },
    checklist: function (items) {
      return '<div class="doc-checklist">' + items.map(function (i) {
        return '<div class="doc-check-row"><span class="ck">' + kbIcon("check", 13) + '</span><span class="cb">' + i + "</span></div>";
      }).join("") + "</div>";
    },
    callout: function (kind, title, text, icon) {
      var ic = icon || (kind === "tip" ? "bulb" : kind === "warn" ? "warn" : "info");
      return '<div class="doc-callout ' + kind + '"><span class="co-ic">' + kbIcon(ic, 20) + '</span><div class="co-body"><div class="co-title">' + title + '</div><div class="co-text">' + text + "</div></div></div>";
    },
    deflist: function (what, why) {
      return '<div class="kb-deflist">' +
        '<div class="kb-def"><div class="dt">' + kbIcon("info", 15) + 'What it is</div><p class="dd">' + what + "</p></div>" +
        '<div class="kb-def"><div class="dt">' + kbIcon("zap", 15) + 'Why it matters</div><p class="dd">' + why + "</p></div></div>";
    },
    steps: function (arr) {
      return '<div class="doc-steps">' + arr.map(function (s, i) {
        return '<div class="doc-step"><div class="doc-step-num">' + (i + 1) + '</div><div class="doc-step-body">' +
          (s.id ? '<h3 id="' + s.id + '" data-toc="' + (i + 1) + ' · ' + s.toc + '" data-toc-sub>' + s.title + "</h3>" : "<h4>" + s.title + "</h4>") +
          (s.body || "") + "</div></div>";
      }).join("") + "</div>";
    },
    flow: function (arr) {
      return '<div class="kb-flow">' + arr.map(function (s, i) {
        return '<div class="kb-flow-step' + (s.accent ? " is-accent" : "") + '"><span class="fn">' + (i + 1) + '</span><div class="ft">' + s.t + '</div><div class="fd">' + s.d + "</div></div>";
      }).join("") + "</div>";
    },
    statuses: function (arr) {
      return '<div class="kb-statuses">' + arr.map(function (s) {
        return '<span class="kb-stat ' + (s.tone || "") + '"><i></i>' + s.label + "</span>";
      }).join("") + "</div>";
    },
    faq: function (items) {
      return '<div class="kb-faq">' + items.map(function (it, i) {
        return '<div class="kb-faq-item' + (i === 0 ? " open" : "") + '">' +
          '<button class="kb-faq-q"><span class="qix">' + kbIcon("help-circle", 14) + "</span>" + it.q +
          '<span class="qchev">' + kbIcon("chevron-right", 16) + "</span></button>" +
          '<div class="kb-faq-a">' + it.a + "</div></div>";
      }).join("") + "</div>";
    },
    shot: function (url, label, cap, img) {
      var body = img
        ? '<img src="' + img + '" alt="' + label + '" style="display:block;width:100%;height:auto" onerror="this.style.display=\'none\';this.closest(\'.doc-shot-frame\').querySelector(\'.doc-shot-canvas\').style.display=\'flex\'" />' +
          '<div class="doc-shot-canvas" style="display:none">' + kbIcon("image", 42, "pi") +
          '<span class="pl">' + label + '</span><span class="pd">Add image: ' + img + '</span></div>'
        : '<div class="doc-shot-canvas">' + kbIcon("image", 42, "pi") +
          '<span class="pl">' + label + '</span><span class="pd">Screenshot placeholder</span></div>';
      return '<figure class="doc-shot"><div class="doc-shot-frame"><div class="doc-shot-bar">' +
        '<span class="dots"><i style="background:#F8285A"></i><i style="background:#F6B100"></i><i style="background:#17C653"></i></span>' +
        '<span class="doc-shot-url">' + url + '</span><span style="width:44px"></span></div>' +
        body + '</div>' +
        (cap ? '<figcaption class="doc-shot-cap"><b>Fig.</b> ' + cap + "</figcaption>" : "") + "</figure>";
    },
  };

  window.KB_ICON = KB_ICON;
  window.kbIcon = kbIcon;
  window.kb = kb;
})();
