import PropTypes from 'prop-types';
import { useId } from 'react';
import FieldError from './FieldError';

export const fieldClasses = (error) =>
    [
        'block w-full rounded-md border-0 px-3 py-2 text-slate-900 shadow-sm ring-1 ring-inset placeholder:text-slate-400',
        'focus:ring-2 focus:ring-inset sm:text-sm disabled:bg-slate-50 disabled:text-slate-500',
        error ? 'ring-red-400 focus:ring-red-500' : 'ring-slate-300 focus:ring-indigo-600',
    ].join(' ');

export default function Input({ label, error, hint, id, className = '', ...props }) {
    const generatedId = useId();
    const inputId = id || generatedId;
    const describedBy = [error && `${inputId}-error`, hint && `${inputId}-hint`].filter(Boolean).join(' ') || undefined;

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
                    {label}
                </label>
            )}
            <input
                id={inputId}
                className={fieldClasses(error)}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                {...props}
            />
            {hint && !error && (
                <p id={`${inputId}-hint`} className="mt-1.5 text-xs text-slate-500">
                    {hint}
                </p>
            )}
            <FieldError id={`${inputId}-error`} message={error} />
        </div>
    );
}

Input.propTypes = {
    label: PropTypes.node,
    error: PropTypes.string,
    hint: PropTypes.node,
    id: PropTypes.string,
    className: PropTypes.string,
};
