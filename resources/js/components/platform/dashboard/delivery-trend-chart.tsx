import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type DeliveryTrendRow = {
    date: string;
    sent: number;
    failed: number;
    pending: number;
};

type Props = {
    rows: DeliveryTrendRow[];
};

const axisTickStyle = { fill: 'hsl(var(--muted-foreground))', fontSize: 12 };

const formatDayLabel = (value: unknown): string => {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return '';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
};

const formatTooltipLabel = (value: unknown): string => formatDayLabel(value);

export default function DeliveryTrendChart({ rows }: Props) {
    if (rows.length === 0) {
        return (
            <div className="flex h-72 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No delivery trend data available.
            </div>
        );
    }

    return (
        <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={rows}>
                    <defs>
                        <linearGradient
                            id="sentFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="hsl(var(--primary))"
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="95%"
                                stopColor="hsl(var(--primary))"
                                stopOpacity={0.05}
                            />
                        </linearGradient>
                        <linearGradient
                            id="failedFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="hsl(var(--destructive))"
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="95%"
                                stopColor="hsl(var(--destructive))"
                                stopOpacity={0.05}
                            />
                        </linearGradient>
                        <linearGradient
                            id="pendingFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="hsl(var(--muted-foreground))"
                                stopOpacity={0.2}
                            />
                            <stop
                                offset="95%"
                                stopColor="hsl(var(--muted-foreground))"
                                stopOpacity={0.05}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke="hsl(var(--border))"
                    />
                    <XAxis
                        dataKey="date"
                        minTickGap={20}
                        tickFormatter={formatDayLabel}
                        tick={axisTickStyle}
                    />
                    <YAxis allowDecimals={false} tick={axisTickStyle} />
                    <Tooltip
                        contentStyle={{
                            background: 'hsl(var(--card))',
                            border: '1px solid hsl(var(--border))',
                            borderRadius: '0.75rem',
                        }}
                        labelFormatter={(label) => formatTooltipLabel(label)}
                    />
                    <Legend />
                    <Area
                        type="monotone"
                        dataKey="sent"
                        name="Sent"
                        stroke="hsl(var(--primary))"
                        fill="url(#sentFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="failed"
                        name="Failed"
                        stroke="hsl(var(--destructive))"
                        fill="url(#failedFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="pending"
                        name="Pending"
                        stroke="hsl(var(--muted-foreground))"
                        fill="url(#pendingFill)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
