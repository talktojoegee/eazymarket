(function () {
  'use strict';

  function closeAllMenus(except) {
    document.querySelectorAll('[data-hui-menu].is-open').forEach(function (m) {
      if (m !== except) {
        m.classList.remove('is-open');
        var t = document.querySelector('[data-hui-menu-toggle="' + m.id + '"]');
        if (t) t.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
    var focusable = m.querySelector('[autofocus],input,button,select,textarea');
    if (focusable) setTimeout(function () { focusable.focus(); }, 40);
    m.dispatchEvent(new CustomEvent('hui:modal:open'));
  }

  function closeModal(el) {
    var m = el.closest ? el.closest('[data-hui-modal]') : el;
    if (!m) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
    m.dispatchEvent(new CustomEvent('hui:modal:close'));
  }

  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-hui-modal-open]');
    if (open) { e.preventDefault(); openModal(open.getAttribute('data-hui-modal-open')); return; }

    var close = e.target.closest('[data-hui-modal-close]');
    if (close) { e.preventDefault(); closeModal(close); return; }

    var backdrop = e.target.closest('[data-hui-modal]');
    if (backdrop && e.target === backdrop) { closeModal(backdrop); return; }

    var toggle = e.target.closest('[data-hui-menu-toggle]');
    if (toggle) {
      e.preventDefault();
      var menu = document.getElementById(toggle.getAttribute('data-hui-menu-toggle'));
      if (menu) {
        var willOpen = !menu.classList.contains('is-open');
        closeAllMenus(willOpen ? menu : null);
        menu.classList.toggle('is-open', willOpen);
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      }
      return;
    }

    if (!e.target.closest('[data-hui-menu]')) closeAllMenus(null);

    var tab = e.target.closest('[data-hui-tab]');
    if (tab) {
      e.preventDefault();
      var group = tab.getAttribute('data-hui-tab');
      var target = tab.getAttribute('data-hui-tab-target');
      document.querySelectorAll('[data-hui-tab="' + group + '"]').forEach(function (t) {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
      document.querySelectorAll('[data-hui-panel="' + group + '"]').forEach(function (p) {
        p.classList.toggle('hidden', p.getAttribute('data-hui-panel-id') !== target);
      });
      document.dispatchEvent(new CustomEvent('hui:tab:shown', { detail: { group: group, target: target } }));
      return;
    }

    var coll = e.target.closest('[data-hui-collapse]');
    if (coll) {
      e.preventDefault();
      var body = document.getElementById(coll.getAttribute('data-hui-collapse'));
      if (body) {
        var isHidden = body.classList.toggle('hidden');
        coll.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        var chev = coll.querySelector('[data-hui-chevron]');
        if (chev) chev.style.transform = isHidden ? '' : 'rotate(180deg)';
      }
      return;
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllMenus(null);
      document.querySelectorAll('[data-hui-modal]:not(.hidden)').forEach(closeModal);
    }
  });

  var tip;
  function showTip(host) {
    var text = host.getAttribute('data-hui-tip');
    if (!text) return;
    tip = document.createElement('div');
    tip.className = 'hui-tip';
    tip.textContent = text;
    document.body.appendChild(tip);
    var r = host.getBoundingClientRect();
    var left = r.left + r.width / 2 - tip.offsetWidth / 2;
    left = Math.max(6, Math.min(left, window.innerWidth - tip.offsetWidth - 6));
    tip.style.left = left + 'px';
    var above = r.top - tip.offsetHeight - 8;
    if (above < 4) {
        tip.style.top = (r.bottom + 8 + window.scrollY) + 'px';
        tip.classList.add('hui-tip-below');
    } else {
        tip.style.top = (above + window.scrollY) + 'px';
    }
    requestAnimationFrame(function () { tip.classList.add('is-visible'); });
  }
  function hideTip() { if (tip) { tip.remove(); tip = null; } }
  document.addEventListener('mouseover', function (e) {
    var host = e.target.closest('[data-hui-tip]');
    if (host && !tip) showTip(host);
  });
  document.addEventListener('mouseout', function (e) {
    if (e.target.closest('[data-hui-tip]')) hideTip();
  });

  window.HyperUI = { openModal: openModal, closeModal: function (id) { closeModal(document.getElementById(id)); }, closeMenus: function () { closeAllMenus(null); } };
})();
