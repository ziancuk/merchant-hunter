/**
 * Deep link into Google Maps for a place. OpenStreetMap has no ratings or reviews, so the drawer sends the
 * user to Google Maps for those. Searching by name + address lands on the business listing; coordinates alone
 * (or as the fallback) only drop a pin.
 */
export function googleMapsUrl(place: { name: string; address?: string | null; lat: number; lng: number }): string {
    const text = [place.name, place.address].filter((p): p is string => !!p && p.trim() !== '').join(', ');
    const query = place.address ? text : `${place.name} ${place.lat},${place.lng}`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}
