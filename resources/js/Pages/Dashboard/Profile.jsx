import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import CategoryBadge from '@/Components/Events/CategoryBadge';
import { cn } from '@/lib/utils';

const channelOptions = [
    { value: 'email', label: 'Email' },
    { value: 'push', label: 'Push Notifications' },
    { value: 'both', label: 'Both Email & Push' },
];

const frequencyOptions = [
    { value: 'realtime', label: 'Real-time' },
    { value: 'daily', label: 'Daily Digest' },
    { value: 'weekly', label: 'Weekly Digest' },
];

/**
 * @param {Object} props
 * @param {Object} props.user
 * @param {string} props.user.name
 * @param {string} props.user.email
 * @param {Object} [props.user.interest_profile]
 * @param {Object} [props.user.interest_profile.categories] - e.g. { Music: 0.8, Tech: 0.6 }
 * @param {number} [props.user.interest_profile.discovery_openness] - 0.0 to 1.0
 * @param {Array<string>} [props.user.interest_profile.tags] - free-form interest tags
 * @param {string} [props.user.notification_channel]
 * @param {string} [props.user.notification_frequency]
 */
export default function Profile({ user }) {
    const profile = user?.interest_profile || {};
    const categories = profile.categories || {};
    const discoveryOpenness = profile.discovery_openness ?? 0.5;
    const tags = profile.tags || [];

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        channel: user?.notification_channel || 'email',
        frequency: user?.notification_frequency || 'daily',
    });

    const handleNotificationSubmit = (e) => {
        e.preventDefault();
        put('/settings/notifications');
    };

    const sortedCategories = Object.entries(categories).sort(
        ([, a], [, b]) => b - a
    );

    return (
        <AppLayout title="Your Profile">
            <Head title="Profile" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* User info */}
                <div className="lg:col-span-1">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Account</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <p className="text-sm text-gray-500">Name</p>
                                <p className="font-medium text-gray-900">
                                    {user?.name || 'Unknown'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Email</p>
                                <p className="font-medium text-gray-900">
                                    {user?.email || 'Unknown'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Interest profile */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Category scores */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Interest Categories
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {sortedCategories.length === 0 ? (
                                <p className="text-sm text-gray-400">
                                    No interest data yet. Complete onboarding or
                                    react to events to build your profile.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {sortedCategories.map(([category, score]) => (
                                        <div key={category}>
                                            <div className="flex items-center justify-between mb-1">
                                                <CategoryBadge category={category} />
                                                <span className="text-sm text-gray-500">
                                                    {Math.round(score * 100)}%
                                                </span>
                                            </div>
                                            <div className="w-full bg-gray-100 rounded-full h-2.5">
                                                <div
                                                    className={cn(
                                                        'h-2.5 rounded-full bg-indigo-500 transition-all duration-500'
                                                    )}
                                                    style={{
                                                        width: `${Math.round(score * 100)}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Discovery openness */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Discovery Openness
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-gray-500 mb-3">
                                How willing you are to receive events outside your
                                usual interests.
                            </p>
                            <div className="flex items-center gap-4">
                                <span className="text-sm text-gray-400">
                                    Focused
                                </span>
                                <div className="flex-1 bg-gray-100 rounded-full h-3 relative">
                                    <div
                                        className="h-3 rounded-full bg-indigo-500 transition-all duration-500"
                                        style={{
                                            width: `${Math.round(discoveryOpenness * 100)}%`,
                                        }}
                                    />
                                    <div
                                        className="absolute top-1/2 -translate-y-1/2 w-5 h-5 bg-white border-2 border-indigo-500 rounded-full shadow"
                                        style={{
                                            left: `calc(${Math.round(discoveryOpenness * 100)}% - 10px)`,
                                        }}
                                    />
                                </div>
                                <span className="text-sm text-gray-400">
                                    Adventurous
                                </span>
                            </div>
                            <p className="text-center text-sm font-medium text-gray-700 mt-2">
                                {Math.round(discoveryOpenness * 100)}%
                            </p>
                        </CardContent>
                    </Card>

                    {/* Interest tags */}
                    {tags.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Interest Tags
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Notification Preferences */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Notification Preferences
                            </CardTitle>
                            <CardDescription>
                                Choose how and when you want to receive event
                                recommendations.
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={handleNotificationSubmit}>
                            <CardContent className="space-y-6">
                                <div className="space-y-3">
                                    <Label className="text-base font-medium">
                                        Notification Channel
                                    </Label>
                                    <div className="space-y-2">
                                        {channelOptions.map((option) => (
                                            <label
                                                key={option.value}
                                                className={cn(
                                                    'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors',
                                                    data.channel === option.value
                                                        ? 'border-indigo-500 bg-indigo-50'
                                                        : 'border-gray-200 hover:bg-gray-50'
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="channel"
                                                    value={option.value}
                                                    checked={data.channel === option.value}
                                                    onChange={(e) =>
                                                        setData('channel', e.target.value)
                                                    }
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm font-medium text-gray-900">
                                                    {option.label}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="frequency" className="text-base font-medium">
                                        Frequency
                                    </Label>
                                    <Select
                                        id="frequency"
                                        value={data.frequency}
                                        onChange={(e) =>
                                            setData('frequency', e.target.value)
                                        }
                                    >
                                        {frequencyOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </CardContent>
                            <CardFooter className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Preferences'}
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-green-600">
                                        Saved successfully.
                                    </p>
                                )}
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
