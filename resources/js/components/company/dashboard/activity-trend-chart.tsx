import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type ActivityTrendRow = {
    date: string;
    audits: number;
    invites: number;
};

type Props = {
    rows: ActivityTrendRow[];
};

const axisTickStyle = { fill: 'var(--muted-foreground)', fontSize: 12 };

const formatDate = (value: unknown): string => {
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

export default function ActivityTrendChart({ rows }: Props) {
    if (rows.length === 0) {
        return (
            <div className="flex h-72 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No activity trend data available.
            </div>
        );
    }

    return (
        <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={rows}>
                    <defs>
                        <linearGradient
                            id="companyAuditsFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.35}
                            />
                            <stop
                                offset="95%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.05}
                            />
                        </linearGradient>
                        <linearGradient
                            id="companyInvitesFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="var(--chart-2)"
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="95%"
                                stopColor="var(--chart-2)"
                                stopOpacity={0.04}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke="var(--border)"
                    />
                    <XAxis
                        dataKey="date"
                        minTickGap={20}
                        tickFormatter={formatDate}
                        tick={axisTickStyle}
                    />
                    <YAxis allowDecimals={false} tick={axisTickStyle} />
                    <Tooltip
                        labelFormatter={(label) => formatDate(label)}
                        contentStyle={{
                            background: 'var(--card)',
                            border: '1px solid var(--border)',
                            borderRadius: '0.75rem',
                        }}
                    />
                    <Area
                        type="monotone"
                        dataKey="audits"
                        name="Audit events"
                        stroke="var(--chart-1)"
                        fill="url(#companyAuditsFill)"
                        strokeWidth={2}
                    />
                    <Area
                        type="monotone"
                        dataKey="invites"
                        name="Invites created"
                        stroke="var(--chart-2)"
                        fill="url(#companyInvitesFill)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
