import { getStaticPageMetadata } from '@/lib/page-seo.server';
import Image from 'next/image';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { IconArrowUpRight, IconMapPin } from '@tabler/icons-react';
import { aboutCopy } from '@/data/about.mock';
import { getCategories } from '@/lib/catalog/categories.server';
import { AboutPartners } from '@/components/about/AboutPartners';
import { AboutContactButton } from '@/components/about/AboutContactButton';
import { AboutTestimonials } from '@/components/about/AboutTestimonials';
import { getContactsResult } from '@/lib/contacts.server';
import { HomeImage } from '@/components/home/HomeImage';
import pageStyles from '@/components/catalog/Catalog.module.css';
import homeStyles from '@/components/home/Home.module.css';
import styles from '@/components/about/About.module.css';

export const generateMetadata = () => getStaticPageMetadata('about');
export const dynamic = 'force-dynamic';

export default async function AboutPage() {
  const [categories, contactResult] = await Promise.all([getCategories(), getContactsResult()]);
  const showrooms = contactResult.status === 'success' ? contactResult.data.showrooms : [];
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><span aria-current="page">About Us</span></li>
    </ol></nav>

    <header className={styles.hero}>
      <div>
        <p className={styles.eyebrow}>FOR CONSIDERED SPACES</p>
        <h1>Planters &amp; Greenery for Thoughtfully Designed Spaces</h1>
        <p className={styles.introduction}>{aboutCopy.introduction}</p>
        <AboutContactButton />
      </div>
      <figure>
        <Image src={aboutCopy.heroImage.src} alt={aboutCopy.heroImage.alt} width={700} height={394} sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1420px) 48vw, 650px" preload className={styles.heroImage} />
        {aboutCopy.heroImage.caption && <figcaption>{aboutCopy.heroImage.caption}</figcaption>}
      </figure>
    </header>

    <section className={`${styles.section} ${styles.approach}`} aria-labelledby="professionals-title">
      <div className={styles.sectionIntro}><p className={styles.eyebrow}>Your project brief</p>
        <h2 id="professionals-title">Working With Professionals</h2><p>{aboutCopy.approach}</p>
      </div>
      <div className={styles.considerations}>{aboutCopy.considerations.map((item, index) => <article key={item.title}>
        <span className={styles.step} aria-hidden="true">0{index + 1}</span><div><h3>{item.title}</h3><p>{item.description}</p></div>
      </article>)}</div>
    </section>

    <section className={styles.section} aria-labelledby="partners-title">
      <div className={styles.sectionIntro}><p className={styles.eyebrow}>Trusted names</p>
        <h2 id="partners-title">Brands We&apos;ve Worked With</h2>
      </div>
      <AboutPartners />
    </section>

    <section className={styles.section} aria-labelledby="range-title">
      <div className={styles.sectionIntro}><p className={styles.eyebrow}>Explore the collection</p>
        <h2 id="range-title">Our Product Range</h2><p>Explore outdoor planters, indoor pots and artificial greenery for hospitality, workplace, residential and commercial environments. Browse the collections or contact our team to discuss suitable options for your project.</p>
      </div>
      <div className={`${homeStyles.home} ${homeStyles.categoryGrid}`}>{categories.map((category) => <Link key={category.id} href={category.path} className={homeStyles.categoryCard}>
        <div className={homeStyles.categoryImage}>{category.image && <HomeImage image={{ src: category.image, alt: category.imageAlt ?? category.name }} sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1420px) 32vw, 430px" objectFit="cover" />}</div>
        <div className={homeStyles.categoryContent}><h3>{category.name}</h3><IconArrowUpRight size={24} stroke={1.5} aria-hidden="true" /><p>{category.description}</p></div>
      </Link>)}</div>
    </section>

    <section className={`${styles.section} ${styles.testimonials}`} aria-labelledby="testimonials-title">
      <div className={styles.sectionIntro}><p className={styles.eyebrow}>Customer experiences</p>
        <h2 id="testimonials-title">What Our Clients Say</h2>
      </div>
      <AboutTestimonials />
    </section>

    {!!showrooms.length && <section className={styles.section} aria-labelledby="visit-title">
      <div className={styles.headingRow}><div className={styles.sectionIntro}>
        <p className={styles.eyebrow}>See the collection</p><h2 id="visit-title">Visit Our Showrooms</h2><p>Find our showroom contacts in Dubai, UAE and Riyadh, Saudi Arabia.</p>
      </div><Link href="/contacts" className={styles.textLink}>View All Contacts <IconArrowUpRight size={20} aria-hidden="true" /></Link></div>
      <div className={styles.showrooms}>{showrooms.map((showroom) => <article key={showroom.id}>
        <p className={styles.location}><IconMapPin size={18} stroke={1.5} aria-hidden="true" />{[showroom.city, showroom.country].filter(Boolean).join(', ')}</p>
        <h3>{showroom.name}</h3><address>{showroom.address}</address>
      </article>)}</div>
    </section>}

    <section className={styles.closing} aria-labelledby="discuss-title">
      <p className={styles.eyebrow}>Plan your next space</p><h2 id="discuss-title">Let&apos;s Discuss Your Project</h2>
      <p>Looking for planters or artificial greenery for your next project? Our team can help you explore suitable options.</p>
      <div className={styles.actions}><Link href="/shop" className={styles.primaryButton}>Explore Products <IconArrowUpRight size={20} aria-hidden="true" /></Link>
        <Link href="/contacts" className={styles.secondaryButton}>Contact Us <IconArrowUpRight size={20} aria-hidden="true" /></Link></div>
    </section>
  </Container>;
}
