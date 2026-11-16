<?php

use App\Models\EditorTask;
use App\Models\Serial;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * D218 (owner: "for the detective series I want the episodes minimum 15"): a case planned under the old five-or-six
 * rule that the owner has not started (nothing of it given a date or published) is withdrawn, so a full-length case
 * is planned in its place straight away. A case already started is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        rescue(function () {
            foreach (Serial::where('writer', 'serial_detective')->where('status', 'proposed')->get() as $case) {
                if ($case->plannedEpisodes() >= 15 || $case->episodes()->whereIn('status', ['scheduled', 'published'])->exists()) {
                    continue;
                }
                $case->episodes()->get()->each->delete();   // drafts nobody has seen outside the desk; recoverable
                EditorTask::where('status', 'open')->where('subject_type', $case->getMorphClass())->where('subject_id', $case->id)->get()->each->close('dismissed');
                $case->forceFill(['status' => 'dropped'])->save();
            }
            Setting::put('handover.case_tried', '');
            EditorTask::where('type', 'alert')->where('status', 'open')->where('key', 'handover.case_waiting')->get()->each->close('dismissed');
        }, null, false);
    }

    public function down(): void
    {
    }
};
