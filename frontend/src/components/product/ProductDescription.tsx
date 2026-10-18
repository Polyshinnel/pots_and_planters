import styles from './ProductDescription.module.css';

type Props = {
  heading?: string | null;
  paragraphs?: string[];
};

export function ProductDescription({ heading, paragraphs }: Props) {
  const content = paragraphs?.map((paragraph) => paragraph.trim()).filter(Boolean) ?? [];
  if (!heading?.trim() || content.length === 0) return null;

  return <section className={styles.description} aria-labelledby="product-description-title">
    <h2 id="product-description-title">{heading.trim()}</h2>
    {content.map((paragraph, index) => <p key={`${index}-${paragraph}`}>{paragraph}</p>)}
  </section>;
}
