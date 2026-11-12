<?php

namespace App\Support;

use App\Models\Collection;

/**
 * A collection (বর্ষসেরা and other সংকলন) read as a magazine (owner, 2026-11-12). The magazine template knows an issue's
 * title, slug, cover, opening note, date and address; this gives a collection the same shape, so both share one designer.
 */
final class MagazineBook
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $cover_path,
        public ?string $proposed_cover_path,
        public ?string $editor_note,
        public $published_at,
        private string $address,
    ) {}

    public static function fromCollection(Collection $c): self
    {
        return new self($c->title, 'sonkolon-'.($c->slug ?: $c->id), $c->cover_path, $c->proposed_cover_path, $c->intro,
            $c->approved_at, $c->slug ? $c->url() : Url::to('/sonkolon'));
    }

    public function url(): string
    {
        return $this->address;
    }

    /** The words that differ between an issue and a collection. */
    public const WORDS = ['in' => 'এই সংকলনে', 'of' => 'এই সংকলনের', 'back' => 'সংকলনের পাতায় ফিরুন', 'ed' => 'ভূমিকা', 'note' => 'সংকলকের কথা'];
}
