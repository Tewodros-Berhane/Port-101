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
const seriesColors = {
    sent: 'hsl(var(--chart-2))',
    failed: 'hsl(var(--chart-5))',
    pending: 'hsl(var(--chart-3))',
};

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
                                stopColor={seriesColors.sent}
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="95%"
                                stopColor={seriesColors.sent}
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
                                stopColor={seriesColors.failed}
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="95%"
                                stopColor={seriesColors.failed}
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
                                stopColor={seriesColors.pending}
                                stopOpacity={0.2}
                            />
                            <stop
                                offset="95%"
                                stopColor={seriesColors.pending}
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
                        stroke={seriesColors.sent}
                        fill="url(#sentFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="failed"
                        name="Failed"
                        stroke={seriesColors.failed}
                        fill="url(#failedFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="pending"
                        name="Pending"
                        stroke={seriesColors.pending}
                        fill="url(#pendingFill)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
