import PropTypes from 'prop-types';
import { Link } from '@inertiajs/react';

function decode(label) {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

export default function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="flex flex-wrap items-center justify-center gap-1">
            {links.map((link, index) => {
                const label = decode(link.label);
                const base = 'min-w-9 rounded-md px-3 py-1.5 text-sm text-center';

                if (!link.url) {
                    return (
                        <span key={index} className={`${base} text-slate-400`} aria-disabled="true">
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={`${base} ${
                            link.active
                                ? 'bg-indigo-600 font-semibold text-white'
                                : 'text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                        } focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600`}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}

Pagination.propTypes = {
    links: PropTypes.arrayOf(
        PropTypes.shape({
            url: PropTypes.string,
            label: PropTypes.string.isRequired,
            active: PropTypes.bool,
        }),
    ),
};
