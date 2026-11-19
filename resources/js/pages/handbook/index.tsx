import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { handbook } from '@/routes';

type Props = {
    chapters: {
        title: string;
        articles: { slug: string; title: string; search: string }[];
    }[];
    article: { slug: string; title: string; chapter: string; html: string };
};

export default function Handbook({ chapters, article }: Props) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const visible = chapters
        .map((chapter) => ({
            ...chapter,
            articles: chapter.articles.filter(
                (entry) => !needle || entry.search.includes(needle),
            ),
        }))
        .filter((chapter) => chapter.articles.length > 0);

    return (
        <>
            <Head title={`${article.title} · Handbook`} />
            <div className="grid gap-6 p-4 md:p-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <nav aria-label="Handbook chapters" className="space-y-4">
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search the handbook"
                        aria-label="Search the handbook"
                    />
                    {visible.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No article mentions “{query}”.
                        </p>
                    )}
                    {visible.map((chapter) => (
                        <div key={chapter.title} className="space-y-1">
                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {chapter.title}
                            </p>
                            <ul>
                                {chapter.articles.map((entry) => (
                                    <li key={entry.slug}>
                                        <Link
                                            href={handbook(entry.slug)}
                                            aria-current={
                                                entry.slug === article.slug
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={cn(
                                                'block rounded-md px-2 py-1 text-sm hover:bg-muted',
                                                entry.slug === article.slug &&
                                                    'bg-muted font-medium',
                                            )}
                                        >
                                            {entry.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>
                <article className="max-w-3xl min-w-0">
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        {article.chapter}
                    </p>
                    <h1 className="mb-4 text-xl font-semibold tracking-tight">
                        {article.title}
                    </h1>
                    <div
                        className="handbook-prose"
                        dangerouslySetInnerHTML={{ __html: article.html }}
                    />
                </article>
            </div>
        </>
    );
}

Handbook.layout = {
    breadcrumbs: [{ title: 'Handbook', href: handbook() }],
};
