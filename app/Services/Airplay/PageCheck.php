<?php

namespace App\Services\Airplay;

use App\Support\PublicAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Opens a page the search found and looks whether it really names the
 * track: a search result only shows a snippet, and often a loose match.
 */
class PageCheck
{
    /** Artist and title are both on the page. */
    public const string CONFIRMED = 'confirmed';

    /** Only the artist or only the title is on the page. */
    public const string PARTIAL = 'partial';

    /** The page opened but names neither; it may fill in its content with JavaScript. */
    public const string ABSENT = 'absent';

    public const string UNREACHABLE = 'unreachable';

    private const int MAX_BYTES = 2_000_000;

    /**
     * @return array{verification: string, page_title: string|null, text: string, excerpt: string|null}
     */
    public function check(string $url, ?string $artist, string $title): array
    {
        $failed = ['verification' => self::UNREACHABLE, 'page_title' => null, 'text' => '', 'excerpt' => null];

        // The address comes from a search result: it must not lead into this server or its network.
        if (! PublicAddress::is($url)) {
            return $failed;
        }

        try {
            $response = Http::withUserAgent('Mozilla/5.0 (compatible; airplay-research)')
                ->accept('text/html')->connectTimeout(5)->timeout(12)->withOptions([
                    'stream' => true,
                    'allow_redirects' => ['max' => 3, 'on_redirect' => function ($request, $response, UriInterface $target): void {
                        if (! PublicAddress::is((string) $target)) {
                            throw new RuntimeException('The page redirects to an address that is not public.');
                        }
                    }],
                ])->get($url);

            if ($response->failed()) {
                return $failed;
            }

            // Read no more than is looked at, however much the page sends.
            $html = $response->toPsrResponse()->getBody()->read(self::MAX_BYTES);
        } catch (Throwable) {
            return $failed;
        }

        $pageTitle = preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) === 1 ? $this->clean($match[1]) : null;
        $text = $this->clean((string) preg_replace('/<(script|style|noscript|title)\b.*?<\/\1>/is', ' ', $html));
        $lower = Str::lower($text);

        $hasTitle = str_contains($lower, Str::lower($title));
        $hasArtist = $artist !== null && str_contains($lower, Str::lower($artist));
        $position = mb_stripos($text, $hasTitle ? $title : (string) $artist);

        return [
            'verification' => match (true) {
                $hasTitle && ($hasArtist || $artist === null) => self::CONFIRMED,
                $hasTitle || $hasArtist => self::PARTIAL,
                default => self::ABSENT,
            },
            'page_title' => $pageTitle === null ? null : mb_substr($pageTitle, 0, 500),
            'text' => $text,
            'excerpt' => ($hasTitle || $hasArtist) && $position !== false ? trim(mb_substr($text, max(0, $position - 160), 400)) : null,
        ];
    }

    private function clean(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('/<[^>]+>/', ' $0', $html)), ENT_QUOTES | ENT_HTML5)));
    }
}
