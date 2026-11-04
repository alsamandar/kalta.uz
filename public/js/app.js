/* Kalta.uz — progressive enhancement. Every page works without this file. */
(() => {
  'use strict';

  const K = window.K || { i18n: {}, alternates: {}, locale: 'en' };
  const t = (key) => K.i18n[key] || key;
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';
  const store = {
    get(key, fallback) {
      try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : fallback; } catch (e) { return fallback; }
    },
    set(key, value) {
      try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* private mode */ }
    },
  };
  const jsonHeaders = () => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() });
  const el = (tag, props = {}, children = []) => {
    const node = Object.assign(document.createElement(tag), props);
    children.forEach((child) => node.append(child));
    return node;
  };

  /* ---------- Network helpers: phones lose connections, pages stay open for hours ---------- */
  let lastNetwork = Date.now();
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const newSubmissionId = () => (window.crypto && crypto.randomUUID
    ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = (Math.random() * 16) | 0; return (c === 'x' ? r : (r & 3) | 8).toString(16); }));

  /** After a pause the browser may still hold a dead connection (common on mobile data). A tiny request makes it notice. */
  async function warmUp() {
    if (Date.now() - lastNetwork < 20000) return;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4000);
    try { await fetch('/up.txt?' + Date.now(), { method: 'HEAD', cache: 'no-store', signal: controller.signal }); } catch (e) { /* the real request retries */ }
    clearTimeout(timer);
    lastNetwork = Date.now();
  }

  /** The CSRF token expires with the session; fetch a fresh one from the page itself. */
  async function refreshToken() {
    try {
      const html = await (await fetch(location.pathname + location.search, { headers: { Accept: 'text/html' }, credentials: 'same-origin', cache: 'no-store' })).text();
      const match = html.match(/name="csrf-token" content="([^"]+)"/);
      if (!match) return false;
      $('meta[name="csrf-token"]').content = match[1];
      $$('input[name="_token"]').forEach((input) => { input.value = match[1]; });
      return true;
    } catch (e) { return false; }
  }

  /**
   * fetch() that retries network failures (not HTTP errors) on a fresh connection, and once more after
   * refreshing an expired token. makeRequest must build a new request each time (new FormData, current token).
   * The server recognises repeats through the form's _submission id, so a retry never creates a duplicate.
   */
  async function fetchWithRetry(makeRequest, onRetry) {
    await warmUp();
    let refreshed = false;
    for (let attempt = 1; ; attempt++) {
      let response;
      try {
        response = await makeRequest();
        lastNetwork = Date.now();
      } catch (error) {
        if (attempt >= 3 || !(error instanceof TypeError)) throw error;
        if (onRetry) onRetry();
        await sleep(500 * attempt);
        continue;
      }
      if (response.status === 419 && !refreshed && (await refreshToken())) { refreshed = true; continue; }
      return response;
    }
  }

  /* ---------- Toast ---------- */
  const toastEl = $('[data-toast]');
  let toastTimer;
  const toast = (message) => {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2200);
  };

  /* ---------- Theme ---------- */
  $$('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => {
    const root = document.documentElement;
    const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('kalta-theme', root.dataset.theme); } catch (e) { /* ignore */ }
  }));

  /* ---------- Menus (<details>) ---------- */
  document.addEventListener('click', (event) => {
    $$('[data-menu][open]').forEach((menu) => { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    $$('[data-menu][open]').forEach((menu) => { menu.removeAttribute('open'); const summary = $('summary', menu); if (summary) summary.focus(); });
  });

  /* ---------- Anchors that should also focus a field ---------- */
  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-focus]');
    if (!link) return;
    const field = $(link.dataset.focus);
    if (field) setTimeout(() => field.focus({ preventScroll: true }), 350);
  });

  /* ---------- Copy ---------- */
  async function copyText(text) {
    try { await navigator.clipboard.writeText(text); return true; } catch (e) { /* fall back below */ }
    const area = el('textarea', { value: text, readOnly: true });
    area.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.append(area);
    area.select();
    area.setSelectionRange(0, text.length);
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { /* ignore */ }
    area.remove();
    return ok;
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy], [data-copy-field], [data-copy-text]');
    if (!button) return;

    let text = button.dataset.copyText || '';
    if (button.dataset.copy) {
      const source = $(button.dataset.copy);
      text = source ? (source.value !== undefined ? source.value : source.textContent) : '';
    }
    if (button.dataset.copyField) {
      const scope = button.closest('[data-copy-scope], .result');
      const source = scope && $(`[data-field="${button.dataset.copyField}"]`, scope);
      text = source ? source.value : '';
    }
    if (!text) return;

    const ok = await copyText(text.trim());
    toast(ok ? t('copied') : t('error'));
    if (!ok) return;

    const label = $('span', button);
    const original = label ? label.textContent : null;
    button.classList.add('is-done');
    if (label) label.textContent = t('copied');
    setTimeout(() => { button.classList.remove('is-done'); if (label) label.textContent = original; }, 1600);
  });

  /* ---------- Web Share ---------- */
  $$('[data-share]').forEach((button) => { if (navigator.share) button.hidden = false; });
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-share]');
    if (!button || !navigator.share) return;
    navigator.share({ title: button.dataset.shareTitle || document.title, url: button.dataset.shareUrl || location.href }).catch(() => {});
  });

  /* ---------- QR downloads ---------- */
  function downloadBlob(blob, name) {
    const link = el('a', { href: URL.createObjectURL(blob), download: name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1500);
  }

  async function svgToPng(svg, size = 1024) {
    const clone = svg.cloneNode(true);
    clone.setAttribute('width', size);
    clone.setAttribute('height', size);
    clone.setAttribute('shape-rendering', 'crispEdges');
    if (!clone.getAttribute('xmlns')) clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' }));
    const image = new Image();
    await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; image.src = url; });
    const canvas = el('canvas', { width: size, height: size });
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, size, size);
    ctx.drawImage(image, 0, 0, size, size);
    URL.revokeObjectURL(url);
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-qr-download]');
    if (!button) return;
    const scope = button.closest('[data-qr-scope], .result, .created-card');
    const svg = scope && $('.qr svg', scope);
    if (!svg) return;
    const name = (scope.dataset.qrName || 'kalta-qr');
    try {
      if (button.dataset.qrDownload === 'svg') {
        const clone = svg.cloneNode(true);
        if (!clone.getAttribute('xmlns')) clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', 1024);
        clone.setAttribute('height', 1024);
        downloadBlob(new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml' }), `${name}.svg`);
      } else {
        downloadBlob(await svgToPng(svg), `${name}.png`);
      }
    } catch (e) { toast(t('error')); }
  });

  /* ---------- Recent links (kept in this browser only) ---------- */
  const HISTORY_KEY = 'kalta:history';
  const historyBox = $('[data-history]');

  function remember(entry) {
    const list = store.get(HISTORY_KEY, []).filter((item) => item.code !== entry.code);
    list.unshift({ ...entry, at: Date.now() });
    store.set(HISTORY_KEY, list.slice(0, 15));
    renderHistory();
  }

  function renderHistory() {
    if (!historyBox) return;
    const list = store.get(HISTORY_KEY, []);
    historyBox.hidden = list.length === 0;
    const target = $('[data-history-list]', historyBox);
    target.replaceChildren(...list.map((item) => {
      const short = el('a', { className: 'h-short', href: item.url, target: '_blank', rel: 'noopener', textContent: item.url.replace(/^https?:\/\//, '') });
      const long = el('span', { className: 'h-long', textContent: item.long || '' });
      const copy = el('button', { type: 'button', className: 'btn btn-secondary btn-sm' }, [el('span', { textContent: t('copy') })]);
      copy.dataset.copyText = item.url;
      const remove = el('button', { type: 'button', className: 'icon-btn', title: t('delete'), textContent: '×' });
      remove.setAttribute('aria-label', `${t('delete')}: ${item.url}`);
      remove.addEventListener('click', () => {
        store.set(HISTORY_KEY, store.get(HISTORY_KEY, []).filter((entry) => entry.code !== item.code));
        renderHistory();
      });
      return el('li', {}, [el('div', { className: 'h-main' }, [short, long]), copy, remove]);
    }));
  }

  if (historyBox) {
    $('[data-history-clear]', historyBox).addEventListener('click', () => { store.set(HISTORY_KEY, []); renderHistory(); });
    renderHistory();
  }

  /* ---------- Link shortener ---------- */
  const shortener = $('[data-shortener]');
  if (shortener) {
    const input = $('input[name="url"]', shortener);
    const button = $('button[type="submit"]', shortener);
    const label = $('[data-label]', button);
    const errorBox = $('[data-error]', shortener);
    const resultBox = $('[data-result]');
    const template = $('#result-template');
    const idle = label.textContent;

    const showError = (message) => {
      errorBox.hidden = !message;
      errorBox.textContent = message || '';
      if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
    };
    const busy = (on) => {
      button.setAttribute('aria-busy', on ? 'true' : 'false');
      label.textContent = on ? t('shortening') : idle;
    };

    function renderResult(data) {
      const node = template.content.firstElementChild.cloneNode(true);
      $('[data-field="url"]', node).value = data.url;
      $('[data-field="open"]', node).href = data.url;
      $('[data-field="details"]', node).href = data.created_url;
      $('[data-field="qr"]', node).innerHTML = data.qr; // generated by our server from the short URL
      const share = $('[data-share]', node);
      share.dataset.shareUrl = data.url;
      if (navigator.share) share.hidden = false;
      resultBox.replaceChildren(node);
      node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    shortener.addEventListener('submit', async (event) => {
      event.preventDefault();
      showError('');
      busy(true);
      try {
        const response = await fetchWithRetry(
          () => fetch(shortener.action, { method: 'POST', headers: jsonHeaders(), body: new FormData(shortener), credentials: 'same-origin' }),
          () => toast(t('retrying'))
        );
        if (response.status === 419) {
          showError(t('expired'));
        } else if (response.status === 422) {
          const data = await response.json();
          showError(((Object.values(data.errors || {})[0]) || [])[0] || data.message || t('error'));
        } else if (response.ok) {
          const data = await response.json();
          renderResult(data);
          remember({ code: data.code, url: data.url, long: data.long_url });
          input.value = '';
          const sid = $('input[name="_submission"]', shortener);
          if (sid) sid.value = newSubmissionId(); // the next link is a new submission
        } else {
          showError(t('error'));
        }
      } catch (e) {
        showError(navigator.onLine ? t('stillOffline') : t('offline'));
      } finally {
        busy(false);
      }
    });
  }

  /* ---------- File upload ---------- */
  const uploader = $('[data-upload]');
  if (uploader) {
    const zone = $('.dropzone', uploader);
    const input = $('input[type="file"]', uploader);
    const fileBox = $('[data-file]', uploader);
    const submit = $('button[type="submit"]', uploader);
    const errorBox = $('[data-error]', uploader);
    const progress = $('.progress', uploader);
    const bar = $('span', progress);
    const max = Number(uploader.dataset.max) || 0;
    const readable = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
    const showError = (message) => { errorBox.hidden = !message; errorBox.textContent = message || ''; };
    submit.disabled = !input.files.length; // without JavaScript the button stays usable

    function chosen(file) {
      showError('');
      if (max && file.size > max) {
        showError(`${t('tooBig')} (${readable(file.size)} > ${readable(max)})`);
        input.value = '';
        fileBox.hidden = true;
        submit.disabled = true;
        return;
      }
      $('.name', fileBox).textContent = file.name;
      $('.size', fileBox).textContent = readable(file.size);
      fileBox.hidden = false;
      submit.disabled = false;
    }

    input.addEventListener('change', () => { if (input.files[0]) chosen(input.files[0]); });
    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, (event) => { event.preventDefault(); zone.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, (event) => { event.preventDefault(); zone.classList.remove('is-drag'); }));
    zone.addEventListener('drop', (event) => {
      const file = event.dataTransfer && event.dataTransfer.files[0];
      if (!file) return;
      const transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
      chosen(file);
    });

    uploader.addEventListener('submit', async (event) => {
      const file = input.files[0];
      if (!file) return; // let the browser report the missing file
      event.preventDefault();
      showError('');
      submit.setAttribute('aria-busy', 'true');
      progress.hidden = false;
      bar.style.width = '0';
      await warmUp();

      const fail = (message) => {
        submit.setAttribute('aria-busy', 'false');
        progress.hidden = true;
        bar.style.width = '0';
        showError(message);
      };

      // Up to two tries; the _submission id lets the server ignore a repeat of an upload that did arrive
      const attempt = (n) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploader.action);
        Object.entries(jsonHeaders()).forEach(([name, value]) => xhr.setRequestHeader(name, value));
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) bar.style.width = `${(e.loaded / e.total) * 100}%`; };
        xhr.onload = async () => {
          let data = {};
          try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
          if (xhr.status === 201) {
            remember({ code: data.code, url: data.url, long: file.name });
            location.href = data.created_url;
          } else if (xhr.status === 419 && n === 1 && (await refreshToken())) {
            attempt(2);
          } else if (xhr.status === 413) {
            fail(t('tooBig'));
          } else if (xhr.status === 419) {
            fail(t('expired'));
          } else {
            fail(((Object.values(data.errors || {})[0]) || [])[0] || t('error'));
          }
        };
        xhr.onerror = () => {
          if (n < 2) { toast(t('retrying')); bar.style.width = '0'; setTimeout(() => attempt(n + 1), 800); } else fail(t('stillOffline'));
        };
        xhr.send(new FormData(uploader));
      };
      attempt(1);
    });
  }

  /* ---------- QR generator ---------- */
  const qrForm = $('[data-qr-form]');
  if (qrForm) {
    const text = $('[name="text"]', qrForm);
    const holder = $('[data-qr-holder]');
    const empty = $('[data-qr-empty]');
    const actions = $('[data-qr-actions]');
    const box = $('.qr', holder);
    const errorBox = $('[data-error]', qrForm);
    let timer; let controller;

    const reset = () => { box.hidden = true; box.innerHTML = ''; empty.hidden = false; actions.hidden = true; };
    const generate = async () => {
      const value = text.value.trim();
      if (!value) { reset(); return; }
      if (controller) controller.abort();
      controller = new AbortController();
      try {
        const response = await fetch(qrForm.action, { method: 'POST', headers: jsonHeaders(), body: new FormData(qrForm), signal: controller.signal });
        if (response.ok) {
          box.innerHTML = (await response.json()).svg; // generated by our server
          box.hidden = false; empty.hidden = true; actions.hidden = false; errorBox.hidden = true;
        } else if (response.status === 422) {
          errorBox.textContent = ((Object.values((await response.json()).errors || {})[0]) || [])[0] || t('error');
          errorBox.hidden = false;
        } else if (response.status === 419) {
          errorBox.textContent = t('expired');
          errorBox.hidden = false;
        }
      } catch (e) { /* aborted or offline: keep the previous code */ }
    };

    text.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(generate, 280); });
    qrForm.addEventListener('submit', (event) => { event.preventDefault(); clearTimeout(timer); generate(); });
    if (text.value.trim() && box.innerHTML.trim() === '') generate();
  }

  /* ---------- Text snippets ---------- */
  const codeBlock = $('[data-highlight]');
  if (codeBlock && K.hljs && codeBlock.dataset.highlight) {
    const script = el('script', { src: K.hljs, async: true });
    script.onload = () => { try { window.hljs.highlightElement(codeBlock); } catch (e) { /* plain text is fine */ } };
    document.head.append(script);
  }
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-download-text]');
    if (!button) return;
    const source = $(button.dataset.downloadText);
    if (source) downloadBlob(new Blob([source.textContent], { type: 'text/plain;charset=utf-8' }), button.dataset.filename || 'kalta.txt');
  });

  /* ---------- Link-in-bio live preview ---------- */
  const bioForm = $('[data-bio-form]');
  if (bioForm) {
    const preview = $('[data-bio-preview]');
    const field = (name) => $(`[name="${name}"]`, bioForm);
    const title = $('[data-prev="title"]', preview);
    const description = $('[data-prev="description"]', preview);
    const avatar = $('[data-prev="avatar"]', preview);
    const placeholder = title.textContent;

    const initial = () => ((field('title').value.trim() || placeholder)[0] || '•').toUpperCase();
    const sync = () => {
      title.textContent = field('title').value.trim() || placeholder;
      description.textContent = field('description').value.trim();
      description.hidden = !description.textContent;
      if (!$('img', avatar)) avatar.textContent = initial();
      $$('[data-prev-link]', preview).forEach((row) => { row.hidden = !field(row.dataset.prevLink).value.trim(); });
    };
    bioForm.addEventListener('input', sync);
    sync();

    $$('input[name="style"]', bioForm).forEach((radio) => radio.addEventListener('change', () => { preview.dataset.bioTheme = radio.value; }));
    const file = field('avatar_icon');
    const thumb = $('[data-avatar-thumb]', bioForm);
    file.addEventListener('change', () => {
      if (!file.files[0]) return;
      const url = URL.createObjectURL(file.files[0]);
      thumb.replaceChildren(el('img', { src: url, alt: '' }));
      avatar.replaceChildren(el('img', { src: url, alt: '' }));
    });
  }

  /* ---------- Invitation page: opening envelope, reveal on scroll, scroll depth, countdown and RSVP form ---------- */
  const tune = $('[data-music]');
  const musicBtn = $('[data-music-btn]');
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;
  const setMusic = (on) => { if (musicBtn) musicBtn.setAttribute('aria-pressed', on ? 'true' : 'false'); };
  if (tune) tune.volume = 0.6;
  if (tune && musicBtn) {
    musicBtn.addEventListener('click', () => {
      if (tune.paused) { tune.volume = 0.6; tune.play().then(() => setMusic(true)).catch(() => {}); }
      else { tune.pause(); setMusic(false); }
    });
  }

  // Short sounds for the opening, made on the spot: the wax cracking, the card sliding out, a little bell
  const openingSounds = () => {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    try {
      const ac = new AC();
      const t = ac.currentTime;
      const out = ac.createGain();
      out.gain.value = 0.45;
      out.connect(ac.destination);
      const noise = ac.createBuffer(1, ac.sampleRate, ac.sampleRate);
      const data = noise.getChannelData(0);
      for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
      const hiss = (at, length, type, from, to, level) => {
        const src = ac.createBufferSource();
        const filter = ac.createBiquadFilter();
        const gain = ac.createGain();
        src.buffer = noise;
        filter.type = type;
        filter.frequency.setValueAtTime(from, t + at);
        filter.frequency.exponentialRampToValueAtTime(to, t + at + length);
        gain.gain.setValueAtTime(0.0001, t + at);
        gain.gain.exponentialRampToValueAtTime(level, t + at + Math.min(0.06, length / 3));
        gain.gain.exponentialRampToValueAtTime(0.0001, t + at + length);
        src.connect(filter).connect(gain).connect(out);
        src.start(t + at);
        src.stop(t + at + length + 0.05);
      };
      hiss(0, 0.09, 'bandpass', 2600, 1800, 0.9); // crack
      hiss(0.3, 0.5, 'bandpass', 900, 2200, 0.12); // flap
      hiss(0.9, 0.6, 'lowpass', 1200, 4200, 0.18); // card slides out
      [[1318.5, 0.2], [1975.5, 0.09], [2637, 0.05], [3951, 0.025]].forEach(([freq, level]) => {
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, t + 1.55);
        gain.gain.exponentialRampToValueAtTime(level, t + 1.56);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 3.6);
        osc.connect(gain).connect(out);
        osc.start(t + 1.55);
        osc.stop(t + 3.7);
      });
      setTimeout(() => ac.close(), 4500);
    } catch (e) { /* no sound is fine */ }
  };

  // Things slide, write and draw themselves in as they come into view
  const reveal = $$('.rv, .draw');
  let startReveal = () => {};
  if (reveal.length && 'IntersectionObserver' in window && motion) {
    document.documentElement.classList.add('js-rv', 'js-motion');
    $$('.draw').forEach((svg) => $$('path', svg).forEach((path, i) => {
      path.style.setProperty('--len', Math.ceil(path.getTotalLength() + 2));
      path.style.setProperty('--k', i);
    }));
    startReveal = () => {
      const io = new IntersectionObserver((entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      }), { threshold: 0.01, rootMargin: '0px 0px -5% 0px' });
      reveal.forEach((node) => io.observe(node));
    };
  }

  const seal = $('[data-seal]');
  if (seal) {
    const root = document.documentElement;
    const env = $('.env', seal);
    root.classList.add('seal-open');
    seal.addEventListener('pointermove', (e) => {
      env.style.setProperty('--tx', (e.clientX / innerWidth - 0.5).toFixed(3));
      env.style.setProperty('--ty', (e.clientY / innerHeight - 0.5).toFixed(3));
    });
    const open = () => {
      seal.classList.add('is-opening');
      if (motion) openingSounds();
      if (tune) {
        // starts inside the tap (phones allow sound only then), softly under the fanfare, then swells to full
        tune.volume = 0.3;
        tune.play().then(() => setMusic(true)).catch(() => {});
        setTimeout(() => {
          const swell = setInterval(() => {
            tune.volume = Math.min(0.6, tune.volume + 0.03);
            if (tune.volume >= 0.6) clearInterval(swell);
          }, 100);
        }, motion ? 600 : 0);
      }
      setTimeout(startReveal, motion ? 1900 : 0);
      setTimeout(() => { seal.classList.add('is-open'); root.classList.remove('seal-open'); }, motion ? 2250 : 0);
      setTimeout(() => seal.remove(), motion ? 3000 : 700);
    };
    $('.seal', seal).addEventListener('click', open);
  } else {
    startReveal();
  }

  // Depth while scrolling: each section learns how far it is from the middle of the screen (--p),
  // the gold thread how far down the page the guest has read (--sp)
  const slides = $$('.sl');
  const thread = $('.thread');
  if (slides.length && motion) {
    let queued = false;
    const depth = () => {
      queued = false;
      const vh = innerHeight;
      slides.forEach((s) => {
        const r = s.getBoundingClientRect();
        if (r.bottom < -vh || r.top > vh * 2) return;
        const p = Math.max(-1.5, Math.min(1.5, (r.top + r.height / 2 - vh / 2) / vh));
        s.style.setProperty('--p', p.toFixed(3));
      });
      if (thread) {
        const max = document.documentElement.scrollHeight - vh;
        thread.style.setProperty('--sp', max > 0 ? (scrollY / max).toFixed(4) : 0);
      }
    };
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(depth); } };
    addEventListener('scroll', queue, { passive: true });
    addEventListener('resize', queue);
    depth();
  }

  const countdown = $('[data-countdown]');
  if (countdown) {
    const target = new Date(countdown.dataset.countdown).getTime();
    const unit = (name) => $('[data-unit="' + name + '"]', countdown);
    const show = (node, value) => {
      if (!node || node.textContent === String(value)) return;
      node.textContent = value;
      node.classList.remove('tick');
      void node.offsetWidth; // restart the roll
      node.classList.add('tick');
    };
    const tick = () => {
      const left = Math.max(0, Math.floor((target - Date.now()) / 1000));
      show(unit('d'), Math.floor(left / 86400));
      show(unit('h'), Math.floor((left % 86400) / 3600));
      show(unit('m'), Math.floor((left % 3600) / 60));
      show(unit('s'), left % 60);
    };
    tick();
    setInterval(tick, 1000);
  }
  const partyGroup = $('[data-party]');
  if (partyGroup) {
    const sync = () => { const declined = $('input[name="status"][value="declined"]'); partyGroup.hidden = !!(declined && declined.checked); };
    $$('input[name="status"]').forEach((radio) => radio.addEventListener('change', sync));
    sync();
  }

  /* ---------- Photos: shrink in the browser before they travel over mobile data ---------- */
  const pendingImages = [];

  async function shrinkImage(file, maxEdge, quality = 0.86) {
    if (!/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type) && !/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)) return file;
    const url = URL.createObjectURL(file);
    try {
      const image = new Image();
      image.src = url;
      await image.decode(); // applies the EXIF rotation, exactly like <img> does
      const scale = Math.min(1, maxEdge / Math.max(image.naturalWidth, image.naturalHeight));
      const width = Math.max(1, Math.round(image.naturalWidth * scale));
      const height = Math.max(1, Math.round(image.naturalHeight * scale));
      const canvas = el('canvas', { width, height });
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, width, height);
      ctx.drawImage(image, 0, 0, width, height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
      if (!blob || (blob.size >= file.size && /jpe?g/i.test(file.type))) return file;
      return new File([blob], `${file.name.replace(/\.[^.]+$/, '')}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
    } catch (e) {
      return file; // too big to decode here, or not an image: the server checks it anyway
    } finally {
      URL.revokeObjectURL(url);
    }
  }

  $$('input[type="file"][data-shrink]').forEach((input) => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file || typeof DataTransfer === 'undefined') return;
      const job = shrinkImage(file, Number(input.dataset.shrink) || 1600)
        .then((smaller) => {
          if (smaller === file || !input.files[0] || input.files[0] !== file) return; // nothing gained, or another file was chosen meanwhile
          const transfer = new DataTransfer();
          transfer.items.add(smaller);
          input.files = transfer.files;
        })
        .catch(() => {})
        .finally(() => { const i = pendingImages.indexOf(job); if (i >= 0) pendingImages.splice(i, 1); });
      pendingImages.push(job);
    });
  });

  /* ---------- Posts: a small rich-text editor in place of the textarea (the server cleans whatever it gets) ---------- */
  const postBody = $('textarea[data-post-body]');
  if (postBody) {
    const form = postBody.form;
    const format = $('[data-post-format]', form);
    const draftKey = 'kalta-post-draft';
    const keepDraft = form.hasAttribute('data-post-draft');
    const escapeHtml = (text) => text.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const titleInput = form.elements.title;
    const authorInput = form.elements.author;
    const textToHtml = (text) => text.replace(/\r\n?/g, '\n').split(/\n{2,}/).map((p) => p.trim()).filter(Boolean)
      .map((p) => '<p>' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>').join('');

    const editor = el('div', { className: 'post-body pe-body', contentEditable: 'true' });
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    editor.setAttribute('aria-label', postBody.dataset.placeholder || '');
    editor.dataset.placeholder = postBody.dataset.placeholder || '';

    const draft = keepDraft ? store.get(draftKey, null) : null;
    const startHtml = format.value === 'html' ? postBody.value : textToHtml(postBody.value);
    editor.innerHTML = startHtml || (draft && draft.html) || '<p><br></p>';
    if (draft && !startHtml && !titleInput.value) {
      titleInput.value = draft.title || '';
      if (draft.author && !authorInput.value) authorInput.value = draft.author;
    }

    // toolbar: each button runs a command on the selection; mousedown keeps the selection in the editor
    const exec = (command, value = null) => { editor.focus(); document.execCommand(command, false, value); sync(); };
    const blockOf = () => {
      const node = getSelection().anchorNode;
      const block = node && (node.nodeType === 1 ? node : node.parentElement).closest('h3, h4, blockquote, li, p');
      return block && editor.contains(block) ? block.nodeName.toLowerCase() : '';
    };
    const toggleBlock = (tag) => exec('formatBlock', blockOf() === tag ? 'p' : tag);
    const addLink = () => {
      const url = window.prompt(K.i18n.linkPrompt || 'Link (https://…)', 'https://');
      if (url === null) return;
      if (!url.trim() || url.trim() === 'https://') exec('unlink');
      else exec('createLink', /^(https?:|mailto:|tel:)/i.test(url.trim()) ? url.trim() : 'https://' + url.trim());
    };
    const picker = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: true });

    const tools = [
      ['B', 'bold', () => exec('bold'), ''],
      ['I', 'italic', () => exec('italic'), 'i'],
      ['H', 'heading', () => toggleBlock('h3'), ''],
      ['h', 'subheading', () => toggleBlock('h4'), ''],
      ['“', 'quote', () => toggleBlock('blockquote'), 'q'],
      ['•', 'list', () => exec('insertUnorderedList'), ''],
      ['|'],
      [null, 'link', addLink, ''],
      [null, 'image', () => picker.click(), ''],
    ];
    const bar = el('div', { className: 'pe-tools' });
    bar.setAttribute('role', 'toolbar');
    const icon = { link: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>', image: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>' };
    tools.forEach(([label, name, run, cls]) => {
      if (label === '|') { bar.append(el('span', { className: 'sep' })); return; }
      const button = el('button', { type: 'button', className: cls || '' });
      if (label) button.textContent = label; else button.innerHTML = icon[name];
      button.title = (K.i18n.tools || {})[name] || name;
      button.setAttribute('aria-label', button.title);
      button.dataset.tool = name;
      button.addEventListener('mousedown', (e) => e.preventDefault());
      button.addEventListener('click', run);
      bar.append(button);
    });

    const pressed = () => {
      const block = blockOf();
      $$('button[data-tool]', bar).forEach((b) => {
        const on = { bold: () => document.queryCommandState('bold'), italic: () => document.queryCommandState('italic'),
          heading: () => block === 'h3', subheading: () => block === 'h4', quote: () => block === 'blockquote', list: () => block === 'li' }[b.dataset.tool];
        if (on) b.setAttribute('aria-pressed', on() ? 'true' : 'false');
      });
    };

    function sync() {
      const empty = !editor.textContent.trim() && !$('img', editor);
      editor.classList.toggle('is-empty', empty);
      postBody.value = empty ? '' : editor.innerHTML;
      format.value = 'html';
      if (keepDraft) saveDraft();
    }
    let draftTimer;
    const saveDraft = () => {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(() => store.set(draftKey, { title: titleInput.value, author: authorInput.value, html: postBody.value }), 400);
    };

    // pictures: shrunk here, uploaded at once, then placed where the caret was
    async function upload(file) {
      if (!/^image\//.test(file.type)) return;
      const holder = el('figure');
      const img = el('img', { className: 'is-loading', alt: '', src: URL.createObjectURL(file) });
      holder.append(img);
      const range = getSelection().rangeCount && editor.contains(getSelection().anchorNode) ? getSelection().getRangeAt(0) : null;
      const block = range && (range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement).closest('.pe-body > *');
      if (block) block.after(holder); else editor.append(holder);
      if (!holder.nextElementSibling) holder.after(el('p', {}, [el('br')]));
      toast(t('uploading'));
      const job = (async () => {
        const body = new FormData();
        body.append('image', await shrinkImage(file, 1600));
        const response = await fetchWithRetry(() => fetch(postBody.dataset.imageUpload, { method: 'POST', headers: jsonHeaders(), body, credentials: 'same-origin' }));
        const data = await response.json().catch(() => ({}));
        if (response.status !== 201) throw new Error((data.errors && Object.values(data.errors)[0][0]) || (response.status === 413 ? t('tooBig') : t('error')));
        URL.revokeObjectURL(img.src);
        img.src = data.src;
        img.classList.remove('is-loading');
        img.removeAttribute('class');
      })().catch((error) => { holder.remove(); toast(error.message || t('error')); })
        .finally(() => { sync(); const i = pendingImages.indexOf(job); if (i >= 0) pendingImages.splice(i, 1); });
      pendingImages.push(job);
    }
    picker.addEventListener('change', () => { Array.from(picker.files || []).forEach(upload); picker.value = ''; });

    // pasted text comes in as plain paragraphs: formatting from Word or other sites would only bring clutter
    editor.addEventListener('paste', (e) => {
      const files = Array.from((e.clipboardData && e.clipboardData.files) || []).filter((f) => /^image\//.test(f.type));
      e.preventDefault();
      if (files.length) { files.forEach(upload); return; }
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      if (text) document.execCommand('insertHTML', false, /\n/.test(text) ? textToHtml(text) : escapeHtml(text));
      sync();
    });
    editor.addEventListener('drop', (e) => {
      const files = Array.from((e.dataTransfer && e.dataTransfer.files) || []).filter((f) => /^image\//.test(f.type));
      if (files.length) { e.preventDefault(); files.forEach(upload); }
    });
    editor.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); addLink(); }
    });
    editor.addEventListener('input', sync);
    ['keyup', 'mouseup', 'focus'].forEach((type) => editor.addEventListener(type, pressed));
    document.addEventListener('selectionchange', () => { if (document.activeElement === editor) pressed(); });
    [titleInput, authorInput].forEach((input) => input && input.addEventListener('input', () => keepDraft && saveDraft()));
    // the title grows with its text; its Enter goes on to the story instead of a new line
    const growTitle = () => { titleInput.style.height = 'auto'; titleInput.style.height = titleInput.scrollHeight + 'px'; };
    titleInput.addEventListener('input', growTitle);
    titleInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); editor.focus(); } });
    // the toolbar sticks just below the site header, whatever its height on this screen
    const header = $('.site-header');
    const placeBar = () => bar.style.setProperty('--pe-top', (header ? header.offsetHeight : 0) + 'px');
    addEventListener('resize', placeBar);

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* old browsers */ }
    postBody.hidden = true;
    postBody.removeAttribute('required');
    postBody.after(bar, editor, picker);
    form.addEventListener('submit', sync, true); // before the resilient submit reads the form
    sync();
    growTitle();
    placeBar();
  }
  if ($('[data-post-published]')) store.set('kalta-post-draft', null);

  /* ---------- Long forms: stay on the page, retry on flaky connections, never lose what was typed ---------- */
  function setFormError(form, message) {
    let box = $('[data-form-error]', form);
    if (!box) {
      box = el('p', { className: 'field-error' });
      box.setAttribute('data-form-error', '');
      box.setAttribute('role', 'alert');
      const actions = $('.form-actions', form);
      if (actions) actions.parentNode.insertBefore(box, actions); else form.append(box);
    }
    box.textContent = message || '';
    box.hidden = !message;
  }

  function showFieldErrors(form, errors) {
    let first = null;
    const leftovers = [];
    Object.entries(errors).forEach(([name, messages]) => {
      const found = form.elements[name];
      const control = found instanceof RadioNodeList ? found[0] : found;
      if (!control || control.type === 'hidden') { leftovers.push(messages[0]); return; }
      control.setAttribute('aria-invalid', 'true');
      const note = el('p', { className: 'field-error', textContent: messages[0] });
      note.setAttribute('data-js-error', '');
      note.setAttribute('role', 'alert');
      (control.closest('.field') || control.parentElement).append(note);
      first = first || control;
    });
    if (leftovers.length) setFormError(form, leftovers.join(' '));
    if (first) { first.scrollIntoView({ block: 'center', behavior: 'smooth' }); first.focus({ preventScroll: true }); }
  }

  $$('form[data-resilient]').forEach((form) => {
    const button = $('button[type="submit"]', form);
    const label = button && ($('span', button) || button);
    const idle = label ? label.textContent : '';
    const busy = (on) => {
      if (!button) return;
      button.setAttribute('aria-busy', on ? 'true' : 'false');
      if (label && button.dataset.loading) label.textContent = on ? button.dataset.loading : idle;
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      $$('[data-js-error], .field-error', form).forEach((node) => { if (!node.hasAttribute('data-form-error')) node.remove(); });
      $$('[aria-invalid]', form).forEach((node) => node.removeAttribute('aria-invalid'));
      setFormError(form, '');
      busy(true);

      try {
        await Promise.all(pendingImages); // wait for a photo that is still being shrunk
        const response = await fetchWithRetry(
          () => fetch(form.action, { method: 'POST', headers: jsonHeaders(), body: new FormData(form), credentials: 'same-origin' }),
          () => toast(t('retrying'))
        );

        if (response.status === 201) {
          location.assign((await response.json()).redirect); // stay "busy" while the next page loads
          return;
        }
        if (response.status === 422) showFieldErrors(form, (await response.json()).errors || {});
        else if (response.status === 413) setFormError(form, t('tooBig'));
        else if (response.status === 419) setFormError(form, t('expired'));
        else setFormError(form, t('error'));
      } catch (e) {
        setFormError(form, t('stillOffline'));
      }
      busy(false);
    });
  });

  /* ---------- Forms: confirm destructive actions, show progress, avoid double submits ---------- */
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) event.preventDefault();
  }, true);

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    const button = $('button[type="submit"][data-loading]', form);
    if (!button) return;
    setTimeout(() => {
      button.setAttribute('aria-busy', 'true');
      const label = $('span', button) || button;
      label.textContent = button.dataset.loading;
    }, 0);
  });

  /* ---------- Suggest the visitor's language (never redirects on its own) ---------- */
  document.addEventListener('click', (event) => {
    if (event.target.closest('.menu-list a[hreflang], .site-footer a[hreflang]')) {
      try { localStorage.setItem('kalta-lang-dismiss', '1'); } catch (e) { /* ignore */ }
    }
  });
  try {
    const alternates = K.alternates || {};
    const preferred = (navigator.languages || [navigator.language || ''])
      .map((lang) => String(lang).slice(0, 2).toLowerCase())
      .find((lang) => alternates[lang]);
    if (preferred && preferred !== K.locale && !localStorage.getItem('kalta-lang-dismiss')) {
      const copy = {
        ru: ['Открыть сайт на русском?', 'Да', 'Нет'],
        en: ['View this site in English?', 'Yes', 'No'],
        uz: ['Saytni oʻzbek tilida ochamizmi?', 'Ha', 'Yoʻq'],
      }[preferred];
      const dismiss = () => { try { localStorage.setItem('kalta-lang-dismiss', '1'); } catch (e) { /* ignore */ } bar.remove(); };
      const yes = el('a', { className: 'btn btn-primary btn-sm', href: alternates[preferred], textContent: copy[1] });
      yes.addEventListener('click', dismiss);
      const no = el('button', { type: 'button', className: 'btn btn-ghost btn-sm', textContent: copy[2] });
      no.addEventListener('click', dismiss);
      const bar = el('div', { className: 'suggest', role: 'dialog' }, [el('span', { textContent: copy[0], lang: preferred }), el('div', { className: 'actions' }, [yes, no])]);
      bar.setAttribute('aria-label', copy[0]);
      document.body.append(bar);
    }
  } catch (e) { /* never break the page over a suggestion */ }

  /* ---------- Shared with the page-specific scripts (manage.js) ---------- */
  window.Kalta = { t, el, store, toast, copyText, fetchWithRetry, jsonHeaders };

  /* ---------- Clean up the service worker the old text-snippet page may have installed ---------- */
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((list) => list.forEach((registration) => registration.unregister())).catch(() => {});
  }
})();
