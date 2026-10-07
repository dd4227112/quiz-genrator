import PropTypes from 'prop-types';
import { useId } from 'react';
import FieldError from './FieldError';
import { fieldClasses } from './Input';

export default function Textarea({ label, error, hint, id, className = '', rows = 3, ...props }) {
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
            <textarea
                id={inputId}
                rows={rows}
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

Textarea.propTypes = {
    label: PropTypes.node,
    error: PropTypes.string,
    hint: PropTypes.node,
    id: PropTypes.string,
    className: PropTypes.string,
    rows: PropTypes.number,
};
