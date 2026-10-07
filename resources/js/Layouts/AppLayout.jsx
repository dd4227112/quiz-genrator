import PropTypes from 'prop-types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FlashMessages from './FlashMessages';

function NavLink({ href, active, children, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={`rounded-md px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ${
                active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            }`}
        >
            {children}
        </Link>
    );
}

NavLink.propTypes = {
    href: PropTypes.string.isRequired,
    active: PropTypes.bool,
    children: PropTypes.node,
    onClick: PropTypes.func,
};

export default function AppLayout({ title, children }) {
    const { auth, appName } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const close = () => setMenuOpen(false);

    const links = (
        <>
            <NavLink href={route('question-sets.create')} active={route().current('question-sets.create')} onClick={close}>
                New questions
            </NavLink>
            <NavLink
                href={route('question-sets.index')}
                active={route().current('question-sets.index') || route().current('question-sets.show')}
                onClick={close}
            >
                History
            </NavLink>
        </>
    );

    return (
        <div className="min-h-full">
            <Head title={title} />
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow"
            >
                Skip to content
            </a>
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                    <div className="flex items-center gap-6">
                        <Link
                            href={route('question-sets.create')}
                            className="flex items-center gap-2 font-semibold text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-600"
                        >
                            <span className="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white" aria-hidden="true">
                                Q
                            </span>
                            <span className="hidden sm:inline">{appName}</span>
                        </Link>
                        <nav aria-label="Main" className="hidden items-center gap-1 md:flex">
                            {links}
                        </nav>
                    </div>

                    <div className="hidden items-center gap-3 md:flex">
                        <span className="text-sm text-slate-600">{auth.user?.name}</span>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        >
                            Log out
                        </Link>
                    </div>

                    <button
                        type="button"
                        className="rounded-md p-2 text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-600 md:hidden"
                        aria-expanded={menuOpen}
                        aria-controls="mobile-menu"
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        <span className="sr-only">{menuOpen ? 'Close menu' : 'Open menu'}</span>
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" aria-hidden="true">
                            {menuOpen ? (
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            ) : (
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            )}
                        </svg>
                    </button>
                </div>

                {menuOpen && (
                    <div id="mobile-menu" className="border-t border-slate-200 px-4 py-3 md:hidden">
                        <nav aria-label="Mobile" className="flex flex-col gap-1">
                            {links}
                        </nav>
                        <div className="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                            <span className="text-sm text-slate-600">{auth.user?.name}</span>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
                            >
                                Log out
                            </Link>
                        </div>
                    </div>
                )}
            </header>

            <main id="main" className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
                <FlashMessages />
                {children}
            </main>
        </div>
    );
}

AppLayout.propTypes = {
    title: PropTypes.string,
    children: PropTypes.node,
};
