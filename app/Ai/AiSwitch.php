<?php

namespace App\Ai;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Setting;

/** The owner's on/off button for AI reports (D37). Admins only; every change is logged. */
class AiSwitch
{
    public static function paused(): bool
    {
        return Setting::get('ai.paused') === '1';
    }

    public static function set(bool $paused): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        Setting::put('ai.paused', $paused ? '1' : '0');
        AuditLog::record($paused ? 'ai.paused' : 'ai.resumed');
    }
}
