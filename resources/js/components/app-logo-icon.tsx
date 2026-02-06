import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M12 6H22C29.1797 6 34 10.8203 34 18C34 25.1797 29.1797 30 22 30H16V36H12V6ZM16 10V26H22C26.9706 26 30 22.9706 30 18C30 13.0294 26.9706 10 22 10H16Z"
            />
        </svg>
    );
}
