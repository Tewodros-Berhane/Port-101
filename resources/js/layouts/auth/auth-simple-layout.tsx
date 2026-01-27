import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { Link } from '@inertiajs/react';
import { Boxes } from 'lucide-react';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#f7f7f4] p-6 md:p-10">
            <div className="w-full max-w-md">
                <div className="flex flex-col gap-6 rounded-3xl border border-slate-200 bg-white p-8 text-slate-900 shadow-sm">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium text-slate-900"
                        >
                            <div className="mb-1 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md">
                                <Boxes className="size-5" />
                            </div>
                            <span className="text-sm font-semibold">
                                Port-101
                            </span>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-semibold text-slate-900">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-slate-600">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
