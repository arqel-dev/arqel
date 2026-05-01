import type { PluginReview } from '../../types';

type Props = {
  reviews: PluginReview[];
};

export function ReviewList({ reviews }: Props): JSX.Element {
  if (reviews.length === 0) {
    return <p className="text-sm text-neutral-500">Ainda não há reviews.</p>;
  }

  return (
    <ul className="space-y-4">
      {reviews.map((review) => (
        <li key={review.id} className="rounded border border-neutral-200 bg-white p-4">
          <header className="flex items-center justify-between">
            <span className="text-sm font-medium text-neutral-900">
              {'⭐'.repeat(review.stars)}
              <span className="sr-only">{review.stars} estrelas</span>
            </span>
            {typeof review.helpful_count === 'number' && (
              <span className="text-xs text-neutral-500">{review.helpful_count} úteis</span>
            )}
          </header>
          {review.comment && <p className="mt-1 text-sm text-neutral-700">{review.comment}</p>}
        </li>
      ))}
    </ul>
  );
}
