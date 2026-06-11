import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import {
    ShoppingCart,
    Zap,
    Package,
    Search,
    AlertCircle,
} from 'lucide-react';

/**
 * Types for the store page props
 */
interface FlashSale {
    active: boolean;
    price: number | null;
    starts_at: string | null;
    ends_at: string | null;
}

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    effective_price: number;
    flash_sale: FlashSale;
    image_url: string | null;
    stock: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PageProps {
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        links: PaginationLink[];
    };
    flash_sale_only: boolean;
}

/**
 * Store page — displays all products with flash sale filtering.
 * Served by Inertia from the backend at GET /store
 */
export default function Store({ products, flash_sale_only }: PageProps) {
    // Lazy state initializer reads from localStorage only on first render —
    // avoids calling setState synchronously inside an effect.
    const [cart, setCart] = useState<Record<number, number>>(() => {
        try {
            const stored = localStorage.getItem('flashstore_cart');

            return stored ? (JSON.parse(stored) as Record<number, number>) : {};
        } catch {
            return {};
        }
    });
    const [search, setSearch] = useState(() => {
        if (typeof window !== 'undefined') {
            return new URLSearchParams(window.location.search).get('search') || '';
        }
        return '';
    });
    const [orderModal, setOrderModal] = useState<Product | null>(null);
    const [cartModal, setCartModal] = useState(false);

    const cartItemCount = Object.values(cart).reduce((s, q) => s + q, 0);

    // Persist cart to localStorage whenever it changes
    const updateCart = (updater: (prev: Record<number, number>) => Record<number, number>) => {
        setCart((prev) => {
            const next = updater(prev);
            localStorage.setItem('flashstore_cart', JSON.stringify(next));
            return next;
        });
    };

    const addToCart = (product: Product, qty: number = 1) => {
        updateCart((prev) => {
            const nextQty = (prev[product.id] ?? 0) + qty;
            if (nextQty <= 0) {
                const next = { ...prev };
                delete next[product.id];
                return next;
            }
            if (nextQty > product.stock) {
                return { ...prev, [product.id]: product.stock };
            }
            return { ...prev, [product.id]: nextQty };
        });
    };

    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(
                '/store',
                { search, flash_sale: flash_sale_only ? 1 : undefined },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 400);

        return () => clearTimeout(timeoutId);
    }, [search, flash_sale_only]);

    return (
        <>
            <Head title="Shop — FlashStore" />

            {/* Full-page dark layout */}
            <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">

                {/* Top nav */}
                <nav className="sticky top-0 z-40 border-b border-white/5 bg-slate-950/80 backdrop-blur-xl">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-purple-600">
                                <Zap className="h-4 w-4 text-white" />
                            </div>
                            <span className="text-lg font-bold text-white">FlashStore</span>
                        </Link>

                        {/* Search */}
                        <div className="order-last flex w-full flex-1 items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 sm:order-none sm:max-w-md">
                            <Search className="h-4 w-4 text-slate-400" />
                            <input
                                className="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none"
                                placeholder="Search products…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>

                        {/* Flash Sale filter */}
                        <div className="flex items-center gap-2 sm:gap-3">
                            <Link
                                href={flash_sale_only ? '/store' : '/store?flash_sale=1'}
                                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all ${
                                    flash_sale_only
                                        ? 'bg-yellow-500/20 text-yellow-400 ring-1 ring-yellow-500/30'
                                        : 'bg-white/5 text-slate-400 hover:bg-white/10'
                                }`}
                            >
                                <Zap className="h-4 w-4" />
                                Flash Sale
                            </Link>

                            {/* Cart button */}
                            <button
                                onClick={() => setCartModal(true)}
                                className="relative flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500"
                            >
                                <ShoppingCart className="h-4 w-4" />
                                Cart
                                {cartItemCount > 0 && (
                                    <span className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-pink-500 text-xs font-bold">
                                        {cartItemCount}
                                    </span>
                                )}
                            </button>
                        </div>
                    </div>
                </nav>

                <main className="mx-auto max-w-7xl px-6 py-10">
                    {/* Page header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-black text-white">
                            {flash_sale_only ? '⚡ Flash Sale Products' : 'All Products'}
                        </h1>
                        <p className="mt-1 text-slate-400">
                            {products.total} product{products.total !== 1 ? 's' : ''} found
                        </p>
                    </div>

                    {/* Product Grid */}
                    {products.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-32 text-center">
                            <Package className="mb-4 h-16 w-16 text-slate-600" />
                            <p className="text-xl font-semibold text-slate-400">No products found</p>
                        </div>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    cartQty={cart[product.id] ?? 0}
                                    onAddToCart={() => addToCart(product)}
                                    onBuyNow={() => setOrderModal(product)}
                                />
                            ))}
                        </div>
                    )}

                    {/* Pagination */}
                    {products.last_page > 1 && (
                        <div className="mt-12 flex flex-wrap items-center justify-center gap-2">
                            {products.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    preserveState
                                    preserveScroll
                                    className={`flex items-center justify-center rounded-xl border px-3 py-1.5 text-xs sm:px-4 sm:py-2 sm:text-sm transition-all ${
                                        link.active
                                            ? 'border-violet-500 bg-violet-500/20 text-white'
                                            : 'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </main>
            </div>

            {/* Quick Order Modal */}
            {orderModal && (
                <QuickOrderModal
                    product={orderModal}
                    onClose={() => setOrderModal(null)}
                />
            )}

            {/* Cart Modal */}
            {cartModal && (
                <CartCheckoutModal
                    cart={cart}
                    products={products.data}
                    onClose={() => setCartModal(false)}
                    onUpdateCart={addToCart}
                    onClearCart={() => {
                        setCart({});
                        localStorage.removeItem('flashstore_cart');
                    }}
                />
            )}
        </>
    );
}

Store.layout = (page: React.ReactNode) => page;

// ─────────────────────────────────────────────────────────────────────────────
// ProductCard component
// ─────────────────────────────────────────────────────────────────────────────

function ProductCard({
    product,
    cartQty,
    onAddToCart,
    onBuyNow,
}: {
    product: Product;
    cartQty: number;
    onAddToCart: () => void;
    onBuyNow: () => void;
}) {
    const isFlash = product.flash_sale.active;
    const outOfStock = product.stock === 0;
    const discount = isFlash && product.flash_sale.price
        ? Math.round((1 - product.flash_sale.price / product.price) * 100)
        : 0;

    return (
        <div
            className={`group relative flex flex-col overflow-hidden rounded-2xl border transition-all duration-300 hover:scale-[1.02] ${
                isFlash
                    ? 'border-yellow-500/30 bg-gradient-to-b from-yellow-950/40 to-slate-900'
                    : 'border-white/5 bg-white/3 hover:border-violet-500/30'
            }`}
        >
            {/* Flash sale badge */}
            {isFlash && (
                <div className="absolute left-3 top-3 z-10 flex items-center gap-1 rounded-lg bg-yellow-400 px-2 py-1">
                    <Zap className="h-3 w-3 text-yellow-900" />
                    <span className="text-xs font-black text-yellow-900">FLASH -{discount}%</span>
                </div>
            )}

            {/* Out of stock overlay */}
            {outOfStock && (
                <div className="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-black/60 backdrop-blur-sm">
                    <div className="rounded-xl bg-slate-800 px-4 py-2">
                        <p className="text-sm font-bold text-red-400">Out of Stock</p>
                    </div>
                </div>
            )}

            {/* Product image placeholder */}
            <div className={`relative h-48 ${isFlash ? 'bg-yellow-900/20' : 'bg-slate-800/50'} flex items-center justify-center`}>
                <Package className={`h-20 w-20 ${isFlash ? 'text-yellow-700' : 'text-slate-600'}`} />
            </div>

            {/* Content */}
            <div className="flex flex-1 flex-col p-5">
                <h3 className="mb-2 line-clamp-2 text-sm font-bold leading-snug text-white">{product.name}</h3>
                <p className="mb-4 line-clamp-2 text-xs leading-relaxed text-slate-500">{product.description}</p>

                {/* Price */}
                <div className="mb-4 mt-auto">
                    {isFlash ? (
                        <div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-xl font-black text-yellow-400">
                                    Rp {product.effective_price.toLocaleString('id-ID')}
                                </span>
                            </div>
                            <span className="text-xs text-slate-500 line-through">
                                Rp {product.price.toLocaleString('id-ID')}
                            </span>
                        </div>
                    ) : (
                        <span className="text-xl font-black text-white">
                            Rp {product.price.toLocaleString('id-ID')}
                        </span>
                    )}

                    {/* Stock indicator */}
                    <div className="mt-2 flex items-center gap-1.5">
                        <div
                            className={`h-1.5 w-1.5 rounded-full ${
                                product.stock > 10
                                    ? 'bg-emerald-400'
                                    : product.stock > 0
                                      ? 'bg-yellow-400 animate-pulse'
                                      : 'bg-red-400'
                            }`}
                        />
                        <span className="text-xs text-slate-500">
                            {product.stock > 0 ? `${product.stock} left` : 'Sold out'}
                        </span>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <button
                        onClick={onAddToCart}
                        disabled={outOfStock || cartQty >= product.stock}
                        className="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-white/10 bg-white/5 py-2.5 text-xs font-semibold text-slate-300 transition-all hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ShoppingCart className="h-3.5 w-3.5" />
                        {cartQty > 0 ? `In cart (${cartQty})` : 'Add to Cart'}
                    </button>
                    <button
                        onClick={onBuyNow}
                        disabled={outOfStock}
                        className={`flex-1 rounded-xl py-2.5 text-xs font-bold text-white transition-all disabled:cursor-not-allowed disabled:opacity-40 ${
                            isFlash
                                ? 'bg-yellow-500 hover:bg-yellow-400'
                                : 'bg-violet-600 hover:bg-violet-500'
                        }`}
                    >
                        Buy Now
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// QuickOrderModal component
// ─────────────────────────────────────────────────────────────────────────────

function QuickOrderModal({ product, onClose }: { product: Product; onClose: () => void }) {
    const [form, setForm] = useState({
        customer_name: '',
        customer_email: '',
        quantity: 1,
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);

    const isFlash = product.flash_sale.active;
    const unitPrice = product.effective_price;
    const subtotal = unitPrice * form.quantity;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const res = await fetch('/api/orders', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    customer_name: form.customer_name,
                    customer_email: form.customer_email,
                    items: [{ product_id: product.id, quantity: form.quantity }],
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                const errorsObj = data.errors as Record<string, string[]> | undefined;
                const errMsg =
                    errorsObj?.['items']?.[0] ??
                    (errorsObj ? Object.values(errorsObj)[0]?.[0] : undefined) ??
                    (data.message as string | undefined) ??
                    'Order failed. Please try again.';
                setError(errMsg);
            } else {
                setSuccess(true);
            }
        } catch {
            setError('Network error. Please check your connection.');
        } finally {
            setLoading(false);
        }
    };
    const handleClose = () => {
        onClose();
        if (success) {
            window.location.reload();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={handleClose} />
            <div className="relative w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
                {success ? (
                    <div className="flex flex-col items-center p-10 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20">
                            <Zap className="h-8 w-8 text-emerald-400" />
                        </div>
                        <h3 className="mb-2 text-xl font-bold text-white">Order Confirmed!</h3>
                        <p className="mb-6 text-slate-400">
                            Your order for <strong className="text-white">{product.name}</strong> has been placed.
                        </p>
                        <button
                            onClick={handleClose}
                            className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white hover:bg-violet-500"
                        >
                            Continue Shopping
                        </button>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit}>
                        <div className="border-b border-white/5 p-6">
                            <h3 className="text-lg font-bold text-white">Quick Order</h3>
                            <p className="mt-1 text-sm text-slate-400">{product.name}</p>
                        </div>

                        <div className="space-y-4 p-6">
                            {error && (
                                <div className="flex items-start gap-2 rounded-xl border border-red-500/30 bg-red-500/10 p-4">
                                    <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400" />
                                    <p className="text-sm text-red-300">{error}</p>
                                </div>
                            )}

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-slate-400">Full Name</label>
                                <input
                                    required
                                    type="text"
                                    className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-500 outline-none focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/50"
                                    placeholder="Your full name"
                                    value={form.customer_name}
                                    onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-slate-400">Email</label>
                                <input
                                    required
                                    type="email"
                                    className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-500 outline-none focus:border-violet-500/50"
                                    placeholder="your@email.com"
                                    value={form.customer_email}
                                    onChange={(e) => setForm({ ...form, customer_email: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-slate-400">Quantity</label>
                                <input
                                    required
                                    type="number"
                                    min={1}
                                    max={product.stock}
                                    className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none focus:border-violet-500/50"
                                    value={form.quantity}
                                    onChange={(e) => setForm({ ...form, quantity: parseInt(e.target.value) || 1 })}
                                />
                            </div>

                            {/* Price summary */}
                            <div className="rounded-xl border border-white/5 bg-white/3 p-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-slate-400">Unit price</span>
                                    <span className={`font-semibold ${isFlash ? 'text-yellow-400' : 'text-white'}`}>
                                        Rp {unitPrice.toLocaleString('id-ID')}
                                        {isFlash && <span className="ml-1 text-xs text-yellow-600">⚡</span>}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-2">
                                    <span className="font-bold text-white">Total</span>
                                    <span className="text-lg font-black text-white">
                                        Rp {subtotal.toLocaleString('id-ID')}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="flex gap-3 border-t border-white/5 p-6">
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 rounded-xl border border-white/10 py-3 text-sm font-semibold text-slate-400 hover:bg-white/5"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading}
                                className={`flex-1 rounded-xl py-3 text-sm font-bold text-white transition-all disabled:opacity-60 ${
                                    isFlash
                                        ? 'bg-yellow-500 hover:bg-yellow-400'
                                        : 'bg-violet-600 hover:bg-violet-500'
                                }`}
                            >
                                {loading ? 'Placing Order…' : 'Place Order'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// CartCheckoutModal component
// ─────────────────────────────────────────────────────────────────────────────

function CartCheckoutModal({
    cart,
    products,
    onClose,
    onUpdateCart,
    onClearCart,
}: {
    cart: Record<number, number>;
    products: Product[];
    onClose: () => void;
    onUpdateCart: (product: Product, qty: number) => void;
    onClearCart: () => void;
}) {
    const [form, setForm] = useState({
        customer_name: '',
        customer_email: '',
    });
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState('');

    const cartItems = Object.entries(cart)
        .map(([id, qty]) => {
            const product = products.find((p) => p.id === parseInt(id));
            return product ? { product, qty } : null;
        })
        .filter((item): item is { product: Product; qty: number } => item !== null);

    const total = cartItems.reduce((sum, item) => sum + item.product.effective_price * item.qty, 0);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    ...form,
                    items: cartItems.map((item) => ({
                        product_id: item.product.id,
                        quantity: item.qty,
                    })),
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to place order');
            }

            setSuccess(true);
            onClearCart();
            setTimeout(() => {
                onClose();
                window.location.reload();
            }, 2000);
        } catch (err: any) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div className="w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
                <div className="border-b border-white/5 bg-slate-950 p-6">
                    <h2 className="text-xl font-black text-white">Your Cart</h2>
                </div>

                {success ? (
                    <div className="p-8 text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20">
                            <Zap className="h-8 w-8 text-emerald-400" />
                        </div>
                        <h3 className="text-xl font-bold text-white">Order Confirmed!</h3>
                        <p className="mt-2 text-sm text-slate-400">Thank you for your purchase.</p>
                    </div>
                ) : cartItems.length === 0 ? (
                    <div className="p-8 text-center">
                        <Package className="mx-auto mb-4 h-12 w-12 text-slate-600" />
                        <h3 className="text-lg font-bold text-white">Cart is empty</h3>
                        <p className="mt-2 text-sm text-slate-400">Add some products to your cart first.</p>
                        <button
                            onClick={onClose}
                            className="mt-6 rounded-xl bg-white/10 px-6 py-2.5 text-sm font-semibold text-white hover:bg-white/20"
                        >
                            Close
                        </button>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit}>
                        <div className="max-h-64 overflow-y-auto p-6 space-y-4 border-b border-white/5">
                            {cartItems.map((item) => (
                                <div key={item.product.id} className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-sm">
                                    <div className="flex-1">
                                        <p className="font-bold text-white line-clamp-1">{item.product.name}</p>
                                        <p className="text-slate-400">Rp {item.product.effective_price.toLocaleString('id-ID')}</p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <div className="flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            <button
                                                type="button"
                                                onClick={() => onUpdateCart(item.product, -1)}
                                                className="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-white hover:bg-white/20"
                                            >
                                                -
                                            </button>
                                            <span className="w-4 text-center font-bold text-white">{item.qty}</span>
                                            <button
                                                type="button"
                                                onClick={() => onUpdateCart(item.product, 1)}
                                                disabled={item.qty >= item.product.stock}
                                                className="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-white hover:bg-white/20 disabled:opacity-30"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <div className="w-24 text-right font-bold text-white">
                                            Rp {(item.qty * item.product.effective_price).toLocaleString('id-ID')}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="p-6 space-y-4">
                            {error && (
                                <div className="flex items-start gap-2 rounded-xl bg-red-500/10 p-4 text-red-400 ring-1 ring-red-500/20">
                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <p className="text-sm font-medium">{error}</p>
                                </div>
                            )}

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-slate-400">Full Name</label>
                                <input
                                    required
                                    className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none focus:border-violet-500/50"
                                    value={form.customer_name}
                                    onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-slate-400">Email Address</label>
                                <input
                                    required
                                    type="email"
                                    className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none focus:border-violet-500/50"
                                    value={form.customer_email}
                                    onChange={(e) => setForm({ ...form, customer_email: e.target.value })}
                                />
                            </div>

                            <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-4">
                                <span className="font-bold text-white">Total</span>
                                <span className="text-xl font-black text-white">
                                    Rp {total.toLocaleString('id-ID')}
                                </span>
                            </div>
                        </div>

                        <div className="flex gap-3 border-t border-white/5 p-6">
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 rounded-xl border border-white/10 py-3 text-sm font-semibold text-slate-400 hover:bg-white/5"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading}
                                className="flex-1 rounded-xl bg-violet-600 py-3 text-sm font-bold text-white hover:bg-violet-500 transition-all disabled:opacity-60"
                            >
                                {loading ? 'Processing…' : 'Checkout'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
