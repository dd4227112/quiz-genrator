import PropTypes from 'prop-types';
import axios from 'axios';
import { useId, useRef, useState } from 'react';
import Alert from '../Alert';
import Button from '../Button';
import Spinner from '../Spinner';
import { errorMessage } from '../../lib/errors';

export default function ClarificationBox({ questionId }) {
    const panelId = useId();
    const inputId = useId();
    const inputRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [history, setHistory] = useState(null);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [pending, setPending] = useState(null);
    const [error, setError] = useState(null);

    const loadHistory = async () => {
        setLoadingHistory(true);
        setError(null);
        try {
            const { data } = await axios.get(route('questions.clarifications.index', questionId));
            setHistory(data.data);
        } catch (err) {
            setError(errorMessage(err, 'Could not load previous clarifications.'));
        } finally {
            setLoadingHistory(false);
        }
    };

    const toggle = () => {
        const next = !open;
        setOpen(next);
        if (next && history === null && !loadingHistory) {
            loadHistory();
        }
        if (next) {
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    };

    const submit = async (event) => {
        event.preventDefault();
        const text = message.trim();
        if (!text || sending) {
            return;
        }

        setSending(true);
        setPending(text);
        setError(null);

        try {
            const { data } = await axios.post(route('questions.clarifications.store', questionId), { message: text });
            setHistory((current) => [...(current ?? []), data.data]);
            setMessage('');
        } catch (err) {
            setError(errorMessage(err, 'The AI could not answer right now. Please try again.'));
        } finally {
            setSending(false);
            setPending(null);
        }
    };

    const count = history?.length ?? 0;

    return (
        <div className="border-t border-slate-100 pt-4">
            <button
                type="button"
                onClick={toggle}
                aria-expanded={open}
                aria-controls={panelId}
                className="inline-flex items-center gap-1.5 rounded text-sm font-medium text-indigo-600 hover:text-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            >
                <svg
                    className={`h-4 w-4 transition-transform ${open ? 'rotate-90' : ''}`}
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fillRule="evenodd"
                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                        clipRule="evenodd"
                    />
                </svg>
                Ask the AI about this question{count > 0 ? ` (${count})` : ''}
            </button>

            {open && (
                <div id={panelId} className="mt-3 space-y-3">
                    {loadingHistory && (
                        <div className="flex items-center gap-2 text-sm text-slate-500">
                            <Spinner size="sm" label="" /> Loading previous clarifications…
                        </div>
                    )}

                    {history?.length === 0 && !pending && (
                        <p className="text-sm text-slate-500">No clarifications yet. Ask anything about this question.</p>
                    )}

                    {(history?.length > 0 || pending) && (
                        <ol className="space-y-3" aria-live="polite">
                            {history?.map((item) => (
                                <li key={item.id} className="space-y-2">
                                    <div className="ml-auto max-w-[85%] rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white sm:max-w-[75%]">
                                        <span className="sr-only">You asked: </span>
                                        {item.user_message}
                                    </div>
                                    <div className="max-w-[90%] whitespace-pre-line rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-800 sm:max-w-[80%]">
                                        <span className="sr-only">AI answered: </span>
                                        {item.ai_response}
                                    </div>
                                </li>
                            ))}
                            {pending && (
                                <li className="space-y-2">
                                    <div className="ml-auto max-w-[85%] rounded-lg bg-indigo-600/80 px-3 py-2 text-sm text-white sm:max-w-[75%]">
                                        {pending}
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-slate-500">
                                        <Spinner size="sm" label="" /> Thinking…
                                    </div>
                                </li>
                            )}
                        </ol>
                    )}

                    {error && (
                        <Alert type="error" onDismiss={() => setError(null)}>
                            {error}
                        </Alert>
                    )}

                    <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
                        <label htmlFor={inputId} className="sr-only">
                            Ask a follow-up about this question
                        </label>
                        <input
                            ref={inputRef}
                            id={inputId}
                            type="text"
                            value={message}
                            maxLength={1000}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder="e.g. Why is option C wrong?"
                            disabled={sending}
                            className="block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-slate-50"
                        />
                        <Button type="submit" loading={sending} disabled={message.trim().length === 0} className="shrink-0">
                            Ask
                        </Button>
                    </form>
                </div>
            )}
        </div>
    );
}

ClarificationBox.propTypes = {
    questionId: PropTypes.number.isRequired,
};
