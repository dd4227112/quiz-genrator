import PropTypes from 'prop-types';
import axios from 'axios';
import { useState } from 'react';
import Alert from '../Alert';
import Badge from '../Badge';
import Button from '../Button';
import Card from '../Card';
import AttemptResult from './AttemptResult';
import ClarificationBox from './ClarificationBox';
import MatchingQuestion from './MatchingQuestion';
import MultipleChoiceQuestion from './MultipleChoiceQuestion';
import ShortAnswerQuestion from './ShortAnswerQuestion';
import TrueFalseQuestion from './TrueFalseQuestion';
import { emptyDraft, isDraftComplete } from './answers';
import { attemptShape, questionShape, revealShape } from './propTypes';
import { errorMessage } from '../../lib/errors';

const TYPE_COMPONENTS = {
    true_false: TrueFalseQuestion,
    multiple_choice: MultipleChoiceQuestion,
    matching: MatchingQuestion,
    short_answer: ShortAnswerQuestion,
};

const TYPE_COLORS = {
    true_false: 'blue',
    multiple_choice: 'indigo',
    matching: 'purple',
    short_answer: 'green',
};

export default function QuestionCard({ question, number, attempt, revealed, reveal, loading, error, onToggle, onGraded, onRetry }) {
    const Body = TYPE_COMPONENTS[question.type];
    const headingId = `question-${question.id}-heading`;
    const [draft, setDraft] = useState(() => attempt?.response ?? emptyDraft(question));
    const [answering, setAnswering] = useState(!attempt);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(null);
    const isAnswerRevealed = revealed && !!reveal;

    const submit = async () => {
        setSubmitting(true);
        setSubmitError(null);
        try {
            const { data } = await axios.post(route('questions.attempts.store', question.id), { response: draft });
            onGraded(question.id, data.attempt, { answer: data.answer, explanation: data.explanation });
            setAnswering(false);
        } catch (err) {
            setSubmitError(errorMessage(err, 'Could not check your answer. Please try again.'));
        } finally {
            setSubmitting(false);
        }
    };

    const tryAgain = () => {
        setDraft(emptyDraft(question));
        setAnswering(true);
        setSubmitError(null);
        onRetry(question.id);
    };

    return (
        <Card as="article" aria-labelledby={headingId} className="space-y-4 p-5 sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-semibold text-slate-500">Question {number}</span>
                <Badge color={TYPE_COLORS[question.type] ?? 'gray'}>{question.type_label ?? question.type}</Badge>
                {attempt && !answering && (
                    <Badge color={attempt.is_correct ? 'green' : 'red'}>{attempt.is_correct ? 'Correct' : 'Incorrect'}</Badge>
                )}
            </div>

            <h3 id={headingId} className="whitespace-pre-line text-base font-medium text-slate-900">
                {question.prompt}
            </h3>

            {Body ? (
                <Body
                    question={question}
                    value={draft}
                    onChange={setDraft}
                    disabled={!answering || submitting}
                    attempt={answering ? null : attempt}
                    revealed={isAnswerRevealed}
                    reveal={reveal}
                />
            ) : (
                <p className="text-sm text-slate-500">Unsupported question type.</p>
            )}

            {!answering && attempt && <AttemptResult attempt={attempt} type={question.type} />}

            {isAnswerRevealed && reveal?.explanation && (
                <div className="rounded-lg bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-inset ring-slate-200">
                    <span className="font-semibold text-slate-900">Explanation: </span>
                    {reveal.explanation}
                </div>
            )}

            {(submitError || error) && <Alert type="error">{submitError || error}</Alert>}

            <div className="flex flex-wrap items-center gap-2">
                {answering ? (
                    <Button
                        size="sm"
                        onClick={submit}
                        loading={submitting}
                        disabled={!isDraftComplete(question, draft)}
                        aria-label={`Check answer for question ${number}`}
                    >
                        {submitting && question.type === 'short_answer' ? 'Marking…' : 'Check answer'}
                    </Button>
                ) : (
                    <Button size="sm" variant="secondary" onClick={tryAgain} aria-label={`Try question ${number} again`}>
                        Try again
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onToggle}
                    loading={loading}
                    aria-expanded={isAnswerRevealed}
                    aria-label={`${isAnswerRevealed ? 'Hide' : 'Show'} answer for question ${number}`}
                >
                    {isAnswerRevealed ? 'Hide answer' : 'Show answer'}
                </Button>
                {submitting && question.type === 'short_answer' && (
                    <span className="text-xs text-slate-500">The AI is reading your answer…</span>
                )}
            </div>

            <ClarificationBox questionId={question.id} />
        </Card>
    );
}

QuestionCard.propTypes = {
    question: questionShape.isRequired,
    number: PropTypes.number.isRequired,
    attempt: attemptShape,
    revealed: PropTypes.bool,
    reveal: revealShape,
    loading: PropTypes.bool,
    error: PropTypes.string,
    onToggle: PropTypes.func.isRequired,
    onGraded: PropTypes.func.isRequired,
    onRetry: PropTypes.func.isRequired,
};
