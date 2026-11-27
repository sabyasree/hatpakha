{{-- D315: one occasion's greetings: our own lines, checked; a poet's line only when two pages give it letter for letter --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $G = \App\Support\GreetingPlan::GROUPS;
    if ($tone !== 'happy') { $G['chhoto'] = 'ছোট বার্তা'; }
    $body = (array) $s->body;
    $poet = $body['poet'] ?? null;
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8" data-greet="{{ $bn }}">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['শুভেচ্ছা বার্তা', $U('/janun/greetings')], [$bn, '']],
            'kicker' => 'জেনে রাখুন · শুভেচ্ছা বার্তা', 'title' => $h1, 'trust' => 'বার্তাগুলি নতুন লেখা, বানান ও রীতি যাচাই করা', 'trustBy' => $U('/janun')])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $en }}</p>
        @foreach (preg_split('/\n\s*\n/', trim((string) ($body['intro'] ?? ''))) as $para)
            <p class="mt-4 font-serif text-lg leading-relaxed">{{ $para }}</p>
        @endforeach
        <nav aria-label="কার জন্য" class="mt-5 flex flex-wrap gap-2">
            @foreach ((array) ($body['groups'] ?? []) as $g)
                <a href="#{{ $g['key'] }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $G[$g['key']] ?? '' }}</a>
            @endforeach
            @if ($festivalUrl)<a href="{{ $festivalUrl }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">তিথি ও সময়</a>@endif
        </nav>
        <p data-greet-status role="status" class="sr-only"></p>
        <p class="mt-3 text-sm text-ink2">যে বার্তা আপনার মনের কথা বলে, <b>কপি</b> করুন, <b>হোয়াটসঅ্যাপে</b> পাঠান, বা <b>ছবি</b> বানিয়ে নিন। ছবি আপনার ফোনেই তৈরি হয়।</p>

        @if ($poet)
            <figure class="lined mt-6 rounded-2xl border-2 border-navy bg-peach-light p-5 tilt-a">
                <blockquote class="poem-bn whitespace-pre-line font-serif text-xl leading-relaxed" data-line>{{ $poet['text'] }}</blockquote>
                <figcaption class="mt-2 text-sm text-ink2">{{ $poet['poet'] }}@if ($poet['work']), «{{ $poet['work'] }}»@endif · মিলিয়ে দেখা:
                    @foreach ((array) $poet['urls'] as $u)<a href="{{ $u }}" rel="nofollow noopener" target="_blank" class="underline">{{ preg_replace('/^www\./', '', (string) parse_url($u, PHP_URL_HOST)) }}</a>@if (! $loop->last), @endif @endforeach</figcaption>
                @include('janun.partials.greet-actions', ['text' => $poet['text']."\n— ".$poet['poet']])
            </figure>
        @endif

        @foreach ((array) ($body['groups'] ?? []) as $g)
            <section id="{{ $g['key'] }}" class="mt-8 scroll-mt-20">
                <h2 class="font-display text-2xl font-extrabold">{{ $G[$g['key']] ?? '' }}</h2>
                <ol class="mt-3 grid gap-3">
                    @foreach ((array) $g['lines'] as $l)
                        <li class="rounded-2xl border border-rule bg-white/60 p-4">
                            <p class="font-serif text-lg leading-relaxed" data-line>{{ $l['bn'] }}</p>
                            @if (filled($l['en'] ?? ''))<p lang="en" class="mt-1 text-sm text-ink2">{{ $l['en'] }}</p>@endif
                            @include('janun.partials.greet-actions', ['text' => $l['bn']])
                        </li>
                    @endforeach
                </ol>
            </section>
            @if ($loop->first)<x-ad-slot spot="list" :height="250" />@endif
        @endforeach

        @if (! empty($body['faq']))
            <section class="mt-8">
                <h2 class="font-display text-2xl font-extrabold">প্রশ্ন ও উত্তর</h2>
                @foreach ((array) $body['faq'] as $f)
                    <details class="mt-2 rounded-xl border border-rule p-3"><summary class="min-h-11 cursor-pointer font-bold">{{ $f['q'] }}</summary><p class="mt-2 font-serif leading-relaxed">{{ $f['a'] }}</p></details>
                @endforeach
            </section>
        @endif
        <x-ad-slot spot="list" :height="250" />
        @if ($others->isNotEmpty())
            <h2 class="mt-8 font-display text-xl font-extrabold">আরও শুভেচ্ছা বার্তা</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($others as $o)<a href="{{ $o->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $o->name() }}</a>@endforeach
            </div>
        @endif
        @include('janun.partials.read-magazine')
    </article>

    <dialog data-card-dialog aria-labelledby="card-h" class="m-auto w-[min(92vw,460px)] rounded-2xl border-2 border-navy bg-paper p-4 text-navy backdrop:bg-navy/60">
        <h2 id="card-h" class="mb-2 font-display text-lg font-extrabold">শুভেচ্ছার ছবি</h2>
        <canvas width="1080" height="1080" role="img" class="h-auto w-full rounded-xl" aria-label="শুভেচ্ছার ছবি"></canvas>
        <p data-card-msg role="status" class="mt-2 text-sm"></p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" data-card-share hidden class="min-h-11 rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">ছবি পাঠান</button>
            <a data-card-save download class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">ছবি নামান</a>
            <button type="button" data-card-close class="min-h-11 rounded-full px-4 font-bold underline">বন্ধ</button>
        </div>
    </dialog>
    <script nonce="{{ $nonce }}">
    (() => {
        const status = document.querySelector('[data-greet-status]'), page = @json($s->url()), title = @json($bn), band = @json($tone === 'happy' ? '#CB1B32' : '#13212C'), dlg = document.querySelector('[data-card-dialog]');
        const cv = dlg.querySelector('canvas'), ctx = cv.getContext('2d'), save = dlg.querySelector('[data-card-save]'), share = dlg.querySelector('[data-card-share]');
        let file = null, said = '';
        const msg = dlg.querySelector('[data-card-msg]');
        const wrap = (text, max) => {
            const out = [];
            for (const para of text.split('\n')) {
                let line = '';
                for (const w of para.split(/\s+/)) {
                    const t = line ? line + ' ' + w : w;
                    if (ctx.measureText(t).width > max && line) { out.push(line); line = w; } else { line = t; }
                }
                out.push(line);
            }
            return out;
        };
        const draw = async (text) => {
            said = text; msg.textContent = 'ছবি তৈরি হচ্ছে…'; cv.setAttribute('aria-label', text);
            try { await Promise.all([document.fonts.load('800 60px "Anek Bangla"', title), document.fonts.load('400 60px "Noto Serif Bengali"', text), document.fonts.load('48px "Galada"', 'হাতপাখা')]); } catch (e) { /* the phone's own Bengali font */ }
            ctx.fillStyle = '#FBEBD8'; ctx.fillRect(0, 0, 1080, 1080);
            ctx.fillStyle = band; ctx.fillRect(0, 0, 1080, 150);
            ctx.strokeStyle = '#E39A6A'; ctx.lineWidth = 6; ctx.strokeRect(40, 190, 1000, 760);
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillStyle = '#FFFFFF'; ctx.font = '800 60px "Anek Bangla", sans-serif'; ctx.fillText(title, 540, 78);
            const parts = text.split('\n— '), body = parts[0], by = parts[1] || '';
            let size = body.length > 160 ? 48 : body.length > 90 ? 60 : 80, lines;
            do { ctx.font = `400 ${size}px "Noto Serif Bengali", serif`; lines = wrap(body, 860); size -= 4; } while (lines.length * (size + 4) * 1.6 > 640 && size > 30);
            if (lines.length > 1 && lines[lines.length - 1].split(' ').length === 1) { lines = wrap(body, 760); }   // no word left alone on the last line
            const lh = (size + 4) * 1.6, top = (by ? 545 : 570) - (lines.length - 1) * lh / 2;
            ctx.fillStyle = '#13212C'; lines.forEach((l, i) => ctx.fillText(l, 540, top + i * lh));
            if (by) { ctx.fillStyle = '#4D5A63'; ctx.font = `400 ${Math.round(size * 0.7)}px "Noto Serif Bengali", serif`; ctx.fillText('— ' + by, 540, top + lines.length * lh + 20); }
            ctx.fillStyle = '#CB1B32'; ctx.font = '52px "Galada", "Anek Bangla", sans-serif'; ctx.fillText('হাতপাখা', 540, 1005);
            ctx.fillStyle = '#4D5A63'; ctx.font = '600 28px "Anek Bangla", sans-serif'; ctx.fillText('hatpakha.com', 540, 1050);
            const blob = await new Promise(r => cv.toBlob(r, 'image/png'));
            save.href = URL.createObjectURL(blob); save.download = 'hatpakha-shubhechha.png';
            file = new File([blob], 'hatpakha-shubhechha.png', { type: 'image/png' });
            share.hidden = !(navigator.canShare && navigator.canShare({ files: [file] }));
            msg.textContent = 'ছবি তৈরি হয়েছে।'; (share.hidden ? save : share).focus();
        };
        document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'কপি হয়েছে'; status.textContent = 'বার্তা কপি হয়েছে।'; } catch (e) { b.textContent = 'কপি হয়নি'; status.textContent = 'কপি হয়নি।'; }
            setTimeout(() => { b.textContent = 'কপি'; }, 2000);
        }));
        document.querySelectorAll('[data-card]').forEach(b => b.addEventListener('click', async () => { dlg.showModal(); await draw(b.dataset.card); }));
        share.addEventListener('click', () => { if (file) navigator.share({ files: [file], title, text: said + '\n\n' + page }).catch(() => {}); });
        dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
        dlg.querySelector('[data-card-close]').addEventListener('click', () => dlg.close());
    })();
    </script>
</x-layouts.app>
