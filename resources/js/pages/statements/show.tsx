import { Link, router, setLayoutProps } from '@inertiajs/react';
import { Download, Eye, EyeOff } from 'lucide-react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import { DeleteButton } from '@/components/delete-button';
import { Page } from '@/components/page';
import { StatementView } from '@/components/statement-view';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { date } from '@/lib/format';
import { show as showPeriod } from '@/routes/periods';
import { download as portalDownload, index as portal } from '@/routes/portal';
import { download, index, show } from '@/routes/statements';
import type { StatementData } from '@/types';

type Props = {
    statement: {
        id: number;
        name: string;
        archived: boolean;
        revision_of_id: number | null;
        period_id: number;
        published: boolean;
        created_at: string;
        data: StatementData;
    };
    statementLabel?: { id: number; name: string; logo_url: string | null };
    portal: boolean;
};

export default function ShowStatement({
    statement,
    statementLabel,
    portal: isPortal,
}: Props) {
    const data = statement.data;

    setLayoutProps({
        breadcrumbs: [
            isPortal
                ? { title: 'My statements', href: portal() }
                : { title: 'Statements', href: index() },
            { title: statement.name, href: '#' },
        ],
    });

    return (
        <Page
            title={statement.name}
            description={
                <span className="flex flex-wrap items-center gap-2">
                    {!isPortal &&
                        (statement.archived ? (
                            <Badge variant="outline">Archived revision</Badge>
                        ) : statement.published ? (
                            <Badge variant="success">Published to payee</Badge>
                        ) : (
                            <Badge variant="warning">
                                Draft – not visible to the payee
                            </Badge>
                        ))}
                    <span>
                        {date(data.period.starts_on)} –{' '}
                        {date(data.period.ends_on)} · generated{' '}
                        {date(statement.created_at)}
                    </span>
                    {!isPortal && statement.revision_of_id && (
                        <Link
                            href={show(statement.revision_of_id)}
                            className="underline underline-offset-4"
                        >
                            previous revision
                        </Link>
                    )}
                </span>
            }
            actions={
                <>
                    <Button variant="outline" asChild>
                        <a
                            href={
                                isPortal
                                    ? portalDownload.url(statement.id)
                                    : download.url(statement.id)
                            }
                        >
                            <Download />
                            Download CSV
                        </a>
                    </Button>
                    {isPortal ? (
                        <Button variant="outline" asChild>
                            <Link href={portal()}>My statements</Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={showPeriod(statement.period_id)}>
                                    Period
                                </Link>
                            </Button>
                            {!statement.archived && (
                                <Button
                                    variant={
                                        statement.published
                                            ? 'outline'
                                            : 'default'
                                    }
                                    onClick={() =>
                                        router.post(
                                            StatementController.publish.url(
                                                statement.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {statement.published ? <EyeOff /> : <Eye />}
                                    {statement.published
                                        ? 'Unpublish'
                                        : 'Publish to payee'}
                                </Button>
                            )}
                            <DeleteButton
                                url={StatementController.destroy.url(
                                    statement.id,
                                )}
                                label="Delete"
                                title={`Delete ${statement.name}?`}
                                description="The payee loses access to it. Transactions and payouts are not affected, and you can generate it again from the period."
                            />
                        </>
                    )}
                </>
            }
        >
            <StatementView data={data} brand={statementLabel} />
        </Page>
    );
}
