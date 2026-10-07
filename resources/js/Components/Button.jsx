import PropTypes from 'prop-types';
import { Link } from '@inertiajs/react';
import Spinner from './Spinner';

const variants = {
    primary: 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 disabled:bg-indigo-400',
    secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-indigo-600 disabled:text-slate-400',
    danger: 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600 disabled:bg-red-400',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-indigo-600 disabled:text-slate-400',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3.5 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
};

export default function Button({
    as = 'button',
    href,
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled = false,
    className = '',
    children,
    type = 'button',
    ...props
}) {
    const classes = [
        'inline-flex items-center justify-center gap-2 rounded-md font-semibold shadow-sm transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed',
        variants[variant],
        sizes[size],
        className,
    ].join(' ');

    if (as === 'link') {
        return (
            <Link href={href} className={classes} {...props}>
                {children}
            </Link>
        );
    }

    return (
        <button type={type} className={classes} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
            {loading && <Spinner size="sm" className="text-current" label="" />}
            {children}
        </button>
    );
}

Button.propTypes = {
    as: PropTypes.oneOf(['button', 'link']),
    href: PropTypes.string,
    variant: PropTypes.oneOf(Object.keys(variants)),
    size: PropTypes.oneOf(Object.keys(sizes)),
    loading: PropTypes.bool,
    disabled: PropTypes.bool,
    className: PropTypes.string,
    type: PropTypes.string,
    children: PropTypes.node,
};
