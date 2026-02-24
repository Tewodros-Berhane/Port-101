import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type GovernanceTimeSeriesRow = {
    date: string;
    notifications_total: number;
    escalations_triggered: number;
    escalations_acknowledged: number;
    digests_sent: number;
    digests_opened: number;
};

type Props = {
    rows: GovernanceTimeSeriesRow[];
};

const axisTickStyle = { fill: 'var(--muted-foreground)', fontSize: 12 };

const formatDateLabel = (value: string) => {
    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
};

export default function GovernanceTimeSeriesChart({ rows }: Props) {
    if (rows.length === 0) {
        return (
            <div className="flex h-72 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No governance trend data available.
            </div>
        );
    }

    return (
        <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={rows} margin={{ left: 12, right: 12 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                    <XAxis
                        dataKey="date"
                        tickFormatter={formatDateLabel}
                        tick={axisTickStyle}
                        minTickGap={24}
                    />
                    <YAxis allowDecimals={false} tick={axisTickStyle} />
                    <Tooltip
                        contentStyle={{
                            background: 'var(--card)',
                            border: '1px solid var(--border)',
                            borderRadius: '0.75rem',
                        }}
                        labelFormatter={(value) => formatDateLabel(String(value))}
                    />
                    <Legend />
                    <Line
                        type="monotone"
                        dataKey="notifications_total"
                        name="Notifications"
                        stroke="var(--chart-1)"
                        strokeWidth={2}
                        dot={false}
                    />
                    <Line
                        type="monotone"
                        dataKey="escalations_triggered"
                        name="Escalations"
                        stroke="var(--chart-5)"
                        strokeWidth={2}
                        dot={false}
                    />
                    <Line
                        type="monotone"
                        dataKey="escalations_acknowledged"
                        name="Escalations acknowledged"
                        stroke="var(--chart-2)"
                        strokeWidth={2}
                        dot={false}
                    />
                    <Line
                        type="monotone"
                        dataKey="digests_sent"
                        name="Digests sent"
                        stroke="var(--chart-3)"
                        strokeWidth={2}
                        dot={false}
                    />
                    <Line
                        type="monotone"
                        dataKey="digests_opened"
                        name="Digests opened"
                        stroke="var(--chart-4)"
                        strokeWidth={2}
                        dot={false}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}
