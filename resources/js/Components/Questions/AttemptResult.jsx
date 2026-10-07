import PropTypes from 'prop-types';
import { attemptShape } from './propTypes';

export default function AttemptResult({ attempt, type }) {
    const partial = type === 'matching' && !attempt.is_correct && attempt.score > 0;
    const tone = attempt.is_correct ? 'correct' : partial ? 'partial' : 'wrong';
    const styles = {
        correct: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        partial: 'bg-amber-50 text-amber-900 ring-amber-200',
        wrong: 'bg-red-50 text-red-900 ring-red-200',
    };
    const title = { correct: 'Correct!', partial: 'Partly correct', wrong: 'Not quite' }[tone];

    return (
        <div role="status" className={`rounded-lg p-4 text-sm ring-1 ring-inset ${styles[tone]}`}>
            <p className="flex flex-wrap items-center gap-x-2 font-semibold">
                <span aria-hidden="true">{attempt.is_correct ? '✓' : '✗'}</span>
                {title}
                {(type === 'short_answer' || type === 'matching') && (
                    <span className="font-normal opacity-80">· Score {attempt.score}/100</span>
                )}
                {attempt.graded_by === 'ai' && <span className="text-xs font-normal opacity-70">(marked by AI)</span>}
            </p>
            {attempt.feedback && <p className="mt-1">{attempt.feedback}</p>}
        </div>
    );
}

AttemptResult.propTypes = {
    attempt: attemptShape.isRequired,
    type: PropTypes.string.isRequired,
};
