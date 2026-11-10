const amountFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

export function formatPrice(priceAed: number | string | null): string {
  if (priceAed === null) return 'Price on enquiry';
  if (typeof priceAed === 'number') return Number.isFinite(priceAed) ? `AED ${amountFormatter.format(priceAed)}` : 'Price on enquiry';
  const match = /^(\d+)(?:\.(\d+))?$/.exec(priceAed);
  if (!match) return 'Price on enquiry';
  const integer = match[1].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const fraction = (match[2] ?? '').replace(/0+$/, '');
  return `AED ${integer}${fraction ? `.${fraction}` : ''}`;
}
