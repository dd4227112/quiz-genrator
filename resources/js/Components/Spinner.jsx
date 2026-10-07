import PropTypes from 'prop-types';

const sizes = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-10 w-10' };

export default function Spinner({ size = 'md', className = 'text-indigo-600', label = 'Loading' }) {
    return (
        <span role={label ? 'status' : undefined} className="inline-flex items-center">
            <svg className={`animate-spin ${sizes[size]} ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            {label && <span className="sr-only">{label}</span>}
        </span>
    );
}

Spinner.propTypes = {
    size: PropTypes.oneOf(Object.keys(sizes)),
    className: PropTypes.string,
    label: PropTypes.string,
};
