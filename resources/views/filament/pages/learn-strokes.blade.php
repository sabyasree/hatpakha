{{-- D325: stroke order: look, approve, redraw, make a new draft, delete, add. Logo palette only. --}}
@php($n = fn ($v) => \App\Support\Bn::num((string) $v))
<x-filament-panels::page>
    @vite(['resources/js/learn.js'])
    <style>
        .hp-s-box { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; }
        .hp-s-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(12.5rem, 1fr)); }
        .hp-s-card { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: .7rem; text-align: center; background: #fff; color: #13212C; }
        .hp-s-card canvas { width: 160px; height: 160px; margin: 0 auto; display: block; }
        .hp-s-badge { display: inline-block; font-size: .78rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; }
        .hp-s-draft { background: #FBEBD8; color: #13212C; } .hp-s-ok { background: #DDF1FA; color: #12304A; }
        .hp-s-btn { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0 .9rem; border-radius: 999px; font-weight: 700; border: 2px solid #13212C; background: #fff; color: #13212C; cursor: pointer; }
        .hp-s-red { background: #CB1B32; border-color: #CB1B32; color: #fff; }
        .hp-s-tools { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: center; margin-top: .5rem; }
        dialog.hp-s-dlg { border: 2px solid #13212C; border-radius: 1rem; padding: 1rem; max-width: 26rem; width: 94vw; background: #FFFBF4; color: #13212C; }
        dialog.hp-s-dlg::backdrop { background: rgba(19,33,44,.6); }
    </style>
    <div class="hp-s-box">
        <p>{{ __('প্রতিটি অক্ষর কোন টানে, কোন ক্রমে লেখা হয়। মেশিনের খসড়া দেখে ঠিক থাকলে "অনুমোদন", না হলে "নিজে আঁকুন": আঙুল বা মাউস দিয়ে প্রথম টান থেকে শেষ টান পর্যন্ত আঁকুন। শিশুরা শুধু অনুমোদিত আঁকা দেখে (ওয়ার্কশিটের প্রথম ঘরে আর পাঠে আঙুলে লেখার সময়)।') }}</p>
        <p style="margin-top:.4rem"><b>{{ $n($counts['approved'] ?? 0) }}</b> {{ __('অনুমোদিত') }} · <b>{{ $n($counts['draft'] ?? 0) }}</b> {{ __('খসড়া') }}</p>
        <div class="hp-s-tools" style="justify-content:flex-start">
            @foreach (['all' => __('সব'), 'draft' => __('খসড়া'), 'approved' => __('অনুমোদিত')] as $k => $label)
                <button type="button" wire:click="$set('only', '{{ $k }}')" class="hp-s-btn {{ $only === $k ? 'hp-s-red' : '' }}">{{ $label }}</button>
            @endforeach
            <span style="flex:1"></span>
            <input type="text" wire:model="newGlyph" maxlength="6" placeholder="{{ __('নতুন: যেমন কা, ক্ষ') }}" aria-label="{{ __('নতুন অক্ষর বা চিহ্ন') }}" style="min-height:2.75rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.4);padding:0 .7rem;width:9rem;background:transparent">
            <button type="button" class="hp-s-btn" data-s-add>{{ __('যোগ করে খসড়া বানান') }}</button>
        </div>
    </div>
    <div class="hp-s-grid">
        @foreach ($items as $s)
            <div class="hp-s-card" wire:key="s-{{ $s->id }}-{{ $s->updated_at?->timestamp }}">
                <canvas width="320" height="320" data-stroke-glyph="{{ $s->glyph }}" data-strokes="{{ json_encode($s->strokes) }}" x-data x-init="$nextTick(() => window.hpStrokes && window.hpStrokes.paintStrokeCanvases($el.parentElement))"></canvas>
                <p style="margin-top:.3rem"><b style="font-size:1.4rem">{{ $s->glyph }}</b> · {{ $n(count($s->strokes)) }} {{ __('টান') }}
                    <span class="hp-s-badge {{ $s->status === 'approved' ? 'hp-s-ok' : 'hp-s-draft' }}">{{ $s->status === 'approved' ? __('অনুমোদিত') : __('খসড়া') }}</span></p>
                <p style="font-size:.78rem;opacity:.8">{{ $s->source === 'desk' ? __('ডেস্কে আঁকা') : __('মেশিনের খসড়া') }}</p>
                <div class="hp-s-tools">
                    @if ($s->status === 'approved')<button type="button" class="hp-s-btn" wire:click="unapprove({{ $s->id }})">{{ __('খসড়ায় ফেরান') }}</button>
                    @else<button type="button" class="hp-s-btn hp-s-red" wire:click="approve({{ $s->id }})">{{ __('অনুমোদন') }}</button>@endif
                    <button type="button" class="hp-s-btn" data-s-draw="{{ $s->glyph }}">{{ __('নিজে আঁকুন') }}</button>
                    <button type="button" class="hp-s-btn" data-s-redraft="{{ $s->glyph }}">{{ __('নতুন খসড়া') }}</button>
                    <button type="button" class="hp-s-btn" wire:click="remove({{ $s->id }})" wire:confirm="{{ __('এই আঁকা মুছবেন?') }}">{{ __('মুছুন') }}</button>
                </div>
            </div>
        @endforeach
    </div>

    <dialog class="hp-s-dlg" data-s-dlg wire:ignore aria-labelledby="hp-s-h">
        <h2 id="hp-s-h" style="font-weight:800;font-size:1.2rem">{{ __('নিজে আঁকুন') }}: <span data-s-glyph></span></h2>
        <p style="font-size:.9rem">{{ __('প্রথম টান থেকে শুরু করুন, প্রতিটি টান আঙুল না তুলে। ভুল হলে "শেষ টান মুছুন"।') }}</p>
        <canvas data-s-pad width="640" height="640" style="width:100%;aspect-ratio:1;border:1px solid #E39A6A;border-radius:.75rem;background:#fff;touch-action:none;margin-top:.5rem"></canvas>
        <div class="hp-s-tools">
            <button type="button" class="hp-s-btn" data-s-undo>{{ __('শেষ টান মুছুন') }}</button>
            <button type="button" class="hp-s-btn" data-s-clear>{{ __('সব মুছুন') }}</button>
            <button type="button" class="hp-s-btn hp-s-red" data-s-save>{{ __('রাখুন ও অনুমোদন') }}</button>
            <button type="button" class="hp-s-btn" data-s-close>{{ __('বন্ধ') }}</button>
        </div>
    </dialog>

    <script>
        (() => {
            if (window.hpStrokesDesk) return; window.hpStrokesDesk = true;
            const wire = () => Livewire.find(document.querySelector('[data-s-dlg]').closest('[wire\\:id]').getAttribute('wire:id'));
            const dlg = document.querySelector('[data-s-dlg]'); const pad = dlg.querySelector('[data-s-pad]'); const ctx = pad.getContext('2d');
            let glyph = ''; let strokes = []; let cur = null;
            const paint = () => { ctx.clearRect(0, 0, 640, 640); window.hpStrokes.drawGlyph(ctx, 640, glyph, '#E8DCCB'); window.hpStrokes.drawStrokes(ctx, 640, cur ? strokes.concat([cur]) : strokes); };
            const pos = (e) => { const r = pad.getBoundingClientRect(); return [Math.round(((e.clientX - r.left) / r.width) * 1000) / 10, Math.round(((e.clientY - r.top) / r.height) * 1000) / 10]; };
            pad.addEventListener('pointerdown', (e) => { cur = [pos(e)]; pad.setPointerCapture(e.pointerId); });
            pad.addEventListener('pointermove', (e) => { if (!cur) return; const p = pos(e); const l = cur[cur.length - 1]; if (Math.hypot(p[0] - l[0], p[1] - l[1]) >= 1.5) { cur.push(p); paint(); } });
            const end = () => { if (cur && cur.length >= 2) strokes.push(cur); cur = null; paint(); };
            pad.addEventListener('pointerup', end); pad.addEventListener('pointercancel', end);
            const open = async (g) => { glyph = g; strokes = []; cur = null; dlg.querySelector('[data-s-glyph]').textContent = g; dlg.showModal(); try { await document.fonts.load('500 120px "Anek Bangla"', g); } catch (e) {} paint(); };
            document.addEventListener('click', async (e) => {
                const t = e.target.closest('[data-s-draw],[data-s-redraft],[data-s-add]'); if (!t) return;
                if (t.dataset.sDraw) open(t.dataset.sDraw);
                else if (t.dataset.sRedraft) { const s = await window.hpStrokes.draftStrokes(t.dataset.sRedraft); wire().call('saveDraft', t.dataset.sRedraft, JSON.stringify(s)); }
                else { const g = (document.querySelector('[wire\\:model="newGlyph"]').value || '').trim(); if (!g) return; const s = await window.hpStrokes.draftStrokes(g); wire().call('saveDraft', g, JSON.stringify(s)); }
            });
            dlg.querySelector('[data-s-undo]').addEventListener('click', () => { strokes.pop(); paint(); });
            dlg.querySelector('[data-s-clear]').addEventListener('click', () => { strokes = []; paint(); });
            dlg.querySelector('[data-s-close]').addEventListener('click', () => dlg.close());
            dlg.querySelector('[data-s-save]').addEventListener('click', () => { if (!strokes.length) return; wire().call('save', glyph, JSON.stringify(strokes)); dlg.close(); });
        })();
    </script>
</x-filament-panels::page>
