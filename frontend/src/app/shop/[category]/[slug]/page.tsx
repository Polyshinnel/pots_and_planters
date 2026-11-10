import type { Metadata } from 'next';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { notFound, permanentRedirect } from 'next/navigation';
import { getProductBySlug, getRelatedProducts } from '@/lib/catalog/products.server';
import { getCategories } from '@/lib/catalog/categories.server';
import { ServerApiError } from '@/lib/api.server';
import { primaryCategory, productImages, productSpecifications } from '@/lib/catalog/product-detail';
import { formatPrice } from '@/lib/format-price';
import { CatalogCard } from '@/components/catalog/CatalogCard';
import { ProductGallery } from '@/components/product/ProductGallery';
import { ProductActions } from '@/components/product/ProductActions';
import { ProductDescription } from '@/components/product/ProductDescription';
import { ProductErrorState } from '@/components/product/ProductErrorState';
import styles from '@/components/product/Product.module.css';
import type { Product } from '@/types/catalog';

type Props = { params: Promise<{ category: string; slug: string }> };

async function resolveProduct(params: Props['params']) {
  const { category: categorySlug, slug } = await params;
  const product = await getProductBySlug(slug);
  if (!product) notFound();
  const category = primaryCategory(product, await getCategories());
  if (!category) notFound();
  if (category.slug !== categorySlug) permanentRedirect(product.canonicalPath ?? `${category.path}/${encodeURIComponent(product.slug)}`);
  return { product, category };
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  let resolved: Awaited<ReturnType<typeof resolveProduct>>;
  try {
    resolved = await resolveProduct(params);
  } catch (error) {
    // Metadata runs outside the route error boundary. Keep API failures out of
    // metadata generation so the page can render its explicit API error state.
    // Product 404 still reaches notFound() and retains its HTTP status.
    if (error instanceof ServerApiError && error.status !== 404) {
      return { robots: { index: false, follow: true } };
    }
    throw error;
  }
  const { product, category } = resolved;
  return {
    title: product.seo?.title || product.seoTitle?.trim() || product.name,
    ...(product.seo?.description || product.seoDescription ? { description: product.seo?.description ?? product.seoDescription! } : {}),
    robots: product.seo?.robots ?? { index: false, follow: true },
    alternates: { canonical: product.canonicalPath ?? `${category.path}/${encodeURIComponent(product.slug)}` },
    openGraph: { type: 'website', url: product.canonicalPath, title: product.seo?.title ?? product.name, ...(product.seo?.description ? { description: product.seo.description } : {}), ...(product.seo?.ogImage ? { images: [product.seo.ogImage] } : {}) },
  };
}

export default async function ProductPage({ params }: Props) {
  let resolved: Awaited<ReturnType<typeof resolveProduct>>;
  try {
    resolved = await resolveProduct(params);
  } catch (error) {
    if (error instanceof ServerApiError) return <ProductErrorState />;
    throw error;
  }
  const { product, category } = resolved;
  let related: Product[] = [];
  let relatedUnavailable = false;
  try { related = await getRelatedProducts(product, 4); }
  catch (error) { relatedUnavailable = true; console.error('Related Product API request failed', error instanceof Error ? error.message : 'unavailable'); }
  const specifications = productSpecifications(product);
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol>
      <li><Link href="/">Home</Link></li>
      <li><Link href={category.path}>{category.name}</Link></li>
      <li><span aria-current="page">{product.name}</span></li>
    </ol></nav>
    <div className={styles.top}>
      <ProductGallery key={product.id} images={productImages(product)} name={product.name} />
      <section className={styles.info} aria-labelledby="product-title">
        <p className={styles.sku}>Model: {product.sku}</p>
        <h1 id="product-title">{product.name}</h1>
        {specifications.length > 0 && <section aria-labelledby="specifications-title">
          <h2 id="specifications-title">Specifications</h2>
          <dl className={styles.specs}>{specifications.map(({ label, value }) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
        </section>}
        <p className={styles.price}>{formatPrice(product.priceVerified === false ? null : product.priceAed)}</p>
        <ProductActions key={product.id} product={{ id: product.id, sku: product.sku, slug: product.slug, name: product.name, categoryId: category.id, image: productImages(product)[0]?.src, specifications }} />
      </section>
    </div>
    <ProductDescription heading={product.descriptionHeading} paragraphs={product.descriptionParagraphs} html={product.descriptionHtml} />
    {relatedUnavailable ? <section className={styles.related} aria-label="Related products"><p role="alert">Related products are temporarily unavailable. Please try again.</p></section> : related.length > 0 && <section className={styles.related} aria-labelledby="related-title">
      <h2 id="related-title">Related Products</h2>
      <div className={styles.relatedGrid}>{related.map((item) => <CatalogCard key={item.id} product={{ ...item, priceAed: item.priceVerified === false ? null : item.priceAed }} />)}</div>
    </section>}
  </Container>;
}
