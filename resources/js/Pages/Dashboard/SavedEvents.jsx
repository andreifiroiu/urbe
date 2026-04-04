import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EventList from '@/Components/Events/EventList';

/**
 * @param {Object} props
 * @param {Array<Object>} props.events
 */
export default function SavedEvents({ events = [] }) {
    return (
        <AppLayout title="Saved Events">
            <Head title="Saved Events" />
            <EventList
                events={events}
                emptyMessage="You haven't saved any events yet. Browse events and bookmark the ones you like."
            />
        </AppLayout>
    );
}
