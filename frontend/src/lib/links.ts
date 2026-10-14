import { router, type Href } from 'expo-router';

/** Map a server deep link ("/today", "/history", "/dishes", "/dishes/30") to an app route. */
export function linkToHref(link: string | null | undefined): Href | null {
  if (!link) return null;
  const dish = link.match(/^\/dishes\/(\d+)/);
  if (dish) return { pathname: '/dish/[id]', params: { id: dish[1] } };
  if (link.startsWith('/dishes/custom') || link.startsWith('/custom')) return { pathname: '/dishes', params: { tab: 'custom' } };
  if (link.startsWith('/dishes')) return '/dishes';
  if (link.startsWith('/history')) return '/history';
  if (link.startsWith('/settings')) return '/settings';
  if (link.startsWith('/notifications')) return '/notifications';
  return '/today';
}

export function openLink(link: string | null | undefined) {
  const href = linkToHref(link);
  if (href) router.navigate(href);
}
