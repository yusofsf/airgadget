import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type Brand = { id: number; name: string };
type Category = { id: number; name: string; slug?: string; products_count?: number };
type UploadedImage = { id?: number; path: string; sort_order?: number };
type Tag = { id: number; name: string; slug: string };
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
    brand?: Brand | null;
    category?: Category | null;
    main_image?: string | null;
    images?: UploadedImage[];
    attributes?: { color?: string };
    stock: number;
    is_active?: boolean;
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
};
type ShippingMethod = { code: string; name: string; description: string; cost: number; is_active: boolean };
type Order = { id: number; number: string; invoice_token?: string; status: string; subtotal?: number; discount?: number; shipping_cost?: number; total: number; shipping_method: string; payment_method?: string; payment_receipt?: string; card_to_card_amount?: number; payment_reference?: string; paid_at?: string; created_at: string; address?: { first_name?: string; last_name?: string; customer_name?: string; phone?: string; postal_code?: string; full?: string }; items?: { id?: number; name?: string; sku?: string; price?: number; quantity: number }[]; user?: { first_name?: string; phone_number?: string } };
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
    main_image_index: number;
    images: File[];
};
type ArticleForm = {
    title: string;
    topic: string;
    excerpt: string;
    body: string;
    tags: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    main_image: File | null;
};

const supportPhone = '09205850190';
const storeAddress = 'خراسان رضوی، مشهد، عبدالمطلب ۳۵';
const siteTitle = 'ایرگجت | لوازم جانبی موبایل';
const siteDescription = 'خرید مطمئن لوازم جانبی موبایل، ایرپاد و گجت با ارسال سریع از ایرگجت مشهد';
const toman = (n: number) => new Intl.NumberFormat('fa-IR').format(n) + ' تومان';
const toCard = (p: Product): CardProduct => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    price: Number(p.price || 0),
    sale: p.sale_price ? Number(p.sale_price) : undefined,
    brand: p.brand?.name || 'بدون برند',
    color: p.attributes?.color || 'مشکی',
    stock: Number(p.stock || 0),
    image: p.main_image || p.images?.[0]?.path,
});

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
    shippingMethods = [],
    accounting = {},
    orders = [],
    invoice,
    invoiceShipping,
    trackedOrder,
    trackingError,
    paymentResult,
    cardToCard,
}: any) {
    const [search, setSearch] = useState('');
    const [cart, setCart] = useState<CardProduct[]>(() => {
        if (typeof window === 'undefined') return [];
        try { return JSON.parse(window.localStorage.getItem('airgadget-cart') || '[]'); } catch { return []; }
    });
    const [fav, setFav] = useState<number[]>([]);
    const [panel, setPanel] = useState<'cart' | 'account' | null>(null);
    const [filter, setFilter] = useState('');
    const [menuOpen, setMenuOpen] = useState(false);
    const productItems: Product[] = Array.isArray(products) ? products : products?.data || [];
    const list = productItems.map(toCard);
    const heroImage = '/images/airpods-pro.jpg';
    const pageSeo = resolveSeo(view, product, article);
    const displayed = useMemo(
        () =>
            list
                .filter((p) => p.name.includes(search) || p.brand.toLowerCase().includes(search.toLowerCase()))
                .filter((p) => !filter || (filter === 'sale' ? !!p.sale : filter === 'stock' ? p.stock : p.brand === filter)),
        [list, search, filter],
    );
    useEffect(() => {
        window.localStorage.setItem('airgadget-cart', JSON.stringify(cart));
    }, [cart]);
    const addToCart = (item: CardProduct) => {
        setCart((items) => items.filter((cartItem) => cartItem.id === item.id).length < item.stock ? [...items, item] : items);
    };
    const section =
        view === 'shop'
            ? 'فروشگاه'
            : view === 'articles'
              ? 'مجله ایرگجت'
              : view === 'admin'
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
                    <Articles articles={Array.isArray(articles) ? articles : articles?.data || []} topics={topics} tags={tags} />
                ) : view === 'article' ? (
                    <ArticleDetail article={article} />
                ) : view === 'product' ? (
                    <ProductDetail product={product} add={addToCart} />
                ) : view === 'admin' ? (
                    <Admin products={productItems} articles={Array.isArray(articles) ? articles : []} categories={categories} brands={brands} accounting={accounting} orders={orders} shippingMethods={shippingMethods} />
                ) : view === 'account' ? (
                    <Account orders={orders} auth={auth} />
                ) : view === 'checkout' ? (
                    <Checkout cart={cart} shippingMethods={shippingMethods} clearCart={() => setCart([])} auth={auth} cardToCard={cardToCard} />
                ) : view === 'invoice' ? (
                    <Invoice order={invoice} shipping={invoiceShipping} clearCart={() => setCart([])} />
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
                                <h2>{view === 'shop' ? 'همه محصولات' : 'آخرین محصولات'}</h2>
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
                        {displayed.length ? (
                            <div className="product-grid">
                                {displayed.map((p) => (
                                    <ProductCard
                                        key={p.id}
                                        p={p}
                                        add={addToCart}
                                        fav={fav.includes(p.id)}
                                        toggle={() =>
                                            setFav((items) => (items.includes(p.id) ? items.filter((item) => item !== p.id) : [...items, p.id]))
                                        }
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
                <button className="heart" aria-label="افزودن به علاقه‌مندی" onClick={toggle}>
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

function resolveSeo(view: string, product?: Product, article?: Article): SeoMeta {
    if (view === 'product' && product) {
        return {
            title: product.meta_title || `${product.name} | ایرگجت`,
            description: product.meta_description || product.short_description || `خرید ${product.name} از ایرگجت با پشتیبانی ${supportPhone}`,
            keywords: product.meta_keywords || undefined,
            image: product.main_image || product.images?.[0]?.path || undefined,
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

function ProductDetail({ product, add }: { product: Product; add: (product: CardProduct) => void }) {
    if (!product) {
        return <main className="page"><h1>محصول پیدا نشد</h1></main>;
    }

    const card = toCard(product);

    return (
        <main className="page product-detail">
            <div className="product-detail-grid">
                <section className="product-gallery">
                    {card.image ? <img src={card.image} alt={product.name} /> : <div className="photo-placeholder">بدون عکس</div>}
                    {product.images && product.images.length > 1 && (
                        <div className="thumbs">
                            {product.images.map((image) => <img src={image.path} alt={product.name} key={image.id || image.path} />)}
                        </div>
                    )}
                </section>
                <section className="product-info">
                    <small>{product.category?.name || 'محصول'} · {product.brand?.name || 'ایرگجت'}</small>
                    <h1>{product.name}</h1>
                    <p>{product.short_description || product.description || 'این محصول با ضمانت اصالت کالا در ایرگجت عرضه می‌شود.'}</p>
                    <div className="product-price">
                        {product.sale_price && <del>{toman(Number(product.price))}</del>}
                        <b>{toman(Number(product.sale_price || product.price || 0))}</b>
                    </div>
                    <button className="primary" disabled={!card.stock} onClick={() => add(card)}>
                        {card.stock ? 'افزودن به سبد خرید' : 'ناموجود'}
                    </button>
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
                    const count = countByName.get(title);

                    return (
                        <Link href="/shop" key={title}>
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

function Articles({ articles, topics, tags }: { articles: Article[]; topics: string[]; tags: Tag[] }) {
    return (
        <main className="page">
            <em>راهنما و بررسی تخصصی</em>
            <h1>مجله ایرگجت</h1>
            <div className="article-taxonomy">
                {topics.map((topic) => <span key={topic}>{topic}</span>)}
                {tags.map((tag) => <small key={tag.id}>#{tag.name}</small>)}
            </div>
            <div className="article-grid">
                {articles.length ? articles.map((article) => (
                    <article className="article" key={article.id}>
                        {article.image ? <img src={article.image} alt={article.title} loading="lazy" /> : <div className="article-image" />}
                        <small>{article.topic || 'مجله ایرگجت'}</small>
                        <h2>{article.title}</h2>
                        <p>{article.excerpt || 'نکات کاربردی و اطلاعات دقیق برای انتخاب بهتر لوازم جانبی.'}</p>
                        <div className="article-tags">{article.tags?.map((tag) => <span key={tag.id}>#{tag.name}</span>)}</div>
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

function ArticleDetail({ article }: { article: Article }) {
    if (!article) {
        return <main className="page"><h1>مقاله پیدا نشد</h1></main>;
    }

    return (
        <main className="page article-detail">
            <em>{article.topic || 'مجله ایرگجت'}</em>
            <h1>{article.title}</h1>
            {article.image && <img src={article.image} alt={article.title} />}
            {article.excerpt && <p className="lead">{article.excerpt}</p>}
            <article>{article.body}</article>
            {article.tags && article.tags.length > 0 && (
                <div className="article-tags">{article.tags.map((tag) => <span key={tag.id}>#{tag.name}</span>)}</div>
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

function Account({ orders, auth }: { orders: Order[]; auth: any }) {
    const { props } = usePage<any>();
    const [section, setSection] = useState<'orders' | 'profile'>('orders');
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
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
    return (
        <main className="page account">
            <h1>حساب کاربری من</h1>
            {props.flash?.status && <div className="admin-status">{props.flash.status}</div>}
            <div className="account-grid">
                <aside>
                    <b>داشبورد</b>
                    <button className={section === 'orders' ? 'active' : ''} onClick={() => setSection('orders')}>سفارش‌های من</button>
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

function Checkout({ cart, shippingMethods, clearCart, auth, cardToCard }: { cart: CardProduct[]; shippingMethods: ShippingMethod[]; clearCart: () => void; auth: any; cardToCard?: { number: string; holder: string } }) {
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
                <form onSubmit={(event) => { event.preventDefault(); form.post(route('checkout.store'), { forceFormData: form.data.payment_method === 'card_to_card', onSuccess: clearCart }); }}>
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
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
    useEffect(() => {
        if (order.paid_at) clearCart();
    }, [order.paid_at]);
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
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
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

function PaymentResult({ result }: { result: { success?: boolean; number?: string; message?: string } }) {
    return (
        <main className="page payment-result">
            <div className={result?.success ? 'result-icon success' : 'result-icon failed'}>{result?.success ? '✓' : '×'}</div>
            <h1>{result?.success ? 'پرداخت موفق بود' : 'پرداخت انجام نشد'}</h1>
            {result?.number && <p>کد سفارش: <b>{result.number}</b></p>}
            <p>{result?.message}</p>
            <div><Link className="primary" href="/">بازگشت به فروشگاه</Link><Link className="secondary" href="/track-order">پیگیری سفارش</Link></div>
        </main>
    );
}

function Admin({
    products,
    articles,
    categories,
    brands,
    accounting,
    orders,
    shippingMethods,
}: {
    products: Product[];
    articles: Article[];
    categories: Category[];
    brands: Brand[];
    accounting: Accounting;
    orders: Order[];
    shippingMethods: ShippingMethod[];
}) {
    const { props } = usePage<any>();
    const [previews, setPreviews] = useState<string[]>([]);
    const [articlePreview, setArticlePreview] = useState<string | null>(null);
    const [tab, setTab] = useState<'accounting' | 'products' | 'articles' | 'shipping'>('accounting');
    const { data, setData, post, processing, errors, reset } = useForm<AdminForm>({
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
        main_image_index: 0,
        images: [],
    });
    const articleForm = useForm<ArticleForm>({
        title: '',
        topic: '',
        excerpt: '',
        body: '',
        tags: '',
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        main_image: null,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('admin.products.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                previews.forEach((preview) => URL.revokeObjectURL(preview));
                setPreviews([]);
                reset();
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
                                لطفاً خطاهای فرم را بررسی کنید. برای ثبت سریع، فقط نام محصول کافی است و SKU، قیمت و موجودی می‌توانند خالی بمانند.
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
                            <input value={data.price} onChange={(event) => setData('price', event.target.value)} inputMode="numeric" placeholder="قیمت اختیاری" />
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
                        <label className="upload-box">
                            <span>انتخاب و آپلود عکس محصول</span>
                            <small>می‌توانید چند عکس انتخاب کنید؛ بعد یکی را به عنوان عکس اصلی بزنید.</small>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                onChange={(event) => {
                                    previews.forEach((preview) => URL.revokeObjectURL(preview));
                                    const files = Array.from(event.target.files || []);
                                    setData('images', files);
                                    setData('main_image_index', 0);
                                    setPreviews(files.map((file) => URL.createObjectURL(file)));
                                }}
                            />
                        </label>
                        {errors.images && <small className="form-error">{errors.images}</small>}
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
                        <button className="primary" disabled={processing}>
                            {processing ? 'در حال ثبت...' : 'ثبت محصول'}
                        </button>
                    </form>
                </section>
                <section className={tab === 'articles' ? '' : 'admin-hidden'}>
                    <h2>افزودن مقاله</h2>
                    <form className="admin-form" onSubmit={submitArticle}>
                        {Object.keys(articleForm.errors).length > 0 && <div className="form-error-box">لطفاً خطاهای مقاله را بررسی کنید.</div>}
                        <input value={articleForm.data.title} onChange={(event) => articleForm.setData('title', event.target.value)} placeholder="عنوان مقاله" />
                        {articleForm.errors.title && <small className="form-error">{articleForm.errors.title}</small>}
                        <input value={articleForm.data.topic} onChange={(event) => articleForm.setData('topic', event.target.value)} placeholder="موضوع مقاله، مثل راهنمای خرید" />
                        {articleForm.errors.topic && <small className="form-error">{articleForm.errors.topic}</small>}
                        <input value={articleForm.data.tags} onChange={(event) => articleForm.setData('tags', event.target.value)} placeholder="تگ‌ها با ویرگول، مثل ایرپاد، شارژر" />
                        <textarea value={articleForm.data.excerpt} onChange={(event) => articleForm.setData('excerpt', event.target.value)} placeholder="خلاصه مقاله" />
                        <textarea value={articleForm.data.body} onChange={(event) => articleForm.setData('body', event.target.value)} placeholder="متن کامل مقاله" />
                        {articleForm.errors.body && <small className="form-error">{articleForm.errors.body}</small>}
                        <input value={articleForm.data.meta_title} onChange={(event) => articleForm.setData('meta_title', event.target.value)} placeholder="عنوان سئو مقاله" />
                        <textarea value={articleForm.data.meta_description} onChange={(event) => articleForm.setData('meta_description', event.target.value)} placeholder="توضیحات متا مقاله" />
                        <input value={articleForm.data.meta_keywords} onChange={(event) => articleForm.setData('meta_keywords', event.target.value)} placeholder="کلمات کلیدی مقاله" />
                        <label className="upload-box">
                            <span>عکس اصلی مقاله</span>
                            <small>این عکس در لیست مقاله‌ها و Open Graph استفاده می‌شود.</small>
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
                </section>
            </div>
            {tab === 'accounting' && <AccountingPanel accounting={accounting} orders={orders} />}
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
    const form = useForm({
        name: product.name,
        price: String(product.price || 0),
        sale_price: product.sale_price ? String(product.sale_price) : '',
        stock: String(product.stock || 0),
        is_active: product.is_active !== false,
    });

    return (
        <form className="manage-row" onSubmit={(event) => { event.preventDefault(); form.patch(route('admin.products.update', product.id), { preserveScroll: true }); }}>
            <div className="manage-heading">
                {product.main_image ? <img src={product.main_image} alt={product.name} /> : <div className="manage-placeholder" />}
                <span><b>{product.name}</b><small>{product.sku || 'بدون SKU'}</small></span>
            </div>
            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} aria-label="نام محصول" />
            <div className="admin-two">
                <input value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} inputMode="numeric" placeholder="قیمت" />
                <input value={form.data.sale_price} onChange={(event) => form.setData('sale_price', event.target.value)} inputMode="numeric" placeholder="قیمت تخفیفی" />
            </div>
            <div className="admin-two">
                <input value={form.data.stock} onChange={(event) => form.setData('stock', event.target.value)} inputMode="numeric" placeholder="موجودی" />
                <label className="admin-check"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> نمایش در فروشگاه</label>
            </div>
            {Object.keys(form.errors).length > 0 && <small className="form-error">مقادیر محصول را بررسی کنید.</small>}
            <div className="manage-actions">
                <button className="primary" disabled={form.processing}>ذخیره تغییرات</button>
                <button type="button" className="danger-button" onClick={() => { if (window.confirm(`محصول «${product.name}» حذف شود؟`)) router.delete(route('admin.products.destroy', product.id), { preserveScroll: true }); }}>حذف محصول</button>
            </div>
        </form>
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
    const form = useForm({ title: article.title, topic: article.topic || '', excerpt: article.excerpt || '', body: article.body || '', is_published: article.is_published !== false });

    return (
        <form className="manage-row" onSubmit={(event) => { event.preventDefault(); form.patch(route('admin.articles.update', article.id), { preserveScroll: true }); }}>
            <div className="manage-heading">
                {article.image ? <img src={article.image} alt={article.title} /> : <div className="manage-placeholder" />}
                <span><b>{article.title}</b><small>{article.tags?.map((tag) => `#${tag.name}`).join(' ') || 'بدون تگ'}</small></span>
            </div>
            <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="عنوان" />
            <input value={form.data.topic} onChange={(event) => form.setData('topic', event.target.value)} placeholder="موضوع" />
            <textarea value={form.data.excerpt} onChange={(event) => form.setData('excerpt', event.target.value)} placeholder="خلاصه" />
            <textarea value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} placeholder="متن مقاله" />
            <label className="admin-check"><input type="checkbox" checked={form.data.is_published} onChange={(event) => form.setData('is_published', event.target.checked)} /> مقاله منتشر باشد</label>
            {Object.keys(form.errors).length > 0 && <small className="form-error">عنوان، موضوع و متن مقاله را بررسی کنید.</small>}
            <div className="manage-actions">
                <button className="primary" disabled={form.processing}>ذخیره تغییرات</button>
                <button type="button" className="danger-button" onClick={() => { if (window.confirm(`مقاله «${article.title}» حذف شود؟`)) router.delete(route('admin.articles.destroy', article.id), { preserveScroll: true }); }}>حذف مقاله</button>
            </div>
        </form>
    );
}

function AccountingPanel({ accounting, orders }: { accounting: Accounting; orders: Order[] }) {
    const labels: Record<string, string> = { pending_payment: 'در انتظار پرداخت', pending_review: 'ثبت شده', processing: 'تأیید شده', completed: 'ارسال شده', cancelled: 'لغو شده', failed: 'پرداخت ناموفق', refunded: 'مرجوع شده' };
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
            <h2>سفارش‌ها و وضعیت مالی</h2>
            <div className="orders-table">
                {orders.length ? orders.map((order) => (
                    <article key={order.id}>
                        <div className="admin-order-head">
                            <span><b>سفارش {order.number}</b><small>{order.address?.customer_name || 'مهمان'} · {new Intl.DateTimeFormat('fa-IR').format(new Date(order.created_at))}</small></span>
                            <span><b>{toman(Number(order.total))}</b><small>{order.items?.reduce((sum, item) => sum + Number(item.quantity), 0) || 0} کالا</small></span>
                            <select value={order.status} onChange={(event) => router.patch(route('admin.orders.status', order.id), { status: event.target.value }, { preserveScroll: true })}>
                                {Object.entries(labels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}
                            </select>
                        </div>
                        <div className="admin-order-customer">
                            <span><b>مشتری:</b> {order.address?.customer_name}</span>
                            <span><b>موبایل:</b> {order.address?.phone}</span>
                            <span><b>کد پستی:</b> {order.address?.postal_code}</span>
                            <span className="wide"><b>آدرس:</b> {order.address?.full}</span>
                        </div>
                        <div className="admin-order-items">
                            {order.items?.map((item) => <p key={item.id || item.sku}><span>{item.name}</span><span>{item.quantity} عدد</span><span>واحد: {toman(Number(item.price || 0))}</span><b>کل: {toman(Number(item.price || 0) * item.quantity)}</b></p>)}
                        </div>
                        <div className="admin-order-actions">
                            <span>ارسال: {toman(Number(order.shipping_cost || 0))} · جمع کل: <b>{toman(Number(order.total))}</b></span>
                            {order.payment_method === 'card_to_card' && <span className="receipt-summary">واریزی اعلام‌شده: <b>{toman(Number(order.card_to_card_amount || 0))}</b>{order.payment_receipt && <a href={route('admin.orders.receipt', order.id)} target="_blank" rel="noreferrer">مشاهده فیش</a>}</span>}
                            {order.invoice_token && <><Link href={`/orders/${order.id}/invoice/${order.invoice_token}`}>فاکتور</Link><a href={`/orders/${order.id}/invoice/${order.invoice_token}/pdf`} target="_blank" rel="noreferrer">PDF فاکتور</a></>}
                        </div>
                    </article>
                )) : <p>هنوز سفارشی ثبت نشده است؛ آمار مالی صفر و واقعی است.</p>}
            </div>
        </section>
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
