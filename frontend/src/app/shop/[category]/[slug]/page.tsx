import type { Metadata } from 'next';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { notFound, permanentRedirect } from 'next/navigation';
import { catalogRepository } from '@/lib/catalog/repository';
import { primaryCategory, productImages, productSpecifications } from '@/lib/catalog/product-detail';
import { formatPrice } from '@/lib/format-price';
import { CatalogCard } from '@/components/catalog/CatalogCard';
import { ProductGallery } from '@/components/product/ProductGallery';
import { ProductActions } from '@/components/product/ProductActions';
import { ProductDescription } from '@/components/product/ProductDescription';
import styles from '@/components/product/Product.module.css';

type Props = { params: Promise<{ category: string; slug: string }> };

async function resolveProduct(params: Props['params']) {
  const { category: categorySlug, slug } = await params;
  const product = await catalogRepository.getProductBySlug(slug);
  if (!product) notFound();
  const category = primaryCategory(product, await catalogRepository.getCategories());
  if (!category) notFound();
  if (category.slug !== categorySlug) permanentRedirect(`${category.path}/${encodeURIComponent(product.slug)}`);
  return { product, category };
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { product, category } = await resolveProduct(params);
  return {
    title: product.seoTitle?.trim() || `${product.name} — Model ${product.sku} | Pots & Planters`,
    description: product.seoDescription?.trim() || product.description?.trim() || `Explore ${product.name}, model ${product.sku}. Add this model to your enquiry at Pots & Planters.`,
    robots: { index: false, follow: true },
    alternates: { canonical: `${category.path}/${encodeURIComponent(product.slug)}` },
  };
}

export default async function ProductPage({ params }: Props) {
  const { product, category } = await resolveProduct(params);
  const related = await catalogRepository.getRelatedProducts(product);
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
    <ProductDescription heading={product.descriptionHeading} paragraphs={product.descriptionParagraphs} />
    {related.length > 0 && <section className={styles.related} aria-labelledby="related-title">
      <h2 id="related-title">Related Products</h2>
      <div className={styles.relatedGrid}>{related.map((item) => <CatalogCard key={item.id} product={{ ...item, priceAed: item.priceVerified === false ? null : item.priceAed }} />)}</div>
    </section>}
  </Container>;
}
