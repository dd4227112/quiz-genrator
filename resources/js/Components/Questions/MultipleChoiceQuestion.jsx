import { answerInputProps } from './propTypes';
import { choiceTone, toneClasses } from './answers';

export default function MultipleChoiceQuestion({ question, value, onChange, disabled, attempt, revealed, reveal }) {
    const options = question.payload?.options ?? [];
    const correctKey = revealed ? reveal?.answer?.key : undefined;
    const graded = Boolean(attempt) && disabled;
    const name = `mc-${question.id}`;

    return (
        <fieldset disabled={disabled}>
            <legend className="sr-only">Choose one option</legend>
            <div className="grid gap-2">
                {options.map((option) => {
                    const selected = value?.key === option.key;
                    const tone = choiceTone({
                        selected,
                        isCorrectChoice: correctKey === undefined ? undefined : correctKey === option.key,
                        graded,
                        gradedCorrect: attempt?.is_correct,
                    });

                    return (
                        <label
                            key={option.key}
                            className={`flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 text-sm ring-1 ring-inset transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-indigo-600 has-[:disabled]:cursor-default ${toneClasses[tone]}`}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.key}
                                className="sr-only"
                                checked={selected}
                                onChange={() => onChange({ key: option.key })}
                            />
                            <span
                                className={`grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-semibold ${
                                    tone === 'correct'
                                        ? 'bg-emerald-600 text-white'
                                        : tone === 'wrong'
                                          ? 'bg-red-600 text-white'
                                          : tone === 'selected'
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-slate-100 text-slate-600'
                                }`}
                                aria-hidden="true"
                            >
                                {option.key}
                            </span>
                            <span className="pt-0.5">
                                <span className="sr-only">Option {option.key}: </span>
                                {option.text}
                                {tone === 'correct' && <span className="ml-2 text-xs font-semibold text-emerald-700">✓ Correct</span>}
                                {tone === 'wrong' && <span className="ml-2 text-xs font-semibold text-red-700">✗ Your answer</span>}
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}

MultipleChoiceQuestion.propTypes = answerInputProps;
