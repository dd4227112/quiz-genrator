import { useId } from 'react';
import { answerInputProps } from './propTypes';

const MAX_LENGTH = 5000;

export default function ShortAnswerQuestion({ value, onChange, disabled, revealed, reveal }) {
    const id = useId();
    const text = value?.text ?? '';
    const answer = revealed ? reveal?.answer : null;

    return (
        <div className="space-y-4">
            <div>
                <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700">
                    Your answer
                </label>
                <textarea
                    id={id}
                    rows={4}
                    maxLength={MAX_LENGTH}
                    value={text}
                    disabled={disabled}
                    onChange={(e) => onChange({ text: e.target.value })}
                    placeholder="Write your answer as you would in an interview. The AI will mark it."
                    className="block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-slate-50 disabled:text-slate-700"
                />
                {!disabled && (
                    <p className="mt-1 text-right text-xs text-slate-400">
                        {text.length}/{MAX_LENGTH}
                    </p>
                )}
            </div>

            {answer && (
                <div className="rounded-lg bg-emerald-50 p-4 ring-1 ring-inset ring-emerald-200">
                    <h4 className="text-sm font-semibold text-emerald-900">Model answer</h4>
                    <p className="mt-1 whitespace-pre-line text-sm text-emerald-900">{answer.text}</p>
                    {answer.key_points?.length > 0 && (
                        <>
                            <h5 className="mt-3 text-xs font-semibold uppercase tracking-wide text-emerald-800">Key points</h5>
                            <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-emerald-900">
                                {answer.key_points.map((point, index) => (
                                    <li key={index}>{point}</li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

ShortAnswerQuestion.propTypes = answerInputProps;
