import { Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Props = {
    sent: number;
    failed: number;
    pending: number;
};

const COLORS = {
    sent: 'hsl(var(--primary))',
    failed: 'hsl(var(--destructive))',
    pending: 'hsl(var(--muted-foreground))',
};

export default function DeliveryStatusDonut({ sent, failed, pending }: Props) {
    const rows = [
        { name: 'Sent', value: sent, fill: COLORS.sent },
        { name: 'Failed', value: failed, fill: COLORS.failed },
        { name: 'Pending', value: pending, fill: COLORS.pending },
    ].filter((row) => row.value > 0);

    if (rows.length === 0) {
        return (
            <div className="flex h-56 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No invite delivery volume in this window.
            </div>
        );
    }

    return (
        <div className="h-56 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie
                        data={rows}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={55}
                        outerRadius={85}
                        paddingAngle={3}
                    />
                    <Tooltip
                        contentStyle={{
                            background: 'hsl(var(--card))',
                            border: '1px solid hsl(var(--border))',
                            borderRadius: '0.75rem',
                        }}
                    />
                </PieChart>
            </ResponsiveContainer>
            <div className="mt-3 flex flex-wrap items-center gap-3 text-xs">
                <span className="inline-flex items-center gap-2 text-muted-foreground">
                    <span
                        className="size-2 rounded-full"
                        style={{ background: COLORS.sent }}
                    />
                    Sent
                </span>
                <span className="inline-flex items-center gap-2 text-muted-foreground">
                    <span
                        className="size-2 rounded-full"
                        style={{ background: COLORS.failed }}
                    />
                    Failed
                </span>
                <span className="inline-flex items-center gap-2 text-muted-foreground">
                    <span
                        className="size-2 rounded-full"
                        style={{ background: COLORS.pending }}
                    />
                    Pending
                </span>
            </div>
        </div>
    );
}
