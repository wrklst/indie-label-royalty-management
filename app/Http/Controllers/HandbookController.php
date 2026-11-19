<?php

namespace App\Http\Controllers;

use App\Services\Handbook\Handbook;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class HandbookController extends Controller
{
    /**
     * The whole handbook: every chapter in the navigation and one article.
     */
    public function show(Handbook $handbook, ?string $slug = null): Response
    {
        $article = $slug === null ? $handbook->articles()->first() : $handbook->find($slug);

        abort_if($article === null, 404);

        return Inertia::render('handbook/index', [
            'chapters' => $handbook->articles()->groupBy('chapter')->map(fn ($articles, string $chapter): array => [
                'title' => $chapter,
                'articles' => $articles->map(fn (array $entry): array => [
                    'slug' => $entry['slug'],
                    'title' => $entry['title'],
                    'search' => mb_strtolower($entry['title'].' '.$entry['body']),
                ])->values(),
            ])->values(),
            'article' => [
                'slug' => $article['slug'],
                'title' => $article['title'],
                'chapter' => $article['chapter'],
                'html' => $handbook->html($article),
            ],
        ]);
    }

    /**
     * One article for the help panel beside the page.
     */
    public function panel(Handbook $handbook, string $slug): JsonResponse
    {
        $article = $handbook->find($slug) ?? abort(404);

        return response()->json([
            'slug' => $article['slug'],
            'title' => $article['title'],
            'chapter' => $article['chapter'],
            'html' => $handbook->html($article),
        ]);
    }
}
