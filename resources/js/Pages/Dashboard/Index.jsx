import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EventList from '@/Components/Events/EventList';

/**
 * @param {Object} props
 * @param {Array<Object>} props.recommendations
 * @param {Array<Object>} props.discoveryEvents
 */
export default function Index({ recommendations = [], discoveryEvents = [] }) {
    return (
        <AppLayout>
            <Head title="Dashboard" />

            {/* Recommended section */}
            <section className="mb-10">
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                    Recommended for You
                </h2>
                <EventList
                    events={recommendations}
                    emptyMessage="No recommendations yet. Complete onboarding to get personalized suggestions."
                />
            </section>

            {/* Discovery section */}
            <section>
                <div className="mb-4">
                    <h2 className="text-xl font-semibold text-gray-900">
                        Discover Something New
                    </h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Events outside your usual interests that you might enjoy
                    </p>
                </div>
                <EventList
                    events={discoveryEvents}
                    emptyMessage="No discovery events available right now."
                />
            </section>
        </AppLayout>
    );
}
