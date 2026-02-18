# SEO Remediation Report

Date: 2026-02-18
Project: Whimsical Frog
Workspace: `/Users/jongraves/Documents/Websites/WhimsicalFrog`

## What Changed

### 1) Indexable product detail URLs
- Added canonical product URL support at `/product/<product-slug--sku-slug>`.
- Added server-side product SEO payload generation in `includes/helpers/SpaSeoHelper.php` for `/product/...`.
- Added product detail React view in `src/components/storefront/ProductDetailView.tsx`.
- Added legacy canonicalization redirect in `router.php`:
  - `/shop?sku=<SKU>` now returns `301` to canonical `/product/...`.
- Product schema now uses canonical product URLs for both `Product.url` and `Offer.url`.

### 2) Metadata uniqueness and targeting
- Added path-specific default metadata (home/about/contact) in `includes/helpers/SpaSeoHelper.php` with unique intent-aligned title/description.
- Added category metadata generation for `/shop/category/<slug>` with unique title/description/canonical/image.
- Added product metadata generation with unique title/description/canonical/image per product.
- OG/Twitter tags remain emitted per route with route-specific values.

### 3) Heading/content intent improvements
- Added explicit commercial-intent shop H1 in `src/components/storefront/ShopView.tsx`.
- Added product-page H1 in `src/components/storefront/ProductDetailView.tsx`.
- Added keyword-intent copy blocks for:
  - Custom tumblers
  - Personalized t-shirts
  - Handmade resin gifts
  - Custom gift requests

### 4) Internal linking and crawlability
- Added crawlable product links from shop cards (`<a href="/product/...">`) in:
  - `src/components/storefront/shop/ItemCard.tsx`
  - `src/components/storefront/shop/partials/ProductGridArea.tsx`
- Added category links in shop template and product template.
- Added contextual policy/support links (contact/policy/privacy/terms).
- Added server-side hidden discoverability nav for rooms, categories, products, and social URLs in `includes/helpers/SpaSeoHelper.php`, injected from `router.php`.

### 5) Schema quality cleanup
- Removed spammy `keywords` and long marketing `additionalProperty` stuffing from `Product` schema output.
- Kept concise valid schema by page type:
  - Home/About/Contact: `Organization`
  - Shop/Category/Room: `CollectionPage` with `ItemList`
  - Product pages: `Product` with `Offer` (availability, price, currency, condition, URL, SKU)

### 6) Sitemap and robots
- Updated `includes/helpers/SitemapHelper.php` to include:
  - `/shop/category/<slug>` URLs
  - canonical `/product/...` URLs
- Removed legacy `/shop?sku=...` product URLs from sitemap output.
- `robots.txt` remains crawl-allowing with sitemap declaration.

### 7) Performance-oriented SEO hygiene
- Added route-level code splitting for product page via lazy-loaded `ProductDetailView` in `src/components/MainPageRenderer.tsx`.
- Added eager/async image decode hints on product hero image and lazy image loading in shop cards.
- Kept functionality unchanged while reducing first-load impact for product-detail UI code.

## URL Mapping (Old -> New)

### Product URL canonicalization
- Pattern:
  - Old: `/shop?sku=<SKU>`
  - New: `/product/<product-slug--sku-slug>`
- Example:
  - Old: `/shop?sku=WF-RE-001`
  - New: `/product/ribbit-glow-pressed-flower-resin-tray-blue-oval-happy-hop-penstance-ribbit-worthy-pond-spark--wf-re-001`
- Redirect status: `301 Moved Permanently`.

### Category URL canonicalization
- Pattern:
  - Old: `/shop?category=<slug>`
  - New: `/shop/category/<slug>`
- Redirect status: `301 Moved Permanently`.

## Metadata Matrix

| Page | Title | Meta Description | Canonical |
|---|---|---|---|
| `/` | Whimsical Frog \| Custom Gifts & Handmade Decor | Shop custom tumblers, personalized t-shirts, handmade resin gifts, and custom gift requests at WhimsicalFrog. | `http://<host>/` |
| `/shop` | Shop Custom Items \| Whimsical Frog | Browse our complete collection of custom crafts, personalized items, and handmade goods. Find the perfect item for any occasion. | `http://<host>/shop` |
| `/shop/category/tumblers` | Tumblers \| Custom Gifts & Personalized Items | Shop tumblers from WhimsicalFrog, including custom tumblers, personalized t-shirts, and handmade resin gifts. | `http://<host>/shop/category/tumblers` |
| `/about` | About WhimsicalFrog \| Handmade Gift Studio | Learn how WhimsicalFrog creates custom gifts, personalized apparel, and handcrafted resin keepsakes. | `http://<host>/about` |
| `/contact` | Contact WhimsicalFrog \| Custom Order Requests | Contact WhimsicalFrog for custom gift requests, turnaround questions, shipping support, and order help. | `http://<host>/contact` |
| `/product/<slug--sku>` | Dynamic product-specific title (truncated to SEO-safe length) | Dynamic product-specific description | `http://<host>/product/<slug--sku>` |

## Schema Coverage Summary

- Home/About/Contact:
  - `Organization` JSON-LD present.
- Shop:
  - `CollectionPage` + `ItemList` with product entities.
- Category pages:
  - `CollectionPage` + `ItemList` scoped to category.
- Product pages:
  - `Product` + `Offer`, includes `sku`, `priceCurrency`, `price`, `availability`, `itemCondition`, canonical `url`, `image`, `brand`.

## Internal Linking Summary

- Shop product cards now include crawlable anchors to product detail URLs.
- Shop template includes category links to `/shop/category/<slug>`.
- Product template includes:
  - breadcrumb links (`/shop`, category page)
  - related product links
  - support/policy links (`/contact`, `/policy`, `/privacy`, `/terms`)
- Server-rendered hidden discoverability nav includes room/category/product links for non-JS crawling.

## Verification Checklist

Verification environment:
- Route verification via local PHP router: `php -S 127.0.0.1:8090 router.php`
- Metadata checks via `curl` against 6 pages (`/`, `/shop`, `/shop/category/tumblers`, `/about`, `/contact`, one `/product/...`).

### Checklist results
- [x] Unique title/meta across homepage + 5 key internal pages.
- [x] Correct canonical tags emitted per checked URL.
- [x] OG/Twitter tags present for checked pages.
- [x] JSON-LD present for checked pages.
- [x] Product detail URL indexability path works on router and emits product schema.
- [x] `/shop?sku=...` canonicalized with `301` to `/product/...`.
- [x] Sitemap includes `/product/...` URLs.
- [x] Sitemap includes `/shop/category/...` URLs.
- [x] Sitemap no longer emits `/shop?sku=...` product links.
- [x] `robots.txt` allows crawling and points to sitemap.
- [x] Navigation/cart flow preserved (shop modal open actions retained; product page CTA opens item modal).

### Notes
- Raw static HTML shell includes startup/no-JS H1 content in addition to app-rendered content. Primary route-level H1s were added for shop and product templates in React.
- Product titles/descriptions are content-derived and may naturally truncate at SEO-safe lengths for very long catalog names.

## Files Updated

- `router.php`
- `includes/helpers/SpaSeoHelper.php`
- `includes/helpers/SitemapHelper.php`
- `src/components/AppShell.tsx`
- `src/components/MainPageRenderer.tsx`
- `src/components/storefront/ShopView.tsx`
- `src/components/storefront/ProductDetailView.tsx`
- `src/components/storefront/shop/ItemCard.tsx`
- `src/components/storefront/shop/partials/ProductGridArea.tsx`
- `src/hooks/storefront/useShopUI.ts`
- `src/types/seo.ts`
- `src/types/index.ts`
- `src/utils/product-url.ts`

## Commands Run

- `php -l router.php`
- `php -l includes/helpers/SpaSeoHelper.php`
- `php -l includes/helpers/SitemapHelper.php`
- `npm run -s build`
- `node scripts/repo_hygiene.mjs`
- `curl` verification on key URLs and redirects

## Repo Hygiene Output

- `scripts/repo_hygiene.mjs` completed successfully.
- It reported existing orphan files across `.local`, root backup duplicates, and pre-existing src files; no destructive cleanup performed in this remediation.
