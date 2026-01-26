import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';

export function CompanySwitcher() {
    const { company, companies } = usePage<SharedData>().props;

    if (!company) {
        return null;
    }

    const handleSwitch = (companyId: string) => {
        if (companyId === company.id) {
            return;
        }

        router.post('/company/switch', {
            company_id: companyId,
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="gap-2"
                    data-test="company-switcher"
                >
                    <Building2 className="size-4" />
                    <span className="max-w-[180px] truncate">
                        {company.name}
                    </span>
                    {companies.length > 1 && (
                        <ChevronsUpDown className="size-4 opacity-70" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56">
                <DropdownMenuLabel>Company</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {companies.map((item) => (
                    <DropdownMenuItem
                        key={item.id}
                        onClick={() => handleSwitch(item.id)}
                        className="flex items-center justify-between"
                    >
                        <span className="truncate">{item.name}</span>
                        {item.id === company.id && (
                            <Check className="size-4 text-muted-foreground" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
