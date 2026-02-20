import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Download } from 'lucide-react';

type Props = {
    adminActionsCsvUrl: string;
    adminActionsJsonUrl: string;
    deliveryTrendsCsvUrl: string;
    deliveryTrendsJsonUrl: string;
};

export default function OperationsExportMenu({
    adminActionsCsvUrl,
    adminActionsJsonUrl,
    deliveryTrendsCsvUrl,
    deliveryTrendsJsonUrl,
}: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" type="button">
                    <Download className="size-4" />
                    Export reports
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Admin actions</DropdownMenuLabel>
                <DropdownMenuItem asChild>
                    <a href={adminActionsCsvUrl}>Download CSV</a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={adminActionsJsonUrl}>Download JSON</a>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Delivery trends</DropdownMenuLabel>
                <DropdownMenuItem asChild>
                    <a href={deliveryTrendsCsvUrl}>Download CSV</a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={deliveryTrendsJsonUrl}>Download JSON</a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
