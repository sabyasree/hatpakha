<?php

namespace Tests\Feature;

use App\Console\Commands\ImportWordPress;
use App\Filament\Resources\Posts\Pages\EditPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2: the final WordPress re-import must never overwrite an editor's work. */
class ReimportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_in_desk_marks_the_post_and_the_importer_then_skips_it(): void
    {
        $post = $this->makePost(['slug' => 'edited', 'legacy_wp_id' => 555]);
        $this->assertFalse(ImportWordPress::editedInDesk(555));

        $page = new EditPost();
        $data = (fn (array $d) => $this->mutateFormDataBeforeSave($d))->call($page, ['title' => 'নতুন শিরোনাম']);
        $this->assertNotNull($data['edited_in_desk_at']);

        $post->forceFill($data)->save();
        $this->assertTrue(ImportWordPress::editedInDesk(555));
    }

    public function test_applied_audit_decisions_survive_a_reimport(): void
    {
        $post = $this->makePost(['slug' => 'removed', 'legacy_wp_id' => 777]);
        $this->assertFalse(ImportWordPress::editedInDesk(777));
        $post->forceFill(['status' => 'archived', 'audit_decision' => 'applied'])->saveQuietly();

        $this->assertTrue(ImportWordPress::editedInDesk(777));
    }
}
