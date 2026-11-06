// Submission form (Phase 3b): autosave, word count, preview. No inline handlers (strict CSP).
const form = document.querySelector('form[data-submit-form]');
if (form) {
    const csrf = form.querySelector('input[name=_token]').value;
    const title = form.querySelector('[name=title]');
    const body = form.querySelector('[name=body]');
    const section = form.querySelector('[name=section_id]');
    const status = document.getElementById('autosave-status');
    const count = document.getElementById('word-count');
    let autosaveUrl = form.dataset.autosaveUrl;
    let timer = null;
    let dirty = false;

    const words = () => {
        const n = body.value.trim() ? body.value.trim().split(/\s+/u).length : 0;
        const lines = body.value.split('\n').filter((l) => l.trim()).length;
        count.textContent = `${n.toLocaleString('bn-IN')} শব্দ · ${lines.toLocaleString('bn-IN')} লাইন`;
    };

    const post = (url, payload) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
    }).then((r) => (r.ok ? r.json() : Promise.reject(r.status)));

    const payload = () => ({ title: title.value, body: body.value, section_id: section.value ? Number(section.value) : null });

    const save = () => {
        if (!dirty || !form.dataset.editable) return;
        dirty = false;
        status.textContent = 'রাখা হচ্ছে…';
        post(autosaveUrl, payload()).then((res) => {
            if (!res.saved) { status.textContent = ''; return; }
            status.textContent = `খসড়া রাখা হয়েছে ${res.at}`;
            if (res.id && form.dataset.new) {
                // First autosave of a new piece: from now on keep editing that draft.
                delete form.dataset.new;
                form.action = res.url;
                autosaveUrl = res.url + 'autosave/';
                history.replaceState(null, '', res.url);
            }
        }).catch(() => { dirty = true; status.textContent = 'রাখা যায়নি — ইন্টারনেট দেখুন, একটু পরে আবার চেষ্টা হবে।'; });
    };

    const changed = () => { dirty = true; words(); clearTimeout(timer); timer = setTimeout(save, 4000); };
    [title, body, section].forEach((el) => el.addEventListener('input', changed));
    window.addEventListener('beforeunload', (e) => { if (dirty) { save(); e.preventDefault(); } });
    words();

    // Preview in a <dialog>, rendered by the server exactly like the published page.
    const dialog = document.getElementById('preview');
    document.getElementById('preview-btn')?.addEventListener('click', () => {
        post(form.dataset.previewUrl, payload()).then((res) => {
            dialog.querySelector('[data-title]').textContent = title.value || 'শিরোনামহীন';
            const box = dialog.querySelector('[data-body]');
            box.className = res.poem ? 'poem-bn mt-6 text-center' : 'prose-bn mt-6';
            box.innerHTML = res.html; // sanitised + formatted on the server
            dialog.showModal();
        });
    });
    dialog?.querySelector('[data-close]')?.addEventListener('click', () => dialog.close());
}
