import { useEffect, useRef } from 'react';
import ChatBubble from '@/Components/Chat/ChatBubble';
import TypingIndicator from '@/Components/Chat/TypingIndicator';

/**
 * @param {Object} props
 * @param {Array<{id: string, role: string, content: string, created_at: string}>} props.messages
 * @param {boolean} [props.isTyping]
 */
export default function ChatWindow({ messages = [], isTyping = false }) {
    const bottomRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping]);

    return (
        <div className="flex-1 overflow-y-auto p-4">
            {messages.length === 0 && (
                <div className="flex items-center justify-center h-full text-gray-400 text-sm">
                    Start a conversation to set up your preferences.
                </div>
            )}
            {messages.map((message) => (
                <ChatBubble
                    key={message.id}
                    role={message.role}
                    content={message.content}
                    timestamp={message.created_at}
                />
            ))}
            {isTyping && <TypingIndicator />}
            <div ref={bottomRef} />
        </div>
    );
}
