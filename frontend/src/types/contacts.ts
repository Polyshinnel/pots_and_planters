export interface ContactPhone {
  label: string;
  href: `tel:+${string}`;
}

export interface ContactDetails {
  phones?: readonly ContactPhone[];
  emails?: readonly string[];
  instagram?: { handle: string; url: string };
}

export interface Showroom extends ContactDetails {
  id: string;
  name: string;
  description?: string;
  address: string;
  city?: string;
  country?: string;
  openingHours?: readonly string[];
  directionsUrl?: string;
  coordinates?: { latitude: number; longitude: number };
}

/** Serializable frontend contract for future Laravel-managed contact content. */
export interface ContactsData {
  primary: ContactDetails;
  showrooms: readonly Showroom[];
  whatsappUrl: `https://wa.me/${string}` | null;
}
