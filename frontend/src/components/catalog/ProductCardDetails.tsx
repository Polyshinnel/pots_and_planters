import { formatSpecifications } from '@/lib/catalog/format-specifications';
import { formatPrice } from '@/lib/format-price';
import type { ProductSpecifications } from '@/types/catalog';

type Props = {
  sku?: string;
  name: string;
  specifications?: ProductSpecifications;
  price: number | null;
  sample?: boolean;
  heading: 'h2' | 'h3';
  classNames: { content: string; sku: string; name: string; specifications: string; footer: string; price: string; view: string };
};

export function ProductCardDetails({ sku, name, specifications, price, sample, heading, classNames: c }: Props) {
  const formattedSpecifications = formatSpecifications(specifications);
  const Heading = heading;
  return <div className={c.content}>
    {sku && <p className={c.sku}>Model: {sku}{sample ? ' · Sample' : ''}</p>}
    <Heading className={c.name}>{name}</Heading>
    <p className={c.specifications} aria-hidden={!formattedSpecifications || undefined}>{formattedSpecifications || '\u00a0'}</p>
    <div className={c.footer}>
      <p className={c.price}>{formatPrice(price)}</p>
      <span className={c.view}>View product <span aria-hidden="true">↗</span></span>
    </div>
  </div>;
}
