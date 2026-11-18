import type { Auth } from '@/types/auth';
import type { CurrentLabel } from '@/types/royalties';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            label: CurrentLabel | null;
            labels: { id: number; name: string }[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
