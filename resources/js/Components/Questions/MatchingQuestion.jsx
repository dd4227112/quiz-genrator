import { useId } from 'react';
import { answerInputProps } from './propTypes';

export default function MatchingQuestion({ question, value, onChange, disabled, attempt, revealed, reveal }) {
    const baseId = useId();
    const left = question.payload?.left ?? [];
    const right = question.payload?.right ?? [];
    const rightText = Object.fromEntries(right.map((item) => [item.id, item.text]));
    const leftText = Object.fromEntries(left.map((item) => [item.id, item.text]));
    const pairs = value?.pairs ?? [];
    const chosen = Object.fromEntries(pairs.map((pair) => [pair.left, pair.right]));
    const correctPairs = revealed ? (reveal?.answer?.pairs ?? []) : [];
    const correct = Object.fromEntries(correctPairs.map((pair) => [pair.left, pair.right]));
    const graded = Boolean(attempt) && disabled;
    const knowCorrect = correctPairs.length > 0;

    const choose = (leftId, rightId) => {
        onChange({
            pairs: left.map((item) => ({
                left: item.id,
                right: item.id === leftId ? rightId : (chosen[item.id] ?? ''),
            })),
        });
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-5 md:grid-cols-[3fr_2fr]">
                <div>
                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Column A — choose a match</h4>
                    <ul className="space-y-2">
                        {left.map((item) => {
                            const selectId = `${baseId}-${item.id}`;
                            const pick = chosen[item.id] ?? '';
                            const rowState = graded && knowCorrect && pick ? (correct[item.id] === pick ? 'correct' : 'wrong') : null;

                            return (
                                <li
                                    key={item.id}
                                    className={`flex flex-col gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-inset sm:flex-row sm:items-center sm:justify-between ${
                                        rowState === 'correct'
                                            ? 'bg-emerald-50 ring-emerald-300'
                                            : rowState === 'wrong'
                                              ? 'bg-red-50 ring-red-300'
                                              : 'ring-slate-200'
                                    }`}
                                >
                                    <label htmlFor={selectId} className="flex gap-2 text-slate-800">
                                        <span className="font-semibold text-slate-500">{item.id}.</span>
                                        <span>{item.text}</span>
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <select
                                            id={selectId}
                                            value={pick}
                                            disabled={disabled}
                                            onChange={(e) => choose(item.id, e.target.value)}
                                            className="w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 disabled:bg-transparent disabled:text-slate-700 sm:w-auto"
                                        >
                                            <option value="">Choose…</option>
                                            {right.map((option) => {
                                                const usedElsewhere = Object.entries(chosen).some(
                                                    ([leftId, rightId]) => rightId === option.id && leftId !== item.id,
                                                );
                                                return (
                                                    <option key={option.id} value={option.id} disabled={usedElsewhere}>
                                                        {option.id}
                                                        {usedElsewhere ? ' (used)' : ''}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                        {rowState === 'correct' && <span className="text-emerald-700" aria-label="correct">✓</span>}
                                        {rowState === 'wrong' && (
                                            <span className="whitespace-nowrap text-xs font-semibold text-red-700">
                                                ✗ <span className="sr-only">incorrect, correct match is </span>
                                                {correct[item.id]}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
                <div>
                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Column B</h4>
                    <ul className="space-y-2">
                        {right.map((item) => (
                            <li key={item.id} className="flex gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-200">
                                <span className="font-semibold text-slate-500">{item.id}.</span>
                                <span>{item.text}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            {knowCorrect && (
                <div className="rounded-lg bg-emerald-50 p-4 ring-1 ring-inset ring-emerald-200">
                    <h4 className="text-sm font-semibold text-emerald-900">Correct matches</h4>
                    <ul className="mt-2 space-y-1.5 text-sm text-emerald-900">
                        {correctPairs.map((pair) => (
                            <li key={`${pair.left}-${pair.right}`} className="flex flex-wrap gap-x-2">
                                <span className="font-semibold">
                                    {pair.left} → {pair.right}
                                </span>
                                <span className="text-emerald-800">
                                    {leftText[pair.left]} — {rightText[pair.right]}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

MatchingQuestion.propTypes = answerInputProps;
