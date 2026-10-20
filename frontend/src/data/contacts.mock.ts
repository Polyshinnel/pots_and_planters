import type { ContactsData, Showroom } from '@/types/contacts';

// Source: FE-08, “Confirmed showroom and contact data — user-supplied amendment”.
const mainShowroom: Showroom = {
  id: 'deva-harmony-trading',
  name: 'DEVA HARMONY TRADING',
  description: 'Main Dubai showroom / warehouse.',
  address: 'Warehouse#4, Al Kahraman Street#4, Al Quoz Industrial 4, Dubai',
  city: 'Dubai', country: 'UAE',
  phones: [
    { label: '+971 58 580 7443', href: 'tel:+971585807443' },
    { label: '+971 58 520 2572', href: 'tel:+971585202572' },
  ],
  emails: ['pots.decor@potsandplanters.ae', 'uaepots@potsandplanters.ae'],
  instagram: { handle: '@plantersuae', url: 'https://www.instagram.com/plantersuae' },
};

export const contactsData: ContactsData = {
  // No established company-wide values existed: use the supplied main Dubai contact.
  primary: { phones: mainShowroom.phones, emails: mainShowroom.emails, instagram: mainShowroom.instagram },
  // Existing site configuration deliberately disables the unconfirmed legacy destination.
  whatsappUrl: null,
  showrooms: [
    mainShowroom,
    {
      id: 'real-touch-flowers', name: 'Real Touch Flowers',
      description: 'Our second showroom, Real Touch Flowers, is dedicated exclusively to premium artificial plants and trees by Real Touch.',
      address: 'UAE, Dubai, Jabal Ali Industrial First, Sheikh Saeed Bin Mohd Bin Hasher Ware House, Warehouse No. 163',
      city: 'Dubai', country: 'UAE',
      phones: [{ label: '+971 58 520 2572', href: 'tel:+971585202572' }],
      emails: ['manager.realtouchtrees@gmail.com'],
      instagram: { handle: '@realtouch.uae', url: 'https://www.instagram.com/realtouch.uae' },
    },
    {
      id: 'design-house-projects', name: 'Design House Projects',
      description: 'Our third showroom, Design House Projects, is dedicated exclusively to premium artificial plants and trees.',
      address: 'RHGA4460, 6824 Al Imam Saud Ibn Abdul Aziz Branch Rd, 4460, Riyadh 12282',
      city: 'Riyadh', country: 'Saudi Arabia',
      phones: [{ label: '+966 54 477 7707', href: 'tel:+966544777707' }],
      instagram: { handle: '@designhouseprojects', url: 'https://www.instagram.com/designhouseprojects/' },
    },
  ],
};
