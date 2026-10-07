import PropTypes from 'prop-types';
import { Head, usePage } from '@inertiajs/react';
import Card from '../Components/Card';
import FlashMessages from './FlashMessages';

export default function GuestLayout({ title, subtitle, children }) {
    const { appName } = usePage().props;

    return (
        <div className="flex min-h-full flex-col justify-center px-4 py-12">
            <Head title={title} />
            <div className="mx-auto w-full max-w-md">
                <div className="mb-8 text-center">
                    <span className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-indigo-600 text-lg font-bold text-white" aria-hidden="true">
                        Q
                    </span>
                    <p className="mt-3 text-sm font-medium text-slate-500">{appName}</p>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{title}</h1>
                    {subtitle && <p className="mt-2 text-sm text-slate-600">{subtitle}</p>}
                </div>
                <div className="mb-4">
                    <FlashMessages />
                </div>
                <Card className="p-6 sm:p-8">{children}</Card>
            </div>
        </div>
    );
}

GuestLayout.propTypes = {
    title: PropTypes.string.isRequired,
    subtitle: PropTypes.node,
    children: PropTypes.node,
};
