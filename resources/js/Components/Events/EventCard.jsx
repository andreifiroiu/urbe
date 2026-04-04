import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/Components/ui/Card';
import CategoryBadge from '@/Components/Events/CategoryBadge';
import ReactionButtons from '@/Components/Events/ReactionButtons';

/**
 * @param {Object} props
 * @param {Object} props.event
 * @param {string} props.event.id
 * @param {string} props.event.title
 * @param {string} [props.event.image_url]
 * @param {string} [props.event.starts_at]
 * @param {string} [props.event.venue_name]
 * @param {string} [props.event.category]
 * @param {string} [props.event.price]
 * @param {string|null} [props.event.current_reaction]
 */
export default function EventCard({ event }) {
    const formattedDate = event.starts_at
        ? new Date(event.starts_at).toLocaleDateString(undefined, {
              weekday: 'short',
              month: 'short',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : null;

    return (
        <Card className="overflow-hidden hover:shadow-md transition-shadow">
            <Link href={`/events/${event.id}`} className="block">
                <div className="aspect-video bg-gray-100 relative overflow-hidden">
                    {event.image_url ? (
                        <img
                            src={event.image_url}
                            alt={event.title}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center text-gray-300">
                            <svg
                                className="w-12 h-12"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={1.5}
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                    )}
                    {event.category && (
                        <div className="absolute top-2 left-2">
                            <CategoryBadge category={event.category} />
                        </div>
                    )}
                </div>
                <CardContent className="p-4">
                    <h3 className="font-semibold text-gray-900 line-clamp-2 mb-1">
                        {event.title}
                    </h3>
                    {formattedDate && (
                        <p className="text-sm text-gray-500 mb-1">{formattedDate}</p>
                    )}
                    {event.venue_name && (
                        <p className="text-sm text-gray-500 mb-2 truncate">
                            {event.venue_name}
                        </p>
                    )}
                    {event.price && (
                        <p className="text-sm font-medium text-indigo-600">
                            {event.price}
                        </p>
                    )}
                </CardContent>
            </Link>
            <div className="px-4 pb-4">
                <ReactionButtons
                    eventId={event.id}
                    currentReaction={event.current_reaction}
                />
            </div>
        </Card>
    );
}
