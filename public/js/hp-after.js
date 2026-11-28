/* D243: under a finished game, for a signed-in player: today's total out of 300, which games are left, the place on
   this month's board (only ever the way up), the next level and the run of days. Filled from the score's answer. */
window.hpAfter = function (root, j) {
    const box = root.querySelector('[data-game-after]');
    if (!box || !j || !j.ok) return;
    const bn = (n) => Number(n).toLocaleString('en-US').replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const $ = (s) => box.querySelector('[data-after-' + s + ']');
    $('total').textContent = bn(j.today.total) + ' / ' + bn(j.today.max);
    $('bar').style.width = Math.round(100 * j.today.total / j.today.max) + '%';
    // D330 (game designers): three games make a full day; past that the rest are a choice, not a debt
    const done = j.today.groups.filter((g) => g.done).length;
    const full = done >= 3 || j.today.total >= j.today.max;
    $('full').hidden = !full;
    $('more-label').textContent = full ? 'চাইলে আরও খেলুন (সেরা তিনটে বদলাতে পারে)' : 'আর ' + bn(3 - done) + 'টা খেলা হলেই আজকের দিন পূর্ণ';
    const games = $('games'); games.textContent = '';
    const chip = (g) => {
        const s = document.createElement('span');
        s.className = 'rounded-xl px-2 py-1.5 text-center ' + (g.done ? 'bg-navy text-white' : 'border-2 border-dashed border-navy');
        s.textContent = g.done ? '✓ ' + g.name + ' ' + bn(g.points) : g.name;
        return s;
    };
    j.today.groups.filter((g) => g.done).forEach((g) => games.appendChild(chip(g)));
    const rest = $('rest'); rest.textContent = '';
    j.today.groups.filter((g) => !g.done).forEach((g) => rest.appendChild(chip(g)));
    const r = j.rank || {};
    $('rank').textContent = !r.now ? '' : (r.up > 0 ? '⬆ ' + bn(r.up) + ' জনকে পেরিয়ে এ মাসে এখন ' + bn(r.now) + ' নম্বরে'
        : (r.now === 1 ? 'এ মাসে আপনিই সবার আগে' : 'এ মাসে আপনি ' + bn(r.now) + ' নম্বরে' + (r.gap ? '। আর ' + bn(r.gap) + ' পয়েন্ট পেলে ' + bn(r.now - 1) + ' নম্বরে' : '')));
    // D329: the week's place, the nearest title within reach
    const w = j.week || {};
    if (w.rank) $('rank').textContent = ($('rank').textContent ? $('rank').textContent.replace(/।?$/, '। ') : '') + (w.rank <= 3 ? 'এ সপ্তাহে আপনি সেরা তিনে (' + bn(w.rank) + ' নম্বর)!' : 'এ সপ্তাহে ' + bn(w.rank) + ' নম্বরে' + (w.gap ? '; আর ' + bn(w.gap) + ' পয়েন্টে ' + bn(w.rank - 1) + ' নম্বর।' : '।'));
    const next = $('next');
    if (j.next) {
        next.hidden = false; next.href = j.next.url;
        next.textContent = full ? 'আরও একটা খেলবেন? ' + j.next.name + ' →' : 'এবার ' + j.next.name + ' খেলুন →';
        if (j.today.total >= j.today.max) next.className = 'mt-3 flex min-h-11 items-center justify-center font-bold text-sky-night underline';
    }
    if (j.level && j.level.nextName) {
        $('level').textContent = j.level.name;
        $('level-next').textContent = j.level.nextName + ' হতে আর ' + bn(j.level.left);
        $('level-bar').style.width = j.level.percent + '%';
    } else { $('level-box').hidden = true; }
    // D330: this week's seven days; a missed day is one empty dot, not a run lost
    if (w.days && $('week')) {
        const names = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি']; const today = new Date(Date.now() + (330 + new Date().getTimezoneOffset()) * 60000).getDay();
        const ol = $('week-dots'); ol.textContent = '';
        w.days.forEach((on, i) => {
            const li = document.createElement('li'); li.className = 'flex flex-col items-center gap-0.5';
            const dot = document.createElement('span'); dot.className = 'flex h-7 w-7 items-center justify-center rounded-full border-2 text-xs font-extrabold ' + (on ? 'border-navy bg-red text-white' : (i === today ? 'border-red bg-white' : (i < today ? 'border-dashed border-ink2 bg-white' : 'border-navy bg-white')));
            dot.textContent = on ? '✓' : '';
            const n = document.createElement('span'); n.className = 'text-[13px] font-bold text-ink2'; n.textContent = names[i];
            li.append(dot, n); ol.appendChild(li);
        });
        const c = w.days.filter(Boolean).length;
        $('week-text').textContent = 'এ সপ্তাহে ' + bn(c) + ' দিন' + (c < 3 ? '। ' + bn(3) + ' দিন খেললেই সপ্তাহের সেরার দৌড়ে।' : '। সপ্তাহের সেরার দৌড়ে আছেন।');
        $('week').hidden = false;
    }
    // D330: a missed day made up with one more game: told as an offer, never as a loss
    if (j.mend) {
        const left = Math.max(0, 4 - done);
        $('streak').textContent = left ? 'আর ' + bn(left) + 'টা খেললেই টানা ' + bn(j.streak) + ' দিনের ধারা জোড়া লাগবে।' : 'ধারা জোড়া লেগেছে, টানা ' + bn(j.streak + 1) + ' দিন!';
    } else $('streak').textContent = j.streak > 1 ? 'টানা ' + bn(j.streak) + ' দিন খেলছেন। সপ্তাহে একদিন বাদ গেলে ধারা ভাঙে না।' : '';
    box.hidden = false;
};
