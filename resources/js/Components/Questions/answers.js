/**
 * Helpers shared by the question components for drafting and displaying answers.
 */

export function emptyDraft(question) {
    switch (question.type) {
        case 'true_false':
            return { value: null };
        case 'multiple_choice':
            return { key: null };
        case 'matching':
            return { pairs: (question.payload?.left ?? []).map((item) => ({ left: item.id, right: '' })) };
        case 'short_answer':
            return { text: '' };
        default:
            return {};
    }
}

export function isDraftComplete(question, draft) {
    switch (question.type) {
        case 'true_false':
            return typeof draft?.value === 'boolean';
        case 'multiple_choice':
            return Boolean(draft?.key);
        case 'matching':
            return (draft?.pairs ?? []).length > 0 && draft.pairs.every((pair) => pair.right);
        case 'short_answer':
            return (draft?.text ?? '').trim().length >= 2;
        default:
            return false;
    }
}

/**
 * Tailwind classes for a selectable choice (True/False button or MC option).
 *
 * - correct answer known (answer revealed) -> green
 * - the user's graded choice that was wrong -> red
 * - the user's graded choice that was right -> green
 * - currently selected while answering -> indigo
 */
export function choiceTone({ selected, isCorrectChoice, graded, gradedCorrect }) {
    if (isCorrectChoice) {
        return 'correct';
    }
    if (graded && selected) {
        return gradedCorrect ? 'correct' : 'wrong';
    }
    if (selected) {
        return 'selected';
    }
    return graded || isCorrectChoice === false ? 'muted' : 'idle';
}

export const toneClasses = {
    correct: 'bg-emerald-50 text-emerald-900 ring-emerald-400',
    wrong: 'bg-red-50 text-red-900 ring-red-400',
    selected: 'bg-indigo-50 text-indigo-900 ring-indigo-500 ring-2',
    muted: 'text-slate-500 ring-slate-200',
    idle: 'text-slate-700 ring-slate-300 hover:bg-slate-50',
};
