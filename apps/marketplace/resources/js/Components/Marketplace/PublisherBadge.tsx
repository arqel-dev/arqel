import { Link } from '@inertiajs/react';
import type { PublisherSnapshot } from '../../types';

type Props = {
  publisher: PublisherSnapshot;
  showLink?: boolean;
};

export function PublisherBadge({ publisher, showLink = true }: Props): JSX.Element {
  const initials = publisher.name
    .split(' ')
    .map((word) => word.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

  const inner = (
    <span
      data-testid="publisher-badge"
      className="inline-flex items-center gap-1.5 text-xs text-neutral-600"
    >
      {publisher.avatar_url ? (
        <img
          data-testid="publisher-avatar"
          src={publisher.avatar_url}
          alt={publisher.name}
          className="h-5 w-5 rounded-full"
        />
      ) : (
        <span
          data-testid="publisher-avatar-fallback"
          className="flex h-5 w-5 items-center justify-center rounded-full bg-neutral-200 text-[10px] font-medium text-neutral-700"
        >
          {initials}
        </span>
      )}
      <span data-testid="publisher-name">{publisher.name}</span>
      {publisher.verified === true && (
        <span
          data-testid="publisher-verified"
          role="img"
          aria-label="Verified publisher"
          title="Verified publisher"
          className="text-blue-600"
        >
          ✓
        </span>
      )}
    </span>
  );

  if (!showLink) return inner;

  return (
    <Link href={`/publishers/${publisher.slug}`} className="hover:underline">
      {inner}
    </Link>
  );
}
