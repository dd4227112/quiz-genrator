import PropTypes from 'prop-types';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import Pagination from '../../Components/Pagination';
import StatusBadge from '../../Components/StatusBadge';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate, pluralize } from '../../lib/format';

export default function Index({ questionSets }) {
    const [deletingId, setDeletingId] = useState(null);
    const sets = questionSets.data;

    const destroy = (set) => {
        if (!window.confirm(`Delete "${set.topic}" and all of its questions? This cannot be undone.`)) {
            return;
        }

        router.delete(route('question-sets.destroy', set.id), {
            preserveScroll: true,
            onStart: () => setDeletingId(set.id),
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <AppLayout title="History">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900">History</h1>
                    <p className="mt-1 text-sm text-slate-600">All the question sets you have generated.</p>
                </div>
                <Button as="link" href={route('question-sets.create')}>
                    New questions
                </Button>
            </div>

            {sets.length === 0 ? (
                <Card className="px-6 py-16 text-center">
                    <p className="font-semibold text-slate-900">No question sets yet</p>
                    <p className="mt-1 text-sm text-slate-500">Generate your first set to see it here.</p>
                    <Button as="link" href={route('question-sets.create')} className="mt-6">
                        Generate questions
                    </Button>
                </Card>
            ) : (
                <Card as="ul" className="divide-y divide-slate-100">
                    {sets.map((set) => (
                        <li key={set.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={route('question-sets.show', set.id)}
                                        className="truncate font-semibold text-slate-900 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    >
                                        {set.topic}
                                    </Link>
                                    <StatusBadge status={set.status} />
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    {set.level?.name} ·{' '}
                                    {set.status === 'completed'
                                        ? pluralize(set.questions_count ?? 0, 'question')
                                        : `${pluralize(set.requested_count, 'question')} requested`}{' '}
                                    · {set.question_types.map((type) => type.label).join(', ')}
                                </p>
                                <p className="mt-0.5 text-xs text-slate-400">{formatDate(set.created_at)}</p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button as="link" variant="secondary" size="sm" href={route('question-sets.show', set.id)}>
                                    View<span className="sr-only"> {set.topic}</span>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                    onClick={() => destroy(set)}
                                    loading={deletingId === set.id}
                                >
                                    Delete<span className="sr-only"> {set.topic}</span>
                                </Button>
                            </div>
                        </li>
                    ))}
                </Card>
            )}

            <Pagination links={questionSets.links} />
        </AppLayout>
    );
}

Index.propTypes = {
    questionSets: PropTypes.shape({
        data: PropTypes.arrayOf(
            PropTypes.shape({
                id: PropTypes.number.isRequired,
                topic: PropTypes.string.isRequired,
                status: PropTypes.string.isRequired,
                requested_count: PropTypes.number,
                questions_count: PropTypes.number,
                created_at: PropTypes.string,
                level: PropTypes.shape({ name: PropTypes.string }),
                question_types: PropTypes.arrayOf(PropTypes.shape({ label: PropTypes.string })),
            }),
        ).isRequired,
        links: PropTypes.array,
    }).isRequired,
};
