import { Link } from '@inertiajs/react';
import { EmptyState, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

/**
 * Where someone lands who belongs to no label and may not create one: they
 * were removed from the last label they were in.
 */
export default function NoLabel() {
    return (
        <Page
            title="You are not in a label"
            description="Your account is not part of any label at the moment."
        >
            <EmptyState
                title="Ask the label to add you again"
                description="You can be removed from a label by its admins. To get access back, ask the person who runs the label to invite you in Settings → Team. You can sign out here in the meantime."
                action={
                    <Button variant="outline" asChild>
                        <Link href={logout()} as="button">
                            Log out
                        </Link>
                    </Button>
                }
            />
        </Page>
    );
}

NoLabel.layout = { breadcrumbs: [{ title: 'No label', href: '#' }] };
