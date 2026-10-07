import PropTypes from 'prop-types';

const colors = {
    gray: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    yellow: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/20',
    blue: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    purple: 'bg-violet-50 text-violet-700 ring-violet-600/20',
};

export default function Badge({ color = 'gray', className = '', children }) {
    return (
        <span
            className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${colors[color]} ${className}`}
        >
            {children}
        </span>
    );
}

Badge.propTypes = {
    color: PropTypes.oneOf(Object.keys(colors)),
    className: PropTypes.string,
    children: PropTypes.node,
};
