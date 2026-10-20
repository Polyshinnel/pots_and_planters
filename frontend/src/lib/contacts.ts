import type { Showroom } from '@/types/contacts';

/** Address search only; does not imply a verified pin or entrance. */
export function showroomDirectionsUrl(showroom: Showroom): string | null {
  if (showroom.directionsUrl) return showroom.directionsUrl;
  if (!showroom.address.trim()) return null;
  return `https://www.google.com/maps/search/?${new URLSearchParams({ api: '1', query: showroom.address })}`;
}
