import { login } from '@/routes';
import { dashboard as companyDashboard } from '@/routes/company';
import { dashboard as platformDashboard } from '@/routes/platform';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    Check,
    Gauge,
    Layers,
    LineChart,
    Play,
    ShieldCheck,
    Sparkles,
    Star,
    Workflow,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const lazySectionStyle = {
    contentVisibility: 'auto',
    containIntrinsicSize: '760px',
} as const;

const navLinks = [
    { label: 'Features', href: '#features' },
    { label: 'Demo', href: '#demo' },
    { label: 'Pricing', href: '#pricing' },
];

const heroBullets = [
    { emoji: '⚡', text: 'Launch in weeks with guided setup' },
    { emoji: '🧭', text: 'Clear, connected workflows across teams' },
    { emoji: '📊', text: 'Live KPIs to act fast and stay aligned' },
];

const heroBadges = [
    {
        label: 'Inventory accuracy',
        value: '99.2%',
        className: 'right-6 top-6',
    },
    {
        label: 'Close in days',
        value: '5.3',
        className: 'left-6 bottom-10',
    },
    {
        label: 'Cash forecast',
        value: '$124k',
        className: 'right-10 bottom-24',
    },
];

const featureItems = [
    {
        title: 'Unified operations',
        description:
            'Sales, inventory, purchasing, and finance in one connected flow.',
        icon: Workflow,
    },
    {
        title: 'Fast, clean setup',
        description:
            'Opinionated defaults and guided configuration get teams live quickly.',
        icon: Sparkles,
    },
    {
        title: 'Built to scale',
        description:
            'Modular architecture lets you add only what you need as you grow.',
        icon: Layers,
    },
    {
        title: 'Reliable insights',
        description:
            'Real-time KPIs and reports for clear, confident decisions.',
        icon: LineChart,
    },
    {
        title: 'Operational guardrails',
        description:
            'Approvals and audit trails keep every change accountable.',
        icon: ShieldCheck,
    },
    {
        title: 'Performance ready',
        description: 'Designed for speed, clarity, and dependable daily use.',
        icon: Gauge,
    },
];

const featureHighlights = [
    { value: '48%', label: 'Less manual work' },
    { value: '3x', label: 'Faster approvals' },
    { value: '99.9%', label: 'Inventory confidence' },
];

const proofStats = [
    { value: '4.9', label: 'Average satisfaction' },
    { value: '38%', label: 'Faster close cycle' },
    { value: '22%', label: 'Lower overhead' },
    { value: '65%', label: 'Fewer manual steps' },
];

const testimonials = [
    {
        name: 'Nora Fields',
        role: 'Operations Director',
        quote: 'We finally have one system that every team trusts and uses daily.',
        initials: 'NF',
    },
    {
        name: 'Luis Moreno',
        role: 'Finance Lead',
        quote: 'Month-end close is smooth and predictable now. No more surprises.',
        initials: 'LM',
    },
    {
        name: 'Aria Chen',
        role: 'Inventory Manager',
        quote: 'We can see exactly what is in stock and what is moving, instantly.',
        initials: 'AC',
    },
];

const logoItems = [
    'Northwind',
    'Brightlane',
    'Harborline',
    'Stonebridge',
    'Arrowfield',
    'Cedar & Co',
];

const successStory = {
    title: 'Port-101 brought every workflow into one timeline.',
    description:
        'A mid-sized distributor reduced order processing time by 41% after unifying sales and inventory workflows inside Port-101.',
};

const demoTabs = [
    {
        id: 'builder',
        label: 'Builder',
        title: 'Build workflows in minutes',
        description:
            'Configure approvals, default values, and rules without complex setup.',
        bullets: [
            'Drag-and-define stages',
            'Template-based modules',
            'Instant validation',
        ],
        metrics: ['7 min setup', '4 step flow', '1 source of truth'],
    },
    {
        id: 'analytics',
        label: 'Analytics',
        title: 'See the numbers that matter',
        description:
            'Real-time dashboards keep teams aligned with cash, inventory, and sales.',
        bullets: ['Live cash forecast', 'Inventory turns', 'Sales velocity'],
        metrics: ['6 dashboards', '12 KPI cards', '1 click export'],
    },
    {
        id: 'distribution',
        label: 'Distribution',
        title: 'Deliver on time, every time',
        description:
            'Track orders, stock, and fulfillment with confidence and accuracy.',
        bullets: [
            'Warehouse picks',
            'Shipment tracking',
            'Backorder visibility',
        ],
        metrics: ['98% on time', '5 day lead', '2x accuracy'],
    },
];

const benefitStats = [
    { value: '18 hrs', label: 'Saved per week' },
    { value: '3x', label: 'Faster approvals' },
    { value: '24%', label: 'More cash visibility' },
    { value: '0', label: 'Spreadsheet handoffs' },
];

const comparisonRows = [
    {
        label: 'Setup time',
        traditional: 'Months of configuration',
        port: 'Weeks with guided setup',
    },
    {
        label: 'Workflow clarity',
        traditional: 'Disconnected tools',
        port: 'Single timeline view',
    },
    {
        label: 'Reporting speed',
        traditional: 'Manual rollups',
        port: 'Live dashboards',
    },
    {
        label: 'Team adoption',
        traditional: 'Training heavy',
        port: 'Intuitive daily use',
    },
];

const pricingTiers = [
    {
        name: 'Starter',
        price: '$59',
        description: 'For lean teams getting operational visibility fast.',
        features: ['Core modules', '3 workflows', 'Email support'],
    },
    {
        name: 'Growth',
        price: '$129',
        description: 'For growing teams ready to scale operations.',
        features: ['All modules', 'Unlimited workflows', 'Priority support'],
        highlight: true,
    },
    {
        name: 'Enterprise',
        price: 'Custom',
        description: 'For complex operations with advanced needs.',
        features: ['Custom modules', 'Dedicated success', 'Security reviews'],
    },
];

const finalBenefits = [
    'Role-based access control',
    'Audit trails and approvals',
    'Live reporting and exports',
    'Modular growth path',
];

function useReveal(threshold = 0.15) {
    const ref = useRef<HTMLElement | null>(null);
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsVisible(true);
                    observer.unobserve(entry.target);
                }
            },
            { threshold },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [threshold]);

    return { ref, isVisible };
}

type RevealSectionProps = {
    id?: string;
    className: string;
    children: React.ReactNode;
};

function RevealSection({ id, className, children }: RevealSectionProps) {
    const { ref, isVisible } = useReveal();

    return (
        <section
            id={id}
            ref={ref}
            style={lazySectionStyle}
            className={`${className} transition duration-700 motion-reduce:transition-none ${
                isVisible
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-5 opacity-0'
            }`}
        >
            {children}
        </section>
    );
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth.user);
    const isSuperAdmin = Boolean(auth.user?.is_super_admin);
    const dashboardHref = isSuperAdmin
        ? platformDashboard()
        : companyDashboard();
    const [isScrolled, setIsScrolled] = useState(false);
    const [activeTab, setActiveTab] = useState(demoTabs[0].id);

    useEffect(() => {
        const onScroll = () => setIsScrolled(window.scrollY > 12);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const activeDemo =
        demoTabs.find((tab) => tab.id === activeTab) ?? demoTabs[0];

    return (
        <>
            <Head title="Port-101">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>
            <div id="top" className="min-h-screen bg-[#f7f7f4] text-slate-950">
                <header
                    className={`sticky top-0 z-50 border-b transition ${
                        isScrolled
                            ? 'border-slate-200 bg-[rgba(247,247,244,0.96)] shadow-sm backdrop-blur'
                            : 'border-transparent bg-[#f7f7f4]'
                    }`}
                >
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                        <a href="#top" className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md">
                                <Boxes className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-base font-semibold tracking-tight">
                                    Port-101
                                </p>
                                <p className="text-xs text-slate-500">
                                    Modern ERP for growing teams
                                </p>
                            </div>
                        </a>
                        <nav className="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                            {navLinks.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="hover:text-slate-900"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>
                        <div className="flex items-center gap-3">
                            {isAuthenticated ? (
                                <Link
                                    href={dashboardHref}
                                    className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:border-slate-400"
                                >
                                    Open dashboard
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="text-sm font-semibold text-slate-700 hover:text-slate-900"
                                    >
                                        Sign in
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main>
                    <section className="mx-auto grid w-full max-w-6xl grid-cols-1 gap-12 px-6 pt-12 pb-20 md:grid-cols-[1.1fr_0.9fr]">
                        <div className="flex flex-col gap-6">
                            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1 text-xs font-semibold tracking-[0.2em] text-slate-600 uppercase">
                                Port-101 Platform
                            </div>
                            <h1 className="text-4xl leading-tight font-semibold tracking-tight text-slate-950 md:text-5xl lg:text-6xl">
                                A single system to run sales, inventory, and
                                finance.
                            </h1>
                            <p className="text-lg text-slate-600 md:text-xl">
                                Port-101 connects every workflow from order to
                                payment, giving teams a shared source of truth
                                and real-time visibility.
                            </p>
                            <div className="grid gap-3 text-sm text-slate-700">
                                {heroBullets.map((item) => (
                                    <div
                                        key={item.text}
                                        className="flex items-center gap-3"
                                    >
                                        <span className="text-lg" aria-hidden>
                                            {item.emoji}
                                        </span>
                                        <span>{item.text}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-wrap items-center gap-4">
                                {isAuthenticated ? (
                                    <Link
                                        href={dashboardHref}
                                        className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    >
                                        Go to dashboard
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                ) : (
                                    <>
                                        <a
                                            href="#demo"
                                            className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-900 shadow-sm transition hover:border-slate-400"
                                        >
                                            View demo
                                        </a>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="relative">
                            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-xs tracking-[0.2em] text-slate-500 uppercase">
                                            Live workspace
                                        </p>
                                        <h3 className="mt-2 text-lg font-semibold text-slate-900">
                                            Executive overview
                                        </h3>
                                    </div>
                                    <Gauge className="h-5 w-5 text-slate-400" />
                                </div>
                                <div className="mt-6 grid gap-4">
                                    {[
                                        {
                                            title: 'Orders',
                                            value: '128',
                                            trend: '+12%',
                                        },
                                        {
                                            title: 'Inventory turns',
                                            value: '4.6',
                                            trend: '+0.8',
                                        },
                                        {
                                            title: 'Receivables',
                                            value: '$92,400',
                                            trend: '-6%',
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.title}
                                            className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm"
                                        >
                                            <div>
                                                <p className="text-slate-500">
                                                    {item.title}
                                                </p>
                                                <p className="mt-1 text-base font-semibold text-slate-900">
                                                    {item.value}
                                                </p>
                                            </div>
                                            <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {item.trend}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-6 h-24 rounded-2xl border border-dashed border-slate-200 bg-slate-50" />
                            </div>
                            {heroBadges.map((badge) => (
                                <div
                                    key={badge.label}
                                    className={`absolute ${badge.className} rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold text-slate-700 shadow-sm`}
                                >
                                    <p className="text-slate-500">
                                        {badge.label}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-900">
                                        {badge.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <RevealSection
                        id="features"
                        className="mx-auto w-full max-w-6xl scroll-mt-24 px-6 py-20"
                    >
                        <div className="flex flex-col gap-4">
                            <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                Features
                            </p>
                            <h2 className="text-3xl font-semibold text-slate-900 md:text-4xl">
                                The core workflows your team uses every day.
                            </h2>
                            <p className="max-w-2xl text-lg text-slate-600">
                                Port-101 turns scattered tools into a single
                                timeline, so every team knows what happens next.
                            </p>
                        </div>
                        <div className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {featureItems.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1"
                                >
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-md">
                                        <feature.icon className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-slate-900">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-slate-600">
                                            {feature.description}
                                        </p>
                                    </div>
                                    {/* <div className="h-16 rounded-2xl border border-dashed border-slate-200 bg-slate-50" /> */}
                                </div>
                            ))}
                        </div>
                        <div className="mt-10 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
                            <div className="grid gap-6 md:grid-cols-3">
                                {featureHighlights.map((item) => (
                                    <div key={item.label}>
                                        <p className="text-2xl font-semibold text-slate-900">
                                            {item.value}
                                        </p>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {item.label}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </RevealSection>

                    <RevealSection className="mx-auto w-full max-w-6xl px-6 pb-20">
                        <div className="grid gap-12 lg:grid-cols-[0.7fr_1.3fr]">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                    Social proof
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold text-slate-900 md:text-4xl">
                                    Trusted by teams who run operations daily.
                                </h2>
                                <p className="mt-4 text-lg text-slate-600">
                                    Operators rely on Port-101 to keep orders
                                    flowing and reporting accurate.
                                </p>
                                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                    {proofStats.map((stat) => (
                                        <div
                                            key={stat.label}
                                            className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                                        >
                                            <p className="text-2xl font-semibold text-slate-900">
                                                {stat.value}
                                            </p>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {stat.label}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="grid gap-6">
                                <div className="grid gap-4 md:grid-cols-3">
                                    {testimonials.map((testimonial) => (
                                        <div
                                            key={testimonial.name}
                                            className="rounded-3xl border border-slate-200 bg-white p-5 text-sm shadow-sm"
                                        >
                                            <div className="flex items-center gap-1 text-amber-500">
                                                {Array.from({ length: 5 }).map(
                                                    (_, index) => (
                                                        <Star
                                                            key={index}
                                                            className="h-4 w-4 fill-current"
                                                        />
                                                    ),
                                                )}
                                            </div>
                                            <p className="mt-3 text-slate-600">
                                                {testimonial.quote}
                                            </p>
                                            <div className="mt-4 flex items-center gap-3">
                                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                                                    {testimonial.initials}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900">
                                                        {testimonial.name}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {testimonial.role}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                        Selected teams
                                    </p>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                        {logoItems.map((logo) => (
                                            <div
                                                key={logo}
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-600"
                                            >
                                                {logo}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <h3 className="text-lg font-semibold text-slate-900">
                                        {successStory.title}
                                    </h3>
                                    <p className="mt-2 text-sm text-slate-600">
                                        {successStory.description}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </RevealSection>

                    <RevealSection
                        id="demo"
                        className="mx-auto w-full max-w-6xl scroll-mt-24 px-6 pb-20"
                    >
                        <div className="grid gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                    Demo
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold text-slate-900 md:text-4xl">
                                    Explore the live product experience.
                                </h2>
                                <p className="mt-4 text-lg text-slate-600">
                                    Move through real scenarios and see how
                                    Port-101 keeps every step connected.
                                </p>
                                <div
                                    className="mt-8"
                                    role="tablist"
                                    aria-label="Demo tabs"
                                >
                                    <div className="inline-flex rounded-full border border-slate-200 bg-white p-1">
                                        {demoTabs.map((tab) => (
                                            <button
                                                key={tab.id}
                                                type="button"
                                                role="tab"
                                                aria-selected={
                                                    activeTab === tab.id
                                                }
                                                onClick={() =>
                                                    setActiveTab(tab.id)
                                                }
                                                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                                                    activeTab === tab.id
                                                        ? 'bg-slate-900 text-white'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                {tab.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <h3 className="text-lg font-semibold text-slate-900">
                                        {activeDemo.title}
                                    </h3>
                                    <p className="mt-2 text-sm text-slate-600">
                                        {activeDemo.description}
                                    </p>
                                    <div className="mt-4 grid gap-2 text-sm text-slate-600">
                                        {activeDemo.bullets.map((bullet) => (
                                            <div
                                                key={bullet}
                                                className="flex items-center gap-2"
                                            >
                                                <Check className="h-4 w-4 text-emerald-600" />
                                                {bullet}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 grid gap-3 sm:grid-cols-3">
                                        {activeDemo.metrics.map((metric) => (
                                            <div
                                                key={metric}
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                {metric}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 flex flex-wrap items-center gap-3">
                                        <a
                                            href="#pricing"
                                            className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:bg-slate-800"
                                        >
                                            Try interactive demo
                                            <ArrowRight className="h-4 w-4" />
                                        </a>
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm"
                                        >
                                            Request access
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-6">
                                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-semibold text-slate-900">
                                            Demo workspace
                                        </h4>
                                        <span className="text-xs text-slate-500">
                                            Live preview
                                        </span>
                                    </div>
                                    <div className="mt-4 grid gap-3">
                                        {[
                                            'Sales pipeline',
                                            'Inventory overview',
                                            'Cash forecast',
                                        ].map((label) => (
                                            <div
                                                key={label}
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
                                            >
                                                {label}
                                            </div>
                                        ))}
                                    </div>
                                    {/* <div className="mt-4 h-28 rounded-2xl border border-dashed border-slate-200 bg-slate-50" /> */}
                                </div>
                                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-semibold text-slate-900">
                                            Video tour
                                        </h4>
                                        <span className="text-xs text-slate-500">
                                            3 minutes
                                        </span>
                                    </div>
                                    <div className="mt-4 flex aspect-video items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50">
                                        <button
                                            type="button"
                                            className="flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm"
                                        >
                                            <Play className="h-4 w-4" />
                                            Play demo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </RevealSection>

                    <RevealSection
                        id="pricing"
                        className="mx-auto w-full max-w-6xl scroll-mt-24 px-6 pb-20"
                    >
                        <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                        Benefits
                                    </p>
                                    <h2 className="mt-3 text-3xl font-semibold text-slate-900 md:text-4xl">
                                        Replace slow handoffs with clear
                                        execution.
                                    </h2>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {benefitStats.map((stat) => (
                                        <div
                                            key={stat.label}
                                            className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm"
                                        >
                                            <p className="text-lg font-semibold text-slate-900">
                                                {stat.value}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {stat.label}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
                                <div className="grid gap-4">
                                    <div className="grid grid-cols-3 text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase">
                                        <span>Capability</span>
                                        <span>Traditional ERP</span>
                                        <span>Port-101</span>
                                    </div>
                                    {comparisonRows.map((row) => (
                                        <div
                                            key={row.label}
                                            className="grid grid-cols-3 items-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                                        >
                                            <span className="font-semibold text-slate-900">
                                                {row.label}
                                            </span>
                                            <span>{row.traditional}</span>
                                            <span className="text-emerald-700">
                                                {row.port}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-12">
                            <div className="flex flex-col gap-3">
                                <p className="text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                    Pricing
                                </p>
                                <h2 className="text-3xl font-semibold text-slate-900 md:text-4xl">
                                    Plans that grow with your operations.
                                </h2>
                                <p className="max-w-2xl text-lg text-slate-600">
                                    Transparent pricing with no hidden
                                    implementation fees.
                                </p>
                            </div>
                            <div className="mt-10 grid gap-6 lg:grid-cols-3">
                                {pricingTiers.map((tier) => (
                                    <div
                                        key={tier.name}
                                        className={`rounded-3xl border bg-white p-6 shadow-sm ${
                                            tier.highlight
                                                ? 'border-emerald-500 ring-2 ring-emerald-500/20'
                                                : 'border-slate-200'
                                        }`}
                                    >
                                        {tier.highlight && (
                                            <span className="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                Most popular
                                            </span>
                                        )}
                                        <h3 className="mt-4 text-xl font-semibold text-slate-900">
                                            {tier.name}
                                        </h3>
                                        <p className="mt-2 text-sm text-slate-600">
                                            {tier.description}
                                        </p>
                                        <p className="mt-4 text-3xl font-semibold text-slate-900">
                                            {tier.price}
                                            {tier.price !== 'Custom' && (
                                                <span className="text-sm font-medium text-slate-500">
                                                    {' '}
                                                    per user/month
                                                </span>
                                            )}
                                        </p>
                                        <ul className="mt-6 space-y-2 text-sm text-slate-600">
                                            {tier.features.map((feature) => (
                                                <li
                                                    key={feature}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Check className="h-4 w-4 text-emerald-600" />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                        <div className="mt-6">
                                            <Link
                                                href={login()}
                                                className={`inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold shadow-sm transition ${
                                                    tier.highlight
                                                        ? 'bg-slate-900 text-white hover:bg-slate-800'
                                                        : 'border border-slate-300 bg-white text-slate-900 hover:border-slate-400'
                                                }`}
                                            >
                                                Request access
                                                <ArrowRight className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </RevealSection>

                    <RevealSection className="mx-auto w-full max-w-6xl px-6 pb-24">
                        <div className="rounded-3xl bg-slate-900 px-10 py-12 text-white shadow-md">
                            <div className="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                                <div>
                                    <h2 className="text-3xl font-semibold">
                                        Ready to move operations faster with
                                        Port-101?
                                    </h2>
                                    <p className="mt-3 text-slate-300">
                                        Start with core modules, then expand as
                                        your team grows. We keep every workflow
                                        connected from day one.
                                    </p>
                                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                        {finalBenefits.map((benefit) => (
                                            <div
                                                key={benefit}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Check className="h-4 w-4 text-emerald-300" />
                                                {benefit}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-8 flex flex-wrap items-center gap-3">
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center gap-2 rounded-full border border-white/50 px-5 py-3 text-sm font-semibold text-white transition hover:border-white"
                                        >
                                            Talk to us
                                        </Link>
                                    </div>
                                </div>
                                <div className="flex flex-col gap-4">
                                    <div className="rounded-2xl border border-white/20 bg-white/10 p-5 text-sm">
                                        <p className="text-xs tracking-[0.2em] text-slate-300 uppercase">
                                            Impact snapshot
                                        </p>
                                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                            {[
                                                '41% faster fulfillment',
                                                '3x clearer approvals',
                                                '24/7 visibility',
                                                '1 dashboard view',
                                            ].map((item) => (
                                                <div
                                                    key={item}
                                                    className="text-sm text-slate-100"
                                                >
                                                    {item}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                                        <p className="font-semibold">
                                            Limited onboarding slots this month
                                        </p>
                                        <p className="mt-2 text-amber-800">
                                            Reserve a guided setup window to
                                            launch faster.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </RevealSection>
                </main>

                <footer className="border-t border-slate-200 pt-8 pb-10">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <Boxes className="h-4 w-4" />
                            </div>
                            <span>Port-101 ERP</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <a
                                href="#features"
                                className="hover:text-slate-900"
                            >
                                Features
                            </a>
                            <a href="#demo" className="hover:text-slate-900">
                                Demo
                            </a>
                            <a href="#pricing" className="hover:text-slate-900">
                                Pricing
                            </a>
                            <span className="text-xs text-slate-400">
                                (c) 2026 Port-101
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
