// Multi-step profile form: navigation, validation (mirrors src/form.php), camera, uploads and submit.
(() => {
  const form = document.getElementById('profile-form');
  const steps = [...form.querySelectorAll('.step')];
  const total = steps.length;
  const stepLinks = [...document.querySelectorAll('.step-link')];
  const backBtn = document.getElementById('back-btn');
  const nextBtn = document.getElementById('next-btn');
  const submitBtn = document.getElementById('submit-btn');
  const navHint = document.getElementById('nav-hint');
  const alertBox = document.getElementById('form-alert');
  const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const MAX_UPLOAD = 5 * 1024 * 1024;

  let current = 1;
  let furthest = 1;

  /* ---------------- Validation ---------------- */

  const chars = s => [...s].length;
  const clean = (s, multiline) => multiline
    ? s.replace(/\r\n?/g, '\n').replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim()
    : s.replace(/\s+/g, ' ').trim();

  const normalizePhone = raw => {
    const m = raw.replace(/[\s\-().]/g, '').match(/^(?:\+?63|0)(9\d{9})$/);
    return m ? '0' + m[1] : null;
  };

  function messageFor(wrapper) {
    const key = wrapper.dataset.field;

    if (key === 'sim_card') {
      return form.querySelector('input[name="sim_card"]:checked') ? '' : 'Please pick your network.';
    }
    if (key === 'selfie') {
      return document.getElementById('selfie_base64').value ? '' : 'Please take or upload a selfie.';
    }
    if (key === 'consent') {
      return document.getElementById('consent').checked ? '' : 'Please tick the box to continue.';
    }
    if (key.startsWith('mood_photo_') || key.startsWith('mood_reason_')) {
      const card = wrapper.closest('.mood');
      const file = card.querySelector('input[type="file"]').files[0];
      const reason = clean(card.querySelector('textarea').value, true);
      if (key.startsWith('mood_photo_')) {
        if (!file) return reason ? 'Add a photo for this mood — or clear the reason.' : '';
        if (!IMAGE_TYPES.includes(file.type)) return 'Only JPG, PNG or WebP photos are allowed.';
        if (file.size > MAX_UPLOAD) return 'Photos must be 5 MB or smaller.';
        return '';
      }
      if (chars(reason) > 500) return 'Please keep it under 500 characters.';
      return file && !reason ? 'Tell us why — or remove the photo.' : '';
    }

    const el = wrapper.querySelector('input, textarea');
    const value = clean(el.value, el.tagName === 'TEXTAREA');
    const min = Number(el.dataset.min || 0);
    const max = Number(el.maxLength > 0 ? el.maxLength : 0);

    if (!value) return 'This field is required.';
    if (min && el.type !== 'tel' && chars(value) < min) return `Please enter at least ${min} characters.`;
    if (max && chars(value) > max) return `Please keep it under ${max} characters.`;
    if (el.dataset.pattern && !new RegExp(el.dataset.pattern, 'u').test(value)) return el.dataset.patternMsg;

    switch (el.type) {
      case 'email':
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value) && el.checkValidity() ? '' : 'Please enter a valid email address.';
      case 'tel':
        return normalizePhone(value) ? '' : 'Enter a PH mobile number like 0917 123 4567.';
      case 'date': {
        const d = new Date(value + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return 'Please enter a valid date.';
        if (d > new Date()) return 'Your birthday can’t be in the future.';
        if (value > el.max || value < el.min) return 'Please enter your real birthday.';
        return '';
      }
    }
    return '';
  }

  function setError(wrapper, msg, { quiet = false } = {}) {
    const key = wrapper.dataset.field;
    const errEl = document.getElementById(`${key}-error`);
    wrapper.classList.toggle('invalid', !!msg);
    wrapper.classList.toggle('valid', !msg && !quiet && hasValue(wrapper));
    wrapper.querySelectorAll('input:not([type="radio"]), textarea').forEach(i => i.setAttribute('aria-invalid', msg ? 'true' : 'false'));
    if (errEl) errEl.textContent = msg || '';
  }

  function hasValue(wrapper) {
    const el = wrapper.querySelector('input:not([type="file"]):not([type="radio"]):not([type="checkbox"]), textarea');
    return el ? el.value.trim() !== '' : true;
  }

  const wrappersIn = section => [...section.querySelectorAll('[data-field]')];

  function validateStep(n, { focus = true } = {}) {
    let first = null;
    for (const w of wrappersIn(steps[n - 1])) {
      const msg = messageFor(w);
      setError(w, msg);
      if (msg && !first) first = w;
    }
    if (first && focus) {
      first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const target = first.querySelector('input:not([type="hidden"]):not(.sr-only), textarea, button') || first.querySelector('input');
      target?.focus({ preventScroll: true });
    }
    return !first;
  }

  // Live feedback: validate after the user leaves a field, then on every change.
  form.addEventListener('focusout', e => {
    const w = e.target.closest('[data-field]');
    if (w && !w.contains(e.relatedTarget) && (e.target.value || w.classList.contains('invalid'))) {
      w.dataset.touched = '1';
      setError(w, messageFor(w));
    }
  });
  form.addEventListener('input', e => {
    const w = e.target.closest('[data-field]');
    if (!w) return;
    updateCounter(e.target);
    if (w.dataset.touched || w.classList.contains('invalid')) setError(w, messageFor(w));
    // A mood reason and photo depend on each other.
    const card = e.target.closest('.mood');
    if (card) card.querySelectorAll('[data-field]').forEach(x => x !== w && x.classList.contains('invalid') && setError(x, messageFor(x)));
  });
  form.addEventListener('change', e => {
    const w = e.target.closest('[data-field]');
    if (w && (e.target.type === 'radio' || e.target.type === 'checkbox' || e.target.type === 'date')) setError(w, messageFor(w));
  });

  function updateCounter(el) {
    const counter = el.closest('.field')?.querySelector('.count');
    if (counter) counter.textContent = `${chars(el.value)}/${el.maxLength}`;
  }

  // Format phone numbers as the user types: 0917 123 4567
  const phone = document.getElementById('phone');
  phone?.addEventListener('blur', () => {
    const n = normalizePhone(phone.value);
    if (n) phone.value = `${n.slice(0, 4)} ${n.slice(4, 7)} ${n.slice(7)}`;
  });

  /* ---------------- Navigation ---------------- */

  function show(n, { focusHeading = true } = {}) {
    if (current === 5 && n !== 5) stopCamera();
    current = n;
    furthest = Math.max(furthest, n);

    steps.forEach((s, i) => { s.hidden = i !== n - 1; });
    stepLinks.forEach((link, i) => {
      const step = i + 1;
      link.disabled = step > furthest;
      link.classList.toggle('is-current', step === n);
      link.classList.toggle('is-done', step < furthest || (step < n));
      link.setAttribute('aria-current', step === n ? 'step' : 'false');
    });

    backBtn.hidden = n === 1;
    nextBtn.hidden = n === total;
    submitBtn.hidden = n !== total;
    navHint.hidden = n >= 5;

    document.getElementById('progress-step').textContent = `Step ${n} of ${total}`;
    document.getElementById('progress-title').textContent = steps[n - 1].dataset.title;
    document.getElementById('progress-bar').style.width = `${(n / total) * 100}%`;

    if (n === total) buildReview();
    hideAlert();

    if (focusHeading) {
      document.getElementById('main').scrollIntoView({ behavior: 'smooth', block: 'start' });
      steps[n - 1].querySelector('h2').focus({ preventScroll: true });
    }
  }

  nextBtn.addEventListener('click', () => {
    if (validateStep(current)) show(current + 1);
  });
  backBtn.addEventListener('click', () => show(current - 1));
  stepLinks.forEach((link, i) => link.addEventListener('click', () => {
    const target = i + 1;
    // Going forward still requires every step in between to be valid.
    for (let s = current; s < target; s++) {
      if (!validateStep(s)) { if (s !== current) show(s); return; }
    }
    show(target);
  }));

  form.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox') {
      e.preventDefault();
      if (current < total) nextBtn.click();
    }
  });

  /* ---------------- Selfie ---------------- */

  const video = document.getElementById('video');
  const preview = document.getElementById('selfie-preview');
  const idle = document.getElementById('camera-idle');
  const cameraMsg = document.getElementById('camera-msg');
  const startBtn = document.getElementById('start-camera');
  const captureBtn = document.getElementById('capture-btn');
  const retakeBtn = document.getElementById('retake-btn');
  const selfieInput = document.getElementById('selfie_base64');
  const selfieFile = document.getElementById('selfie-file');
  const countdown = document.getElementById('countdown');
  const flash = document.getElementById('flash');
  const selfieWrap = form.querySelector('[data-field="selfie"]');
  let stream = null;

  async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraMsg.textContent = 'Your browser can’t open the camera here. Upload a photo instead.';
      return;
    }
    startBtn.disabled = true;
    cameraMsg.textContent = 'Waiting for camera permission…';
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
      video.srcObject = stream;
      await video.play();
      video.hidden = false;
      idle.hidden = true;
      preview.hidden = true;
      captureBtn.hidden = false;
      retakeBtn.hidden = true;
    } catch (err) {
      cameraMsg.textContent = err.name === 'NotAllowedError'
        ? 'Camera access was blocked. Allow it in your browser, or upload a photo instead.'
        : 'We couldn’t find a camera. Upload a photo instead.';
    } finally {
      startBtn.disabled = false;
    }
  }

  function stopCamera() {
    stream?.getTracks().forEach(t => t.stop());
    stream = null;
    video.hidden = true;
    captureBtn.hidden = true;
    if (!selfieInput.value) {
      idle.hidden = false;
      cameraMsg.textContent = 'Your camera is off.';
    }
  }

  // Draws a centered square crop, scaled to at most 720px, and returns a JPEG data URL.
  function toSquareJpeg(source, w, h, mirror) {
    const side = Math.min(w, h);
    const out = Math.min(720, side);
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = out;
    const ctx = canvas.getContext('2d');
    if (mirror) { ctx.translate(out, 0); ctx.scale(-1, 1); }
    ctx.drawImage(source, (w - side) / 2, (h - side) / 2, side, side, 0, 0, out, out);
    return canvas.toDataURL('image/jpeg', 0.88);
  }

  function setSelfie(dataUrl) {
    selfieInput.value = dataUrl;
    preview.src = dataUrl;
    preview.hidden = false;
    idle.hidden = true;
    retakeBtn.hidden = false;
    setError(selfieWrap, '');
    selfieWrap.classList.add('valid');
  }

  startBtn.addEventListener('click', startCamera);

  captureBtn.addEventListener('click', async () => {
    captureBtn.disabled = true;
    countdown.hidden = false;
    for (const n of [3, 2, 1]) {
      countdown.textContent = n;
      countdown.classList.remove('tick'); void countdown.offsetWidth; countdown.classList.add('tick');
      await new Promise(r => setTimeout(r, 750));
    }
    countdown.hidden = true;
    flash.classList.remove('go'); void flash.offsetWidth; flash.classList.add('go');
    setSelfie(toSquareJpeg(video, video.videoWidth, video.videoHeight, true));
    captureBtn.disabled = false;
    stopCamera();
  });

  retakeBtn.addEventListener('click', () => {
    selfieInput.value = '';
    preview.hidden = true;
    retakeBtn.hidden = true;
    selfieWrap.classList.remove('valid');
    startCamera();
  });

  selfieFile.addEventListener('change', () => {
    const file = selfieFile.files[0];
    selfieFile.value = '';
    if (!file) return;
    if (!IMAGE_TYPES.includes(file.type)) return setError(selfieWrap, 'Only JPG, PNG or WebP photos are allowed.');
    if (file.size > 15 * 1024 * 1024) return setError(selfieWrap, 'That photo is too large.');
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      stopCamera();
      setSelfie(toSquareJpeg(img, img.naturalWidth, img.naturalHeight, false));
      URL.revokeObjectURL(url);
    };
    img.onerror = () => setError(selfieWrap, 'We couldn’t read that photo. Try another one.');
    img.src = url;
  });

  /* ---------------- Mood photos ---------------- */

  document.querySelectorAll('.mood').forEach(card => {
    const input = card.querySelector('input[type="file"]');
    const drop = card.querySelector('.drop');
    const img = drop.querySelector('img');
    const removeBtn = card.querySelector('.mood-remove');
    const photoWrap = drop;
    let objectUrl = null;

    const render = () => {
      const file = input.files[0];
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      const msg = messageFor(photoWrap);
      setError(photoWrap, msg, { quiet: true });
      if (file && !msg) {
        objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        img.hidden = false;
        card.classList.add('has-photo');
        removeBtn.hidden = false;
      } else {
        img.hidden = true;
        card.classList.remove('has-photo');
        removeBtn.hidden = !file;
      }
      const reasonWrap = card.querySelector('.field');
      if (reasonWrap.classList.contains('invalid')) setError(reasonWrap, messageFor(reasonWrap));
    };

    input.addEventListener('change', render);
    removeBtn.addEventListener('click', () => { input.value = ''; render(); });

    ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('dragging')));
    drop.addEventListener('drop', e => {
      e.preventDefault();
      if (e.dataTransfer.files.length) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        input.files = dt.files;
        render();
      }
    });
  });

  /* ---------------- Review ---------------- */

  function buildReview() {
    const review = document.getElementById('review');
    review.replaceChildren();
    const data = new FormData(form);

    for (let n = 1; n < total; n++) {
      const section = steps[n - 1];
      const box = document.createElement('div');
      box.className = 'review-block';

      const head = document.createElement('div');
      head.className = 'review-head';
      const h = document.createElement('h3');
      h.textContent = section.dataset.title;
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'link-btn';
      edit.textContent = 'Edit';
      edit.setAttribute('aria-label', `Edit ${section.dataset.title}`);
      edit.addEventListener('click', () => show(n));
      head.append(h, edit);
      box.append(head);

      if (n === 5) {
        const pic = document.createElement('img');
        pic.className = 'review-selfie';
        pic.alt = 'Your selfie';
        pic.src = selfieInput.value;
        box.append(pic);
      } else if (n === 6) {
        const list = document.createElement('div');
        list.className = 'review-moods';
        section.querySelectorAll('.mood').forEach(card => {
          const file = card.querySelector('input[type="file"]').files[0];
          if (!file) return;
          const fig = document.createElement('figure');
          const im = document.createElement('img');
          im.src = card.querySelector('.drop img').src;
          im.alt = '';
          const cap = document.createElement('figcaption');
          cap.textContent = `${card.querySelector('.mood-emoji').textContent} ${card.querySelector('b').textContent}`;
          fig.append(im, cap);
          list.append(fig);
        });
        if (!list.children.length) {
          const p = document.createElement('p');
          p.className = 'muted';
          p.textContent = 'No mood photos — that’s okay!';
          box.append(p);
        } else box.append(list);
      } else {
        const dl = document.createElement('dl');
        section.querySelectorAll('[data-field]').forEach(w => {
          const key = w.dataset.field;
          const label = w.querySelector('label, legend').textContent;
          const dt = document.createElement('dt');
          dt.textContent = label;
          const dd = document.createElement('dd');
          dd.textContent = String(data.get(key) ?? '').trim() || '—';
          dl.append(dt, dd);
        });
        box.append(dl);
      }
      review.append(box);
    }
  }

  /* ---------------- Submit ---------------- */

  function showAlert(msg) {
    alertBox.textContent = msg;
    alertBox.hidden = false;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function hideAlert() { alertBox.hidden = true; }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    for (let n = 1; n <= total; n++) {
      if (!validateStep(n, { focus: false })) {
        show(n);
        validateStep(n);
        return;
      }
    }

    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    hideAlert();

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      let body = {};
      try { body = await res.json(); } catch { /* non-JSON error page */ }

      if (res.ok && body.ok) {
        stopCamera();
        form.hidden = true;
        document.querySelector('.progress').hidden = true;
        stepLinks.forEach(l => { l.classList.add('is-done'); l.classList.remove('is-current'); l.disabled = true; });
        document.getElementById('success-name').textContent = (body.name || '').split(' ')[0] || 'friend';
        if (!body.emailed) {
          document.getElementById('success-text').textContent = 'Your profile is complete and saved. (We couldn’t send the email right now, but your answers are safe.)';
        }
        const success = document.getElementById('success');
        success.hidden = false;
        success.focus();
        return;
      }

      if (body.errors) {
        let firstStep = total;
        for (const [key, msg] of Object.entries(body.errors)) {
          const w = form.querySelector(`[data-field="${CSS.escape(key)}"]`);
          if (!w) continue;
          setError(w, msg);
          firstStep = Math.min(firstStep, Number(w.closest('.step').dataset.step));
        }
        show(firstStep);
      }
      showAlert(body.message || 'Something went wrong. Please try again.');
    } catch {
      showAlert('Network error — check your connection and try again.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.classList.remove('loading');
    }
  });

  show(1, { focusHeading: false });
})();
