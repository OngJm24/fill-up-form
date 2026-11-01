// Admin dashboard. All user-supplied text is inserted with textContent, never innerHTML.
(() => {
  const dataEl = document.getElementById('submissions-data');
  const records = dataEl ? JSON.parse(dataEl.textContent) : [];
  const byId = new Map(records.map(r => [String(r.id), r]));

  const SECTIONS = [
    ['📝 Personal', [['Address', 'address'], ['Birthday', 'birthday'], ['Mobile', 'phone'], ['SIM network', 'sim_card']]],
    ['🌈 Favorites', [['Food', 'fav_food'], ['Drink', 'fav_drink'], ['Hobby', 'hobby'], ['Color', 'fav_color']]],
    ['🎈 Fun & quirky', [['Superpower', 'fun_question', true], ['Spirit animal', 'fav_animal'], ['Weird food combo', 'weird_food']]],
    ['🌟 Life & dreams', [['Biggest dream', 'biggest_dream', true], ['Role model', 'role_model'], ['In 10 years', 'future_self'], ['World change', 'world_change', true]]],
  ];
  const EMOJI = { happy: '😊', sad: '😢', excited: '🤩', angry: '😡' };

  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };

  // Search
  const search = document.getElementById('search');
  const rows = [...document.querySelectorAll('#rows tr')];
  const none = document.getElementById('no-results');
  search?.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    rows.forEach(tr => {
      const hit = !q || tr.dataset.search.includes(q);
      tr.hidden = !hit;
      if (hit) shown++;
    });
    if (none) none.hidden = shown > 0;
  });

  // Detail dialog
  const dialog = document.getElementById('detail');
  dialog.addEventListener('click', e => {
    if (e.target === dialog || e.target.closest('[data-close]')) dialog.close();
  });

  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-view]');
    if (btn) open(byId.get(btn.dataset.view));
  });

  async function open(r) {
    if (!r) return;
    document.getElementById('d-name').textContent = r.name;
    document.getElementById('d-email').textContent = r.email;

    const selfie = document.getElementById('d-selfie');
    selfie.hidden = !r.selfie_url;
    if (r.selfie_url) { selfie.src = r.selfie_url; selfie.alt = `Selfie of ${r.name}`; }

    const wrap = document.getElementById('d-sections');
    wrap.replaceChildren();
    for (const [title, fields] of SECTIONS) {
      wrap.append(el('h3', 'detail-h', title));
      const grid = el('dl', 'info-grid');
      for (const [label, key, wide] of fields) {
        const item = el('div', wide ? 'info wide' : 'info');
        item.append(el('dt', null, label), el('dd', null, r[key] || '—'));
        grid.append(item);
      }
      wrap.append(grid);
    }

    const moods = document.getElementById('d-moods');
    moods.replaceChildren(el('p', 'muted', 'Loading…'));
    dialog.showModal();

    try {
      const res = await fetch(`moods.php?id=${encodeURIComponent(r.id)}`, { headers: { Accept: 'application/json' } });
      if (res.status === 403) { location.href = 'login.php'; return; }
      const list = await res.json();
      moods.replaceChildren();
      if (!list.length) moods.append(el('p', 'muted', 'No mood photos shared.'));
      for (const m of list) {
        const card = el('figure', 'mood-card');
        const img = el('img');
        img.src = m.photo;
        img.alt = `${m.mood} mood photo`;
        img.loading = 'lazy';
        const cap = el('figcaption');
        cap.append(el('b', null, `${EMOJI[m.mood] || ''} ${m.mood}`), el('span', null, m.reason || 'No reason given'));
        card.append(img, cap);
        moods.append(card);
      }
    } catch {
      moods.replaceChildren(el('p', 'error-text', 'Couldn’t load mood photos.'));
    }
  }
})();
