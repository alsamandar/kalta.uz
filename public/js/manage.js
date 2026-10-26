/* Kalta.uz — wedding invitation pages for the hosts (create, edit, manage). Progressive enhancement only. */
(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const K = window.Kalta || {}; // app.js is deferred before this file

  /* ---------- Date and time: say it in words, so a wrong AM/PM is easy to spot ---------- */
  $$('input[data-when]').forEach((input) => {
    const hint = $(input.dataset.when);
    if (!hint) return;
    let parts = {};
    try { parts = JSON.parse(input.dataset.parts || '{}'); } catch (e) { /* no labels */ }
    const locale = ({ uz: 'uz-Latn', ru: 'ru', en: 'en' })[document.documentElement.lang] || undefined;

    const describe = () => {
      const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(input.value);
      hint.textContent = '';
      hint.classList.remove('is-warn');
      if (!match) return;
      const [, year, month, day, hour, minute] = match.map(Number);
      const date = new Date(year, month - 1, day, hour, minute);
      let when;
      try { when = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date); } catch (e) { when = input.value.slice(0, 10); }
      const part = hour < 5 || hour >= 22 ? 'night' : hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
      const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
      hint.textContent = `${when} · ${time}${parts[part] ? ` (${parts[part]})` : ''}`;
      hint.classList.toggle('is-warn', hour < 9 || hour >= 23); // weddings are rarely at dawn: probably AM/PM the wrong way round
    };

    input.addEventListener('input', describe);
    input.addEventListener('change', describe);
    describe();
  });

  /* ---------- Message preview on the edit page ---------- */
  const messageField = $('[data-message-field]');
  if (messageField) {
    const preview = $('[data-message-preview]');
    const fallback = messageField.dataset.fallback || '';
    const link = messageField.dataset.link || '';
    const sample = messageField.dataset.sample || '';
    const render = () => {
      const text = messageField.value.trim() ? messageField.value.trim().replace(/\{name\}/g, sample) : fallback;
      preview.textContent = `${text}\n${link}`;
    };
    messageField.addEventListener('input', render);
    render();
  }

  /* ---------- The host dashboard ---------- */
  const page = $('[data-manage]');
  if (!page) return;

  let words = {};
  try { words = JSON.parse(page.dataset.i18n || '{}'); } catch (e) { /* English fallbacks below */ }
  const say = (key, replacements = {}) => Object.entries(replacements).reduce((text, [name, value]) => text.replace(`:${name}`, value), words[key] || key);
  const invitationId = page.dataset.manage;

  const list = $('[data-guest-list]');
  const rows = list ? $$('[data-guest]', list) : [];

  const tools = $('[data-guest-tools]');
  const searchBox = $('[data-search]');
  const chips = $$('[data-filter]');
  const showing = $('[data-showing]');
  const noMatch = $('[data-no-match]');
  const filterKey = `kalta:invitation:${invitationId}:filter`;
  let filter = 'all';

  const plain = (text) => String(text || '').toLowerCase().replace(/[’‘ʻʼ'`´′]/g, '').replace(/\s+/g, ' ').trim();
  const matchesFilter = (row) => {
    const stage = row.dataset.stage;
    switch (filter) {
      case 'unsent': return stage === 'unsent';
      case 'waiting': return stage === 'sent' || stage === 'seen';
      case 'coming': return stage === 'coming';
      case 'declined': return stage === 'declined';
      default: return true;
    }
  };

  function refilter() {
    if (!tools) return;
    const query = plain(searchBox ? searchBox.value : '');
    let shown = 0;
    rows.forEach((row) => {
      const visible = matchesFilter(row) && (!query || row.dataset.search.includes(query));
      row.hidden = !visible;
      if (visible) shown++;
    });

    ['unsent', 'waiting', 'coming', 'declined'].forEach((name) => {
      const counter = $(`[data-count-for="${name}"]`);
      if (!counter) return;
      counter.textContent = rows.filter((row) => {
        const stage = row.dataset.stage;
        return name === 'waiting' ? stage === 'sent' || stage === 'seen' : stage === name;
      }).length;
    });

    const narrowed = filter !== 'all' || query !== '';
    if (showing) {
      showing.hidden = !narrowed;
      showing.textContent = narrowed ? say('showing', { shown, total: rows.length }) : '';
    }
    if (noMatch) noMatch.hidden = shown > 0 || rows.length === 0;
  }

  function setFilter(name) {
    filter = name;
    chips.forEach((chip) => chip.setAttribute('aria-pressed', chip.dataset.filter === name ? 'true' : 'false'));
    K.store && K.store.set(filterKey, name);
    refilter();
  }

  if (tools) {
    tools.hidden = false;
    chips.forEach((chip) => chip.addEventListener('click', () => setFilter(chip.dataset.filter)));
    if (searchBox) searchBox.addEventListener('input', refilter);
    const saved = K.store && K.store.get(filterKey, 'all');
    if (saved && chips.some((chip) => chip.dataset.filter === saved)) setFilter(saved); else refilter();

    // Coming back from an edit lands on one guest: make sure a filter does not hide them
    const reveal = () => {
      const target = location.hash.startsWith('#guest-') ? document.getElementById(location.hash.slice(1)) : null;
      if (target && target.hidden) { if (searchBox) searchBox.value = ''; setFilter('all'); target.scrollIntoView({ block: 'center' }); }
    };
    reveal();
    window.addEventListener('hashchange', reveal);
  }

  // The phone-number tip can be closed for good
  const tip = $('[data-tip]');
  if (tip && K.store) {
    if (K.store.get('kalta:tip-phone', false)) {
      tip.hidden = true;
    } else {
      const close = $('[data-tip-close]', tip);
      close.hidden = false;
      close.addEventListener('click', () => { tip.hidden = true; K.store.set('kalta:tip-phone', true); });
    }
  }

  /* ---------- "Sent" marks: tapping Telegram, WhatsApp, Copy or Share notes that this guest has been told ---------- */
  const chipTemplate = $('[data-stage-chips]');
  const chipSource = chipTemplate ? chipTemplate.content : null; // the markup of a <template> lives in .content
  const snack = $('[data-snack]');
  let snackTimer; let snackUndo = null;

  function showSnack(text, undo) {
    if (!snack) return;
    $('[data-snack-text]', snack).textContent = text;
    snackUndo = undo;
    snack.hidden = false;
    clearTimeout(snackTimer);
    snackTimer = setTimeout(() => { snack.hidden = true; snackUndo = null; }, 12000);
  }
  if (snack) {
    $('[data-snack-undo]', snack).addEventListener('click', () => {
      const undo = snackUndo;
      snack.hidden = true;
      snackUndo = null;
      if (undo) undo();
    });
  }

  function paint(row, state) {
    row.dataset.sent = state.sent ? '1' : '0';
    const toggle = $('[data-sent-toggle]', row);
    if (toggle) { if (state.sent) { toggle.name = 'undo'; toggle.value = '1'; } else { toggle.removeAttribute('name'); toggle.removeAttribute('value'); } }

    if (['unsent', 'sent', 'seen'].includes(state.stage) && ['unsent', 'sent', 'seen'].includes(row.dataset.stage)) {
      row.dataset.stage = state.stage;
      const avatar = $('.avatar', row);
      if (avatar) avatar.className = `avatar av-${state.stage}`;
      const template = chipSource && $(`[data-stage="${state.stage}"]`, chipSource);
      const chip = $('[data-status-chip]', row);
      if (template && chip) { chip.className = template.className; chip.replaceChildren(...Array.from(template.childNodes).map((node) => node.cloneNode(true))); }
    }

    const activity = $('[data-activity]', row);
    if (activity) {
      activity.replaceChildren();
      if (state.sent) activity.append(Object.assign(document.createElement('span'), { textContent: say('sentNow') }));
      activity.hidden = !state.sent;
    }
  }

  async function setSent(row, sent, via) {
    const previous = { sent: row.dataset.sent === '1', stage: row.dataset.stage };
    const inFlight = ['unsent', 'sent', 'seen'].includes(row.dataset.stage); // a guest who replied keeps their stage
    paint(row, { sent, stage: !inFlight ? row.dataset.stage : (sent ? (row.dataset.stage === 'unsent' ? 'sent' : row.dataset.stage) : 'unsent') });

    const body = new FormData();
    if (sent) body.set('via', via || 'manual'); else body.set('undo', '1');

    try {
      const response = await K.fetchWithRetry(() => fetch($('[data-sent-toggle]', row).getAttribute('formaction'), { method: 'POST', headers: K.jsonHeaders(), body, credentials: 'same-origin', keepalive: true }));
      if (!response.ok) throw new Error(String(response.status));
      paint(row, await response.json());
      setTimeout(refilter, 700);
      return true;
    } catch (e) {
      paint(row, previous);
      K.toast && K.toast(say('error'));
      refilter();
      return false;
    }
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-track]');
    const row = trigger && trigger.closest('[data-guest]');
    if (!row) return;
    if (row.dataset.sent === '1') return; // already marked: do not keep rewriting it (and no "undo" for a repeat)
    setSent(row, true, trigger.dataset.track).then((ok) => {
      if (ok) showSnack(say('marked', { name: row.dataset.name }), () => setSent(row, false));
    });
  });

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sent-toggle]');
    const row = toggle && toggle.closest('[data-guest]');
    if (!row || !K.fetchWithRetry) return;
    event.preventDefault();
    const menu = toggle.closest('details');
    if (menu) menu.removeAttribute('open');
    setSent(row, row.dataset.sent !== '1', 'manual');
  });

  // The phone's own share sheet (Telegram, WhatsApp, Messages, ...): only where the browser has one
  if (navigator.share) $$('[data-guest-share]').forEach((button) => { button.hidden = false; });
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-guest-share]');
    const row = button && button.closest('[data-guest]');
    if (!row) return;
    const menu = button.closest('details');
    if (menu) menu.removeAttribute('open');
    try {
      await navigator.share({ text: row.dataset.text, url: row.dataset.link });
    } catch (e) { return; } // closed without sending
    if (row.dataset.sent !== '1') setSent(row, true, 'share').then((ok) => { if (ok) showSnack(say('marked', { name: row.dataset.name }), () => setSent(row, false)); });
  });

  // Items of the "..." menus close it (copy buttons keep their own "Copied!" toast)
  document.addEventListener('click', (event) => {
    const item = event.target.closest('.menu-list button, .menu-list a');
    const menu = item && item.closest('details[data-menu]');
    if (menu && !item.matches('[data-sent-toggle], [data-guest-share]')) setTimeout(() => menu.removeAttribute('open'), 0);
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm-click]');
    if (button && !window.confirm(button.dataset.confirmClick)) event.preventDefault();
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) window.print();
  });

  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refilter(); });

  /* ---------- Replies that arrived since the hosts last looked ---------- */
  const seenKey = `kalta:invitation:${invitationId}:visit`;
  const lastVisit = K.store ? K.store.get(seenKey, null) : null;
  const banner = $('[data-new-banner]');
  if (lastVisit && banner) {
    const fresh = rows.filter((row) => Number(row.dataset.responded) * 1000 > lastVisit);
    fresh.forEach((row) => { const tag = $('[data-new-tag]', row); if (tag) tag.hidden = false; });
    if (fresh.length) {
      $('[data-new-text]', banner).textContent = say('newReplies', { count: fresh.length });
      banner.hidden = false;
      $('[data-show-new]', banner).addEventListener('click', () => {
        if (searchBox) searchBox.value = '';
        setFilter('all');
        rows.forEach((row) => { row.hidden = !fresh.includes(row); });
        if (showing) { showing.hidden = false; showing.textContent = say('showing', { shown: fresh.length, total: rows.length }); }
        fresh[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
      });
    }
  }
  const stamp = () => K.store && K.store.set(seenKey, Date.now());
  if (!lastVisit) stamp(); // the first visit has nothing "new"
  setTimeout(stamp, 20000); // looked at the page for a while: whatever is on it counts as seen
  window.addEventListener('pagehide', stamp);

  /* ---------- Add guests: tell how many lines were understood ---------- */
  const guestInput = $('[data-guest-input]');
  const guestCount = $('[data-guest-count]');
  if (guestInput && guestCount) {
    const tip = guestCount.textContent;
    guestInput.addEventListener('input', () => {
      const lines = guestInput.value.split(/\r?\n/).map((line) => line.replace(/^\s*(\d{1,3}\s*[.)]|[-•*–—·])\s+/, '').trim()).filter(Boolean);
      guestCount.textContent = lines.length ? say('recognised', { count: lines.length }) : tip;
    });
  }
})();
