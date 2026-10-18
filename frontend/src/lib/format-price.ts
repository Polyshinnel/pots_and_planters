const amountFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

export function formatPrice(priceAed: number | null): string {
  return priceAed !== null && Number.isFinite(priceAed)
    ? `AED ${amountFormatter.format(priceAed)}`
    : 'Price on enquiry';
}
