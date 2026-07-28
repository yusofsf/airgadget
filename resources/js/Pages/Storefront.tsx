import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Brand = { id: number; name: string };
type Category = { id: number; name: string; slug?: string; products_count?: number };
type UploadedImage = { id?: number; path: string; sort_order?: number };
type Product = {
    id: number;
    name: string;
    sku?: string;
    price: number;
    sale_price?: number | null;
    brand?: Brand | null;
    category?: Category | null;
    main_image?: string | null;
    images?: UploadedImage[];
    attributes?: { color?: string };
    stock: number;
};
type CardProduct = {
    id: number;
    name: string;
    price: number;
    sale?: number;
    brand: string;
    color: string;
    image?: string;
    stock: boolean;
    tag?: string;
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
    main_image_index: number;
    images: File[];
};

const supportPhone = '09205850190';
const storeAddress = 'عبدالمطلب 35';
const toman = (n: number) => new Intl.NumberFormat('fa-IR').format(n) + ' تومان';
const toCard = (p: Product): CardProduct => ({
    id: p.id,
    name: p.name,
    price: Number(p.price || 0),
    sale: p.sale_price ? Number(p.sale_price) : undefined,
    brand: p.brand?.name || 'بدون برند',
    color: p.attributes?.color || 'مشکی',
    stock: Number(p.stock || 0) > 0,
    image: p.main_image || p.images?.[0]?.path,
});

export default function Storefront({
    view = 'home',
    products,
    auth,
    categories = [],
    brands = [],
    categoryCounts = [],
}: any) {
    const [search, setSearch] = useState('');
    const [cart, setCart] = useState<CardProduct[]>([]);
    const [fav, setFav] = useState<number[]>([]);
    const [panel, setPanel] = useState<'cart' | 'account' | null>(null);
    const [filter, setFilter] = useState('');
    const [menuOpen, setMenuOpen] = useState(false);
    const productItems: Product[] = Array.isArray(products) ? products : products?.data || [];
    const list = productItems.map(toCard);
    const heroImage = list.find((product) => product.image)?.image;
    const displayed = useMemo(
        () =>
            list
                .filter((p) => p.name.includes(search) || p.brand.toLowerCase().includes(search.toLowerCase()))
                .filter((p) => !filter || (filter === 'sale' ? !!p.sale : filter === 'stock' ? p.stock : p.brand === filter)),
        [list, search, filter],
    );
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
                    : '';

    return (
        <>
            <Head title={section || 'ایرگجت | لوازم جانبی موبایل'}>
                <meta name="description" content="خرید مطمئن لوازم جانبی موبایل، ایرپاد و گجت با ارسال سریع" />
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
                        <button className="icon account-icon" aria-label="حساب کاربری" onClick={() => setPanel('account')}>
                            ◎
                        </button>
                        <button className="icon basket" aria-label="سبد خرید" onClick={() => setPanel('cart')}>
                            ▱<i>{cart.length}</i>
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
                                {heroImage && <img src={heroImage} alt="لوازم جانبی موبایل ایرگجت" />}
                                <span className="float-card">✓ تضمین اصالت کالا</span>
                                <span className="float-sale">
                                    خرید
                                    <small>حضوری و آنلاین</small>
                                </span>
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
                    <Articles />
                ) : view === 'admin' ? (
                    <Admin products={productItems} categories={categories} brands={brands} />
                ) : view === 'account' ? (
                    <Account />
                ) : view === 'checkout' ? (
                    <Checkout cart={cart} />
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
                                        add={(item: CardProduct) => setCart((items) => [...items, item])}
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
                    <CartDrawer cart={cart} setCart={setCart} close={() => setPanel(null)} />
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
                <h3>{p.name}</h3>
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

function Articles() {
    return (
        <main className="page">
            <em>راهنما و بررسی تخصصی</em>
            <h1>مجله ایرگجت</h1>
            <div className="article-grid">
                {[
                    ['راهنمای خرید بهترین ایرپاد برای ورزش', 'راهنمای خرید'],
                    ['۵ روش افزایش عمر باتری گوشی', 'آموزش'],
                    ['مقایسه شارژرهای GaN و معمولی', 'بررسی تخصصی'],
                ].map((article) => (
                    <article className="article" key={article[0]}>
                        <div className="article-image" />
                        <small>{article[1]}</small>
                        <h2>{article[0]}</h2>
                        <p>نکات کاربردی و اطلاعات دقیق برای انتخاب بهتر لوازم جانبی.</p>
                        <a>ادامه مطلب ←</a>
                    </article>
                ))}
            </div>
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

function Account() {
    return (
        <main className="page account">
            <h1>حساب کاربری من</h1>
            <div className="account-grid">
                <aside>
                    <b>داشبورد</b>
                    <a>سفارش‌های من</a>
                    <a>آدرس‌ها</a>
                    <a>علاقه‌مندی‌ها</a>
                    <a>جزئیات حساب</a>
                </aside>
                <section>
                    <h2>سلام، خوش آمدید</h2>
                    <p>از این بخش می‌توانید سفارش‌ها، آدرس‌ها و فاکتورهای خود را مدیریت کنید.</p>
                    <div className="stats">
                        <b>۰<small>سفارش</small></b>
                        <b>۰<small>علاقه‌مندی</small></b>
                        <b>۰<small>آدرس</small></b>
                    </div>
                </section>
            </div>
        </main>
    );
}

function Checkout({ cart }: any) {
    return (
        <main className="page checkout">
            <h1>تکمیل سفارش</h1>
            <div className="checkout-grid">
                <form>
                    <h3>روش ارسال</h3>
                    <label><input type="radio" name="ship" defaultChecked /> پیک شهری مشهد</label>
                    <label><input type="radio" name="ship" /> دریافت حضوری</label>
                    <label><input type="radio" name="ship" /> پست پیشتاز</label>
                    <h3>روش پرداخت</h3>
                    <label><input type="radio" name="pay" defaultChecked /> پرداخت آنلاین</label>
                    <label><input type="radio" name="pay" /> کارت به کارت و ارسال تصویر رسید</label>
                    <input placeholder="آدرس کامل" defaultValue={storeAddress} />
                    <button className="primary">ثبت سفارش</button>
                </form>
                <aside>
                    <h3>خلاصه سفارش</h3>
                    <p>{cart.length} کالا در سبد شما</p>
                    <b>{toman(cart.reduce((sum: number, p: CardProduct) => sum + (p.sale || p.price), 0))}</b>
                </aside>
            </div>
        </main>
    );
}

function Admin({ products, categories, brands }: { products: Product[]; categories: Category[]; brands: Brand[] }) {
    const { props } = usePage<any>();
    const [previews, setPreviews] = useState<string[]>([]);
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
        main_image_index: 0,
        images: [],
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

    return (
        <main className="page admin">
            <h1>پنل مدیریت</h1>
            {props.flash?.status && <div className="admin-status">{props.flash.status}</div>}
            <div className="dashboard">
                <div><b>{new Intl.NumberFormat('fa-IR').format(products.length)}</b><small>محصول ثبت‌شده</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(products.filter((p) => p.stock > 0).length)}</b><small>محصول موجود</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(categories.length)}</b><small>دسته‌بندی</small></div>
                <div><b>{new Intl.NumberFormat('fa-IR').format(brands.length)}</b><small>برند</small></div>
            </div>
            <div className="admin-grid">
                <section>
                    <h2>افزودن محصول</h2>
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
                <section>
                    <h2>محصولات اخیر</h2>
                    <div className="admin-products">
                        {products.length ? (
                            products.map((product) => (
                                <article key={product.id}>
                                    {product.main_image ? <img src={product.main_image} alt={product.name} /> : <div />}
                                    <span>
                                        <b>{product.name}</b>
                                        <small>{product.sku || 'بدون SKU'} · موجودی {new Intl.NumberFormat('fa-IR').format(product.stock)}</small>
                                    </span>
                                </article>
                            ))
                        ) : (
                            <p>هنوز محصولی ثبت نشده است.</p>
                        )}
                    </div>
                </section>
            </div>
        </main>
    );
}

function CartDrawer({ cart, setCart, close }: any) {
    return (
        <aside className="drawer">
            <button className="close" aria-label="بستن" onClick={close}>×</button>
            <h2>سبد خرید شما</h2>
            {cart.length ? (
                <>
                    {cart.map((p: CardProduct, i: number) => (
                        <div className="cart-item" key={`${p.id}-${i}`}>
                            {p.image && <img src={p.image} alt="" />}
                            <span>{p.name}<small>{toman(p.sale || p.price)}</small></span>
                            <button aria-label={`حذف ${p.name}`} onClick={() => setCart((items: CardProduct[]) => items.filter((_, index) => index !== i))}>
                                ×
                            </button>
                        </div>
                    ))}
                    <div className="total">
                        جمع کل <b>{toman(cart.reduce((sum: number, p: CardProduct) => sum + (p.sale || p.price), 0))}</b>
                    </div>
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
                        {auth.user.is_admin ? 'ورود به پنل مدیریت' : 'مشاهده حساب کاربری'}
                    </Link>
                </>
            ) : (
                <>
                    <p>برای مشاهده سفارش‌ها، آدرس‌ها و علاقه‌مندی‌ها وارد حساب خود شوید.</p>
                    <Link className="primary full" href="/login">ورود / ثبت‌نام</Link>
                </>
            )}
        </aside>
    );
}
