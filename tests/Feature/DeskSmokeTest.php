<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Section;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Every /desk screen renders (list, create, edit) for an admin. Catches 500s before editors do. */
class DeskSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_screen_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        $post = $this->makePost(['slug' => 'smoke', 'title' => 'সম্পাদকীয়']);
        $section = Section::where('slug', 'poetry')->first() ?? Section::create(['slug' => 'editorial', 'name_bn' => 'সম্পাদকীয়', 'name_en' => 'Editorial', 'format' => 'prose', 'sort' => 5]);
        $records = [
            'Posts' => $post,
            'Sections' => $section,
            'Authors' => Author::create(['slug' => 'a', 'display_name' => 'A']),
            'Issues' => Issue::create(['slug' => '2020-10', 'title' => 'শারদীয়া', 'kind' => 'festival', 'status' => 'published']),
            'Comments' => Comment::create(['post_id' => $post->id, 'author_name' => 'পাঠক', 'body' => 'ভালো', 'status' => 'approved']),
            'Redirects' => Redirect::create(['from_path' => '/old/', 'to_path' => '/new/', 'status_code' => 301]),
            'Users' => User::factory()->create(),
            'Pages' => Page::create(['slug' => 'about', 'title' => 'কথা', 'body_html' => '<p>x</p>']),
        ];

        foreach ($records as $res => $record) {
            $singular = rtrim($res, 's');
            $ns = "App\\Filament\\Resources\\{$res}\\Pages\\";
            Livewire::test($ns."List{$res}")->assertOk();
            if (class_exists($ns."Create{$singular}")) {
                Livewire::test($ns."Create{$singular}")->assertOk();
            }
            if (class_exists($ns."Edit{$singular}")) {
                Livewire::test($ns."Edit{$singular}", ['record' => $record->getRouteKey()])->assertOk();
            }
        }
    }
}
