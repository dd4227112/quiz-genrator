import PropTypes from 'prop-types';
import axios from 'axios';
import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Alert from '../../Components/Alert';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import Spinner from '../../Components/Spinner';
import StatusBadge from '../../Components/StatusBadge';
import QuestionCard from '../../Components/Questions/QuestionCard';
import { questionShape } from '../../Components/Questions/propTypes';
import AppLayout from '../../Layouts/AppLayout';
import { errorMessage } from '../../lib/errors';
import { formatDate, pluralize } from '../../lib/format';

const POLL_INTERVAL_MS = 3000;

function usePollStatus(questionSet) {
    const [pollError, setPollError] = useState(null);
    const failures = useRef(0);

    useEffect(() => {
        if (questionSet.status !== 'pending') {
            return undefined;
        }

        let stopped = false;
        const interval = setInterval(async () => {
            try {
                const { data } = await axios.get(route('question-sets.status', questionSet.id));
                failures.current = 0;
                setPollError(null);

                if (data.status !== 'pending' && !stopped) {
                    stopped = true;
                    clearInterval(interval);
                    router.reload({ only: ['questionSet', 'flash'] });
                }
            } catch {
                failures.current += 1;
                if (failures.current >= 3) {
                    setPollError('Having trouble checking progress. We will keep trying…');
                }
            }
        }, POLL_INTERVAL_MS);

        return () => {
            stopped = true;
            clearInterval(interval);
        };
    }, [questionSet.id, questionSet.status]);

    return pollError;
}

function useAnswers(questions) {
    const [answers, setAnswers] = useState({});
    const [revealed, setRevealed] = useState({});
    const [loading, setLoading] = useState({});
    const [errors, setErrors] = useState({});

    const fetchAnswer = useCallback(
        async (id) => {
            if (answers[id]) {
                return answers[id];
            }

            setLoading((s) => ({ ...s, [id]: true }));
            setErrors((s) => ({ ...s, [id]: null }));

            try {
                const { data } = await axios.get(route('questions.answer', id));
                const value = { answer: data.answer, explanation: data.explanation };
                setAnswers((s) => ({ ...s, [id]: value }));
                return value;
            } catch (err) {
                setErrors((s) => ({ ...s, [id]: errorMessage(err, 'Could not load the answer. Please try again.') }));
                return null;
            } finally {
                setLoading((s) => ({ ...s, [id]: false }));
            }
        },
        [answers],
    );

    const toggle = async (id) => {
        if (revealed[id]) {
            setRevealed((s) => ({ ...s, [id]: false }));
            return;
        }

        if (await fetchAnswer(id)) {
            setRevealed((s) => ({ ...s, [id]: true }));
        }
    };

    // Store an answer received from elsewhere (e.g. after grading) and reveal it.
    const provide = (id, value) => {
        setAnswers((s) => ({ ...s, [id]: value }));
        setRevealed((s) => ({ ...s, [id]: true }));
    };

    const hide = (id) => setRevealed((s) => ({ ...s, [id]: false }));

    const allRevealed = questions.length > 0 && questions.every((q) => revealed[q.id]);

    const toggleAll = async () => {
        if (allRevealed) {
            setRevealed({});
            return;
        }

        const results = await Promise.all(questions.map((q) => fetchAnswer(q.id).then((value) => [q.id, !!value])));
        setRevealed(Object.fromEntries(results));
    };

    const anyLoading = Object.values(loading).some(Boolean);

    return { answers, revealed, loading, errors, toggle, toggleAll, allRevealed, anyLoading, provide, hide };
}

export default function Show({ questionSet }) {
    const questions = questionSet.questions ?? [];
    const pollError = usePollStatus(questionSet);
    const { answers, revealed, loading, errors, toggle, toggleAll, allRevealed, anyLoading, provide, hide } = useAnswers(questions);
    const [attempts, setAttempts] = useState(() =>
        Object.fromEntries(questions.filter((q) => q.latest_attempt).map((q) => [q.id, q.latest_attempt])),
    );
    const answeredCount = Object.keys(attempts).length;
    const correctCount = Object.values(attempts).filter((a) => a.is_correct).length;

    const handleGraded = (id, attempt, reveal) => {
        setAttempts((s) => ({ ...s, [id]: attempt }));
        provide(id, reveal);
    };
    const [retrying, setRetrying] = useState(false);

    const retry = () => {
        router.post(route('question-sets.regenerate', questionSet.id), {}, {
            onStart: () => setRetrying(true),
            onFinish: () => setRetrying(false),
            onHttpException: (response) => {
                if (response?.status === 429) {
                    alert(response?.data?.message || 'You have reached the hourly generation limit. Please try again later.');
                    return false;
                }
                return undefined;
            },
        });
    };

    return (
        <AppLayout title={questionSet.topic}>
            <Card className="p-5 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={questionSet.status} />
                            <span className="text-sm text-slate-500">{formatDate(questionSet.created_at)}</span>
                        </div>
                        <h1 className="mt-2 break-words text-2xl font-bold tracking-tight text-slate-900">{questionSet.topic}</h1>
                        <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                            <div className="flex gap-1.5">
                                <dt className="font-medium text-slate-500">Level:</dt>
                                <dd>{questionSet.level?.name}</dd>
                            </div>
                            <div className="flex gap-1.5">
                                <dt className="font-medium text-slate-500">Questions:</dt>
                                <dd>
                                    {questionSet.status === 'completed'
                                        ? `${questions.length} of ${questionSet.requested_count}`
                                        : questionSet.requested_count}
                                </dd>
                            </div>
                            {questionSet.status === 'completed' && questions.length > 0 && (
                                <div className="flex gap-1.5">
                                    <dt className="font-medium text-slate-500">Your score:</dt>
                                    <dd aria-live="polite">
                                        {answeredCount === 0
                                            ? 'Not answered yet'
                                            : `${correctCount} correct of ${answeredCount} answered`}
                                    </dd>
                                </div>
                            )}
                            <div className="flex flex-wrap items-center gap-1.5">
                                <dt className="font-medium text-slate-500">Types:</dt>
                                <dd className="flex flex-wrap gap-1">
                                    {questionSet.question_types.map((type) => (
                                        <Badge key={type.value}>{type.label}</Badge>
                                    ))}
                                </dd>
                            </div>
                        </dl>
                        {questionSet.additional_notes && (
                            <p className="mt-3 text-sm text-slate-600">
                                <span className="font-medium text-slate-500">Notes:</span> {questionSet.additional_notes}
                            </p>
                        )}
                    </div>

                    {questionSet.status === 'completed' && questions.length > 0 && (
                        <Button variant="secondary" onClick={toggleAll} loading={anyLoading && !allRevealed} className="shrink-0">
                            {allRevealed ? 'Hide all answers' : 'Show all answers'}
                        </Button>
                    )}
                </div>
            </Card>

            {questionSet.status === 'pending' && (
                <Card className="flex flex-col items-center gap-4 px-6 py-16 text-center" aria-live="polite">
                    <Spinner size="lg" label="Generating questions" />
                    <div>
                        <p className="font-semibold text-slate-900">Generating {pluralize(questionSet.requested_count, 'question')}…</p>
                        <p className="mt-1 text-sm text-slate-500">
                            This usually takes under a minute. You can leave this page and come back from History.
                        </p>
                    </div>
                    {pollError && <p className="text-sm text-amber-700">{pollError}</p>}
                </Card>
            )}

            {questionSet.status === 'failed' && (
                <Alert type="error" title="We couldn't generate these questions">
                    <p>{questionSet.error_message || 'Something went wrong. Please try again.'}</p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <Button variant="danger" onClick={retry} loading={retrying}>
                            Try again
                        </Button>
                        <Button as="link" variant="secondary" href={route('question-sets.create')}>
                            Change settings
                        </Button>
                    </div>
                </Alert>
            )}

            {questionSet.status === 'completed' && (
                <>
                    {questionSet.error_message && <Alert type="warning">{questionSet.error_message}</Alert>}

                    {questions.length === 0 ? (
                        <Card className="px-6 py-12 text-center text-sm text-slate-500">No questions were saved for this set.</Card>
                    ) : (
                        <ol className="space-y-4" aria-label="Questions">
                            {questions.map((question, index) => (
                                <li key={question.id}>
                                    <QuestionCard
                                        question={question}
                                        number={index + 1}
                                        attempt={attempts[question.id]}
                                        onGraded={handleGraded}
                                        onRetry={hide}
                                        revealed={!!revealed[question.id]}
                                        reveal={answers[question.id]}
                                        loading={!!loading[question.id]}
                                        error={errors[question.id]}
                                        onToggle={() => toggle(question.id)}
                                    />
                                </li>
                            ))}
                        </ol>
                    )}

                    <div className="flex justify-center gap-3 pt-2">
                        <Button as="link" variant="secondary" href={route('question-sets.index')}>
                            Back to history
                        </Button>
                        <Button as="link" href={route('question-sets.create')}>
                            Generate more
                        </Button>
                    </div>
                </>
            )}

            {questionSet.status !== 'completed' && (
                <p className="text-center text-sm">
                    <Link href={route('question-sets.index')} className="font-medium text-indigo-600 hover:text-indigo-500">
                        ← Back to history
                    </Link>
                </p>
            )}
        </AppLayout>
    );
}

Show.propTypes = {
    questionSet: PropTypes.shape({
        id: PropTypes.number.isRequired,
        topic: PropTypes.string.isRequired,
        level: PropTypes.shape({ name: PropTypes.string }),
        question_types: PropTypes.arrayOf(PropTypes.shape({ value: PropTypes.string, label: PropTypes.string })).isRequired,
        requested_count: PropTypes.number.isRequired,
        additional_notes: PropTypes.string,
        status: PropTypes.oneOf(['pending', 'completed', 'failed']).isRequired,
        error_message: PropTypes.string,
        created_at: PropTypes.string,
        questions: PropTypes.arrayOf(questionShape),
    }).isRequired,
};
