import AppearanceTabs from '@/components/appearance-tabs';
import { Page, Panel } from '@/components/page';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <Page
            title="Appearance"
            description="Update the appearance settings for your account."
        >
            <Panel>
                <AppearanceTabs />
            </Panel>
        </Page>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance',
            href: editAppearance(),
        },
    ],
};
