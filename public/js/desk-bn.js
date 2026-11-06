/*
 * /desk only: finishes the Bengali interface where Filament has no Bengali of its own.
 * 1. Bengali month and day names in the date picker (Filament bundles no dayjs `bn` locale,
 *    and its picker always uses the `en` entry, so that entry is given Bengali names here).
 * 2. Bengali digits in what the desk SHOWS (never in inputs, links, e-mails, paths or code).
 * Display only: nothing typed or saved changes.
 */
(function () {
    var MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    var DAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
    var DAYS_SHORT = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি'];
    var DIGITS = '০১২৩৪৫৬৭৮৯';

    function patchDayjs() {
        var d = window.dayjs;
        if (!d || !d.Ls || !d.Ls.en || d.Ls.en.__bn) return !!(d && d.Ls && d.Ls.en && d.Ls.en.__bn);
        var en = d.Ls.en;
        en.months = MONTHS;
        en.monthsShort = MONTHS;
        en.weekdays = DAYS;
        en.weekdaysShort = DAYS_SHORT;
        en.weekdaysMin = DAYS_SHORT;
        en.weekStart = 0;
        en.__bn = true;
        return true;
    }

    var SKIP = 'input, textarea, script, style, code, pre, a[href^="mailto:"], [contenteditable], .fi-fo-rich-editor, .no-bn-digits';
    function bnDigits(s) { return s.replace(/[0-9]/g, function (c) { return DIGITS[c]; }); }
    function convert(root) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var n;
        while ((n = walker.nextNode())) {
            var t = n.nodeValue;
            // Leave alone anything that looks like an address, e-mail, path, code or Latin word with digits.
            if (!/[0-9]/.test(t) || /[A-Za-z@\/_\\]/.test(t)) continue;
            var el = n.parentElement;
            if (!el || el.closest(SKIP)) continue;
            n.nodeValue = bnDigits(t);
        }
    }

    // The picker reads month/day names when it starts, so patch dayjs the moment Filament
    // assigns window.dayjs (its script loads lazily, after this one).
    var dj = window.dayjs;
    try {
        Object.defineProperty(window, 'dayjs', {
            configurable: true,
            get: function () { return dj; },
            set: function (v) {
                dj = v;
                // Filament registers its bundled locales (incl. a fresh `en`) right after this;
                // re-apply the Bengali names after every registration.
                if (v && typeof v.locale === 'function' && !v.locale.__bn) {
                    var original = v.locale;
                    v.locale = function () { var r = original.apply(this, arguments); patchDayjs(); return r; };
                    v.locale.__bn = true;
                }
                patchDayjs();
            },
        });
    } catch (e) { /* already defined: the observer below still patches */ }
    patchDayjs();

    var queued = false;
    function run() {
        queued = false;
        patchDayjs();
        convert(document.body);
    }
    function queue() { if (!queued) { queued = true; requestAnimationFrame(run); } }

    document.addEventListener('DOMContentLoaded', function () {
        run();
        new MutationObserver(queue).observe(document.body, { childList: true, subtree: true, characterData: true });
    });
    document.addEventListener('livewire:navigated', queue);
})();
