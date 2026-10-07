import PropTypes from 'prop-types';
import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Alert from '../../Components/Alert';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import Checkbox from '../../Components/Checkbox';
import FieldError from '../../Components/FieldError';
import Input from '../../Components/Input';
import MultiSelectCombobox from '../../Components/MultiSelectCombobox';
import Select from '../../Components/Select';
import Textarea from '../../Components/Textarea';
import AppLayout from '../../Layouts/AppLayout';

const TYPE_DESCRIPTIONS = {
    true_false: 'Quick checks of facts and concepts.',
    multiple_choice: 'Four options, one correct answer.',
    matching: 'Pair related items across two columns.',
    short_answer: 'Open responses with a model answer.',
};

export default function Create({ levels, topics, maxTopics, questionTypes, maxCount }) {
    const [requestError, setRequestError] = useState(null);
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        topic_ids: [],
        level_id: levels.find((level) => level.name === 'Intermediate')?.id ?? levels[0]?.id ?? '',
        question_types: questionTypes.map((type) => type.value),
        count: Math.min(10, maxCount),
        additional_notes: '',
    });

    const toggleType = (value, checked) => {
        clearErrors('question_types');
        setData(
            'question_types',
            checked ? [...data.question_types, value] : data.question_types.filter((type) => type !== value),
        );
    };

    const submit = (event) => {
        event.preventDefault();
        setRequestError(null);

        post(route('question-sets.store'), {
            onHttpException: (response) => {
                const message = response?.data?.message;
                setRequestError(
                    response?.status === 429
                        ? message || 'You have reached the hourly generation limit. Please try again later.'
                        : message || 'Something went wrong. Please try again.',
                );
                return false;
            },
            onNetworkError: () => {
                setRequestError('Network error. Check your connection and try again.');
                return false;
            },
        });
    };

    const topicOptions = useMemo(
        () => topics.map((topic) => ({ value: topic.id, label: topic.name, group: topic.category, rank: topic.rank })),
        [topics],
    );
    const topicsError = errors.topic_ids || Object.entries(errors).find(([key]) => key.startsWith('topic_ids.'))?.[1];
    const typesError = errors.question_types || Object.entries(errors).find(([key]) => key.startsWith('question_types.'))?.[1];
    const noTypesSelected = data.question_types.length === 0;

    return (
        <AppLayout title="New questions">
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-slate-900">Generate interview questions</h1>
                <p className="mt-1 text-sm text-slate-600">
                    Choose a profession, course, or program, then the level and question types, and we&apos;ll create questions with
                    answers you can reveal one by one.
                </p>
            </div>

            {requestError && (
                <Alert type="error" onDismiss={() => setRequestError(null)}>
                    {requestError}
                </Alert>
            )}

            <Card as="form" onSubmit={submit} className="space-y-6 p-6 sm:p-8" noValidate>
                <MultiSelectCombobox
                    label="Profession, course, or program"
                    options={topicOptions}
                    value={data.topic_ids}
                    onChange={(ids) => {
                        clearErrors('topic_ids');
                        setData('topic_ids', ids);
                    }}
                    max={maxTopics}
                    placeholder="Search, e.g. nurse, Laravel, accountant, CCNA…"
                    hint={`Pick from the list (up to ${maxTopics}). Combine fields for mixed questions, e.g. Laravel Developer + Database Administrator.`}
                    error={topicsError}
                />

                <div className="grid gap-6 sm:grid-cols-2">
                    <Select
                        label="Level"
                        name="level_id"
                        value={data.level_id}
                        onChange={(e) => setData('level_id', Number(e.target.value))}
                        options={levels.map((level) => ({ value: level.id, label: level.name }))}
                        error={errors.level_id}
                    />
                    <Input
                        label="Number of questions"
                        name="count"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={maxCount}
                        required
                        value={data.count}
                        onChange={(e) => setData('count', e.target.value === '' ? '' : Number(e.target.value))}
                        hint={`Between 1 and ${maxCount}.`}
                        error={errors.count}
                    />
                </div>

                <fieldset aria-describedby={typesError ? 'question-types-error' : undefined}>
                    <legend className="text-sm font-medium text-slate-700">Question types</legend>
                    <p className="text-xs text-slate-500">Choose at least one. Questions are split evenly across the selected types.</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        {questionTypes.map((type) => (
                            <Checkbox
                                key={type.value}
                                name="question_types[]"
                                value={type.value}
                                label={type.label}
                                description={TYPE_DESCRIPTIONS[type.value]}
                                checked={data.question_types.includes(type.value)}
                                onChange={(e) => toggleType(type.value, e.target.checked)}
                                className="rounded-lg p-3 ring-1 ring-slate-200 has-[:checked]:bg-indigo-50/50 has-[:checked]:ring-indigo-300"
                            />
                        ))}
                    </div>
                    <FieldError id="question-types-error" message={typesError || (noTypesSelected ? 'Select at least one question type.' : null)} />
                </fieldset>

                <Textarea
                    label={
                        <>
                            Additional notes <span className="font-normal text-slate-400">(optional)</span>
                        </>
                    }
                    name="additional_notes"
                    placeholder="e.g. Focus on REST APIs and testing"
                    maxLength={500}
                    value={data.additional_notes}
                    onChange={(e) => setData('additional_notes', e.target.value)}
                    hint={`${data.additional_notes.length}/500 characters`}
                    error={errors.additional_notes}
                />

                <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-end">
                    {processing && <p className="text-sm text-slate-500">Queuing your request…</p>}
                    <Button type="submit" size="lg" loading={processing} disabled={noTypesSelected || data.topic_ids.length === 0}>
                        Generate questions
                    </Button>
                </div>
            </Card>
        </AppLayout>
    );
}

Create.propTypes = {
    topics: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            name: PropTypes.string.isRequired,
            category: PropTypes.string,
            rank: PropTypes.number,
        }),
    ).isRequired,
    maxTopics: PropTypes.number.isRequired,
    levels: PropTypes.arrayOf(PropTypes.shape({ id: PropTypes.number.isRequired, name: PropTypes.string.isRequired })).isRequired,
    questionTypes: PropTypes.arrayOf(PropTypes.shape({ value: PropTypes.string.isRequired, label: PropTypes.string.isRequired }))
        .isRequired,
    maxCount: PropTypes.number.isRequired,
};
