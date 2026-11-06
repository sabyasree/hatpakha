// Submission form (Phase 3b + D40): small formatting editor, autosave, word count, preview with cover.
// No inline handlers (strict CSP). Without JavaScript the plain textarea still works.
import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';

const form = document.querySelector('form[data-submit-form]');
if (form) {
    const csrf = form.querySelector('input[name=_token]').value;
    const title = form.querySelector('[name=title]');
    const textarea = form.querySelector('[name=body]');
    const formatInput = form.querySelector('[data-body-format]');
    const section = form.querySelector('[name=section_id]');
    const status = document.getElementById('autosave-status');
    const count = document.getElementById('word-count');
    const hint = form.querySelector('[data-editor-hint]');
    let autosaveUrl = form.dataset.autosaveUrl;
    let timer = null;
    let dirty = false;
    let editor = null;

    const isPoem = () => section.selectedOptions[0]?.dataset.format === 'poem';
    const escapeHtml = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // Poems: Enter = new line; Enter on an empty line = new stanza (same rule as before: blank line = stanza).
    const PoemLines = Extension.create({
        name: 'poemLines',
        addKeyboardShortcuts() {
            return {
                Enter: ({ editor: ed }) => {
                    if (!isPoem()) return false;
                    const { $from, empty } = ed.state.selection;
                    if (!empty || $from.parent.type.name !== 'paragraph') return false;
                    if ($from.nodeBefore?.type.name === 'hardBreak') {
                        return ed.chain().command(({ tr }) => { tr.delete($from.pos - 1, $from.pos); return true; }).splitBlock().run();
                    }
                    return ed.commands.setHardBreak();
                },
            };
        },
    });

    const shell = form.querySelector('[data-editor-shell]');
    const mount = form.querySelector('[data-editor]');
    const placeholder = form.querySelector('[data-editor-placeholder]');
    if (shell && mount) {
        let initial = '';
        try { initial = JSON.parse(document.getElementById('body-initial')?.textContent || '""'); } catch { initial = ''; }
        // Saved alignment is a class (ta-center…); the editor reads text-align.
        initial = initial.replace(/ class="ta-(left|center|right)"/g, ' style="text-align: $1"');
        if (!initial && textarea.value.trim()) {
            initial = textarea.value.trim().split(/\n\s*\n/).map((p) => `<p>${escapeHtml(p).replace(/\n/g, '<br>')}</p>`).join('');
        }
        editor = new Editor({
            element: mount,
            extensions: [
                StarterKit.configure({ heading: { levels: [3] }, link: false, code: false, codeBlock: false }),
                TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right'] }),
                Superscript,
                Subscript,
                PoemLines,
            ],
            content: initial,
            // Keep the line being typed clear of the sticky toolbar.
            editorProps: { scrollMargin: { top: 80, bottom: 40, left: 0, right: 0 }, scrollThreshold: { top: 80, bottom: 40, left: 0, right: 0 }, attributes: { class: 'ed-area', 'aria-labelledby': 'body-label', 'aria-multiline': 'true', role: 'textbox' } },
        });
        shell.hidden = false;
        textarea.hidden = true;
        textarea.required = false;
        formatInput.value = 'html';
        hint.hidden = false;

        const sync = () => {
            textarea.value = editor.isEmpty ? '' : editor.getHTML();
            placeholder.hidden = !editor.isEmpty;
        };
        const commands = {
            bold: () => editor.chain().focus().toggleBold().run(),
            italic: () => editor.chain().focus().toggleItalic().run(),
            underline: () => editor.chain().focus().toggleUnderline().run(),
            strike: () => editor.chain().focus().toggleStrike().run(),
            superscript: () => editor.chain().focus().unsetSubscript().toggleSuperscript().run(),
            subscript: () => editor.chain().focus().unsetSuperscript().toggleSubscript().run(),
            alignLeft: () => toggleAlign('left'),
            alignCenter: () => toggleAlign('center'),
            alignRight: () => toggleAlign('right'),
            clear: () => editor.chain().focus().unsetAllMarks().clearNodes().unsetTextAlign().run(),
            heading: () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
            blockquote: () => editor.chain().focus().toggleBlockquote().run(),
            bulletList: () => editor.chain().focus().toggleBulletList().run(),
            orderedList: () => editor.chain().focus().toggleOrderedList().run(),
            horizontalRule: () => editor.chain().focus().setHorizontalRule().run(),
            undo: () => editor.chain().focus().undo().run(),
            redo: () => editor.chain().focus().redo().run(),
        };
        // Clicking the active alignment again returns to the site's default (poems centred, prose left).
        const toggleAlign = (a) => (editor.isActive({ textAlign: a })
            ? editor.chain().focus().unsetTextAlign().run()
            : editor.chain().focus().setTextAlign(a).run());
        const buttons = form.querySelectorAll('[data-toolbar] [data-cmd]');
        buttons.forEach((b) => b.addEventListener('click', () => commands[b.dataset.cmd]?.()));
        const activeFor = {
            heading: () => editor.isActive('heading', { level: 3 }),
            alignLeft: () => editor.isActive({ textAlign: 'left' }),
            alignCenter: () => editor.isActive({ textAlign: 'center' }),
            alignRight: () => editor.isActive({ textAlign: 'right' }),
        };
        const refreshToolbar = () => buttons.forEach((b) => {
            const cmd = b.dataset.cmd;
            if (['undo', 'redo', 'horizontalRule', 'clear'].includes(cmd)) return;
            b.setAttribute('aria-pressed', (activeFor[cmd] ? activeFor[cmd]() : editor.isActive(cmd)) ? 'true' : 'false');
        });
        editor.on('update', () => { sync(); changed(); });
        editor.on('selectionUpdate', refreshToolbar);
        editor.on('transaction', refreshToolbar);
        sync();
    }

    const setHint = () => {
        if (!hint) return;
        hint.textContent = isPoem()
            ? 'কবিতা: Enter চাপলে নতুন লাইন; ফাঁকা লাইনে আবার Enter চাপলে নতুন স্তবক।'
            : 'গদ্য: Enter চাপলে নতুন অনুচ্ছেদ; Shift+Enter চাপলে একই অনুচ্ছেদে নতুন লাইন।';
    };

    const bodyText = () => (editor ? editor.getText({ blockSeparator: '\n' }) : textarea.value);
    const words = () => {
        const t = bodyText();
        const n = t.trim() ? t.trim().split(/\s+/u).length : 0;
        const lines = t.split('\n').filter((l) => l.trim()).length;
        count.textContent = `${n.toLocaleString('bn-IN')} শব্দ · ${lines.toLocaleString('bn-IN')} লাইন`;
    };

    const post = (url, payload) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
    }).then((r) => (r.ok ? r.json() : Promise.reject(r.status)));

    const payload = () => ({
        title: title.value,
        body: textarea.value,
        body_format: formatInput.value,
        section_id: section.value ? Number(section.value) : null,
    });

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

    function changed() { dirty = true; words(); clearTimeout(timer); timer = setTimeout(save, 4000); }
    [title, textarea, section].forEach((el) => el.addEventListener('input', changed));
    section.addEventListener('change', setHint);
    window.addEventListener('beforeunload', (e) => { if (dirty) { save(); e.preventDefault(); } });
    words();
    setHint();

    // Cover: show the chosen picture before saving; warn early about size (the server checks again).
    const cover = form.querySelector('[data-cover]');
    const coverPreview = form.querySelector('[data-cover-preview]');
    cover?.addEventListener('change', () => {
        const file = cover.files?.[0];
        coverPreview.classList.add('hidden');
        const name = form.querySelector('[data-cover-name]');
        if (name) name.textContent = file?.name || 'কোনো ছবি বাছা হয়নি';
        cover.setCustomValidity('');
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) {
            cover.setCustomValidity('ছবিটি ৮ MB-এর মধ্যে হতে হবে।');
            cover.reportValidity();
            return;
        }
        const reader = new FileReader();
        reader.onload = () => { coverPreview.src = reader.result; coverPreview.classList.remove('hidden'); };
        reader.readAsDataURL(file);
    });

    // Preview in a <dialog>: the body is rendered by the server exactly like the published page,
    // with the cover the writer has chosen (new file, or the one already saved).
    const dialog = document.getElementById('preview');
    document.getElementById('preview-btn')?.addEventListener('click', () => {
        post(form.dataset.previewUrl, payload()).then((res) => {
            dialog.querySelector('[data-title]').textContent = title.value || 'শিরোনামহীন';
            const img = dialog.querySelector('[data-preview-cover]');
            const removing = form.querySelector('[name=remove_cover]')?.checked;
            const current = form.querySelector('[data-current-cover]');
            const src = coverPreview && !coverPreview.classList.contains('hidden') ? coverPreview.src : (current && !removing ? current.src : '');
            img.hidden = !src;
            if (src) img.src = src;
            const box = dialog.querySelector('[data-body]');
            box.className = res.poem ? 'poem-bn mt-6 text-center' : 'prose-bn mt-6';
            box.innerHTML = res.html; // sanitised + formatted on the server
            dialog.showModal();
        });
    });
    dialog?.querySelector('[data-close]')?.addEventListener('click', () => dialog.close());
}
