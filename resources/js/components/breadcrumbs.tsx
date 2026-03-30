import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    BreadcrumbEllipsis,
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const resolvedItems =
        breadcrumbs.length > 3
            ? [
                  breadcrumbs[0],
                  'ellipsis' as const,
                  ...breadcrumbs.slice(-2),
              ]
            : breadcrumbs;

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList className="gap-1 text-xs text-[color:var(--text-muted)]">
                        {resolvedItems.map((item, index) => {
                            if (item === 'ellipsis') {
                                return (
                                    <Fragment key={`ellipsis-${index}`}>
                                        <BreadcrumbItem>
                                            <BreadcrumbEllipsis />
                                        </BreadcrumbItem>
                                        <BreadcrumbSeparator />
                                    </Fragment>
                                );
                            }

                            const isLast = index === resolvedItems.length - 1;

                            if (!('title' in item)) {
                                return null;
                            }

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isLast ? (
                                            <BreadcrumbPage className="font-medium text-[color:var(--text-secondary)]">
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink
                                                asChild
                                                className="max-w-[14rem] truncate"
                                            >
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
