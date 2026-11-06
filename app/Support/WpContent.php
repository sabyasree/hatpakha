<?php

namespace App\Support;

/**
 * Converts WordPress content to plain HTML before it is sanitised (docs/ARCHITECTURE.md, import).
 * Handles the shortcodes that actually occur in the Hatpakha archive; unknown plugin shortcodes are
 * dropped, but ordinary bracketed text like "[Bio]" or "[Source]" is left alone.
 */
class WpContent
{
    /** Plugin shortcode names whose tags are removed (inner text kept). */
    private const DROP = 'vc_[a-z_]+|tdc_[a-z_]+|td_[a-z_]+|authoravatars|contact-form-7|mc4wp_form|WpProQuiz|wpuf_[a-z_]+|et_pb_[a-z_]+';

    /**
     * @param  callable(array<int,int>):array<int,array{src:string,alt:string}>  $attachments  gallery ids → images
     */
    public static function convert(string $html, ?callable $attachments = null): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        // Same-site absolute links → root-relative, so they work on staging and never mix http/https.
        $html = preg_replace('~https?://(?:www\.)?hatpakha\.com(?=/)~i', '', $html);

        // [vc_raw_html] holds base64 widget code: never carried over.
        $html = preg_replace('~\[vc_raw_(?:html|js)[^\]]*\].*?\[/vc_raw_(?:html|js)\]~s', '', $html);
        // Headings and FAQ toggles from the page builder.
        $html = preg_replace_callback('~\[vc_custom_heading[^\]]*?text="([^"]*)"[^\]]*\]~', fn ($m) => "\n\n<h2>".e(html_entity_decode($m[1]))."</h2>\n\n", $html);
        $html = preg_replace_callback('~\[vc_toggle[^\]]*?title="([^"]*)"[^\]]*\](.*?)\[/vc_toggle\]~s', fn ($m) => "\n\n<h3>".e(html_entity_decode($m[1]))."</h3>\n\n".trim($m[2])."\n\n", $html);

        // [caption]<img …> text[/caption] → figure + figcaption.
        $html = preg_replace_callback('~\[caption[^\]]*\]\s*(?:<a [^>]*>)?\s*(<img[^>]*>)\s*(?:</a>)?\s*(.*?)\[/caption\]~s',
            fn ($m) => "\n\n<figure>".$m[1].(trim($m[2]) !== '' ? '<figcaption>'.trim($m[2]).'</figcaption>' : '')."</figure>\n\n", $html);

        // [embed]https://youtube…[/embed] → a plain YouTube link on its own line (rendered as a player by Embeds).
        $html = preg_replace_callback('~\[embed[^\]]*\]\s*(\S+?)\s*\[/embed\]~', fn ($m) => "\n\n<p><a href=\"".e($m[1]).'">'.e($m[1])."</a></p>\n\n", $html);

        // [gallery ids="1,2,3"] → one figure per image.
        $html = preg_replace_callback('~\[gallery[^\]]*?ids="([\d,\s]+)"[^\]]*\]~', function ($m) use ($attachments) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $m[1]))));
            if (! $attachments || ! $ids) {
                return '';
            }
            $out = '';
            foreach ($attachments($ids) as $img) {
                $out .= '<figure><img src="'.e($img['src']).'" alt="'.e($img['alt']).'"></figure>'."\n";
            }

            return "\n\n".$out."\n";
        }, $html);

        // Remaining plugin shortcodes: drop the tags, keep any text between them.
        $html = preg_replace('~\[/?(?:'.self::DROP.')(?:\s[^\]]*)?\]~', '', $html);

        return trim(preg_replace("/\n{3,}/", "\n\n", $html));
    }
}
