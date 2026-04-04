import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import ChatWindow from '@/Components/Chat/ChatWindow';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';

/**
 * @param {Object} props
 * @param {Array<{id: string, role: string, content: string, created_at: string}>} props.messages
 */
export default function Chat({ messages = [] }) {
    const [isTyping, setIsTyping] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        message: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!data.message.trim()) return;

        setIsTyping(true);
        post('/onboarding/chat', {
            preserveScroll: true,
            onFinish: () => {
                setIsTyping(false);
                reset('message');
            },
        });
    };

    return (
        <>
            <Head title="Welcome to EventPulse" />
            <div className="min-h-screen bg-gray-50 flex flex-col">
                {/* Header */}
                <div className="bg-white border-b border-gray-200 px-4 py-4">
                    <div className="max-w-2xl mx-auto">
                        <h1 className="text-xl font-bold text-indigo-600">
                            EventPulse
                        </h1>
                        <p className="text-sm text-gray-500">
                            Tell us what you are interested in
                        </p>
                    </div>
                </div>

                {/* Chat area */}
                <div className="flex-1 max-w-2xl mx-auto w-full flex flex-col">
                    <ChatWindow messages={messages} isTyping={isTyping} />

                    {/* Input area */}
                    <div className="border-t border-gray-200 bg-white p-4">
                        <form
                            onSubmit={handleSubmit}
                            className="flex items-center gap-2"
                        >
                            <Input
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                placeholder="Type your message..."
                                disabled={processing}
                                className="flex-1"
                                autoFocus
                            />
                            <Button
                                type="submit"
                                disabled={processing || !data.message.trim()}
                            >
                                <svg
                                    className="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                    />
                                </svg>
                                <span className="hidden sm:inline">Send</span>
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
