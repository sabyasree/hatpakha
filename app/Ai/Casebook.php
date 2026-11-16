<?php

namespace App\Ai;

/**
 * D212 (owner, 2026-11-16: "I want to start a series like Sherlock Holmes… a very good detective story, without
 * copying… the proofreader one… earlier Kolkata… very unique and engaging"). The fixed sheet of Hatpakha's own
 * detective series. Every case is a short serial written from this sheet, so the people, the place and the method
 * never drift from case to case. The form is the classic one (a detective, a narrator, complete cases, fair clues);
 * no name, line, look or plot is taken from any existing detective.
 */
final class Casebook
{
    public const SERIES = 'পাকড়াশীর প্রুফ';

    public const GENRE = 'গোয়েন্দা';

    /** One case: this many weekly episodes. */
    public const EPISODES = [5, 6];

    public const BRIEF = 'This is one CASE of Hatpakha\'s own detective series "পাকড়াশীর প্রুফ" (a "প্রুফ" is a printer\'s proof, and it is evidence). '
        .'Classic detective fiction in its form: one detective with a way of seeing nobody else has, a narrator who tells it, a complete case solved by reasoning the reader could have followed. '
        .'It is ORIGINAL: never use or echo Sherlock Holmes, Watson, Feluda, Topshe, Byomkesh, Ajit, Kiriti, Poirot or any existing detective, companion, catchphrase, scene or plot, and never a case that is a known story with the names changed. '
        ."\n\nTHE FIXED SHEET (never change any of it):"
        ."\nTime and place: Kolkata in the late 1970s. Trams, load-shedding and hurricane lamps, hand-set metal type and treadle machines, tea in clay cups, addas on the rowak, the Book Fair on the Maidan in winter, trunk calls booked through the exchange, no mobile phones, no computers. Get the period right and never mention anything later."
        ."\nHome base: অক্ষর প্রেস, a small letterpress in a lane behind College Street: the smell of ink and kerosene, cases of type, a proofs table by the one window that gets light. Its owner, হরিহরবাবু, is fond of the old man and afraid of trouble."
        ."\nThe detective: শশধর পাকড়াশী, called পাকড়াশীমশাই, in his sixties, forty years a proofreader, now retired but at the press every afternoon because he cannot stay away. Thin, a dhuti and a worn cotton coat, thick glasses pushed up on his forehead when he thinks, a red-and-blue pencil behind his ear. A bad left knee: he walks slowly with an umbrella for a stick and cannot run after anyone, so he has to be right. A widower who lives alone in a rented room with a wall of dictionaries. Gentle, exact, dry-humoured; he corrects people's Bengali without meaning to and apologises for it. He never boasts and never explains early."
        ."\nHis method, which is what makes this series unlike any other: he reads the world the way he reads a proof. A lie is a kind of misprint. He solves cases through language, writing and print: a spelling that belongs to one district or one generation, a word a person of that class or trade would never use, handwriting, the pressure of a pen, a typeface or a worn letter that names the press or the year, paper and ink, a date that does not fit its calendar, a line of verse misquoted, a ledger whose figures were added in a different hand, a signboard repainted, what someone carefully did NOT say. Ordinary observation of people supports this, but the turning clue of every case comes from language, writing or print. His saying, used at most once in a case and never in the last line: \"ভুল কখনও একা আসে না।\""
        ."\nThe narrator: বিশু (বিশ্বনাথ দাস), about twenty, the press's delivery boy, from a village in the Sundarbans, who carries proofs and bundles of books all over the city on an old bicycle. Quick, hungry, curious, a little cheeky, half-educated and ashamed of it; পাকড়াশীমশাই is teaching him to read properly. He tells every case in the first person, in warm plain চলিত Bengali, with a young man's eye for food, money and people. He does the legwork: he can go anywhere because nobody notices a delivery boy. He sees everything and understands half; he is sometimes wrong, sometimes in real danger, and never a fool. The reader knows only what বিশু knows."
        ."\nThe contact: তপন ঘোষাল, a young sub-inspector at the local thana, the son of পাকড়াশীমশাই's old neighbour. Overworked, sceptical, decent. He never brings them a case; he grudgingly answers one question at a time, and at the end he makes the arrest and takes the credit."
        ."\nHow a case comes: somebody brings paper to the press, or বিশু stumbles on something on his rounds: a will, an anonymous letter, an odd advertisement to be set, a book with one page changed, a wedding card, a pamphlet nobody admits printing. The case opens at the press, goes out into the city (and now and then a district town reached by local train), and is explained at the proofs table."
        ."\n\nRULES OF A CASE:"
        ."\n1. Fair play. Before writing, decide who did it, how, why, and the exact clues that prove it. Every clue the solution rests on is shown to the reader in an earlier episode, plainly, in a scene, though its meaning is hidden among other details. Nothing is solved by a confession out of nowhere, a secret twin, a coincidence, a fact kept from the reader, or the supernatural."
        ."\n2. The reader should be able to say, at the end, \"it was there and I missed it\". Give at least two people a real reason to be suspected and one honest false trail that the clues themselves later disprove."
        ."\n3. The crimes are human-sized and of their time and place: a forged will, a vanished manuscript, blackmail, a theft made to look like something else, a death that may not be an accident. No gore, no serial killers, no cruelty shown for its own sake, nothing about real people, real crimes, real families or real presses."
        ."\n4. Each case is new: a different kind of crime, a different corner of the city or a district, a different kind of written clue, a different sort of culprit. Never repeat the trick of an earlier case."
        ."\n5. The pleasure is the two of them: the old man's patience and the boy's impatience, the small comedy of the press, tea, and Kolkata of that time. Let the city be a character. The last episode explains everything in order, at the proofs table, and ends on a quiet human note, not on the arrest.";

    /** What a plan must hold before any episode is written; null when it is fair. */
    public static function unfair(array $plan): ?string
    {
        $episodes = count((array) ($plan['episodes'] ?? []));
        $s = (array) ($plan['solution'] ?? []);
        $clues = array_values((array) ($plan['clues'] ?? []));
        if ($episodes < self::EPISODES[0] || $episodes > self::EPISODES[1] + 1) {
            return 'একটি কেস পাঁচ-ছয় পর্বের হতে হবে।';
        }
        // D213: readers guess by name, so the culprit must be one of the case's own people, named exactly
        $names = array_map(fn ($c) => trim((string) ($c['name'] ?? '')), (array) ($plan['characters'] ?? []));
        if (blank($s['culprit_name'] ?? null) || ! in_array(trim((string) $s['culprit_name']), $names, true)) {
            return 'অপরাধী এই কেসের চরিত্রদের একজন হতে হবে, নাম হুবহু মিলিয়ে।';
        }
        if (count(array_unique(array_filter($names))) < 3) {
            return 'কেসে অন্তত তিনজন চরিত্র থাকতে হবে, যাতে পাঠক অনুমান করতে পারেন।';
        }
        foreach (['culprit', 'method', 'motive', 'proof'] as $k) {
            if (blank($s[$k] ?? null)) {
                return 'পরিকল্পনায় সমাধান পুরো লেখা নেই (কে, কীভাবে, কেন, প্রমাণ)।';
            }
        }
        if (count($clues) < 3) {
            return 'পরিকল্পনায় অন্তত তিনটি সূত্র থাকতে হবে।';
        }
        foreach ($clues as $c) {
            $at = (int) ($c['episode'] ?? 0);
            if ($at < 1 || $at >= $episodes) {
                return 'প্রতিটি সূত্র শেষ পর্বের আগে পাঠককে দেখাতে হবে।';
            }
        }
        if (! collect($clues)->contains(fn ($c) => (bool) ($c['written'] ?? false))) {
            return 'অন্তত একটি সূত্র ভাষা, লেখা বা ছাপা থেকে আসতে হবে।';
        }

        return null;
    }
}
