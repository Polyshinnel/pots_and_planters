import { categories } from '@/config/catalog';
import { homeCategories } from './home.mock';

// Editorial adaptation of legacy template/common/home.tpl: the company copy
// describes designers, developers, buyers, landscapers and hospitality interiors.
// Avoid importing its historical dates, representation claims or service promises.
export const aboutCopy = {
  introduction: 'Pots & Planters helps professionals explore pots, planters and artificial greenery for residential, hospitality and commercial spaces. Based in Dubai, we bring together indoor and outdoor collections for designers, architects and project buyers.',
  approach: 'Every space starts with a different brief. Whether you are an architect, interior or landscape designer, contractor, developer or part of a procurement team, talk to us about what you are sourcing.',
  considerations: [
    { title: 'Start with the space', description: 'Tell us about the setting, intended application and the look you want to achieve, from an entrance or terrace to a hotel lobby or residential interior.' },
    { title: 'Explore forms and finishes', description: 'Discuss dimensions, proportions, materials and finishes to help narrow down the collection for your design brief.' },
    { title: 'Bring your requirements', description: 'Share product references, specifications and quantities so our team can discuss suitable options for your project.' },
  ],
  heroImage: {
    src: '/images/about/project-lounge.jpg',
    alt: 'Lounge with yellow seating, tall dark planters and greenery framed by metal mesh partitions',
    caption: 'A space brought to life with the participation of Pots & Planters.',
  },
};

// Resolve names and URLs from FE-03, with the existing FE-02 category imagery.
export const aboutCategories = categories.map((category) => ({
  ...category,
  image: homeCategories.find((item) => item.id === category.id)?.image ?? null,
}));

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
  { name: 'Julia Alexander', quote: "I recently purchased several planters and pots from this company, and I couldn't be happier with my experience! The variety they offer is impressive, with options that cater to every style and preference. From elegant indoor pots designs to sturdy outdoor pots, there's something for every person.The quality of the products is outstanding. Each planter is well-crafted, ensuring durability while also being visually appealing. I've received so many compliments on the beautiful pots I've placed on my patio! Additionally, their customer service is exceptional. The staff was knowledgeable and helped me choose the right planters for my plants. They even provided helpful tips on care and maintenance.Overall, this company has transformed my gardening experience. I highly recommend them to anyone looking to enhance their home or garden with beautiful and functional planters!", expandable: true },
  { name: 'Kahtan Al kazaz', quote: 'I visited Pots and Planters last day … the welcoming and experience that I had was unique and incredible… Olga and the team explained all options … they were very professional… high ends products and reasonable prices.', expandable: true },
  { name: 'Mickey Maas', quote: 'We ordered pots for our villa! Thank you for quality and perfect service!👍', expandable: false },
  { name: 'Zsombor Mehesz', quote: 'Outstanding quality paired with exceptional customer service.', expandable: false },
] as const;
