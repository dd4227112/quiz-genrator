import PropTypes from 'prop-types';
import { useId } from 'react';
import FieldError from './FieldError';
import { fieldClasses } from './Input';

export default function Select({ label, error, id, options, placeholder, className = '', ...props }) {
    const generatedId = useId();
    const inputId = id || generatedId;

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
                    {label}
                </label>
            )}
            <select
                id={inputId}
                className={fieldClasses(error)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${inputId}-error` : undefined}
                {...props}
            >
                {placeholder && (
                    <option value="" disabled>
                        {placeholder}
                    </option>
                )}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <FieldError id={`${inputId}-error`} message={error} />
        </div>
    );
}

Select.propTypes = {
    label: PropTypes.node,
    error: PropTypes.string,
    id: PropTypes.string,
    placeholder: PropTypes.string,
    className: PropTypes.string,
    options: PropTypes.arrayOf(
        PropTypes.shape({
            value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
            label: PropTypes.string.isRequired,
        }),
    ).isRequired,
};
