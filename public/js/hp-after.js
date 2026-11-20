/* D243: under a finished game, for a signed-in player: today's total out of 300, which games are left, the place on
   this month's board (only ever the way up), the next level and the run of days. Filled from the score's answer. */
window.hpAfter = function (root, j) {
    const box = root.querySelector('[data-game-after]');
    if (!box || !j || !j.ok) return;
    const bn = (n) => Number(n).toLocaleString('en-US').replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const $ = (s) => box.querySelector('[data-after-' + s + ']');
    $('total').textContent = bn(j.today.total) + ' / ' + bn(j.today.max);
    $('bar').style.width = Math.round(100 * j.today.total / j.today.max) + '%';
    const games = $('games'); games.textContent = '';
    j.today.groups.forEach((g) => {
        const s = document.createElement('span');
        s.className = 'rounded-xl px-2 py-1.5 text-center ' + (g.done ? 'bg-navy text-white' : 'border-2 border-dashed border-navy');
        s.textContent = g.done ? '✓ ' + g.name + ' ' + bn(g.points) : g.name + ' বাকি';
        games.appendChild(s);
    });
    const r = j.rank || {};
    $('rank').textContent = !r.now ? '' : (r.up > 0 ? '⬆ ' + bn(r.up) + ' জনকে পেরিয়ে এ মাসে এখন ' + bn(r.now) + ' নম্বরে'
        : (r.now === 1 ? 'এ মাসে আপনিই সবার আগে' : 'এ মাসে আপনি ' + bn(r.now) + ' নম্বরে' + (r.gap ? '। আর ' + bn(r.gap) + ' পয়েন্ট পেলে ' + bn(r.now - 1) + ' নম্বরে' : '')));
    const next = $('next');
    if (j.next) { next.hidden = false; next.href = j.next.url; next.textContent = 'এবার ' + j.next.name + ' খেলুন →'; }
    if (j.level && j.level.nextName) {
        $('level').textContent = j.level.name;
        $('level-next').textContent = j.level.nextName + ' হতে আর ' + bn(j.level.left);
        $('level-bar').style.width = j.level.percent + '%';
    } else { $('level-box').hidden = true; }
    $('streak').textContent = j.streak > 1 ? 'টানা ' + bn(j.streak) + ' দিন খেলছেন। সপ্তাহে একদিন বাদ গেলে ধারা ভাঙে না।' : '';
    box.hidden = false;
};
