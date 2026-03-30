import { Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Props = {
    pending: number;
    accepted: number;
    expired: number;
};

export const inviteStatusColors = {
    pending: 'var(--chart-3)',
    accepted: 'var(--chart-2)',
    expired: 'var(--chart-5)',
} as const;

export default function InviteStatusChart({
    pending,
    accepted,
    expired,
}: Props) {
    const rows = [
        { name: 'Pending', value: pending, fill: inviteStatusColors.pending },
        { name: 'Accepted', value: accepted, fill: inviteStatusColors.accepted },
        { name: 'Expired', value: expired, fill: inviteStatusColors.expired },
    ].filter((row) => row.value > 0);

    if (rows.length === 0) {
        return (
            <div className="flex h-56 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No invite records yet.
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
                        innerRadius={58}
                        outerRadius={84}
                        paddingAngle={rows.length > 1 ? 2 : 0}
                        label={false}
                        labelLine={false}
                        stroke="var(--card)"
                        strokeWidth={2}
                        isAnimationActive={false}
                    />
                    <Tooltip
                        contentStyle={{
                            background: 'var(--card)',
                            border: '1px solid var(--border)',
                            borderRadius: '0.75rem',
                        }}
                    />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}
