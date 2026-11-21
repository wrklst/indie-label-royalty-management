import { usePage } from '@inertiajs/react';
import { ChevronDown, Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    bandcampPackage,
    metadata,
    packageMethod,
    subvertPackage,
    zebralutionPackage,
} from '@/routes/releases';

/**
 * One button for everything a release can be delivered as. Each option is
 * a plain download; when a package cannot be made the server sends the
 * browser back with the reasons, which are listed in a dialog.
 */
export function DeliveryMenu({ releaseId }: { releaseId: number }) {
    const { errors, label } = usePage().props;
    const store = errors?.zebralution_package
        ? 'Zebralution'
        : errors?.bandcamp_package
          ? 'Bandcamp'
          : 'Subvert';
    const problems = (
        errors?.zebralution_package ??
        errors?.bandcamp_package ??
        errors?.subvert_package ??
        ''
    )
        .split('\n')
        .filter((problem) => problem !== '');
    const [open, setOpen] = useState(problems.length > 0);

    const uses = (service: string) => label?.services.includes(service);
    const options = [
        {
            label: 'Full package',
            description: 'All masters, cover, artwork, documents and metadata.',
            href: packageMethod.url(releaseId),
        },
        ...(uses('subvert')
            ? [
                  {
                      label: 'Subvert ZIP',
                      description:
                          "Cover and one audio file per track, ready for Subvert's upload form.",
                      href: subvertPackage.url(releaseId),
                  },
              ]
            : []),
        ...(uses('bandcamp')
            ? [
                  {
                      label: 'Bandcamp ZIP',
                      description:
                          'Cover, one lossless file per track and a sheet to copy from, to unpack and add on Bandcamp.',
                      href: bandcampPackage.url(releaseId),
                  },
              ]
            : []),
        ...(uses('zebralution')
            ? [
                  {
                      label: 'Zebralution ZIP',
                      description:
                          "Zebralution's metadata sheet filled in, the cover, and the WAV masters named for its FTP server.",
                      href: zebralutionPackage.url(releaseId),
                  },
              ]
            : []),
        {
            label: 'Metadata CSV',
            description: 'Release and track metadata as a spreadsheet.',
            href: metadata.url({ query: { release: releaseId } }),
        },
    ];

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download />
                        Delivery
                        <ChevronDown className="opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-80">
                    <DropdownMenuLabel>Download as</DropdownMenuLabel>
                    {options.map((option) => (
                        <DropdownMenuItem key={option.label} asChild>
                            <a
                                href={option.href}
                                className="flex flex-col items-start gap-0.5"
                            >
                                <span className="font-medium">
                                    {option.label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {option.description}
                                </span>
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            The {store} ZIP cannot be made yet
                        </DialogTitle>
                        <DialogDescription>
                            {store === 'Zebralution'
                                ? 'Zebralution sends a release back when any of this is missing. Fix the following, then try again.'
                                : `${store} needs the cover and one audio file per track. Fix the following under Files, then try again.`}
                        </DialogDescription>
                    </DialogHeader>
                    <ul
                        role="alert"
                        className="grid list-disc gap-1 pl-5 text-sm"
                    >
                        {problems.map((problem) => (
                            <li key={problem}>{problem}</li>
                        ))}
                    </ul>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
