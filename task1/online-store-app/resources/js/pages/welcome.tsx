import { Head, Link } from '@inertiajs/react';
import { Zap, Package, ArrowRight, Shield } from 'lucide-react';

/**
 * Welcome / Landing page — shown to unauthenticated users.
 * Showcases the online store with a call-to-action to the shop.
 */
interface WelcomeProps {
    stats: {
        products: number;
        customers: number;
        flash_sales: number;
    };
}

export default function Welcome({ stats }: WelcomeProps) {
    return (
        <>
            <Head title="FlashStore — Online Store" />
            <div className="min-h-screen bg-gradient-to-br from-slate-950 via-purple-950 to-slate-950">
                {/* Navigation */}
                <nav className="fixed top-0 z-50 w-full border-b border-white/5 bg-black/20 backdrop-blur-xl">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600">
                                <Zap className="h-5 w-5 text-white" />
                            </div>
                            <span className="text-xl font-bold text-white">FlashStore</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <Link
                                href="/store"
                                className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition-all hover:bg-violet-500 hover:shadow-lg hover:shadow-violet-500/25"
                            >
                                Shop Now
                            </Link>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="relative flex min-h-screen items-center justify-center overflow-hidden pt-20">
                    {/* Background glow */}
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="absolute left-1/2 top-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-violet-600/20 blur-3xl" />
                        <div className="absolute right-1/4 top-1/4 h-[300px] w-[300px] rounded-full bg-pink-600/10 blur-3xl" />
                    </div>

                    <div className="relative z-10 mx-auto max-w-4xl px-6 text-center">
                        {/* Flash sale badge */}
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-violet-500/30 bg-violet-500/10 px-4 py-2">
                            <Zap className="h-4 w-4 animate-pulse text-yellow-400" />
                            <span className="text-sm font-medium text-violet-300">Flash Sale Live Now!</span>
                        </div>

                        <h1 className="mb-6 text-6xl font-black leading-tight tracking-tight text-white lg:text-7xl">
                            Deals That
                            <span className="block bg-gradient-to-r from-violet-400 via-pink-400 to-violet-400 bg-clip-text text-transparent">
                                Move Fast
                            </span>
                        </h1>

                        <p className="mx-auto mb-10 max-w-2xl text-xl leading-relaxed text-slate-400">
                            Premium tech products at unbeatable flash sale prices.
                            Experience a seamless, fair, and reliable shopping journey.
                        </p>

                        <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                            <Link
                                href="/store"
                                className="group inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-purple-600 px-8 py-4 text-lg font-bold text-white shadow-2xl shadow-violet-500/30 transition-all hover:scale-105 hover:shadow-violet-500/50"
                            >
                                Browse Products
                                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />
                            </Link>
                            <Link
                                href="/store?flash_sale=1"
                                className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-8 py-4 text-lg font-bold text-white backdrop-blur-sm transition-all hover:border-violet-500/50 hover:bg-white/10"
                            >
                                <Zap className="h-5 w-5 text-yellow-400" />
                                Flash Deals
                            </Link>
                        </div>

                        <div className="mt-16 grid grid-cols-3 gap-8 border-t border-white/5 pt-12">
                            {[
                                { label: 'Products', value: `${stats.products}+` },
                                { label: 'Happy Customers', value: `${stats.customers}+` },
                                { label: 'Flash Sales Today', value: stats.flash_sales.toString() },
                            ].map((stat) => (
                                <div key={stat.label} className="text-center">
                                    <div className="text-3xl font-black text-white">{stat.value}</div>
                                    <div className="mt-1 text-sm text-slate-500">{stat.label}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-24">
                    <div className="mx-auto max-w-7xl px-6">
                        <h2 className="mb-4 text-center text-4xl font-black text-white">
                            Why FlashStore?
                        </h2>
                        <p className="mb-16 text-center text-slate-400">Built for speed, fairness, and reliability.</p>

                        <div className="grid gap-6 md:grid-cols-3">
                            {[
                                {
                                    icon: Shield,
                                    title: 'Reliable & Fair',
                                    desc: 'Our advanced checkout system guarantees that the stock you see is what you get. No overselling or cart errors during high traffic.',
                                    color: 'violet',
                                },
                                {
                                    icon: Zap,
                                    title: 'Exclusive Flash Deals',
                                    desc: 'Time-limited offers with massive discounts. Our platform is built to handle thousands of simultaneous buyers effortlessly.',
                                    color: 'yellow',
                                },
                                {
                                    icon: Package,
                                    title: 'Live Stock Tracking',
                                    desc: 'Stay updated with real-time stock availability, so you never miss out on your favorite items before they sell out.',
                                    color: 'emerald',
                                },
                            ].map((feature) => (
                                <div
                                    key={feature.title}
                                    className="group rounded-2xl border border-white/5 bg-white/3 p-8 backdrop-blur-sm transition-all hover:border-violet-500/30 hover:bg-white/5"
                                >
                                    <div
                                        className={`mb-4 flex h-12 w-12 items-center justify-center rounded-xl ${
                                            feature.color === 'violet'
                                                ? 'bg-violet-500/20'
                                                : feature.color === 'yellow'
                                                  ? 'bg-yellow-500/20'
                                                  : 'bg-emerald-500/20'
                                        }`}
                                    >
                                        <feature.icon
                                            className={`h-6 w-6 ${
                                                feature.color === 'violet'
                                                    ? 'text-violet-400'
                                                    : feature.color === 'yellow'
                                                      ? 'text-yellow-400'
                                                      : 'text-emerald-400'
                                            }`}
                                        />
                                    </div>
                                    <h3 className="mb-3 text-xl font-bold text-white">{feature.title}</h3>
                                    <p className="leading-relaxed text-slate-400">{feature.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
