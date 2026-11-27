<?php

namespace App\Support;

/**
 * D315 (owner, 2026-11-27: "build … festival greetings with share pictures", automatic once checked, English words in
 * the address): /janun/greetings/{slug}/. Each occasion: Bengali name, English name for the title, the festival page it
 * belongs to (FestivalPlan key or null), the month it usually falls in (only to decide which set is written first;
 * 0 = any time of the year) and what the writer should keep in mind.
 */
final class GreetingPlan
{
    public const OCCASIONS = [
        'birthday' => ['জন্মদিনের শুভেচ্ছা', 'Birthday Wishes in Bengali', null, 0, 'birthday wishes for family, friends, children, colleagues'],
        'christmas' => ['বড়দিনের শুভেচ্ছা', 'Christmas Wishes in Bengali', null, 12, 'Christmas (বড়দিন), the birth of Jesus (Bangla Academy spelling যিশু, যিশুখ্রিস্ট; not যীশু), church, cake, carols, as celebrated in Bengal; warm and respectful'],
        'happy-new-year' => ['ইংরেজি নববর্ষের শুভেচ্ছা', 'Happy New Year Wishes in Bengali', null, 1, 'the 1 January new year'],
        'saraswati-puja' => ['সরস্বতী পুজোর শুভেচ্ছা', 'Saraswati Puja Wishes in Bengali', 'saraswati', 1, 'Saraswati Puja / Basanta Panchami: learning, students, spring, yellow'],
        'shivaratri' => ['শিবরাত্রির শুভেচ্ছা', 'Maha Shivratri Wishes in Bengali', 'shiboratri', 2, 'Maha Shivaratri: fasting, the night prayer to Shiva'],
        'dol-holi' => ['দোলযাত্রার শুভেচ্ছা', 'Dol Jatra and Holi Wishes in Bengali', 'dol', 3, 'Dol Jatra / Basanta Utsav: colours (আবির), spring, Radha-Krishna'],
        'eid-ul-fitr' => ['ঈদুল ফিতরের শুভেচ্ছা', 'Eid ul-Fitr Wishes in Bengali (Eid Mubarak)', null, 3, 'Eid ul-Fitr after the month of Ramadan: ঈদ মোবারক, prayer, joy, ফিতরা and the poor remembered, visiting family; Bangladeshi and Indian Bengali readers; when the Prophet is named write (সা.)'],
        'poila-baishakh' => ['পয়লা বৈশাখের শুভেচ্ছা', 'Poila Baishakh (Bengali New Year) Wishes', 'boishakh', 4, 'শুভ নববর্ষ, the Bengali new year, for both Bengals: হালখাতা, নববর্ষের শোভাযাত্রা, new clothes; do not name any procession'],
        'eid-ul-adha' => ['ঈদুল আজহার শুভেচ্ছা', 'Eid ul-Adha Wishes in Bengali (Eid Mubarak)', null, 5, 'Eid ul-Adha (কোরবানির ঈদ): the sacrifice in remembrance of Ibrahim (আ.)\'s devotion, the Hajj, sharing with the poor and neighbours; respectful; no detail of slaughter, no jokes about the animal'],
        'jamai-shashthi' => ['জামাইষষ্ঠীর শুভেচ্ছা', 'Jamai Shashthi Wishes in Bengali', 'jamai', 6, 'Jamai Shashthi: the mother-in-law and the son-in-law, food, family, light humour allowed'],
        'rakhi' => ['রাখিবন্ধনের শুভেচ্ছা', 'Raksha Bandhan (Rakhi) Wishes in Bengali', null, 8, 'Raksha Bandhan (রাখিবন্ধন, রাখিপূর্ণিমা): brothers and sisters; Rabindranath\'s রাখিবন্ধন of 16 October 1905 against the Partition of Bengal (not on Rakhi Purnima) may be mentioned as a separate memory'],
        'durga-puja' => ['শারদীয়া ও দুর্গাপুজোর শুভেচ্ছা', 'Durga Puja Wishes in Bengali (Sharodiya)', 'durga', 9, 'শারদীয়া / Durga Puja: autumn, কাশফুল, ঢাক, homecoming, pandals'],
        'bijoya' => ['বিজয়ার শুভেচ্ছা', 'Bijoya Dashami Wishes in Bengali', 'durga', 10, 'Bijoya Dashami: প্রণাম to elders, কোলাকুলি, sweets, আসছে বছর আবার হবে'],
        'lakshmi-puja' => ['লক্ষ্মীপুজোর শুভেচ্ছা', 'Lakshmi Puja Wishes in Bengali', 'kojagori', 10, 'Kojagori Lakshmi Puja: the full moon, আলপনা, প্রসাদ, home and plenty'],
        'kali-puja-diwali' => ['কালীপুজো ও দীপাবলির শুভেচ্ছা', 'Kali Puja and Diwali Wishes in Bengali', 'kali', 10, 'Kali Puja and Dipaboli: lamps (প্রদীপ), light over darkness; safe, quiet, no firecrackers'],
        'bhai-phonta' => ['ভাইফোঁটার শুভেচ্ছা', 'Bhai Phonta Wishes in Bengali', 'bhaiphonta', 11, 'Bhai Phonta: sisters\' blessing for brothers, the ফোঁটা, sweets'],
        // D320 (owner: "add more greeting occasions, as many as possible, what people are searching more")
        'bijoy-dibos' => ['বিজয় দিবসের শুভেচ্ছা', '16 December Victory Day Wishes in Bengali', null, 12, 'Bangladesh\'s Victory Day, 16 December 1971: pride, gratitude to the freedom fighters and martyrs, the red and green flag; respectful; never name a political party or leader'],
        'good-morning' => ['শুভ সকালের বার্তা', 'Good Morning Messages in Bengali', null, 0, 'শুভ সকাল messages: fresh, warm, hopeful, short; to family, friends, someone dear, colleagues; nature (রোদ, পাখি, চা)'],
        'good-night' => ['শুভ রাত্রির বার্তা', 'Good Night Messages in Bengali', null, 0, 'শুভ রাত্রি messages: calm, caring, sweet dreams; to family, friends, someone dear, colleagues'],
        'anniversary' => ['বিবাহবার্ষিকীর শুভেচ্ছা', 'Wedding Anniversary Wishes in Bengali', null, 0, 'wedding anniversary wishes: to one\'s own husband or wife, to parents, to friends, to colleagues'],
        'wedding' => ['বিয়ের শুভেচ্ছা', 'Wedding Wishes in Bengali', null, 0, 'wishes for a couple getting married (শুভ বিবাহ), for every faith; blessings for a new life together'],
        'congratulations' => ['অভিনন্দন বার্তা', 'Congratulations Messages in Bengali', null, 0, 'congratulations on a result, a new job, a promotion, a prize, a new home; proud and warm'],
        'exam-wishes' => ['পরীক্ষার শুভেচ্ছা', 'Exam Good Luck Wishes in Bengali', null, 2, 'good luck before an exam (মাধ্যমিক, উচ্চমাধ্যমিক, SSC, HSC, university): calm, confident, no pressure'],
        'get-well' => ['আরোগ্য কামনার বার্তা', 'Get Well Soon Messages in Bengali', null, 0, 'get well soon: caring, hopeful, gentle; never mention a disease or death', 'care'],
        'condolence' => ['শোকবার্তা', 'Condolence Messages in Bengali', null, 0, 'condolence to a grieving family: quiet, sincere, short, for every faith (no religious phrase that would not fit the family); never cheerful', 'condolence'],
        'thank-you' => ['ধন্যবাদ বার্তা', 'Thank You Messages in Bengali', null, 0, 'thank-you messages: for a gift, help, a teacher, guests, birthday wishes received'],
        'retirement' => ['অবসরের শুভেচ্ছা', 'Retirement Wishes in Bengali', null, 0, 'wishes for someone retiring: gratitude for their years of work, a happy new chapter'],
        'annaprashan' => ['অন্নপ্রাশনের শুভেচ্ছা', 'Annaprashan (Mukhe Bhaat) Wishes in Bengali', null, 0, 'অন্নপ্রাশন / মুখেভাত, the baby\'s first rice: blessings for the child and the parents'],
        'new-baby' => ['নবজাতকের শুভেচ্ছা', 'New Baby Wishes in Bengali', null, 0, 'welcoming a new baby: congratulations to the parents, blessings for the child, for every faith'],
        'griha-prabesh' => ['গৃহপ্রবেশের শুভেচ্ছা', 'Housewarming (Griha Prabesh) Wishes in Bengali', null, 0, 'a new home: happiness, peace, many guests'],
        'makar-sankranti' => ['পৌষ সংক্রান্তির শুভেচ্ছা', 'Poush Sankranti (Makar Sankranti) Wishes in Bengali', null, 1, 'পৌষ সংক্রান্তি / পৌষ পার্বণ: পিঠেপুলি, নলেন গুড়, winter, harvest, kites; both Bengals'],
        'republic-day' => ['প্রজাতন্ত্র দিবসের শুভেচ্ছা', 'Republic Day (26 January) Wishes in Bengali', null, 1, 'India\'s Republic Day, 26 January: the Constitution, the tricolour, unity; respectful; no party or leader named'],
        'ramadan' => ['রমজানের শুভেচ্ছা', 'Ramadan Mubarak Wishes in Bengali', null, 2, 'the start of Ramadan (রমজান মোবারক): fasting, সেহরি and ইফতার, prayer, patience, charity; respectful; when the Prophet is named write (সা.)'],
        'pohela-falgun' => ['পহেলা ফাল্গুনের শুভেচ্ছা', 'Pohela Falgun (Basanta Utsab) Wishes in Bengali', null, 2, 'পহেলা ফাল্গুন / বসন্ত উৎসব: spring, yellow clothes, flowers, কোকিল; both Bengals'],
        'valentines-day' => ['ভালোবাসা দিবসের শুভেচ্ছা', 'Valentine\'s Day Wishes in Bengali', null, 2, 'ভালোবাসা দিবস, 14 February: love for a husband, wife or someone dear, also friends and family; tender, never crude'],
        'ekushe-february' => ['একুশে ফেব্রুয়ারির শ্রদ্ধাঞ্জলি', '21 February (Ekushe) Messages in Bengali', null, 2, 'অমর একুশে, 21 February 1952 and International Mother Language Day: remembering the language martyrs (ভাষাশহিদ), love of the mother tongue; a day of remembrance, so শ্রদ্ধা, never "শুভ" or festive words', 'tribute'],
        'womens-day' => ['নারী দিবসের শুভেচ্ছা', 'Women\'s Day Wishes in Bengali', null, 3, 'International Women\'s Day, 8 March: respect, equality, gratitude to mothers, sisters, colleagues; no stereotypes'],
        'bd-independence-day' => ['২৬ মার্চ স্বাধীনতা দিবসের শুভেচ্ছা', '26 March Independence Day Wishes in Bengali', null, 3, 'Bangladesh\'s Independence Day, 26 March 1971: pride, gratitude to the martyrs; respectful; never name a party or leader'],
        'mothers-day' => ['মা দিবসের শুভেচ্ছা', 'Mother\'s Day Wishes in Bengali', null, 5, 'Mother\'s Day (second Sunday of May): to one\'s own mother, grandmother, mother-in-law; also remembering a mother who has passed away, gently'],
        'rabindra-jayanti' => ['রবীন্দ্রজয়ন্তীর শ্রদ্ধা ও শুভেচ্ছা', 'Rabindra Jayanti (25 Baishakh) Wishes in Bengali', null, 5, 'পঁচিশে বৈশাখ, Rabindranath Tagore\'s birthday: celebration and respect, his songs and poems in daily life; facts only if certain'],
        'buddha-purnima' => ['বুদ্ধপূর্ণিমার শুভেচ্ছা', 'Buddha Purnima Wishes in Bengali', null, 5, 'বুদ্ধপূর্ণিমা: peace, compassion (করুণা), non-violence, the full moon; respectful of Buddhist readers in both Bengals'],
        'nazrul-jayanti' => ['নজরুলজয়ন্তীর শ্রদ্ধা ও শুভেচ্ছা', 'Nazrul Jayanti Wishes in Bengali', null, 5, '১১ জ্যৈষ্ঠ, Kazi Nazrul Islam\'s birthday: the rebel poet, his songs, harmony between faiths; facts only if certain; never quote his lines (they are in copyright)'],
        'fathers-day' => ['বাবা দিবসের শুভেচ্ছা', 'Father\'s Day Wishes in Bengali', null, 6, 'Father\'s Day (third Sunday of June): to one\'s own father, grandfather, father-in-law; also remembering a father who has passed away, gently'],
        'rath-yatra' => ['রথযাত্রার শুভেচ্ছা', 'Rath Yatra Wishes in Bengali', null, 7, 'রথযাত্রা: Jagannath, Balaram and Subhadra, the chariot, the fair (রথের মেলা), পাঁপড় ভাজা'],
        'friendship-day' => ['বন্ধু দিবসের শুভেচ্ছা', 'Friendship Day Wishes in Bengali', null, 8, 'Friendship Day (first Sunday of August): old friends, school friends, light and playful'],
        'india-independence-day' => ['১৫ আগস্ট স্বাধীনতা দিবসের শুভেচ্ছা', '15 August Independence Day Wishes in Bengali', null, 8, 'India\'s Independence Day, 15 August 1947: pride, the freedom fighters (Bengal\'s among them), the tricolour; respectful; never name a party or a living leader'],
        'janmashtami' => ['জন্মাষ্টমীর শুভেচ্ছা', 'Janmashtami Wishes in Bengali', null, 8, 'জন্মাষ্টমী: the birth of Krishna, তালের বড়া, fasting, midnight prayer'],
        'teachers-day' => ['শিক্ষক দিবসের শুভেচ্ছা', 'Teachers\' Day Wishes in Bengali', null, 9, 'Teachers\' Day (5 September in India, 5 October World Teachers\' Day): gratitude and respect for teachers, from students and parents'],
        'mahalaya' => ['মহালয়ার শুভেচ্ছা', 'Mahalaya Wishes in Bengali', 'durga', 9, 'মহালয়া: the start of দেবীপক্ষ, the dawn radio programme মহিষাসুরমর্দিনী, the wait for Durga Puja; also the day of তর্পণ for ancestors, so warm and respectful, not loud'],
    ];

    /** The groups every set is written for: who the message is sent to. */
    public const GROUPS = ['chhoto' => 'ছোট শুভেচ্ছা', 'poribar' => 'পরিবারের জন্য', 'bondhu' => 'বন্ধুদের জন্য', 'office' => 'সহকর্মী ও অফিসের জন্য'];

    /** At least this many of our own lines left after the check, in at least three groups, or the set is held. */
    public const MIN_LINES = 16;

    /** A line that fits a share picture (Bengali letters counted with their signs). */
    public const CARD_MAX = 110;

    /** Occasions on a fixed Gregorian day (month-day). */
    public const FIXED = ['christmas' => '12-25', 'happy-new-year' => '01-01', 'poila-baishakh' => '04-14', 'bijoy-dibos' => '12-16',
        'makar-sankranti' => '01-14', 'republic-day' => '01-26', 'pohela-falgun' => '02-14', 'valentines-day' => '02-14', 'ekushe-february' => '02-21',
        'womens-day' => '03-08', 'bd-independence-day' => '03-26', 'rabindra-jayanti' => '05-08', 'nazrul-jayanti' => '05-25',
        'india-independence-day' => '08-15', 'teachers-day' => '09-05'];

    /** Occasions that are not happy days: the writer and the proofreader are told, and the share picture is navy, not red. */
    public static function tone(string $slug): string
    {
        return self::OCCASIONS[$slug][5] ?? 'happy';
    }

    /** The page's heading: "{name} বার্তা", unless the name already says বার্তা or শ্রদ্ধাঞ্জলি. */
    public static function heading(string $slug): string
    {
        $bn = self::OCCASIONS[$slug][0];

        return preg_match('/(বার্তা|শ্রদ্ধাঞ্জলি)$/u', $bn) ? $bn : $bn.' বার্তা';
    }

    /** The Eids move about eleven days earlier each year: their usual month, by year (approximate, only for the order). */
    public const MOVING = ['eid-ul-fitr' => [2027 => 3, 2028 => 2, 2029 => 2, 2030 => 2], 'eid-ul-adha' => [2027 => 5, 2028 => 5, 2029 => 4, 2030 => 4], 'ramadan' => [2027 => 2, 2028 => 1, 2029 => 1, 2030 => 1]];

    /**
     * When the occasion next comes, as well as we know without asking anyone: a checked festival day (FestivalPlan) first,
     * then a fixed day, then the usual month (its 15th). Used only to decide what is written first and what the hub shows
     * first; never printed. Any-time occasions (birthday) are "today".
     */
    public static function next(string $slug, \Carbon\CarbonImmutable $now): \Carbon\CarbonImmutable
    {
        $today = $now->timezone('Asia/Kolkata')->startOfDay();
        [, , $festival, $month] = self::OCCASIONS[$slug];
        if ($month === 0) {
            // wanted all year: the two most searched straight away, the others after anything dated in the next month
            return in_array($slug, ['birthday', 'good-morning'], true) ? $today : $today->addDays(30);
        }
        if ($festival && ($day = \App\Models\FestivalTime::where(['festival' => $festival, 'status' => 'verified'])->whereDate('day', '>=', $today->toDateString())->min('day'))) {
            return \Carbon\CarbonImmutable::parse($day, 'Asia/Kolkata')->startOfDay();
        }
        foreach ([$today->year, $today->year + 1] as $y) {
            $d = isset(self::FIXED[$slug]) ? \Carbon\CarbonImmutable::parse($y.'-'.self::FIXED[$slug], 'Asia/Kolkata')
                : \Carbon\CarbonImmutable::create($y, self::MOVING[$slug][$y] ?? $month, 15, 0, 0, 0, 'Asia/Kolkata');
            if ($d->gte($today)) {
                return $d;
            }
        }

        return $today->addYear();
    }

    /** Which sets are written first: the occasions coming soonest (any-time ones first of all). */
    public static function order(\Carbon\CarbonImmutable $now): array
    {
        return collect(array_keys(self::OCCASIONS))->sortBy(fn ($k) => self::next($k, $now)->timestamp)->values()->all();
    }
}
