<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\User;
use App\Support\WriterNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D163 (owner): a writer shown by an old login gets the name from their own profile, or keeps it. */
class WriterNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_logins_are_told_apart_from_names_and_profile_names_are_tidied(): void
    {
        foreach (['kolomsainak007', '8257929077', 'Saha_Soma14', 'probasi', 'bijoy_bose_82@yahoo.com', 'T.Kahali'] as $login) {
            $this->assertTrue(WriterNames::loginLike($login), $login);
        }
        foreach (['Poorvita', 'Debasish Hemrom', 'SUPTHITA PAL', 'স্বপ্ন চন্দ'] as $name) {
            $this->assertFalse(WriterNames::loginLike($name), $name);
        }
        $this->assertSame('Bhaskar Das', WriterNames::fromProfile('BHASKAR', 'DAS', 'bhaskar'));
        $this->assertSame('Arnab Bhaumik', WriterNames::fromProfile('ARNAB', 'bhaumik', 'arnabbh'));
        $this->assertSame('McDonald Roy', WriterNames::fromProfile('McDonald', 'roy', 'x1'));
        $this->assertSame('সৌরজিৎ বসাক', WriterNames::fromProfile('সৌরজিৎ', 'বসাক', 'sourojit'));
        $this->assertNull(WriterNames::fromProfile('stuti', '', 'stuti'), 'the same as the login: keep as is');
        $this->assertNull(WriterNames::fromProfile('', '', 'probasi'), 'no name in the profile: keep as is');
        $this->assertNull(WriterNames::fromProfile('buy-now99', '', 'x1'));
        $this->assertNull(WriterNames::fromProfile('Visit https://spam.example', '', 'x1'));
    }

    public function test_command_uses_the_account_name_and_never_touches_a_real_name(): void
    {
        $withName = Author::create(['slug' => 'a1', 'display_name' => 'kolomsainak007', 'user_id' => User::factory()->create(['name' => 'Kalom Sainik'])->id]);
        $noName = Author::create(['slug' => 'a2', 'display_name' => 'probasi', 'user_id' => User::factory()->create(['name' => 'probasi'])->id]);
        $real = Author::create(['slug' => 'a3', 'display_name' => 'Debasish Hemrom', 'user_id' => User::factory()->create(['name' => 'someone_else'])->id]);
        $bengali = Author::create(['slug' => 'a4', 'display_name' => 'shibajidas', 'display_name_bn' => 'শিবাজী দাস', 'user_id' => User::factory()->create(['name' => 'Shibaji Das'])->id]);

        $this->artisan('hatpakha:profile-names', ['--dry' => true])->assertSuccessful();
        $this->assertSame('kolomsainak007', $withName->fresh()->display_name, 'a dry run changes nothing');

        $this->artisan('hatpakha:profile-names')->assertSuccessful();
        $this->assertSame('Kalom Sainik', $withName->fresh()->display_name);
        $this->assertSame('probasi', $noName->fresh()->display_name, 'no name in the profile: kept as is');
        $this->assertSame('Debasish Hemrom', $real->fresh()->display_name, 'a real name is never replaced');
        $this->assertSame('shibajidas', $bengali->fresh()->display_name, 'a writer with a Bengali name set is left alone');
        $this->assertSame('শিবাজী দাস', $bengali->fresh()->publicName());
    }
}
