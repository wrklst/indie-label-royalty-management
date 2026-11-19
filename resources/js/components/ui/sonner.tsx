import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { useEffect, useState } from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { usePlayer } from '@/components/player/player-store';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    // Toasts sit above the audio player, however tall it currently is (it wraps on phones and grows with the queue).
    const hasPlayer = usePlayer((state) => state.queue.length > 0);
    const [dockHeight, setDockHeight] = useState(0);

    useEffect(() => {
        const dock = document.querySelector<HTMLElement>(
            '[data-player="dock"]',
        );

        if (!hasPlayer || !dock) {
            setDockHeight(0);

            return;
        }

        const observer = new ResizeObserver(() =>
            setDockHeight(dock.offsetHeight),
        );
        observer.observe(dock);

        return () => observer.disconnect();
    }, [hasPlayer]);

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            offset={{ bottom: dockHeight + 24 }}
            mobileOffset={{ bottom: dockHeight + 16 }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
