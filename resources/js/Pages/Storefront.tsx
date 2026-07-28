import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type Brand = { id: number; name: string };
type Category = { id: number; name: string; slug?: string; products_count?: number };
type UploadedImage = { id?: number; path: string; sort_order?: number };
type Tag = { id: number; name: string; slug: string; products_count?: number; articles_count?: number };
type ArticleCategory = { id: number; name: string; slug: string; description?: string | null; articles_count?: number };
type ShopFilters = { brand_id: string; category_id: string; min_price: string; max_price: string };
type Product = {
    id: number;
    name: string;
    sku?: string;
    slug?: string;
    price: number;
    sale_price?: number | null;
    short_description?: string | null;
    description?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    meta_keywords?: string | null;
    canonical_url?: string | null;
    gallery?: string[] | null;
    weight?: number | null;
    dimensions?: string | null;
    brand?: Brand | null;
    category?: Category | null;
    main_image?: string | null;
    images?: UploadedImage[];
    attributes?: { color?: string };
    stock: number;
    is_active?: boolean;
    tags?: Tag[];
};
type Article = {
    id: number;
    title: string;
    slug: string;
    excerpt?: string | null;
    body?: string | null;
    image?: string | null;
    topic?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    meta_keywords?: string | null;
    canonical_url?: string | null;
    published_at?: string | null;
    is_published?: boolean;
    tags?: Tag[];
    category?: ArticleCategory | null;
};
type ShippingMethod = { code: string; name: string; description: string; cost: number; is_active: boolean };
type Order = { id: number; number: string; invoice_token?: string; status: string; subtotal?: number; discount?: number; shipping_cost?: number; tax?: number; total: number; shipping_method: string; payment_method?: string; payment_receipt?: string; card_to_card_amount?: number; payment_reference?: string; paid_at?: string; created_at: string; updated_at?: string; items_count?: number; items_sum_quantity?: number; address?: { first_name?: string; last_name?: string; customer_name?: string; phone?: string; postal_code?: string; province?: string; city?: string; full?: string }; items?: { id?: number; name?: string; sku?: string; price?: number; quantity: number }[]; user?: { first_name?: string; last_name?: string; phone_number?: string; email?: string } };
type PaginatedOrders = { data: Order[]; current_page: number; last_page: number; prev_page_url?: string | null; next_page_url?: string | null; total: number };
type Accounting = { received: number; product_revenue: number; shipping_revenue: number; sold_items: number; paid_orders: number; pending_orders: number };
type CardProduct = {
    id: number;
    name: string;
    slug?: string;
    price: number;
    sale?: number;
    brand: string;
    color: string;
    image?: string;
    stock: number;
    tag?: string;
};
type SeoMeta = {
    title: string;
    description: string;
    keywords?: string;
    image?: string;
    canonical?: string;
};
type StoredCart = { items: CardProduct[]; expiresAt: number | null };
type AdminForm = {
    name: string;
    sku: string;
    category_id: string;
    category_name: string;
    brand_id: string;
    brand_name: string;
    price: string;
    sale_price: string;
    stock: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    tags: string;
    main_image_index: number;
    images: File[];
};
type ArticleForm = {
    title: string;
    excerpt: string;
    body: string;
    tags: string;
    category_name: string;
    meta_title: string;
    meta_description: string;
    main_image: File | null;
};
type ProductEditForm = {
    _method: 'patch';
    name: string;
    sku: string;
    category_id: string;
    brand_id: string;
    price: string;
    sale_price: string;
    stock: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    weight: string;
    dimensions: string;
    is_active: boolean;
    images: File[];
    remove_image_ids: number[];
    remove_legacy_paths: string[];
    main_image_choice: string;
    tags: string;
};

const supportPhone = '09205850190';
const storeAddress = 'خراسان رضوی، مشهد، عبدالمطلب ۳۵';
const siteTitle = 'ایرگجت | لوازم جانبی موبایل';
const siteDescription = 'خرید مطمئن لوازم جانبی موبایل، ایرپاد و گجت با ارسال سریع از ایرگجت مشهد';
const toman = (n: number) => new Intl.NumberFormat('fa-IR').format(n) + ' تومان';
const imageUrl = (path?: string | null) => {
    if (!path) return undefined;
    const productStoragePath = path.match(/(?:^|\/)storage(?:\/app\/public)?\/products\/(.+)$/i);
    if (productStoragePath?.[1]) return `/product-images/${productStoragePath[1]}`;
    if (/^(https?:)?\/\//i.test(path) || path.startsWith('/')) return path;
    const normalized = path.replace(/^public\//, 'storage/').replace(/^storage\/app\/public\//, 'storage/');
    return `/${normalized}`;
};
const optimizeProductImage = async (file: File): Promise<File> => {
    const safeUploadSize = 1700 * 1024;
    if (file.size <= safeUploadSize || typeof createImageBitmap === 'undefined') return file;
    try {
        const bitmap = await createImageBitmap(file);
        const maxDimension = 1400;
        const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        let quality = 0.78;
        let blob: Blob | null = null;
        do {
            blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
            quality -= 0.08;
        } while (blob && blob.size > safeUploadSize && quality >= 0.46);
        if (!blob) return file;
        if (blob.size > safeUploadSize) {
            const resizeScale = Math.sqrt(safeUploadSize / blob.size) * 0.92;
            const width = Math.max(1, Math.round(canvas.width * resizeScale));
            const height = Math.max(1, Math.round(canvas.height * resizeScale));
            const resizedCanvas = document.createElement('canvas');
            resizedCanvas.width = width;
            resizedCanvas.height = height;
            resizedCanvas.getContext('2d')?.drawImage(canvas, 0, 0, width, height);
            blob = await new Promise<Blob | null>((resolve) => resizedCanvas.toBlob(resolve, 'image/webp', 0.62));
        }
        if (!blob || blob.size > safeUploadSize) {
            throw new Error('فشرده‌سازی تصویر به حجم مجاز انجام نشد.');
        }
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp', lastModified: Date.now() });
    } catch {
        return file;
    }
};
const prepareProductImages = (files: File[]) => Promise.all(files.map(optimizeProductImage));
const uploadProductImages = async (productId: number, files: File[], mainIndex: number, onProgress: (percentage: number) => void) => {
    for (let index = 0; index < files.length; index++) {
        const payload = new FormData();
        payload.append('image', files[index]);
        payload.append('is_main', index === mainIndex ? '1' : '0');
        await axios.post(route('admin.products.images.store', productId), payload, {
            onUploadProgress: (event) => {
                const fileProgress = event.total ? event.loaded / event.total : 0;
                onProgress(Math.round(((index + fileProgress) / files.length) * 100));
            },
        });
    }
};
const uploadErrorMessage = (error: any) =>
    error?.response?.data?.errors?.image?.[0]
    || error?.response?.data?.errors?.images?.[0]
    || error?.response?.data?.message
    || error?.message
    || 'آپلود تصویر انجام نشد. محدودیت حجم یا دسترسی نوشتن سرور را بررسی کنید.';
const toCard = (p: Product): CardProduct => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    price: Number(p.price || 0),
    sale: p.sale_price ? Number(p.sale_price) : undefined,
    brand: p.brand?.name || 'بدون برند',
    color: p.attributes?.color || 'مشکی',
    stock: Number(p.stock || 0),
    image: imageUrl(p.main_image || p.images?.[0]?.path || p.gallery?.[0]),
});
const readStoredCart = (): StoredCart => {
    if (typeof window === 'undefined') return { items: [], expiresAt: null };
    try {
        const stored = JSON.parse(window.localStorage.getItem('airgadget-cart') || '[]');
        if (Array.isArray(stored)) return { items: stored, expiresAt: null };
        if (stored?.expiresAt && stored.expiresAt <= Date.now()) return { items: [], expiresAt: null };
        return { items: Array.isArray(stored?.items) ? stored.items : [], expiresAt: stored?.expiresAt || null };
    } catch {
        return { items: [], expiresAt: null };
    }
};

export default function Storefront({
    view = 'home',
    products,
    product,
    auth,
    categories = [],
    brands = [],
    categoryCounts = [],
    articles,
    article,
    topics = [],
    tags = [],
    articleCategories = [],
    shippingMethods = [],
    accounting = {},
    orders = [],
    adminOrder,
    adminProduct,
    invoice,
    invoiceShipping,
    trackedOrder,
    trackingError,
    paymentResult,
    cardToCard,
    selectedCategory,
    selectedTag,
    selectedArticleCategory,
    shopFilters = { brand_id: '', category_id: '', min_price: '', max_price: '' },
    brandOptions = [],
    categoryOptions = [],
    favoriteProductIds = [],
    favoriteProducts = [],
}: any) {
    const [search, setSearch] = useState('');
    const [initialCart] = useState<StoredCart>(readStoredCart);
    const [cart, setCart] = useState<CardProduct[]>(initialCart.items);
    const [cartExpiresAt, setCartExpiresAt] = useState<number | null>(initialCart.expiresAt);
    const [fav, setFav] = useState<number[]>(() => favoriteProductIds.map(Number));
    const [panel, setPanel] = useState<'cart' | 'account' | null>(null);
    const [filter, setFilter] = useState('');
    const [shopFilterForm, setShopFilterForm] = useState<ShopFilters>(shopFilters);
    const [menuOpen, setMenuOpen] = useState(false);
    const productItems: Product[] = Array.isArray(products) ? products : products?.data || [];
    const list = productItems.map(toCard);
    const heroImage = '/images/airpods-pro.jpg';
    const pageSeo = resolveSeo(view, product, article, selectedCategory, selectedTag, selectedArticleCategory);
    useEffect(() => {
        if (view === 'shop') setShopFilterForm(shopFilters);
    }, [view, shopFilters?.brand_id, shopFilters?.category_id, shopFilters?.min_price, shopFilters?.max_price]);
    useEffect(() => {
        setFav(favoriteProductIds.map(Number));
    }, [auth?.user?.id, JSON.stringify(favoriteProductIds)]);
    const displayed = useMemo(
        () =>
            list
                .filter((p) => p.name.includes(search) || p.brand.toLowerCase().includes(search.toLowerCase()))
                .filter((p) => !filter || (filter === 'sale' ? !!p.sale : filter === 'stock' ? p.stock : p.brand === filter)),
        [list, search, filter],
    );
    useEffect(() => {
        window.localStorage.setItem('airgadget-cart', JSON.stringify({ items: cart, expiresAt: cartExpiresAt }));
    }, [cart, cartExpiresAt]);
    useEffect(() => {
        if (!cartExpiresAt) return;
        const remaining = cartExpiresAt - Date.now();
        if (remaining <= 0) {
            setCart([]);
            setCartExpiresAt(null);
            return;
        }
        const timer = window.setTimeout(() => {
            setCart([]);
            setCartExpiresAt(null);
        }, remaining);
        return () => window.clearTimeout(timer);
    }, [cartExpiresAt]);
    const addToCart = (item: CardProduct) => {
        setCartExpiresAt(null);
        setCart((items) => items.filter((cartItem) => cartItem.id === item.id).length < item.stock ? [...items, item] : items);
    };
    const clearCart = () => {
        setCart([]);
        setCartExpiresAt(null);
    };
    const holdCartForPayment = () => setCartExpiresAt(Date.now() + 10 * 60 * 1000);
    const releaseCartHold = () => setCartExpiresAt(null);
    const toggleFavorite = async (productId: number) => {
        if (!auth?.user) {
            router.visit(route('login'));
            return;
        }

        const wasFavorite = fav.includes(productId);
        setFav((items) => wasFavorite ? items.filter((id) => id !== productId) : [...items, productId]);
        try {
            const response = await axios.post(route('favorites.toggle', productId));
            setFav((items) => response.data.is_favorite
                ? Array.from(new Set([...items, productId]))
                : items.filter((id) => id !== productId));
        } catch {
            setFav((items) => wasFavorite
                ? Array.from(new Set([...items, productId]))
                : items.filter((id) => id !== productId));
        }
    };
    const applyShopFilters = (event: FormEvent) => {
        event.preventDefault();
        const params = Object.fromEntries(Object.entries(shopFilterForm).filter(([, value]) => value !== ''));
        router.get(route('shop'), params, { preserveState: true, preserveScroll: true });
    };
    const clearShopFilters = () => {
        setShopFilterForm({ brand_id: '', category_id: '', min_price: '', max_price: '' });
        router.get(route('shop'), {}, { preserveState: true, preserveScroll: true });
    };
    const section =
        view === 'shop'
            ? 'فروشگاه'
            : view === 'articles'
              ? 'مجله ایرگجت'
              : view === 'admin' || view === 'admin-orders' || view === 'admin-order' || view === 'admin-product'
                ? 'مدیریت فروشگاه'
                : view === 'account'
                  ? 'حساب کاربری'
                  : view === 'checkout'
                    ? 'تکمیل سفارش'
                    : view === 'invoice'
                      ? 'فاکتور سفارش'
                    : '';

    return (
        <>
            <Head title={pageSeo.title}>
                <meta name="description" content={pageSeo.description} />
                {pageSeo.keywords && <meta name="keywords" content={pageSeo.keywords} />}
                <meta property="og:title" content={pageSeo.title} />
                <meta property="og:description" content={pageSeo.description} />
                <meta property="og:type" content={view === 'article' ? 'article' : view === 'product' ? 'product' : 'website'} />
                {pageSeo.image && <meta property="og:image" content={pageSeo.image} />}
                {pageSeo.canonical && <link rel="canonical" href={pageSeo.canonical} />}
            </Head>
            <div className="site" dir="rtl">
                <div className="topbar">
                    <span>ارسال سریع برای سفارش‌های ثبت‌شده در مشهد</span>
                    <span>پشتیبانی: {supportPhone}</span>
                </div>
                <header>
                    <Link href="/" className="logo" aria-label="ایرگجت">
                        air<span>gadget</span><b>●</b>
                    </Link>
                    <nav className={menuOpen ? 'open' : ''}>
                        <Link href="/shop">فروشگاه</Link>
                        <Link href="/articles">مجله</Link>
                        <Link href="/about-us">درباره ما</Link>
                        <Link href="/contact-us">تماس با ما</Link>
                    </nav>
                    <div className="head-actions">
                        <Link className="account-entry" href={auth?.user ? (auth.user.is_admin ? '/admin' : '/account') : '/login'}>
                            {auth?.user ? 'حساب من' : 'ورود و ثبت‌نام'}
                        </Link>
                        {auth?.user && <button className="header-logout" type="button" onClick={() => router.post(route('logout'))}>خروج</button>}
                        <button className="icon basket" aria-label="سبد خرید" onClick={() => setPanel('cart')}>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 4h2l2.1 9.1a2 2 0 0 0 2 1.55h7.85a2 2 0 0 0 1.94-1.52L20.3 7H6" />
                                <circle cx="9.5" cy="19" r="1.25" />
                                <circle cx="17" cy="19" r="1.25" />
                            </svg>
                            <i>{cart.length}</i>
                        </button>
                        <button
                            className="icon menu-toggle"
                            aria-label="نمایش منو"
                            aria-expanded={menuOpen}
                            onClick={() => setMenuOpen((value) => !value)}
                        >
                            ☰
                        </button>
                    </div>
                </header>

                <div className="search">
                    <span>⌕</span>
                    <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        aria-label="جست‌وجوی محصولات"
                        placeholder="جست‌وجو در میان محصولات و برندها..."
                    />
                    <button onClick={() => document.querySelector('.products')?.scrollIntoView({ behavior: 'smooth' })}>
                        جست‌وجو
                    </button>
                </div>

                {view === 'home' && (
                    <>
                        <main className="hero">
                            <div>
                                <em>تجربه‌ای متفاوت از تکنولوژی</em>
                                <h1>
                                    گجت‌های کوچک،
                                    <br />
                                    <strong>لذت‌های بزرگ</strong>
                                </h1>
                                <p>
                                    جدیدترین لوازم جانبی موبایل و ایرپاد را با تضمین اصالت، آدرس {storeAddress} و پشتیبانی
                                    واقعی تهیه کنید.
                                </p>
                                <Link className="primary" href="/shop">
                                    مشاهده فروشگاه ←
                                </Link>
                            </div>
                            <div className="hero-art">
                                <div className="orb" />
                                <img src={heroImage} alt="ایرپاد پرو سفید" />
                                <span className="hero-photo-credit">
                                    عکس: <a href="https://www.arne-mueseler.com" target="_blank" rel="noreferrer">Arne Müseler</a>
                                    {' / '}
                                    <a href="https://creativecommons.org/licenses/by-sa/3.0/de/deed.en" target="_blank" rel="noreferrer">CC BY-SA 3.0</a>
                                </span>
                                <span className="float-card">✓ تضمین اصالت کالا</span>
                                <span className="float-sale">خرید آنلاین</span>
                            </div>
                        </main>
                        <section className="benefits">
                            <div>
                                ⚡ <b>ارسال سریع</b><small>تحویل در سریع‌ترین زمان</small>
                            </div>
                            <div>
                                ◆ <b>ضمانت اصالت</b><small>کالای اورجینال و باکیفیت</small>
                            </div>
                            <div>
                                ↻ <b>۷ روز ضمانت بازگشت</b><small>خریدی مطمئن و آسان</small>
                            </div>
                            <div>
                                ☏ <b>پشتیبانی واقعی</b><small>{supportPhone}</small>
                            </div>
                        </section>
                        <CategoryCards counts={categoryCounts} />
                    </>
                )}

                {view === 'articles' ? (
                    <Articles articles={Array.isArray(articles) ? articles : articles?.data || []} articleCategories={articleCategories} tags={tags} selectedArticleCategory={selectedArticleCategory} />
                ) : view === 'tag' ? (
                    <TagLanding tag={selectedTag} products={productItems} articles={Array.isArray(articles) ? articles : []} add={addToCart} />
                ) : view === 'article' ? (
                    <ArticleDetail article={article} />
                ) : view === 'product' ? (
                    <ProductDetail product={product} add={addToCart} favorite={fav.includes(product?.id)} toggleFavorite={toggleFavorite} />
                ) : view === 'admin' ? (
                    <Admin products={productItems} articles={Array.isArray(articles) ? articles : []} categories={categories} brands={brands} tags={tags} articleCategories={articleCategories} accounting={accounting} shippingMethods={shippingMethods} />
                ) : view === 'admin-orders' ? (
                    <AdminOrders orders={orders} />
                ) : view === 'admin-order' ? (
                    <AdminOrderDetail order={adminOrder} />
                ) : view === 'admin-product' ? (
                    <AdminProductEditor product={adminProduct} categories={categories} brands={brands} />
                ) : view === 'account' ? (
                    <Account orders={orders} auth={auth} favoriteProducts={favoriteProducts} favoriteIds={fav} add={addToCart} toggleFavorite={toggleFavorite} />
                ) : view === 'checkout' ? (
                    <Checkout cart={cart} shippingMethods={shippingMethods} clearCart={clearCart} holdCartForPayment={holdCartForPayment} releaseCartHold={releaseCartHold} auth={auth} cardToCard={cardToCard} />
                ) : view === 'invoice' ? (
                    <Invoice order={invoice} shipping={invoiceShipping} clearCart={clearCart} />
                ) : view === 'tracking' ? (
                    <OrderTracking order={trackedOrder} error={trackingError} />
                ) : view === 'payment-result' ? (
                    <PaymentResult result={paymentResult} />
                ) : view === 'about' || view === 'contact' || view === 'terms' ? (
                    <StaticPage type={view} />
                ) : (
                    <section className="products">
                        <div className="section-title">
                            <div>
                                <em>{view === 'shop' ? 'کالای مورد علاقه‌تان را پیدا کنید' : 'تازه از راه رسیده'}</em>
                                <h2>{selectedCategory?.name || (view === 'shop' ? 'همه محصولات' : 'آخرین محصولات')}</h2>
                            </div>
                            {view === 'shop' && (
                                <div className="filters">
                                    {[
                                        ['', 'همه'],
                                        ['sale', 'تخفیف‌دار'],
                                        ['stock', 'موجود'],
                                    ].map(([value, label]) => (
                                        <button className={filter === value ? 'active' : ''} onClick={() => setFilter(value)} key={value}>
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            )}
                            {view !== 'shop' && <Link href="/shop">مشاهده همه ←</Link>}
                        </div>
                        {view === 'shop' && <form className="shop-filters" onSubmit={applyShopFilters}>
                            <label><span>برند</span><select value={shopFilterForm.brand_id} onChange={(event) => setShopFilterForm({ ...shopFilterForm, brand_id: event.target.value })}><option value="">همه برندها</option>{brandOptions.map((brand: Brand) => <option value={brand.id} key={brand.id}>{brand.name}</option>)}</select></label>
                            <label><span>دسته‌بندی</span><select value={shopFilterForm.category_id} onChange={(event) => setShopFilterForm({ ...shopFilterForm, category_id: event.target.value })}><option value="">همه دسته‌ها</option>{categoryOptions.map((category: Category) => <option value={category.id} key={category.id}>{category.name}</option>)}</select></label>
                            <label><span>حداقل قیمت</span><input value={shopFilterForm.min_price} onChange={(event) => setShopFilterForm({ ...shopFilterForm, min_price: event.target.value })} inputMode="numeric" placeholder="تومان" /></label>
                            <label><span>حداکثر قیمت</span><input value={shopFilterForm.max_price} onChange={(event) => setShopFilterForm({ ...shopFilterForm, max_price: event.target.value })} inputMode="numeric" placeholder="تومان" /></label>
                            <button className="primary" type="submit">اعمال فیلتر</button>
                            {(shopFilterForm.brand_id || shopFilterForm.category_id || shopFilterForm.min_price || shopFilterForm.max_price) && <button className="secondary" type="button" onClick={clearShopFilters}>حذف فیلترها</button>}
                        </form>}
                        {displayed.length ? (
                            <div className="product-grid">
                                {displayed.map((p) => (
                                    <ProductCard
                                        key={p.id}
                                        p={p}
                                        add={addToCart}
                                        fav={fav.includes(p.id)}
                                        toggle={() => toggleFavorite(p.id)}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="no-results">
                                <b>هنوز محصولی برای نمایش ثبت نشده است</b>
                                <span>از پنل مدیریت محصول اضافه کنید تا همین‌جا نمایش داده شود.</span>
                            </div>
                        )}
                    </section>
                )}

                {view === 'home' && (
                    <OrderTrackingCompact />
                )}

                {view === 'home' && (
                    <section className="promo">
                        <div>
                            <em>ایرگجت مشهد</em>
                            <h2>
                                خرید مطمئن،
                                <br />
                                پشتیبانی واقعی.
                            </h2>
                            <p>
                                آدرس: {storeAddress} · پشتیبانی: {supportPhone}
                            </p>
                            <Link href="/shop" className="light-button">
                                مشاهده فروشگاه
                            </Link>
                        </div>
                        <span>
                            air
                            <br />
                            <b>gadget</b>
                        </span>
                    </section>
                )}

                <footer>
                    <div className="logo">
                        air<span>gadget</span><b>●</b>
                    </div>
                    <p>
                        مرجع خرید حرفه‌ای لوازم جانبی و گجت‌های هوشمند. آدرس: {storeAddress} · پشتیبانی: {supportPhone}
                    </p>
                    <div className="footerlinks">
                        <Link href="/terms">قوانین و مقررات</Link>
                        <Link href="/contact-us">تماس با ما</Link>
                        <Link href="/about-us">درباره ما</Link>
                    </div>
                </footer>

                {panel && <button className="drawer-backdrop" aria-label="بستن پنل" onClick={() => setPanel(null)} />}
                {panel === 'cart' && (
                    <CartDrawer cart={cart} setCart={setCart} close={() => setPanel(null)} shippingMethods={shippingMethods} />
                )}
                {panel === 'account' && <AccountDrawer auth={auth} close={() => setPanel(null)} />}
            </div>
        </>
    );
}

function ProductCard({ p, add, fav, toggle }: any) {
    return (
        <article className="card">
            <div className="photo">
                {p.sale && <span className="badge">{p.tag || 'تخفیف ویژه'}</span>}
                <button className={`heart ${fav ? 'selected' : ''}`} type="button" aria-label={fav ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'} aria-pressed={fav} onClick={toggle}>
                    {fav ? '♥' : '♡'}
                </button>
                {p.image ? <img src={p.image} alt={p.name} loading="lazy" /> : <div className="photo-placeholder">بدون عکس</div>}
                {!p.stock && <span className="out">ناموجود</span>}
            </div>
            <div className="card-body">
                <small>{p.brand}</small>
                <h3>{p.slug ? <Link href={`/products/${p.slug}`}>{p.name}</Link> : p.name}</h3>
                <div>
                    {p.sale && <del>{toman(p.price)}</del>}
                    <b>{toman(p.sale || p.price)}</b>
                    <button disabled={!p.stock} aria-label={`افزودن ${p.name} به سبد`} onClick={() => add(p)}>
                        +
                    </button>
                </div>
            </div>
        </article>
    );
}

function resolveSeo(view: string, product?: Product, article?: Article, selectedCategory?: Category, selectedTag?: Tag, selectedArticleCategory?: ArticleCategory): SeoMeta {
    if (view === 'product' && product) {
        return {
            title: product.meta_title || `${product.name} | ایرگجت`,
            description: product.meta_description || product.short_description || `خرید ${product.name} از ایرگجت با پشتیبانی ${supportPhone}`,
            keywords: product.meta_keywords || product.tags?.map((tag) => tag.name).join(', ') || undefined,
            image: imageUrl(product.main_image || product.images?.[0]?.path || product.gallery?.[0]),
            canonical: product.canonical_url || undefined,
        };
    }

    if (view === 'article' && article) {
        return {
            title: article.meta_title || `${article.title} | مجله ایرگجت`,
            description: article.meta_description || article.excerpt || siteDescription,
            keywords: article.meta_keywords || article.tags?.map((tag) => tag.name).join(', ') || undefined,
            image: article.image || undefined,
            canonical: article.canonical_url || undefined,
        };
    }

    if (view === 'shop' && selectedCategory) {
        return {
            title: `${selectedCategory.name} | فروشگاه ایرگجت`,
            description: `خرید محصولات دسته ${selectedCategory.name} از فروشگاه ایرگجت`,
            canonical: route('categories.show', selectedCategory.slug),
        };
    }

    if (view === 'tag' && selectedTag) {
        return {
            title: `${selectedTag.name} | محصولات و مقالات ایرگجت`,
            description: `محصولات، راهنماها و مطالب مرتبط با ${selectedTag.name} در ایرگجت`,
            canonical: route('tags.show', selectedTag.slug),
        };
    }

    if (view === 'articles' && selectedArticleCategory) {
        return {
            title: `${selectedArticleCategory.name} | مجله ایرگجت`,
            description: selectedArticleCategory.description || `مقالات دسته‌بندی ${selectedArticleCategory.name} در مجله ایرگجت`,
            canonical: route('article-categories.show', selectedArticleCategory.slug),
        };
    }

    if (view === 'articles') {
        return {
            title: 'مجله ایرگجت | راهنمای خرید و آموزش',
            description: 'راهنما، آموزش و بررسی تخصصی لوازم جانبی موبایل، ایرپاد، شارژر و گجت‌های هوشمند',
        };
    }

    if (view === 'shop') {
        return {
            title: 'فروشگاه ایرگجت | خرید لوازم جانبی موبایل',
            description: 'خرید لوازم جانبی موبایل، ایرپاد، شارژر، کابل، قاب و گجت با پشتیبانی ایرگجت',
        };
    }

    return {
        title: siteTitle,
        description: siteDescription,
        image: undefined,
        keywords: 'لوازم جانبی موبایل, ایرپاد, شارژر, قاب گوشی, گجت',
        canonical: undefined,
    };
}

function ProductDetail({ product, add, favorite, toggleFavorite }: { product: Product; add: (product: CardProduct) => void; favorite: boolean; toggleFavorite: (productId: number) => void }) {
    const gallery = Array.from(new Set([
        product?.main_image,
        ...(product?.images?.map((image) => image.path) || []),
        ...(product?.gallery || []),
    ].map(imageUrl).filter((path): path is string => Boolean(path))));
    const [selectedImage, setSelectedImage] = useState<string | undefined>(() => gallery[0]);

    if (!product) {
        return <main className="page"><h1>محصول پیدا نشد</h1></main>;
    }

    const card = toCard(product);

    return (
        <main className="page product-detail">
            <div className="product-detail-grid">
                <section className="product-gallery">
                    {selectedImage || card.image ? <img src={selectedImage || card.image} alt={product.name} /> : <div className="photo-placeholder">بدون عکس</div>}
                    {gallery.length > 1 && (
                        <div className="thumbs">
                            {gallery.map((path) => <button className={selectedImage === path ? 'active' : ''} onClick={() => setSelectedImage(path)} key={path}><img src={path} alt={product.name} /></button>)}
                        </div>
                    )}
                </section>
                <section className="product-info">
                    <small>{product.category?.name || 'محصول'} · {product.brand?.name || 'ایرگجت'}</small>
                    <h1>{product.name}</h1>
                    {product.tags && product.tags.length > 0 && <div className="article-tags">{product.tags.map((tag) => <Link href={route('tags.show', tag.slug)} key={tag.id}>#{tag.name}</Link>)}</div>}
                    <p>{product.short_description || product.description || 'این محصول با ضمانت اصالت کالا در ایرگجت عرضه می‌شود.'}</p>
                    <div className="product-price">
                        {product.sale_price && <del>{toman(Number(product.price))}</del>}
                        <b>{toman(Number(product.sale_price || product.price || 0))}</b>
                    </div>
                    <div className="product-detail-actions">
                        <button className="primary" disabled={!card.stock} onClick={() => add(card)}>
                            {card.stock ? 'افزودن به سبد خرید' : 'ناموجود'}
                        </button>
                        <button className={`favorite-button ${favorite ? 'selected' : ''}`} type="button" aria-pressed={favorite} onClick={() => toggleFavorite(product.id)}>
                            {favorite ? '♥ حذف از علاقه‌مندی‌ها' : '♡ افزودن به علاقه‌مندی‌ها'}
                        </button>
                    </div>
                    {product.description && <article className="product-description">{product.description}</article>}
                </section>
            </div>
        </main>
    );
}

function CategoryCards({ counts }: { counts: Category[] }) {
    const countByName = new Map((counts || []).map((item) => [item.name, item.products_count || 0]));
    const items = [
        ['🎧', 'ایرپاد و هندزفری'],
        ['⚡', 'شارژر و کابل'],
        ['📱', 'قاب و محافظ'],
        ['⌚', 'گجت هوشمند'],
    ];

    return (
        <section className="categories">
            <div className="section-title">
                <div>
                    <em>دسته‌بندی‌ها</em>
                    <h2>هر چه نیاز دارید</h2>
                </div>
            </div>
            <div className="category-grid">
                {items.map(([icon, title]) => {
                    const category = (counts || []).find((item) => item.name === title);
                    const count = category?.products_count;

                    return (
                        <Link href={category?.slug ? route('categories.show', category.slug) : '/shop'} key={title}>
                            <i>{icon}</i>
                            <b>{title}</b>
                            <small>{count ? `${new Intl.NumberFormat('fa-IR').format(count)} محصول ←` : 'مشاهده محصولات ←'}</small>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function Articles({ articles, articleCategories, tags, selectedArticleCategory }: { articles: Article[]; articleCategories: ArticleCategory[]; tags: Tag[]; selectedArticleCategory?: ArticleCategory }) {
    return (
        <main className="page">
            <em>راهنما و بررسی تخصصی</em>
            <h1>{selectedArticleCategory ? `مقالات ${selectedArticleCategory.name}` : 'مجله ایرگجت'}</h1>
            <div className="article-taxonomy">
                {articleCategories.map((category) => <Link className={selectedArticleCategory?.id === category.id ? 'active' : ''} href={route('article-categories.show', category.slug)} key={category.id}>{category.name}</Link>)}
                {tags.map((tag) => <Link href={route('tags.show', tag.slug)} key={tag.id}>#{tag.name}</Link>)}
            </div>
            <div className="article-grid">
                {articles.length ? articles.map((article) => (
                    <article className="article" key={article.id}>
                        {article.image ? <img src={article.image} alt={article.title} loading="lazy" /> : <div className="article-image" />}
                        <small>{article.category?.name || 'مجله ایرگجت'}</small>
                        <h2>{article.title}</h2>
                        <p>{article.excerpt || 'نکات کاربردی و اطلاعات دقیق برای انتخاب بهتر لوازم جانبی.'}</p>
                        <div className="article-tags">{article.tags?.map((tag) => <Link href={route('tags.show', tag.slug)} key={tag.id}>#{tag.name}</Link>)}</div>
                        <Link href={`/articles/${article.slug}`}>ادامه مطلب ←</Link>
                    </article>
                )) : (
                    <div className="no-results">
                        <b>هنوز مقاله‌ای منتشر نشده است</b>
                        <span>از پنل مدیریت مقاله جدید با موضوع، تگ و عکس اصلی ثبت کنید.</span>
                    </div>
                )}
            </div>
        </main>
    );
}

function TagLanding({ tag, products, articles, add }: { tag: Tag; products: Product[]; articles: Article[]; add: (product: CardProduct) => void }) {
    return (
        <main className="page tag-landing">
            <em>برچسب</em>
            <h1>#{tag.name}</h1>
            <h2>محصولات مرتبط</h2>
            <div className="grid">
                {products.length ? products.map((product) => <ProductCard key={product.id} p={toCard(product)} add={add} fav={false} toggle={() => undefined} />) : <p>محصولی با این تگ ثبت نشده است.</p>}
            </div>
            <h2>مقالات مرتبط</h2>
            <div className="article-grid">
                {articles.length ? articles.map((article) => (
                    <article className="article" key={article.id}>
                        {article.image ? <img src={article.image} alt={article.title} loading="lazy" /> : <div className="article-image" />}
                        <small>{article.category?.name || 'مجله ایرگجت'}</small>
                        <h3>{article.title}</h3>
                        <p>{article.excerpt}</p>
                        <Link href={route('articles.show', article.slug)}>ادامه مطلب ←</Link>
                    </article>
                )) : <p>مقاله‌ای با این تگ ثبت نشده است.</p>}
            </div>
        </main>
    );
}

function ArticleDetail({ article }: { article: Article }) {
    if (!article) {
        return <main className="page"><h1>مقاله پیدا نشد</h1></main>;
    }

    return (
        <main className="page article-detail">
            <em>{article.category ? <Link href={route('article-categories.show', article.category.slug)}>{article.category.name}</Link> : 'مجله ایرگجت'}</em>
            <h1>{article.title}</h1>
            {article.image && <img src={article.image} alt={article.title} />}
            {article.excerpt && <p className="lead">{article.excerpt}</p>}
            <article>{article.body}</article>
            {article.tags && article.tags.length > 0 && (
                <div className="article-tags">{article.tags.map((tag) => <Link href={route('tags.show', tag.slug)} key={tag.id}>#{tag.name}</Link>)}</div>
            )}
        </main>
    );
}

function StaticPage({ type }: any) {
    const data: any = {
        about: [
            'درباره ایرگجت',
            'ایرگجت با هدف آسان‌تر کردن خرید کالای دیجیتال و لوازم جانبی موبایل فعالیت می‌کند. کیفیت، اصالت و رضایت شما اولویت ماست.',
        ],
        contact: ['تماس با ما', `آدرس: ${storeAddress} · پشتیبانی: ${supportPhone} · ایمیل: support@airgadget.ir`],
        terms: [
            'قوانین و مقررات',
            'ثبت سفارش به منزله پذیرش قوانین فروشگاه است. امکان بازگشت کالا مطابق قوانین تجارت الکترونیک و شرایط هر محصول فراهم است.',
        ],
    };

    return (
        <main className="page static">
            <em>AirGadget</em>
            <h1>{data[type][0]}</h1>
            <p>{data[type][1]}</p>
            {type === 'contact' && (
                <form>
                    <input placeholder="نام و نام خانوادگی" />
                    <input placeholder="شماره تماس" />
                    <textarea placeholder="پیام شما" />
                    <button className="primary">ارسال پیام</button>
                </form>
            )}
        </main>
    );
}

function Account({ orders, auth, favoriteProducts, favoriteIds, add, toggleFavorite }: { orders: Order[]; auth: any; favoriteProducts: Product[]; favoriteIds: number[]; add: (product: CardProduct) => void; toggleFavorite: (productId: number) => void }) {
    const { props } = usePage<any>();
    const [section, setSection] = useState<'orders' | 'favorites' | 'profile'>('orders');
    const profile = useForm({
        first_name: auth?.user?.first_name || '',
        last_name: auth?.user?.last_name || '',
        phone_number: auth?.user?.phone_number || '',
        postal_code: auth?.user?.postal_code || '',
        address: auth?.user?.address || '',
        email: auth?.user?.email || '',
        password: '',
        password_confirmation: '',
    });
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', unpaid: 'پرداخت‌نشده', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
    return (
        <main className="page account">
            <h1>حساب کاربری من</h1>
            {props.flash?.status && <div className="admin-status">{props.flash.status}</div>}
            <div className="account-grid">
                <aside>
                    <b>داشبورد</b>
                    <button className={section === 'orders' ? 'active' : ''} onClick={() => setSection('orders')}>سفارش‌های من</button>
                    <button className={section === 'favorites' ? 'active' : ''} onClick={() => setSection('favorites')}>کالاهای مورد علاقه</button>
                    <button className={section === 'profile' ? 'active' : ''} onClick={() => setSection('profile')}>ویرایش اطلاعات حساب</button>
                    {auth?.user?.is_admin && <Link href="/admin">پنل مدیریت فروشگاه</Link>}
                    <button className="logout-button" onClick={() => router.post('/logout')}>خروج از حساب</button>
                </aside>
                <section>
                    {section === 'orders' ? <>
                        <h2>سفارش‌های من</h2>
                        <p>{auth?.user?.first_name || 'کاربر'} عزیز، وضعیت سفارش‌ها و فاکتورهای شما اینجاست.</p>
                        <div className="stats"><b>{new Intl.NumberFormat('fa-IR').format(orders.length)}<small>کل سفارش‌ها</small></b><b>{new Intl.NumberFormat('fa-IR').format(orders.filter((order) => order.status === 'completed').length)}<small>تکمیل‌شده</small></b></div>
                        <div className="customer-orders">
                            {orders.length ? orders.map((order) => (
                                <article key={order.id}>
                                    <span><b>سفارش {order.number}</b><small>{labels[order.status] || order.status}</small></span>
                                    <strong>{toman(Number(order.total))}</strong>
                                    {order.invoice_token && <span className="invoice-links"><Link href={`/orders/${order.id}/invoice/${order.invoice_token}`}>مشاهده فاکتور</Link><a href={`/orders/${order.id}/invoice/${order.invoice_token}/pdf`} target="_blank" rel="noreferrer">دانلود PDF</a></span>}
                                </article>
                            )) : <p>هنوز سفارشی ثبت نکرده‌اید.</p>}
                        </div>
                    </> : section === 'favorites' ? <>
                        <h2>کالاهای مورد علاقه من</h2>
                        <p>کالاهایی که قبلاً انتخاب کرده‌اید اینجا نگهداری می‌شوند.</p>
                        {favoriteProducts.filter((item) => favoriteIds.includes(item.id)).length ? (
                            <div className="product-grid account-favorites">
                                {favoriteProducts.filter((item) => favoriteIds.includes(item.id)).map((item) => (
                                    <ProductCard
                                        key={item.id}
                                        p={toCard(item)}
                                        add={add}
                                        fav
                                        toggle={() => toggleFavorite(item.id)}
                                    />
                                ))}
                            </div>
                        ) : <div className="no-results"><b>هنوز کالایی انتخاب نکرده‌اید</b><span>از فروشگاه روی علامت قلب کالا بزنید.</span></div>}
                    </> : <>
                        <h2>ویرایش اطلاعات حساب</h2>
                        <form className="profile-form" onSubmit={(event) => { event.preventDefault(); profile.patch(route('account.profile.update'), { preserveScroll: true, onSuccess: () => profile.reset('password', 'password_confirmation') }); }}>
                            <div className="admin-two"><label>نام<input value={profile.data.first_name} onChange={(event) => profile.setData('first_name', event.target.value)} /></label><label>نام خانوادگی<input value={profile.data.last_name} onChange={(event) => profile.setData('last_name', event.target.value)} /></label></div>
                            <label>شماره موبایل<input inputMode="tel" value={profile.data.phone_number} onChange={(event) => profile.setData('phone_number', event.target.value)} /></label>
                            <label>کد پستی<input inputMode="numeric" value={profile.data.postal_code} onChange={(event) => profile.setData('postal_code', event.target.value)} /></label>
                            <label>آدرس کامل<textarea value={profile.data.address} onChange={(event) => profile.setData('address', event.target.value)} /></label>
                            <label>ایمیل<input type="email" value={profile.data.email} onChange={(event) => profile.setData('email', event.target.value)} /></label>
                            <div className="admin-two"><label>رمز عبور جدید (اختیاری)<input type="password" value={profile.data.password} onChange={(event) => profile.setData('password', event.target.value)} /></label><label>تکرار رمز جدید<input type="password" value={profile.data.password_confirmation} onChange={(event) => profile.setData('password_confirmation', event.target.value)} /></label></div>
                            {Object.keys(profile.errors).length > 0 && <div className="form-error-box">{Object.values(profile.errors)[0]}</div>}
                            <button className="primary" disabled={profile.processing}>{profile.processing ? 'در حال ذخیره...' : 'ذخیره اطلاعات حساب'}</button>
                        </form>
                    </>}
                </section>
            </div>
        </main>
    );
}

function Checkout({ cart, shippingMethods, clearCart, holdCartForPayment, releaseCartHold, auth, cardToCard }: { cart: CardProduct[]; shippingMethods: ShippingMethod[]; clearCart: () => void; holdCartForPayment: () => void; releaseCartHold: () => void; auth: any; cardToCard?: { number: string; holder: string } }) {
    const quantities = Object.values(cart.reduce<Record<number, { product_id: number; quantity: number }>>((items, product) => {
        items[product.id] = items[product.id] || { product_id: product.id, quantity: 0 };
        items[product.id].quantity++;
        return items;
    }, {}));
    const groupedCart = Object.values(cart.reduce<Record<number, { product: CardProduct; quantity: number }>>((items, product) => {
        items[product.id] = items[product.id] || { product, quantity: 0 };
        items[product.id].quantity++;
        return items;
    }, {}));
    const form = useForm({
        first_name: auth?.user?.first_name || '',
        last_name: auth?.user?.last_name || '',
        phone: auth?.user?.phone_number || '',
        postal_code: auth?.user?.postal_code || '',
        address: auth?.user?.address || '',
        shipping_method: shippingMethods[0]?.code || '',
        payment_method: 'zarinpal',
        card_amount: '',
        receipt: null as File | null,
        items: quantities,
    });
    const shipping = shippingMethods.find((method) => method.code === form.data.shipping_method);
    const subtotal = cart.reduce((sum, product) => sum + (product.sale || product.price), 0);
    return (
        <main className="page checkout">
            <h1>تکمیل سفارش</h1>
            {!auth?.user && <div className="checkout-auth-choice"><div><b>خرید بدون ثبت‌نام</b><small>اطلاعات گیرنده را وارد کنید و مستقیم پرداخت کنید.</small></div><span>یا</span><Link href="/login">ورود</Link><Link href="/register">ثبت‌نام</Link></div>}
            {auth?.user && <div className="checkout-user-note">سفارش برای حساب <b>{auth.user.first_name} {auth.user.last_name}</b> ثبت می‌شود و در پنل شما قابل مشاهده است.</div>}
            <div className="checkout-grid">
                <form onSubmit={(event) => {
                    event.preventDefault();
                    if (form.data.payment_method === 'zarinpal') holdCartForPayment();
                    form.post(route('checkout.store'), {
                        forceFormData: form.data.payment_method === 'card_to_card',
                        onSuccess: () => { if (form.data.payment_method === 'card_to_card') clearCart(); },
                        onError: releaseCartHold,
                    });
                }}>
                    <h3>مشخصات تحویل‌گیرنده</h3>
                    <div className="admin-two">
                        <input placeholder="نام" value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} />
                        <input placeholder="نام خانوادگی" value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} />
                    </div>
                    <input placeholder="شماره موبایل" inputMode="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    <input placeholder="کد پستی ۱۰ رقمی" inputMode="numeric" value={form.data.postal_code} onChange={(event) => form.setData('postal_code', event.target.value)} />
                    <textarea placeholder="آدرس کامل گیرنده" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                    <h3>روش ارسال</h3>
                    {shippingMethods.length ? shippingMethods.map((method) => (
                        <label className="shipping-choice" key={method.code}>
                            <input type="radio" name="ship" value={method.code} checked={form.data.shipping_method === method.code} onChange={() => form.setData('shipping_method', method.code)} />
                            <span><b>{method.name}</b><small>{method.description}</small></span>
                            <strong>{Number(method.cost) === 0 ? 'رایگان' : toman(Number(method.cost))}</strong>
                        </label>
                    )) : <p>در حال حاضر روش ارسال فعالی تعریف نشده است.</p>}
                    <h3>روش پرداخت</h3>
                    <label className="payment-choice"><input type="radio" name="pay" checked={form.data.payment_method === 'zarinpal'} onChange={() => form.setData('payment_method', 'zarinpal')} /> پرداخت امن آنلاین با زرین‌پال</label>
                    <label className="payment-choice"><input type="radio" name="pay" checked={form.data.payment_method === 'card_to_card'} onChange={() => form.setData('payment_method', 'card_to_card')} /> کارت‌به‌کارت و بارگذاری فیش</label>
                    {form.data.payment_method === 'card_to_card' && <div className="card-payment-box">
                        <small>مبلغ سفارش را به کارت زیر واریز کنید:</small>
                        <b className="card-number" dir="ltr">{(cardToCard?.number || '6037997199529528').replace(/(\d{4})(?=\d)/g, '$1 ')}</b>
                        <span>به نام {cardToCard?.holder || 'سید محمد یوسف سادات فخر'}</span>
                        <label>مبلغ واریزی (تومان)<input inputMode="numeric" value={form.data.card_amount} onChange={(event) => form.setData('card_amount', event.target.value)} placeholder={String(subtotal + Number(shipping?.cost || 0))} /></label>
                        <label className="receipt-upload">تصویر فیش واریزی<input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => form.setData('receipt', event.target.files?.[0] || null)} /><small>{form.data.receipt?.name || 'حداکثر ۵ مگابایت'}</small></label>
                    </div>}
                    {Object.keys(form.errors).length > 0 && <div className="form-error-box">{Object.values(form.errors)[0]}</div>}
                    <button className="primary" disabled={form.processing || cart.length === 0 || !form.data.shipping_method}>{form.processing ? 'در حال ثبت...' : form.data.payment_method === 'card_to_card' ? 'ثبت فیش و سفارش' : 'پرداخت با زرین‌پال'}</button>
                </form>
                <aside>
                    <h3>خلاصه سفارش</h3>
                    <div className="checkout-items">
                        {groupedCart.map(({ product, quantity }) => <div key={product.id}>{product.image ? <img src={product.image} alt={product.name} /> : <span className="mini-placeholder" />}<span><b>{product.name}</b><small>{quantity} × {toman(product.sale || product.price)}</small></span><strong>{toman((product.sale || product.price) * quantity)}</strong></div>)}
                    </div>
                    <p>هزینه ارسال: {shipping ? (Number(shipping.cost) ? toman(Number(shipping.cost)) : 'رایگان') : '—'}</p>
                    <div className="checkout-grand"><span>جمع کل</span><b>{toman(subtotal + Number(shipping?.cost || 0))}</b></div>
                </aside>
            </div>
        </main>
    );
}

function Invoice({ order, shipping, clearCart }: { order: Order; shipping?: ShippingMethod; clearCart: () => void }) {
    const { props } = usePage<any>();
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', unpaid: 'پرداخت‌نشده', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
    useEffect(() => {
        if (order.paid_at || order.payment_method === 'card_to_card') clearCart();
    }, [order.paid_at, order.payment_method]);
    return (
        <main className="page invoice-page">
            {props.flash?.status && <div className="payment-success"><b>پرداخت موفق</b><span>{props.flash.status}</span></div>}
            <div className="invoice-actions"><Link href="/">بازگشت به فروشگاه</Link><span><a href={`/orders/${order.id}/invoice/${order.invoice_token}/pdf`} target="_blank" rel="noreferrer">دانلود فاکتور PDF</a><button onClick={() => window.print()}>چاپ فاکتور</button></span></div>
            <section className="invoice-sheet">
                <header><div className="logo">air<span>gadget</span></div><div><b>فاکتور فروش</b><small>شماره: {order.number}</small></div></header>
                <div className="invoice-meta">
                    <p><b>خریدار:</b> {order.address?.customer_name}</p>
                    <p><b>موبایل:</b> {order.address?.phone}</p>
                    <p><b>کد پستی:</b> {order.address?.postal_code}</p>
                    <p><b>وضعیت:</b> {labels[order.status] || order.status}</p>
                    {order.payment_reference && <p><b>پیگیری پرداخت:</b> {order.payment_reference}</p>}
                    {order.payment_method === 'card_to_card' && <p><b>روش پرداخت:</b> کارت‌به‌کارت</p>}
                    <p className="invoice-address"><b>آدرس:</b> {order.address?.full}</p>
                </div>
                <div className="invoice-table">
                    <div className="invoice-row invoice-head"><span>شرح کالا</span><span>تعداد</span><span>قیمت واحد</span><span>جمع</span></div>
                    {order.items?.map((item) => <div className="invoice-row" key={item.id || item.sku}><span>{item.name}<small>{item.sku}</small></span><span>{item.quantity}</span><span>{toman(Number(item.price || 0))}</span><span>{toman(Number(item.price || 0) * item.quantity)}</span></div>)}
                </div>
                <div className="invoice-totals">
                    <p><span>جمع کالاها</span><b>{toman(Number(order.subtotal || 0))}</b></p>
                    <p><span>ارسال ({shipping?.name || order.shipping_method})</span><b>{Number(order.shipping_cost || 0) ? toman(Number(order.shipping_cost)) : 'رایگان'}</b></p>
                    <p className="grand"><span>مبلغ قابل پرداخت</span><b>{toman(Number(order.total))}</b></p>
                </div>
                <footer>ایرگجت ـ خراسان رضوی، مشهد، عبدالمطلب ۳۵ ـ پشتیبانی {supportPhone}</footer>
            </section>
        </main>
    );
}

function OrderTrackingCompact() {
    const form = useForm({ number: '', phone: '' });
    return (
        <section className="tracking-compact">
            <div><em>پیگیری سریع</em><h2>سفارشتان کجاست؟</h2><p>کد سفارش و شماره موبایل ثبت‌شده را وارد کنید.</p></div>
            <form onSubmit={(event) => { event.preventDefault(); router.get(route('orders.track'), form.data); }}>
                <input value={form.data.number} onChange={(event) => form.setData('number', event.target.value.toUpperCase())} placeholder="کد سفارش، مانند AG-..." dir="ltr" />
                <input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} placeholder="شماره موبایل" inputMode="tel" dir="ltr" />
                <button className="primary">پیگیری سفارش</button>
            </form>
        </section>
    );
}

function OrderTracking({ order, error }: { order?: Order; error?: string }) {
    const form = useForm({ number: order?.number || '', phone: order?.address?.phone || '' });
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', unpaid: 'پرداخت‌نشده', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
    const steps = ['pending_review', 'processing', 'completed'];
    const activeStep = steps.indexOf(order?.status || '');
    return (
        <main className="page tracking-page">
            <h1>پیگیری سفارش</h1>
            <form className="tracking-form" onSubmit={(event) => { event.preventDefault(); router.get(route('orders.track'), form.data, { preserveState: true }); }}>
                <input value={form.data.number} onChange={(event) => form.setData('number', event.target.value.toUpperCase())} placeholder="کد سفارش" dir="ltr" />
                <input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} placeholder="شماره موبایل سفارش" inputMode="tel" dir="ltr" />
                <button className="primary" disabled={form.processing}>بررسی وضعیت</button>
            </form>
            {error && <div className="form-error-box">{error}</div>}
            {order && <section className="tracking-result">
                <div className="tracking-heading"><span><small>کد سفارش</small><b>{order.number}</b></span><strong>{labels[order.status] || order.status}</strong></div>
                <div className="order-progress">
                    {steps.map((step, index) => <div className={activeStep >= index ? 'done' : ''} key={step}><i>{activeStep >= index ? '✓' : index + 1}</i><span>{labels[step]}</span></div>)}
                </div>
                <div className="tracking-products">{order.items?.map((item) => <p key={item.id || item.sku}><span>{item.name} × {item.quantity}</span><b>{toman(Number(item.price || 0) * item.quantity)}</b></p>)}</div>
                <div className="tracking-total"><span>جمع سفارش</span><b>{toman(Number(order.total))}</b></div>
            </section>}
        </main>
    );
}

function PaymentResult({ result }: { result: { success?: boolean; number?: string; message?: string; expires_at?: string } }) {
    const expiresAt = result?.expires_at ? new Date(result.expires_at).getTime() : 0;
    const [remaining, setRemaining] = useState(() => Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000)));
    useEffect(() => {
        if (!expiresAt) return;
        const timer = window.setInterval(() => setRemaining(Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000))), 1000);
        return () => window.clearInterval(timer);
    }, [expiresAt]);
    const minutes = Math.floor(remaining / 60);
    const seconds = String(remaining % 60).padStart(2, '0');
    return (
        <main className="page payment-result">
            <div className={result?.success ? 'result-icon success' : 'result-icon failed'}>{result?.success ? '✓' : '×'}</div>
            <h1>{result?.success ? 'پرداخت موفق بود' : 'پرداخت انجام نشد'}</h1>
            {result?.number && <p>کد سفارش: <b>{result.number}</b></p>}
            <p>{result?.message}</p>
            {!result?.success && expiresAt > 0 && <div className={remaining > 0 ? 'payment-countdown' : 'payment-countdown expired'}>{remaining > 0 ? <>زمان حفظ سبد: <b dir="ltr">{minutes}:{seconds}</b></> : 'مهلت پرداخت تمام شد؛ وضعیت سفارش پرداخت‌نشده است.'}</div>}
            <div>{!result?.success && remaining > 0 && <Link className="primary" href="/checkout">تلاش دوباره برای پرداخت</Link>}<Link className={remaining > 0 ? 'secondary' : 'primary'} href="/">بازگشت به فروشگاه</Link><Link className="secondary" href="/track-order">پیگیری سفارش</Link></div>
        </main>
    );
}

function Admin({
    products,
    articles,
    categories,
    brands,
    tags,
    articleCategories,
    accounting,
    shippingMethods,
}: {
    products: Product[];
    articles: Article[];
    categories: Category[];
    brands: Brand[];
    tags: Tag[];
    articleCategories: ArticleCategory[];
    accounting: Accounting;
    shippingMethods: ShippingMethod[];
}) {
    const { props } = usePage<any>();
    const [previews, setPreviews] = useState<string[]>([]);
    const [productUploadProgress, setProductUploadProgress] = useState(0);
    const [productUploadError, setProductUploadError] = useState('');
    const [preparingProductImages, setPreparingProductImages] = useState(false);
    const [articlePreview, setArticlePreview] = useState<string | null>(null);
    const [tab, setTab] = useState<'accounting' | 'products' | 'articles' | 'shipping'>('accounting');
    const { data, setData, post, processing, errors, reset, transform } = useForm<AdminForm>({
        name: '',
        sku: '',
        category_id: '',
        category_name: '',
        brand_id: '',
        brand_name: '',
        price: '',
        sale_price: '',
        stock: '0',
        short_description: '',
        description: '',
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        tags: '',
        main_image_index: 0,
        images: [],
    });
    const articleForm = useForm<ArticleForm>({
        title: '',
        excerpt: '',
        body: '',
        tags: '',
        category_name: '',
        meta_title: '',
        meta_description: '',
        main_image: null,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const files = [...data.images];
        const mainImageIndex = data.main_image_index;
        setProductUploadError('');
        setProductUploadProgress(0);
        transform((values) => ({ ...values, images: [] }));
        post(route('admin.products.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: async (page) => {
                const productId = Number((page.props as any).flash?.createdProductId || 0);
                try {
                    if (files.length && !productId) {
                        throw new Error('شناسه محصول برای آپلود تصاویر دریافت نشد.');
                    }
                    if (files.length) {
                        await uploadProductImages(productId, files, mainImageIndex, setProductUploadProgress);
                    }
                    previews.forEach((preview) => URL.revokeObjectURL(preview));
                    setPreviews([]);
                    reset();
                    router.reload({ only: ['products'] });
                } catch (error) {
                    setProductUploadError(uploadErrorMessage(error));
                }
            },
        });
    };
    const submitArticle = (event: FormEvent) => {
        event.preventDefault();
        articleForm.post(route('admin.articles.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (articlePreview) {
                    URL.revokeObjectURL(articlePreview);
                }
                setArticlePreview(null);
                articleForm.reset();
            },
        });
    };

    return (
        <main className="page admin">
            <h1>پنل مدیریت</h1>
            {props.flash?.status && <div className="admin-status">{props.flash.status}</div>}
            <div className="admin-tabs">
                <button className={tab === 'accounting' ? 'active' : ''} onClick={() => setTab('accounting')}>حسابداری مدیریت</button>
                <Link href={route('admin.orders.index')}>مدیریت سفارشات</Link>
                <button className={tab === 'products' ? 'active' : ''} onClick={() => setTab('products')}>مدیریت محصولات</button>
                <button className={tab === 'articles' ? 'active' : ''} onClick={() => setTab('articles')}>مدیریت مقالات</button>
                <button className={tab === 'shipping' ? 'active' : ''} onClick={() => setTab('shipping')}>مدیریت ارسال</button>
            </div>
            <div className="dashboard">
                <div><b>{new Intl.NumberFormat('fa-IR').format(products.length)}</b><small>محصول ثبت‌شده</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(products.filter((p) => p.stock > 0).length)}</b><small>محصول موجود</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(articles.length)}</b><small>مقاله</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(categories.length + brands.length)}</b><small>دسته و برند</small></div>
            </div>
            <div className="admin-grid">
                <section className={tab === 'products' ? '' : 'admin-hidden'}>
                    <h2>افزودن محصول و SEO</h2>
                    <form className="admin-form" onSubmit={submit}>
                        {Object.keys(errors).length > 0 && (
                            <div className="form-error-box">
                                لطفاً خطاهای فرم را بررسی کنید. نام و قیمت اصلی محصول الزامی هستند؛ SKU و موجودی می‌توانند خالی بمانند.
                            </div>
                        )}
                        <input value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="نام محصول" />
                        {errors.name && <small className="form-error">{errors.name}</small>}
                        <input value={data.sku} onChange={(event) => setData('sku', event.target.value)} placeholder="شناسه محصول / SKU اختیاری" />
                        {errors.sku && <small className="form-error">{errors.sku}</small>}
                        <div className="admin-two">
                            <select value={data.category_id} onChange={(event) => setData('category_id', event.target.value)}>
                                <option value="">دسته جدید یا عمومی</option>
                                {categories.map((category) => <option value={category.id} key={category.id}>{category.name}</option>)}
                            </select>
                            <input
                                value={data.category_name}
                                onChange={(event) => setData('category_name', event.target.value)}
                                placeholder="نام دسته جدید"
                            />
                        </div>
                        <div className="admin-two">
                            <select value={data.brand_id} onChange={(event) => setData('brand_id', event.target.value)}>
                                <option value="">بدون برند یا برند جدید</option>
                                {brands.map((brand) => <option value={brand.id} key={brand.id}>{brand.name}</option>)}
                            </select>
                            <input value={data.brand_name} onChange={(event) => setData('brand_name', event.target.value)} placeholder="نام برند جدید" />
                        </div>
                        <div className="admin-two">
                            <input required value={data.price} onChange={(event) => setData('price', event.target.value)} inputMode="numeric" placeholder="قیمت اصلی (الزامی)" />
                            <input
                                value={data.sale_price}
                                onChange={(event) => setData('sale_price', event.target.value)}
                                inputMode="numeric"
                                placeholder="قیمت تخفیفی"
                            />
                        </div>
                        {errors.price && <small className="form-error">{errors.price}</small>}
                        {errors.sale_price && <small className="form-error">{errors.sale_price}</small>}
                        <input value={data.stock} onChange={(event) => setData('stock', event.target.value)} inputMode="numeric" placeholder="موجودی اختیاری" />
                        {errors.stock && <small className="form-error">{errors.stock}</small>}
                        <textarea
                            value={data.short_description}
                            onChange={(event) => setData('short_description', event.target.value)}
                            placeholder="توضیح کوتاه"
                        />
                        <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} placeholder="توضیحات کامل" />
                        <input value={data.meta_title} onChange={(event) => setData('meta_title', event.target.value)} placeholder="عنوان سئو محصول" />
                        <textarea
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            placeholder="توضیحات متا محصول"
                        />
                        <input value={data.meta_keywords} onChange={(event) => setData('meta_keywords', event.target.value)} placeholder="کلمات کلیدی محصول، جداشده با ویرگول" />
                        <input value={data.tags} onChange={(event) => setData('tags', event.target.value)} placeholder="تگ‌های محصول با ویرگول، مثل انکر، هندزفری" />
                        <label className="upload-box">
                            <span>انتخاب تصاویر محصول</span>
                            <small>می‌توانید چند عکس انتخاب کنید؛ بعد یکی را به عنوان عکس اصلی بزنید.</small>
                            <span className="file-picker-button">انتخاب فایل‌ها</span>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                onChange={async (event) => {
                                    previews.forEach((preview) => URL.revokeObjectURL(preview));
                                    setPreparingProductImages(true);
                                    setProductUploadError('');
                                    try {
                                        const files = await prepareProductImages(Array.from(event.target.files || []));
                                        setData('images', files);
                                        setData('main_image_index', 0);
                                        setPreviews(files.map((file) => URL.createObjectURL(file)));
                                    } catch (error) {
                                        setProductUploadError(uploadErrorMessage(error));
                                    } finally {
                                        setPreparingProductImages(false);
                                    }
                                }}
                            />
                        </label>
                        {errors.images && <small className="form-error">{errors.images}</small>}
                        {preparingProductImages && <small className="upload-preparing">در حال کم‌حجم‌سازی تصاویر...</small>}
                        {productUploadError && <small className="form-error upload-error">{productUploadError}</small>}
                        {productUploadProgress > 0 && <div className="upload-progress"><span style={{ width: `${productUploadProgress}%` }} /><b>{new Intl.NumberFormat('fa-IR').format(productUploadProgress)}٪ آپلود شد</b></div>}
                        {previews.length > 0 && (
                            <div className="image-picker">
                                {previews.map((preview, index) => (
                                    <label className={data.main_image_index === index ? 'selected' : ''} key={preview}>
                                        <input
                                            type="radio"
                                            name="main-image"
                                            checked={data.main_image_index === index}
                                            onChange={() => setData('main_image_index', index)}
                                        />
                                        <img src={preview} alt={`تصویر ${index + 1}`} />
                                        <span>عکس اصلی</span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <button className="primary" disabled={processing || preparingProductImages}>
                            {processing || productUploadProgress > 0 && productUploadProgress < 100 ? 'در حال ثبت و آپلود...' : 'ثبت محصول'}
                        </button>
                    </form>
                </section>
                <section className={tab === 'articles' ? '' : 'admin-hidden'}>
                    <h2>افزودن مقاله</h2>
                    <form className="admin-form" onSubmit={submitArticle}>
                        {Object.keys(articleForm.errors).length > 0 && <div className="form-error-box">لطفاً خطاهای مقاله را بررسی کنید.</div>}
                        <input value={articleForm.data.title} onChange={(event) => articleForm.setData('title', event.target.value)} placeholder="عنوان مقاله" />
                        {articleForm.errors.title && <small className="form-error">{articleForm.errors.title}</small>}
                        <input list="article-category-list" value={articleForm.data.category_name} onChange={(event) => articleForm.setData('category_name', event.target.value)} placeholder="دسته‌بندی مقاله" />
                        <datalist id="article-category-list">{articleCategories.map((category) => <option value={category.name} key={category.id} />)}</datalist>
                        <input value={articleForm.data.tags} onChange={(event) => articleForm.setData('tags', event.target.value)} placeholder="تگ‌ها با ویرگول، مثل ایرپاد، شارژر" />
                        <textarea value={articleForm.data.excerpt} onChange={(event) => articleForm.setData('excerpt', event.target.value)} placeholder="خلاصه مقاله" />
                        <textarea value={articleForm.data.body} onChange={(event) => articleForm.setData('body', event.target.value)} placeholder="متن کامل مقاله" />
                        {articleForm.errors.body && <small className="form-error">{articleForm.errors.body}</small>}
                        <input value={articleForm.data.meta_title} onChange={(event) => articleForm.setData('meta_title', event.target.value)} placeholder="عنوان سئو مقاله" />
                        <textarea value={articleForm.data.meta_description} onChange={(event) => articleForm.setData('meta_description', event.target.value)} placeholder="توضیحات متا مقاله" />
                        <label className="upload-box">
                            <span>عکس اصلی مقاله</span>
                            <small>این عکس در لیست مقاله‌ها و Open Graph استفاده می‌شود.</small>
                            <span className="file-picker-button">انتخاب تصویر</span>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(event) => {
                                    if (articlePreview) {
                                        URL.revokeObjectURL(articlePreview);
                                    }
                                    const file = event.target.files?.[0] || null;
                                    articleForm.setData('main_image', file);
                                    setArticlePreview(file ? URL.createObjectURL(file) : null);
                                }}
                            />
                        </label>
                        {articlePreview && <img className="article-preview" src={articlePreview} alt="پیش‌نمایش عکس مقاله" />}
                        <button className="primary" disabled={articleForm.processing}>
                            {articleForm.processing ? 'در حال ثبت...' : 'ثبت مقاله'}
                        </button>
                    </form>
                </section>
            </div>
            <div className="admin-grid admin-lists">
                <section className={tab === 'products' ? '' : 'admin-hidden'}>
                    <h2>مدیریت همه محصولات</h2>
                    <AdminProducts products={products} />
                </section>
                <section className={tab === 'articles' ? '' : 'admin-hidden'}>
                    <h2>مدیریت همه مقالات</h2>
                    <AdminArticles articles={articles} />
                    <TaxonomyManagement tags={tags} articleCategories={articleCategories} />
                </section>
            </div>
            {tab === 'accounting' && <AccountingPanel accounting={accounting} />}
            {tab === 'shipping' && <ShippingPanel methods={shippingMethods} />}
        </main>
    );
}

function AdminProducts({ products }: { products: Product[] }) {
    return (
        <div className="admin-manage-list">
            {products.length ? products.map((product) => <ProductManager key={product.id} product={product} />) : <p>هنوز محصولی ثبت نشده است.</p>}
        </div>
    );
}

function ProductManager({ product }: { product: Product }) {
    const thumbnail = imageUrl(product.main_image || product.images?.[0]?.path || product.gallery?.[0]);

    return (
        <div className="manage-row product-manage-link">
            <Link className="product-manage-content" href={route('admin.products.show', product.id)}>
                <div className="manage-heading">
                    {thumbnail ? <img src={thumbnail} alt={product.name} /> : <div className="manage-placeholder">بدون عکس</div>}
                    <span><b>{product.name}</b><small>{product.sku || 'بدون SKU'}</small></span>
                </div>
                <div className="product-manage-meta">
                    <span><small>قیمت اصلی</small><b>{toman(Number(product.price))}</b></span>
                    <span><small>موجودی</small><b>{new Intl.NumberFormat('fa-IR').format(product.stock)} عدد</b></span>
                    <span className={product.is_active === false ? 'inactive' : 'active'}>{product.is_active === false ? 'غیرفعال' : 'فعال'}</span>
                </div>
                <strong className="manage-open">مشاهده و ویرایش ←</strong>
            </Link>
            <button type="button" className="danger-button product-list-delete" onClick={() => {
                if (window.confirm(`محصول «${product.name}» برای همیشه حذف شود؟`)) {
                    router.delete(route('admin.products.destroy', product.id), { preserveScroll: true });
                }
            }}>حذف محصول</button>
        </div>
    );
}

function AdminProductEditor({ product, categories, brands }: { product: Product; categories: Category[]; brands: Brand[] }) {
    const currentImages = product.images || [];
    const initialMainImage = currentImages.find((image) => image.path === product.main_image) || currentImages[0];
    const legacyImages = Array.from(new Set([
        product.main_image,
        ...(product.gallery || []),
    ].filter((path): path is string => Boolean(path))))
        .filter((path) => !currentImages.some((image) => imageUrl(image.path) === imageUrl(path)))
        .map((path, index) => ({ path, url: imageUrl(path) || path, index }));
    const initialLegacyImage = legacyImages.find((image) => image.path === product.main_image) || legacyImages[0];
    const [previews, setPreviews] = useState<string[]>([]);
    const [imageUploadProgress, setImageUploadProgress] = useState(0);
    const [imageUploadError, setImageUploadError] = useState('');
    const [preparingImages, setPreparingImages] = useState(false);
    const [savingProduct, setSavingProduct] = useState(false);
    const form = useForm<ProductEditForm>({
        _method: 'patch',
        name: product.name || '',
        sku: product.sku || '',
        category_id: product.category?.id ? String(product.category.id) : '',
        brand_id: product.brand?.id ? String(product.brand.id) : '',
        price: String(product.price ?? ''),
        sale_price: product.sale_price ? String(product.sale_price) : '',
        stock: String(product.stock ?? 0),
        short_description: product.short_description || '',
        description: product.description || '',
        meta_title: product.meta_title || '',
        meta_description: product.meta_description || '',
        meta_keywords: product.meta_keywords || '',
        weight: product.weight ? String(product.weight) : '',
        dimensions: product.dimensions || '',
        is_active: product.is_active !== false,
        images: [],
        remove_image_ids: [],
        remove_legacy_paths: [],
        main_image_choice: initialMainImage?.id ? `existing:${initialMainImage.id}` : initialLegacyImage ? `legacy:${initialLegacyImage.index}` : '',
        tags: product.tags?.map((tag) => tag.name).join('، ') || '',
    });

    useEffect(() => () => previews.forEach((preview) => URL.revokeObjectURL(preview)), [previews]);

    const removed = new Set(form.data.remove_image_ids);
    const visibleCurrentImages = currentImages.filter((image) => image.id && !removed.has(image.id));
    const removedLegacy = new Set(form.data.remove_legacy_paths);

    const toggleRemoveImage = (image: UploadedImage) => {
        if (!image.id) return;
        const next = removed.has(image.id)
            ? form.data.remove_image_ids.filter((id) => id !== image.id)
            : [...form.data.remove_image_ids, image.id];
        form.setData('remove_image_ids', next);
        if (!removed.has(image.id) && form.data.main_image_choice === `existing:${image.id}`) {
            const fallback = currentImages.find((item) => item.id && item.id !== image.id && !next.includes(item.id));
            form.setData('main_image_choice', fallback?.id ? `existing:${fallback.id}` : previews.length ? 'new:0' : '');
        }
    };

    const toggleRemoveLegacyImage = (path: string, index: number) => {
        const next = removedLegacy.has(path)
            ? form.data.remove_legacy_paths.filter((item) => item !== path)
            : [...form.data.remove_legacy_paths, path];
        form.setData('remove_legacy_paths', next);
        if (!removedLegacy.has(path) && form.data.main_image_choice === `legacy:${index}`) {
            const fallback = legacyImages.find((image) => image.path !== path && !next.includes(image.path));
            form.setData('main_image_choice', fallback ? `legacy:${fallback.index}` : visibleCurrentImages[0]?.id ? `existing:${visibleCurrentImages[0].id}` : previews.length ? 'new:0' : '');
        }
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const payload = new FormData();

        Object.entries(form.data).forEach(([key, value]) => {
            if (key === 'images') {
                (value as File[]).forEach((file) => payload.append('images[]', file, file.name));
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => payload.append(`${key}[]`, String(item)));
                return;
            }

            payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value ?? ''));
        });

        setImageUploadError('');
        setImageUploadProgress(0);
        setSavingProduct(true);
        form.clearErrors();

        try {
            await axios.post(route('admin.products.update', product.id), payload, {
                headers: { Accept: 'application/json' },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        setImageUploadProgress(Math.round((progressEvent.loaded / progressEvent.total) * 100));
                    }
                },
            });
            previews.forEach((preview) => URL.revokeObjectURL(preview));
            setPreviews([]);
            form.setData('images', []);
            form.setData('remove_image_ids', []);
            form.setData('remove_legacy_paths', []);
            router.reload();
        } catch (error: any) {
            const validationErrors = error?.response?.data?.errors;
            if (validationErrors) {
                Object.entries(validationErrors).forEach(([key, messages]) => {
                    form.setError(
                        key as keyof ProductEditForm,
                        Array.isArray(messages) ? String(messages[0]) : String(messages),
                    );
                });
            }
            setImageUploadError(uploadErrorMessage(error));
        } finally {
            setSavingProduct(false);
        }
    };

    return (
        <main className="page admin admin-product-editor">
            <div className="admin-page-heading">
                <div>
                    <small>مدیریت محصولات</small>
                    <h1>ویرایش {product.name}</h1>
                    <p>اطلاعات، قیمت، موجودی و تصاویر محصول را از این صفحه مدیریت کنید.</p>
                </div>
                <Link className="secondary-link" href={route('admin')}>بازگشت به پنل مدیریت</Link>
            </div>
            <form onSubmit={submit}>
                {Object.keys(form.errors).length > 0 && <div className="form-error-box">
                    <b>ذخیره محصول انجام نشد:</b>
                    {Object.entries(form.errors).map(([field, message]) => <span key={field}>{String(message)}</span>)}
                </div>}
                <section className="product-editor-card">
                    <h2>اطلاعات اصلی</h2>
                    <div className="product-editor-fields">
                        <label><span>نام محصول *</span><input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                        <label><span>شناسه محصول / SKU *</span><input required dir="ltr" value={form.data.sku} onChange={(event) => form.setData('sku', event.target.value)} /></label>
                        <label><span>دسته‌بندی *</span><select required value={form.data.category_id} onChange={(event) => form.setData('category_id', event.target.value)}><option value="">انتخاب دسته‌بندی</option>{categories.map((category) => <option value={category.id} key={category.id}>{category.name}</option>)}</select></label>
                        <label><span>برند</span><select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)}><option value="">بدون برند</option>{brands.map((brand) => <option value={brand.id} key={brand.id}>{brand.name}</option>)}</select></label>
                        <label><span>قیمت اصلی *</span><input required inputMode="numeric" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} /></label>
                        <label><span>قیمت تخفیفی</span><input inputMode="numeric" value={form.data.sale_price} onChange={(event) => form.setData('sale_price', event.target.value)} /></label>
                        <label><span>موجودی *</span><input required inputMode="numeric" value={form.data.stock} onChange={(event) => form.setData('stock', event.target.value)} /></label>
                        <label><span>وزن</span><input inputMode="decimal" value={form.data.weight} onChange={(event) => form.setData('weight', event.target.value)} placeholder="مثلاً ۲۵۰" /></label>
                        <label><span>ابعاد</span><input value={form.data.dimensions} onChange={(event) => form.setData('dimensions', event.target.value)} placeholder="مثلاً ۱۰ × ۵ × ۳ سانتی‌متر" /></label>
                        <label className="admin-check editor-active"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> نمایش محصول در فروشگاه</label>
                        <label className="wide"><span>توضیح کوتاه</span><textarea value={form.data.short_description} onChange={(event) => form.setData('short_description', event.target.value)} /></label>
                        <label className="wide"><span>توضیحات کامل</span><textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></label>
                        <label className="wide"><span>تگ‌های محصول</span><input value={form.data.tags} onChange={(event) => form.setData('tags', event.target.value)} placeholder="با ویرگول جدا کنید" /></label>
                    </div>
                </section>

                <section className="product-editor-card">
                    <h2>تصاویر محصول</h2>
                    <p className="panel-help">عکس اصلی را انتخاب کنید. می‌توانید تصاویر قبلی را حذف یا حداکثر ۸ تصویر جدید اضافه کنید.</p>
                    {(currentImages.length > 0 || legacyImages.length > 0) && <div className="editor-image-grid">
                        {currentImages.map((image) => (
                            <div className={removed.has(image.id || 0) ? 'removed' : ''} key={image.id || image.path}>
                                <img src={imageUrl(image.path)} alt={product.name} />
                                <label><input type="radio" name="editor-main-image" disabled={removed.has(image.id || 0)} checked={form.data.main_image_choice === `existing:${image.id}`} onChange={() => form.setData('main_image_choice', `existing:${image.id}`)} /> عکس اصلی</label>
                                <button type="button" onClick={() => toggleRemoveImage(image)}>{removed.has(image.id || 0) ? 'بازگردانی' : 'حذف عکس'}</button>
                            </div>
                        ))}
                        {legacyImages.map((image) => <div className={removedLegacy.has(image.path) ? 'removed' : ''} key={image.path}>
                            <img src={image.url} alt={product.name} />
                            <label><input type="radio" name="editor-main-image" disabled={removedLegacy.has(image.path)} checked={form.data.main_image_choice === `legacy:${image.index}`} onChange={() => form.setData('main_image_choice', `legacy:${image.index}`)} /> عکس اصلی</label>
                            <button type="button" onClick={() => toggleRemoveLegacyImage(image.path, image.index)}>{removedLegacy.has(image.path) ? 'بازگردانی' : 'حذف عکس'}</button>
                        </div>)}
                    </div>}
                    <label className="upload-box">
                        <span>افزودن تصاویر جدید</span>
                        <small>فرمت JPG، PNG یا WebP و حداکثر حجم هر فایل ۱۰ مگابایت</small>
                        <span className="file-picker-button">انتخاب فایل‌ها</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" multiple onChange={async (event) => {
                            previews.forEach((preview) => URL.revokeObjectURL(preview));
                            setPreparingImages(true);
                            setImageUploadError('');
                            try {
                                const files = await prepareProductImages(Array.from(event.target.files || []));
                                form.clearErrors('images');
                                if (files.some((file) => file.size > 10 * 1024 * 1024)) {
                                    form.setError('images', 'حجم هر تصویر باید کمتر از ۱۰ مگابایت باشد.');
                                    event.target.value = '';
                                    form.setData('images', []);
                                    setPreviews([]);
                                    return;
                                }
                                const nextPreviews = files.map((file) => URL.createObjectURL(file));
                                form.setData('images', files);
                                setPreviews(nextPreviews);
                                if (!form.data.main_image_choice && files.length) form.setData('main_image_choice', 'new:0');
                            } catch (error) {
                                setImageUploadError(uploadErrorMessage(error));
                            } finally {
                                setPreparingImages(false);
                            }
                        }} />
                    </label>
                    {form.errors.images && <small className="form-error upload-error">{form.errors.images}</small>}
                    {preparingImages && <small className="upload-preparing">در حال کم‌حجم‌سازی تصاویر...</small>}
                    {imageUploadError && <small className="form-error upload-error">{imageUploadError}</small>}
                    {form.data.images.length > 0 && <div className="selected-upload-summary">
                        <span>{new Intl.NumberFormat('fa-IR').format(form.data.images.length)} تصویر آماده آپلود است.</span>
                        <b>{form.data.images.map((file) => file.name).join('، ')}</b>
                    </div>}
                    {imageUploadProgress > 0 && <div className="upload-progress"><span style={{ width: `${imageUploadProgress}%` }} /><b>{new Intl.NumberFormat('fa-IR').format(imageUploadProgress)}٪ آپلود شد</b></div>}
                    {previews.length > 0 && <div className="editor-image-grid new-images">{previews.map((preview, index) => (
                        <div key={preview}>
                            <img src={preview} alt={`تصویر جدید ${index + 1}`} />
                            <label><input type="radio" name="editor-main-image" checked={form.data.main_image_choice === `new:${index}`} onChange={() => form.setData('main_image_choice', `new:${index}`)} /> عکس اصلی</label>
                        </div>
                    ))}</div>}
                </section>

                <section className="product-editor-card">
                    <h2>تنظیمات سئو</h2>
                    <div className="product-editor-fields">
                        <label><span>عنوان سئو</span><input value={form.data.meta_title} onChange={(event) => form.setData('meta_title', event.target.value)} /></label>
                        <label><span>کلمات کلیدی</span><input value={form.data.meta_keywords} onChange={(event) => form.setData('meta_keywords', event.target.value)} /></label>
                        <label className="wide"><span>توضیحات متا</span><textarea value={form.data.meta_description} onChange={(event) => form.setData('meta_description', event.target.value)} /></label>
                    </div>
                </section>

                <div className="product-editor-actions">
                    <button className="primary" disabled={savingProduct || preparingImages}>{savingProduct ? 'در حال ذخیره و آپلود...' : 'ذخیره تمام تغییرات'}</button>
                    <button type="button" className="danger-button" onClick={() => { if (window.confirm(`محصول «${product.name}» حذف شود؟`)) router.delete(route('admin.products.destroy', product.id)); }}>حذف محصول</button>
                </div>
            </form>
        </main>
    );
}

function AdminArticles({ articles }: { articles: Article[] }) {
    return (
        <div className="admin-manage-list">
            {articles.length ? articles.map((article) => <ArticleManager key={article.id} article={article} />) : <p>هنوز مقاله‌ای ثبت نشده است.</p>}
        </div>
    );
}

function ArticleManager({ article }: { article: Article }) {
    const form = useForm({
        title: article.title,
        category_name: article.category?.name || '',
        tags: article.tags?.map((tag) => tag.name).join('، ') || '',
        excerpt: article.excerpt || '',
        body: article.body || '',
        meta_title: article.meta_title || '',
        meta_description: article.meta_description || '',
        is_published: article.is_published !== false,
    });

    return (
        <form className="manage-row" onSubmit={(event) => { event.preventDefault(); form.patch(route('admin.articles.update', article.id), { preserveScroll: true }); }}>
            <div className="manage-heading">
                {article.image ? <img src={article.image} alt={article.title} /> : <div className="manage-placeholder" />}
                <span><b>{article.title}</b><small>{article.tags?.map((tag) => `#${tag.name}`).join(' ') || 'بدون تگ'}</small></span>
            </div>
            <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="عنوان" />
            <input value={form.data.category_name} onChange={(event) => form.setData('category_name', event.target.value)} placeholder="دسته‌بندی مقاله" />
            <input value={form.data.tags} onChange={(event) => form.setData('tags', event.target.value)} placeholder="تگ‌ها با ویرگول" />
            <textarea value={form.data.excerpt} onChange={(event) => form.setData('excerpt', event.target.value)} placeholder="خلاصه" />
            <textarea value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} placeholder="متن مقاله" />
            <input value={form.data.meta_title} onChange={(event) => form.setData('meta_title', event.target.value)} placeholder="عنوان سئو" />
            <textarea value={form.data.meta_description} onChange={(event) => form.setData('meta_description', event.target.value)} placeholder="توضیحات متا" />
            <label className="admin-check"><input type="checkbox" checked={form.data.is_published} onChange={(event) => form.setData('is_published', event.target.checked)} /> مقاله منتشر باشد</label>
            {Object.keys(form.errors).length > 0 && <small className="form-error">عنوان و متن مقاله را بررسی کنید.</small>}
            <div className="manage-actions">
                <button className="primary" disabled={form.processing}>ذخیره تغییرات</button>
                <button type="button" className="danger-button" onClick={() => { if (window.confirm(`مقاله «${article.title}» حذف شود؟`)) router.delete(route('admin.articles.destroy', article.id), { preserveScroll: true }); }}>حذف مقاله</button>
            </div>
        </form>
    );
}

function TaxonomyManagement({ tags, articleCategories }: { tags: Tag[]; articleCategories: ArticleCategory[] }) {
    return (
        <section className="taxonomy-management">
            <h2>مدیریت تگ‌ها</h2>
            <div className="taxonomy-list">
                {tags.length ? tags.map((tag) => <TaxonomyRow key={tag.id} item={tag} type="tag" />) : <p>هنوز تگی ثبت نشده است.</p>}
            </div>
            <h2>مدیریت دسته‌بندی مقالات</h2>
            <div className="taxonomy-list">
                {articleCategories.length ? articleCategories.map((category) => <TaxonomyRow key={category.id} item={category} type="category" />) : <p>هنوز دسته‌بندی مقاله‌ای ثبت نشده است.</p>}
            </div>
        </section>
    );
}

function TaxonomyRow({ item, type }: { item: Tag | ArticleCategory; type: 'tag' | 'category' }) {
    const form = useForm({ name: item.name, description: 'description' in item ? item.description || '' : '' });
    const updateRoute = type === 'tag' ? route('admin.tags.update', item.id) : route('admin.article-categories.update', item.id);
    const destroyRoute = type === 'tag' ? route('admin.tags.destroy', item.id) : route('admin.article-categories.destroy', item.id);
    const count = type === 'tag'
        ? Number((item as Tag).products_count || 0) + Number((item as Tag).articles_count || 0)
        : Number((item as ArticleCategory).articles_count || 0);

    return (
        <form className="taxonomy-row" onSubmit={(event) => { event.preventDefault(); form.patch(updateRoute, { preserveScroll: true }); }}>
            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} aria-label="نام" />
            {type === 'category' && <input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} placeholder="توضیحات دسته‌بندی" />}
            <small>{new Intl.NumberFormat('fa-IR').format(count)} مورد مرتبط</small>
            <button className="primary" disabled={form.processing}>ویرایش</button>
            <button type="button" className="danger-button" onClick={() => { if (window.confirm(`«${item.name}» حذف شود؟`)) router.delete(destroyRoute, { preserveScroll: true }); }}>حذف</button>
            {form.errors.name && <span className="form-error">{form.errors.name}</span>}
        </form>
    );
}

function AccountingPanel({ accounting }: { accounting: Accounting }) {
    return (
        <section className="admin-panel">
            <div className="accounting-cards">
                <div><small>وجه واردشده به حساب</small><b>{toman(Number(accounting.received || 0))}</b></div>
                <div><small>درآمد فروش محصولات</small><b>{toman(Number(accounting.product_revenue || 0))}</b></div>
                <div><small>هزینه ارسال دریافتی</small><b>{toman(Number(accounting.shipping_revenue || 0))}</b></div>
                <div><small>تعداد کالای فروخته‌شده</small><b>{new Intl.NumberFormat('fa-IR').format(accounting.sold_items || 0)}</b></div>
                <div><small>سفارش پرداخت‌شده</small><b>{new Intl.NumberFormat('fa-IR').format(accounting.paid_orders || 0)}</b></div>
                <div><small>در انتظار پرداخت/بررسی</small><b>{new Intl.NumberFormat('fa-IR').format(accounting.pending_orders || 0)}</b></div>
            </div>
            <div className="accounting-orders-link">
                <span>برای مشاهده، بررسی و تغییر وضعیت سفارش‌ها وارد بخش مدیریت سفارشات شوید.</span>
                <Link className="primary" href={route('admin.orders.index')}>مدیریت سفارشات</Link>
            </div>
        </section>
    );
}

const orderStatusLabels: Record<string, string> = {
    pending_payment: 'در انتظار پرداخت',
    unpaid: 'پرداخت‌نشده',
    pending_review: 'در انتظار بررسی',
    processing: 'در حال پردازش',
    completed: 'تکمیل و ارسال شده',
    cancelled: 'لغو شده',
    failed: 'پرداخت ناموفق',
    refunded: 'مرجوع شده',
};

const shippingLabels: Record<string, string> = {
    mashhad_courier: 'پیک مشهد',
    pickup: 'تحویل حضوری',
    post: 'ارسال پستی',
};

const paymentLabels: Record<string, string> = {
    zarinpal: 'پرداخت آنلاین زرین‌پال',
    card_to_card: 'کارت به کارت',
};

function AdminOrders({ orders }: { orders: PaginatedOrders | Order[] }) {
    const pagination = Array.isArray(orders) ? null : orders;
    const items = Array.isArray(orders) ? orders : orders?.data || [];

    return (
        <main className="page admin admin-orders-page">
            <div className="admin-page-heading">
                <div>
                    <small>پنل مدیریت</small>
                    <h1>مدیریت سفارشات</h1>
                    <p>فهرست خلاصه همه سفارش‌ها؛ برای مشاهده اطلاعات کامل روی هر سفارش کلیک کنید.</p>
                </div>
                <Link className="secondary-link" href={route('admin')}>بازگشت به پنل مدیریت</Link>
            </div>
            <div className="order-summary-list">
                {items.length ? items.map((order) => (
                    <Link className="order-summary-row" href={route('admin.orders.show', order.id)} key={order.id}>
                        <span>
                            <small>شماره سفارش</small>
                            <b dir="ltr">{order.number}</b>
                        </span>
                        <span>
                            <small>مشتری</small>
                            <b>{order.address?.customer_name || `${order.address?.first_name || ''} ${order.address?.last_name || ''}`.trim() || 'مهمان'}</b>
                            <em dir="ltr">{order.address?.phone}</em>
                        </span>
                        <span>
                            <small>تاریخ ثبت</small>
                            <b>{new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.created_at))}</b>
                        </span>
                        <span>
                            <small>تعداد کالا</small>
                            <b>{new Intl.NumberFormat('fa-IR').format(Number(order.items_sum_quantity || order.items_count || 0))}</b>
                        </span>
                        <span>
                            <small>مبلغ کل</small>
                            <b className="order-total">{toman(Number(order.total))}</b>
                        </span>
                        <strong className={`order-status status-${order.status}`}>{orderStatusLabels[order.status] || order.status}</strong>
                        <i aria-hidden="true">←</i>
                    </Link>
                )) : <div className="empty-orders">هنوز سفارشی ثبت نشده است.</div>}
            </div>
            {pagination && pagination.last_page > 1 && (
                <nav className="order-pagination" aria-label="صفحه‌بندی سفارشات">
                    {pagination.prev_page_url ? <Link href={pagination.prev_page_url}>صفحه قبل</Link> : <span>صفحه قبل</span>}
                    <b>صفحه {new Intl.NumberFormat('fa-IR').format(pagination.current_page)} از {new Intl.NumberFormat('fa-IR').format(pagination.last_page)}</b>
                    {pagination.next_page_url ? <Link href={pagination.next_page_url}>صفحه بعد</Link> : <span>صفحه بعد</span>}
                </nav>
            )}
        </main>
    );
}

function AdminOrderDetail({ order }: { order: Order }) {
    if (!order) return <main className="page"><p>سفارش پیدا نشد.</p></main>;
    const customerName = order.address?.customer_name || `${order.address?.first_name || ''} ${order.address?.last_name || ''}`.trim() || 'مهمان';

    return (
        <main className="page admin admin-order-detail">
            <div className="admin-page-heading">
                <div>
                    <small>جزئیات کامل سفارش</small>
                    <h1 dir="ltr">{order.number}</h1>
                    <p>ثبت‌شده در {new Intl.DateTimeFormat('fa-IR', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(order.created_at))}</p>
                </div>
                <Link className="secondary-link" href={route('admin.orders.index')}>بازگشت به سفارشات</Link>
            </div>

            <section className="order-detail-status">
                <label>
                    <span>وضعیت سفارش</span>
                    <select value={order.status} onChange={(event) => router.patch(route('admin.orders.status', order.id), { status: event.target.value }, { preserveScroll: true })}>
                        {Object.entries(orderStatusLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}
                    </select>
                </label>
                <strong className={`order-status status-${order.status}`}>{orderStatusLabels[order.status] || order.status}</strong>
            </section>

            <div className="order-detail-grid">
                <section className="order-detail-card">
                    <h2>اطلاعات مشتری و تحویل</h2>
                    <dl>
                        <div><dt>نام مشتری</dt><dd>{customerName}</dd></div>
                        <div><dt>شماره موبایل</dt><dd dir="ltr">{order.address?.phone || order.user?.phone_number || '—'}</dd></div>
                        <div><dt>استان و شهر</dt><dd>{[order.address?.province, order.address?.city].filter(Boolean).join('، ') || '—'}</dd></div>
                        <div><dt>کد پستی</dt><dd dir="ltr">{order.address?.postal_code || '—'}</dd></div>
                        <div className="wide"><dt>آدرس کامل</dt><dd>{order.address?.full || '—'}</dd></div>
                    </dl>
                </section>
                <section className="order-detail-card">
                    <h2>پرداخت و ارسال</h2>
                    <dl>
                        <div><dt>روش پرداخت</dt><dd>{paymentLabels[order.payment_method || ''] || order.payment_method || '—'}</dd></div>
                        <div><dt>روش ارسال</dt><dd>{shippingLabels[order.shipping_method] || order.shipping_method}</dd></div>
                        <div><dt>شناسه پرداخت</dt><dd dir="ltr">{order.payment_reference || '—'}</dd></div>
                        <div><dt>زمان پرداخت</dt><dd>{order.paid_at ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.paid_at)) : 'پرداخت نشده'}</dd></div>
                        {order.payment_method === 'card_to_card' && <div><dt>مبلغ اعلامی</dt><dd>{toman(Number(order.card_to_card_amount || 0))}</dd></div>}
                        {order.payment_receipt && <div><dt>فیش واریز</dt><dd><a href={route('admin.orders.receipt', order.id)} target="_blank" rel="noreferrer">مشاهده فیش پرداخت</a></dd></div>}
                    </dl>
                </section>
            </div>

            <section className="order-detail-card order-detail-items">
                <h2>اقلام سفارش</h2>
                <div className="order-items-head"><span>محصول</span><span>تعداد</span><span>قیمت واحد</span><span>قیمت کل</span></div>
                {order.items?.map((item) => (
                    <div className="order-item-row" key={item.id || item.sku}>
                        <span><b>{item.name}</b><small dir="ltr">{item.sku}</small></span>
                        <span>{new Intl.NumberFormat('fa-IR').format(item.quantity)}</span>
                        <span>{toman(Number(item.price || 0))}</span>
                        <strong>{toman(Number(item.price || 0) * item.quantity)}</strong>
                    </div>
                ))}
            </section>

            <section className="order-detail-card order-detail-totals">
                <p><span>جمع کالاها</span><b>{toman(Number(order.subtotal || 0))}</b></p>
                <p><span>تخفیف</span><b>{toman(Number(order.discount || 0))}</b></p>
                <p><span>هزینه ارسال</span><b>{toman(Number(order.shipping_cost || 0))}</b></p>
                {Number(order.tax || 0) > 0 && <p><span>مالیات</span><b>{toman(Number(order.tax))}</b></p>}
                <p className="grand"><span>مبلغ نهایی</span><b>{toman(Number(order.total))}</b></p>
                <div className="admin-order-actions">
                    {order.invoice_token && <><Link href={`/orders/${order.id}/invoice/${order.invoice_token}`}>مشاهده فاکتور</Link><a href={`/orders/${order.id}/invoice/${order.invoice_token}/pdf`} target="_blank" rel="noreferrer">دریافت PDF فاکتور</a></>}
                </div>
            </section>
        </main>
    );
}

function ShippingPanel({ methods }: { methods: ShippingMethod[] }) {
    const form = useForm<{ methods: ShippingMethod[] }>({ methods: methods.map((method) => ({ ...method, cost: Number(method.cost) })) });
    const updateMethod = (index: number, values: Partial<ShippingMethod>) => form.setData('methods', form.data.methods.map((method, itemIndex) => itemIndex === index ? { ...method, ...values } : method));
    return (
        <section className="admin-panel">
            <h2>روش و هزینه ارسال</h2>
            <p className="panel-help">روش‌های فعال همراه با توضیح و هزینه در صفحه تسویه‌حساب به کاربر نشان داده می‌شوند.</p>
            <form className="shipping-admin" onSubmit={(event) => { event.preventDefault(); form.put(route('admin.shipping.update'), { preserveScroll: true }); }}>
                {form.data.methods.map((method, index) => (
                    <div className="shipping-admin-row" key={method.code}>
                        <label className="admin-check"><input type="checkbox" checked={method.is_active} onChange={(event) => updateMethod(index, { is_active: event.target.checked })} /> فعال</label>
                        <input value={method.name} onChange={(event) => updateMethod(index, { name: event.target.value })} placeholder="نام روش ارسال" />
                        <input value={method.description} onChange={(event) => updateMethod(index, { description: event.target.value })} placeholder="توضیح برای مشتری" />
                        <label><span>هزینه (تومان)</span><input value={method.cost} onChange={(event) => updateMethod(index, { cost: Number(event.target.value) })} inputMode="numeric" /></label>
                    </div>
                ))}
                {Object.keys(form.errors).length > 0 && <small className="form-error">اطلاعات روش‌های ارسال را بررسی کنید.</small>}
                <button className="primary" disabled={form.processing}>ذخیره تنظیمات ارسال</button>
            </form>
        </section>
    );
}

function CartDrawer({ cart, setCart, close, shippingMethods }: any) {
    const grouped = Object.values((cart as CardProduct[]).reduce<Record<number, { product: CardProduct; quantity: number }>>((items, product) => {
        items[product.id] = items[product.id] || { product, quantity: 0 };
        items[product.id].quantity++;
        return items;
    }, {}));
    const shipping = (shippingMethods as ShippingMethod[]).find((method) => method.is_active);
    const subtotal = cart.reduce((sum: number, product: CardProduct) => sum + (product.sale || product.price), 0);
    const removeOne = (productId: number) => setCart((items: CardProduct[]) => {
        const index = items.findIndex((item) => item.id === productId);
        return index < 0 ? items : items.filter((_, itemIndex) => itemIndex !== index);
    });
    return (
        <aside className="drawer">
            <button className="close" aria-label="بستن" onClick={close}>×</button>
            <h2>سبد خرید شما</h2>
            {cart.length ? (
                <>
                    {grouped.map(({ product, quantity }) => (
                        <div className="cart-item" key={product.id}>
                            {product.image ? <img src={product.image} alt={product.name} /> : <span className="mini-placeholder" />}
                            <span><b>{product.name}</b><small>قیمت واحد: {toman(product.sale || product.price)}</small><strong>جمع: {toman((product.sale || product.price) * quantity)}</strong></span>
                            <div className="cart-quantity">
                                <button aria-label={`کم کردن ${product.name}`} onClick={() => removeOne(product.id)}>−</button>
                                <b>{quantity}</b>
                                <button aria-label={`اضافه کردن ${product.name}`} disabled={quantity >= product.stock} onClick={() => setCart((items: CardProduct[]) => [...items, product])}>+</button>
                            </div>
                        </div>
                    ))}
                    <div className="cart-cost"><span>جمع محصولات <b>{toman(subtotal)}</b></span><span>هزینه ارسال <b>{shipping ? (Number(shipping.cost) ? toman(Number(shipping.cost)) : 'رایگان') : 'در تسویه‌حساب'}</b></span></div>
                    <div className="total">جمع کل <b>{toman(subtotal + Number(shipping?.cost || 0))}</b></div>
                    <Link className="primary full" href="/checkout">ادامه و پرداخت</Link>
                </>
            ) : (
                <p className="empty">سبد خرید شما خالی است.</p>
            )}
        </aside>
    );
}

function AccountDrawer({ auth, close }: any) {
    return (
        <aside className="drawer">
            <button className="close" aria-label="بستن" onClick={close}>×</button>
            <h2>حساب کاربری</h2>
            {auth?.user ? (
                <>
                    <p>{auth.user.first_name || 'کاربر'} عزیز، خوش آمدید.</p>
                    <Link className="primary full" href={auth.user.is_admin ? '/admin' : '/account'}>
                        ورود به حساب من
                    </Link>
                </>
            ) : (
                <>
                    <p>برای مشاهده سفارش‌ها، آدرس‌ها و علاقه‌مندی‌ها وارد حساب خود شوید.</p>
                    <Link className="primary full" href="/login">ورود</Link>
                    <Link className="secondary full" href="/register">ثبت‌نام</Link>
                </>
            )}
        </aside>
    );
}
