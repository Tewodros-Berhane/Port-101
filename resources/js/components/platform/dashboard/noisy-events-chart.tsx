import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type NoisyEventRow = {
    event: string;
    count: number;
    unread: number;
    high_or_critical: number;
};

type Props = {
    rows: NoisyEventRow[];
};

const axisTickStyle = { fill: 'hsl(var(--muted-foreground))', fontSize: 12 };

const truncateEventLabel = (value: string) => {
    if (value.length <= 24) {
        return value;
    }

    return `${value.slice(0, 21)}...`;
};

export default function NoisyEventsChart({ rows }: Props) {
    const data = rows.slice(0, 6);

    if (data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No noisy events detected.
            </div>
        );
    }

    return (
        <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={data}
                    layout="vertical"
                    margin={{ left: 12, right: 12 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke="hsl(var(--border))"
                    />
                    <XAxis
                        type="number"
                        allowDecimals={false}
                        tick={axisTickStyle}
                    />
                    <YAxis
                        type="category"
                        dataKey="event"
                        width={160}
                        tickFormatter={truncateEventLabel}
                        tick={axisTickStyle}
                    />
                    <Tooltip
                        contentStyle={{
                            background: 'hsl(var(--card))',
                            border: '1px solid hsl(var(--border))',
                            borderRadius: '0.75rem',
                        }}
                    />
                    <Bar
                        dataKey="count"
                        name="Notifications"
                        fill="hsl(var(--primary))"
                        radius={[0, 6, 6, 0]}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
