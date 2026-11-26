<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\JanunDesk;
use App\Models\BanglaMonth;
use App\Models\JanunPage;
use App\Support\Handover;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The first round of the জেনে রাখুন jobs at once (owner, 2026-11-26); same checks, nothing published early. */
class JanunRunTest extends TestCase
{
    use RefreshDatabase;

    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 09:00:00');
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        JanunDesk::$resolve = fn ($h) => ['93.184.216.34'];
        Http::fake(['*' => Http::response('', 404)]);
        Claude::$fake = function ($purpose, $user) {
            $this->asked[] = $purpose.':'.(str_contains($user, '<festival') ? 'festival' : (str_contains($user, '<year>') ? 'calendar' : 'page'));

            return match (true) {
                $purpose === 'janun' => ['description' => 'বর্ণনা', 'sources' => [], 'faq' => [], 'intro' => 'ভূমিকা',
                    'entries' => array_map(fn ($i) => ['term' => 'প্রবাদ'.$i, 'meaning' => 'অর্থ', 'example' => 'উদাহরণ'], range(1, 45)),
                    'facts' => [], 'sections' => [['heading' => 'ক', 'paragraphs' => ['এক'], 'steps' => [], 'mantras' => []], ['heading' => 'খ', 'paragraphs' => ['দুই'], 'steps' => [], 'mantras' => []]],
                    'summary' => 'সারাংশ'],
                str_contains($user, '<festival') => ['events' => [], 'sources' => []],
                str_contains($user, '<year>') => ['months' => [], 'sources' => []],
                default => ['problems' => []],
            };
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_first_round_runs_every_job_once_and_publishes_nothing(): void
    {
        $this->artisan('hatpakha:janun-run', ['--pages' => 3])->assertSuccessful();
        $this->assertSame(2, count(array_filter($this->asked, fn ($a) => str_ends_with($a, ':calendar'))), 'this year and next');
        $this->assertSame(10, count(array_filter($this->asked, fn ($a) => str_ends_with($a, ':festival'))), 'in November only next year\'s festivals');
        $this->assertSame(3, JanunPage::where('status', 'scheduled')->count());
        $this->assertSame(0, JanunPage::where('status', 'published')->count(), 'nothing goes live early');
        $this->assertSame(['2026-11-27 10:00', '2026-11-30 10:00', '2026-12-02 10:00'],
            JanunPage::where('status', 'scheduled')->orderBy('publish_at')->get()->map(fn ($p) => $p->publish_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'))->all());
        $this->assertSame(0, BanglaMonth::where('status', 'verified')->count(), 'no month without two pages');
    }

    public function test_it_stops_when_the_switch_is_off(): void
    {
        Handover::set('janun', false);
        $this->artisan('hatpakha:janun-run')->assertFailed();
        $this->assertSame([], $this->asked);
    }
}
