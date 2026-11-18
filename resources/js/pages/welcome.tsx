import { Head, Link, usePage } from '@inertiajs/react';
import { Disc3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Royalty accounting for record labels" />
            <div className="flex min-h-screen flex-col items-center justify-center gap-8 bg-background p-6 text-foreground">
                <div className="flex size-14 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <Disc3 className="size-8" />
                </div>
                <div className="max-w-xl space-y-3 text-center">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {name}
                    </h1>
                    <p className="text-muted-foreground">
                        Royalty accounting and release management for
                        independent record labels: import distributor
                        statements, split income by contract, send statements to
                        your artists and keep every release with its files in
                        one place.
                    </p>
                </div>
                <div className="flex gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Open dashboard</Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
