
// Editorial adaptation of legacy template/common/home.tpl: the company copy
// describes designers, developers, buyers, landscapers and hospitality interiors.
// Avoid importing its historical dates, representation claims or service promises.
export const aboutCopy = {
  introduction: 'Pots & Planters works with architects, interior designers, hospitality teams and project buyers to source planters and artificial greenery for residential and commercial environments. From individual statement pieces to larger project requirements, we help you explore suitable sizes, finishes and collections for your space.',
  approach: 'Every project starts with a different space and set of requirements. We work with design and procurement professionals to explore suitable planters, finishes and artificial greenery for their projects.',
  considerations: [
    { title: 'Designed Around Your Space', description: 'From hotel entrances and restaurant terraces to office interiors and private residences, we help you explore planters suited to the scale and character of your space.' },
    { title: 'Options for Every Project Brief', description: 'Compare shapes, dimensions, finishes and collections to build a shortlist that works with your design direction and practical requirements.' },
    { title: 'A More Informed Quotation', description: 'Share your preferred products, quantities and project details so our team can discuss availability, alternatives and pricing for your requirements.' },
  ],
  heroImage: {
    src: '/images/about/project-lounge.jpg',
    alt: 'Lounge with yellow seating, tall dark planters and greenery framed by metal mesh partitions',
    caption: '',
  },
};

export const aboutPartners = [
  { name: 'MAN Private Offices', image: '/images/about/partners/man-private-offices.png', href: 'https://manprivateoffices.com/' },
  { name: 'The Ritz-Carlton', image: '/images/about/partners/ritz-carlton.png', href: 'https://www.ritzcarlton.com/' },
  { name: 'Rixos', image: '/images/about/partners/rixos.png', href: 'https://www.rixos.com/' },
  { name: 'Hilton', image: '/images/about/partners/hilton.png', href: 'https://www.hilton.com/' },
  { name: 'Regnum', image: '/images/about/partners/regnum.png', href: 'https://www.regnumhotels.com/' },
  { name: 'Titanic Mardan Palace', image: '/images/about/partners/titanic-mardan-palace.png', href: 'https://www.titanic.com.tr/ru/titanic-mardan-palace' },
  { name: 'Address', image: '/images/about/partners/address.png', href: 'http://adresyalikavak.com/' },
  { name: 'Cartier', image: '/images/about/partners/cartier.png', href: 'https://www.cartier.com/en-us/home' },
  { name: 'Zen Diamond', image: '/images/about/partners/zen-diamond.png', href: 'https://us.zendiamond.com/' },
  { name: 'Istinye Park', image: '/images/about/partners/istinye-park.png', href: 'https://istinyepark.com.tr/' },
  { name: 'Vadistanbul', image: '/images/about/partners/vadistanbul.png', href: 'https://www.vadistanbul.com/en' },
  { name: 'IKEA', image: '/images/about/partners/ikea.png', href: 'https://www.ikea.com/' },
] as const;

export const aboutTestimonials = [
  { name: 'Platon Leonov', quote: 'We would like to thank Olga for the wonderful plants. She gave us excellent advice and helped us choose. Mikhail is a true professional. He quickly secured the plants in the planters and decorated them with decorative stones. We will definitely be back.', expandable: true },
  { name: 'Kahtan Al kazaz', quote: 'I visited Pots and Planters last day … the welcoming and experience that I had was unique and incredible… Olga and the team explained all options … they were very professional… high ends products and reasonable prices.', expandable: true },
  { name: 'Julia Alexander', quote: "I recently purchased several planters and pots from this company, and I couldn't be happier with my experience! The variety they offer is impressive, with options that cater to every style and preference. From elegant indoor pots designs to sturdy outdoor pots, there's something for every person.The quality of the products is outstanding. Each planter is well-crafted, ensuring durability while also being visually appealing. I've received so many compliments on the beautiful pots I've placed on my patio! Additionally, their customer service is exceptional. The staff was knowledgeable and helped me choose the right planters for my plants. They even provided helpful tips on care and maintenance.Overall, this company has transformed my gardening experience. I highly recommend them to anyone looking to enhance their home or garden with beautiful and functional planters!", expandable: true },
  { name: 'Mickey Maas', quote: 'We ordered pots for our villa! Thank you for quality and perfect service!👍', expandable: false },
  { name: 'Zsombor Mehesz', quote: 'Outstanding quality paired with exceptional customer service.', expandable: false },
] as const;
