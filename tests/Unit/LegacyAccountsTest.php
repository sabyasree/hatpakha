<?php

namespace Tests\Unit;

use App\Console\Commands\ImportWordPress as I;
use App\Enums\Role;
use PHPUnit\Framework\TestCase;

/** docs/SECURITY.md §3: least privilege for imported accounts; conservative spam flags. */
class LegacyAccountsTest extends TestCase
{
    public function test_wordpress_admins_and_editors_never_get_panel_rights(): void
    {
        $this->assertSame(Role::Writer, I::mapRole('a:1:{s:13:"administrator";b:1;}', false));
        $this->assertSame(Role::Writer, I::mapRole('a:1:{s:6:"editor";b:1;}', false));
        $this->assertSame(Role::Reader, I::mapRole('a:1:{s:10:"subscriber";b:1;}', false));
        $this->assertSame(Role::Writer, I::mapRole('a:1:{s:10:"subscriber";b:1;}', true));
        $this->assertSame('administrator', I::legacyRole('a:1:{s:13:"administrator";b:1;}'));
    }

    public function test_spam_flags_are_conservative(): void
    {
        $u = fn ($login, $name, $email, $year = '2023-05-01') => (object) ['user_login' => $login, 'display_name' => $name, 'user_email' => $email, 'user_url' => '', 'user_registered' => $year];

        $this->assertNotNull(I::spamReason($u('terrie9929', 'Delbert Mcgrath', 'x@b.bangkokremovals.com'), false));
        $this->assertNotNull(I::spamReason($u('anyone', 'Tom', 'x@tfbnw.net'), true));
        $this->assertNull(I::spamReason($u('sujoy1234', 'Sujay Adhikary', 'sujoy1234@gmail.com'), false), 'Gmail users are never flagged as bots');
        $this->assertNull(I::spamReason($u('terrie9929', 'Delbert Mcgrath', 'x@b.bangkokremovals.com'), true), 'Active accounts are never flagged as bots');
        $this->assertNull(I::spamReason($u('rina1990', 'Rina Das', 'rina@example.org', '2017-01-01'), false));
    }
}
