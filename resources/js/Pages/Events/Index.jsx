import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EventList from '@/Components/Events/EventList';
import { Input } from '@/Components/ui/Input';
import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/utils';

const allCategories = [
    'Music',
    'Tech',
    'Sports',
    'Arts',
    'Food',
    'Nightlife',
    'Business',
    'Health',
    'Education',
    'Community',
    'Film',
    'Theater',
    'Other',
];

/**
 * @param {Object} props
 * @param {Object} props.events - Paginated events object
 * @param {Array<Object>} props.events.data
 * @param {Object} props.events.links
 * @param {Object} props.events.meta
 * @param {number} props.events.current_page
 * @param {number} props.events.last_page
 * @param {string|null} props.events.next_page_url
 * @param {string|null} props.events.prev_page_url
 * @param {string} [props.filters.search]
 * @param {string} [props.filters.category]
 */
export default function Index({ events = {}, filters = {} }) {
    const eventData = events.data || events;
    const [search, setSearch] = useState(filters.search || '');
    const activeCategory = filters.category || null;

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            '/events',
            { search, category: activeCategory },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleCategoryFilter = (category) => {
        const newCategory = activeCategory === category ? null : category;
        router.get(
            '/events',
            { search, category: newCategory },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handlePageChange = (url) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Browse Events">
            <Head title="Events" />

            {/* Search bar */}
            <form onSubmit={handleSearch} className="mb-6">
                <div className="flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search events..."
                        className="flex-1"
                    />
                    <Button type="submit">Search</Button>
                </div>
            </form>

            {/* Category filter chips */}
            <div className="flex flex-wrap gap-2 mb-6">
                {allCategories.map((category) => (
                    <button
                        key={category}
                        onClick={() => handleCategoryFilter(category)}
                        className={cn(
                            'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                            activeCategory === category
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        )}
                    >
                        {category}
                    </button>
                ))}
            </div>

            {/* Event grid */}
            <EventList
                events={Array.isArray(eventData) ? eventData : []}
                emptyMessage="No events match your search. Try different keywords or filters."
            />

            {/* Pagination */}
            {events.last_page > 1 && (
                <div className="flex items-center justify-center gap-2 mt-8">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!events.prev_page_url}
                        onClick={() => handlePageChange(events.prev_page_url)}
                    >
                        Previous
                    </Button>
                    <span className="text-sm text-gray-500">
                        Page {events.current_page} of {events.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!events.next_page_url}
                        onClick={() => handlePageChange(events.next_page_url)}
                    >
                        Next
                    </Button>
                </div>
            )}
        </AppLayout>
    );
}
