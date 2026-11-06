/* Marketing desk: share a ready post straight to a network (D87). Nothing posts by itself: each button only
   opens the network's own share screen (or the phone's share sheet) with the text, link and picture ready;
   the person still presses "Post" there. Facebook does not accept ready text from other sites, so the
   text is also copied for pasting. Instagram has no web share link: phones get the share sheet with the
   picture; computers get the caption copied, the picture downloaded and Instagram opened. */
(function () {
    if (window.hpShare) return;
    const files = new WeakMap();
    const copy = (t) => navigator.clipboard?.writeText(t).catch(() => {});
    const withLink = (t, link) => (link && !t.includes(link) ? t.trim() + '\n\n' + link : t);
    const pop = (url) => window.open(url, '_blank', 'noopener,noreferrer,width=680,height=640');

    // Draw the picture before the click: share sheets must open straight from the tap.
    function prepare(card, name) {
        if (!card || files.has(card) || !window.hpCard?.file) return;
        files.set(card, window.hpCard.file(card, name).catch(() => null));
    }
    function watch(root) {
        const io = new IntersectionObserver((es) => es.forEach((e) => {
            if (e.isIntersecting) { prepare(e.target, e.target.dataset.name); io.unobserve(e.target); }
        }), { rootMargin: '200px' });
        root.querySelectorAll('.hp-card[data-name]').forEach((c) => io.observe(c));
    }

    async function share(kind, text, link, card) {
        const t = withLink(text, link);
        if (kind === 'whatsapp') { pop('https://wa.me/?text=' + encodeURIComponent(t)); return 'হোয়াটসঅ্যাপ খুলেছে: চ্যাট, গ্রুপ বা চ্যানেল বেছে পাঠান।'; }
        if (kind === 'facebook') {
            pop(link ? 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link) : 'https://www.facebook.com/');
            copy(text);
            return 'ফেসবুক খুলেছে। লেখাটি কপি হয়েছে: সেখানে পেস্ট করে পোস্ট করুন।';
        }
        if (card && !files.has(card)) prepare(card, card.dataset.name);
        const file = card ? await (files.get(card) || null) : null;
        const can = (d) => navigator.share && (!navigator.canShare || navigator.canShare(d));
        if (kind === 'native' || (kind === 'instagram' && file && can({ files: [file] }) && /Android|iPhone|iPad/i.test(navigator.userAgent))) {
            // Instagram captions can't hold a working link, so its text goes as written.
            const words = kind === 'instagram' ? text : t;
            const d = file && can({ files: [file], text: words }) ? { files: [file], text: words } : { text: words, url: kind === 'instagram' ? undefined : link || undefined };
            if (kind === 'instagram') copy(text);
            try { await navigator.share(d); return kind === 'instagram' ? 'তালিকা থেকে ইনস্টাগ্রাম বাছুন। ক্যাপশন কপি হয়েছে, দরকারে পেস্ট করুন।' : ''; }
            catch (e) { if (e.name === 'AbortError') return ''; copy(t); return 'শেয়ার খোলা গেল না; লেখাটি কপি হয়েছে।'; }
        }
        // Instagram on a computer: caption copied, picture downloaded, Instagram opened.
        copy(text);
        if (file) { const a = document.createElement('a'); a.href = URL.createObjectURL(file); a.download = file.name; a.click(); }
        pop('https://www.instagram.com/');
        return file ? 'ক্যাপশন কপি হয়েছে, ছবি নেমেছে। ইনস্টাগ্রামে "তৈরি করুন" টিপে ছবিটি দিন, ক্যাপশন পেস্ট করুন।' : 'ক্যাপশন কপি হয়েছে। ইনস্টাগ্রামে একটি ছবির সঙ্গে পেস্ট করুন।';
    }

    window.hpShare = { share, watch, prepare, canNative: () => !!navigator.share };
})();
