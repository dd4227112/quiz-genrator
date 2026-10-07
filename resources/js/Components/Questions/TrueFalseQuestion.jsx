import { answerInputProps } from './propTypes';
import { choiceTone, toneClasses } from './answers';

export default function TrueFalseQuestion({ question, value, onChange, disabled, attempt, revealed, reveal }) {
    const correctValue = revealed ? reveal?.answer?.value : undefined;
    const graded = Boolean(attempt) && disabled;
    const name = `tf-${question.id}`;

    return (
        <fieldset disabled={disabled}>
            <legend className="sr-only">Choose True or False</legend>
            <div className="flex gap-3">
                {[true, false].map((option) => {
                    const selected = value?.value === option;
                    const tone = choiceTone({
                        selected,
                        isCorrectChoice: correctValue === undefined ? undefined : correctValue === option,
                        graded,
                        gradedCorrect: attempt?.is_correct,
                    });

                    return (
                        <label
                            key={String(option)}
                            className={`flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium ring-1 ring-inset transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-indigo-600 has-[:disabled]:cursor-default sm:flex-none sm:px-8 ${toneClasses[tone]}`}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={String(option)}
                                className="sr-only"
                                checked={selected}
                                onChange={() => onChange({ value: option })}
                            />
                            {option ? 'True' : 'False'}
                            {tone === 'correct' && <span aria-label="correct">✓</span>}
                            {tone === 'wrong' && <span aria-label="incorrect">✗</span>}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}

TrueFalseQuestion.propTypes = answerInputProps;
