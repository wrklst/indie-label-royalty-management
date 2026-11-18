import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 1a19 19 0 1 0 0 38 19 19 0 0 0 0-38Zm0 7a12 12 0 1 1 0 24 12 12 0 0 1 0-24Zm0 3.500a8.500 8.500 0 1 0 0 17 8.500 8.500 0 0 0 0-17Zm0 6a2.500 2.500 0 1 1 0 5 2.500 2.500 0 0 1 0-5Z"
            />
        </svg>
    );
}
