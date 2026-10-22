export interface ContactPhone {
  label: string;
  href: `tel:+${string}`;
}

export interface ContactDetails {
  phones?: readonly ContactPhone[];
  emails?: readonly string[];
  instagram?: { handle: string; url: string } | null;
}

export interface Showroom extends ContactDetails {
  id: string;
  name: string;
  description?: string | null;
  address: string;
  city?: string;
  country?: string;
  openingHours?: readonly string[];
  directionsUrl?: string;
  coordinates?: { latitude: number; longitude: number };
}

/** Serializable frontend contract for future Laravel-managed contact content. */
export interface ContactsData {
  primary: Showroom | null;
  showrooms: readonly Showroom[];
  whatsappUrl: string | null;
}

export interface ContactsApiResponse { data: ContactsData }

export type ContactsResult =
  | { status: 'success'; data: ContactsData }
  | { status: 'error' };
