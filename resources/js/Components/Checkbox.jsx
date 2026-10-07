import PropTypes from 'prop-types';
import { useId } from 'react';

export default function Checkbox({ label, description, id, className = '', ...props }) {
    const generatedId = useId();
    const inputId = id || generatedId;

    return (
        <div className={`relative flex items-start gap-3 ${className}`}>
            <div className="flex h-6 items-center">
                <input
                    id={inputId}
                    type="checkbox"
                    className="h-4 w-4 rounded border-slate-300 text-indigo-600 accent-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    aria-describedby={description ? `${inputId}-description` : undefined}
                    {...props}
                />
            </div>
            <div className="text-sm leading-6">
                <label htmlFor={inputId} className="font-medium text-slate-800">
                    {label}
                </label>
                {description && (
                    <p id={`${inputId}-description`} className="text-slate-500">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

Checkbox.propTypes = {
    label: PropTypes.node.isRequired,
    description: PropTypes.node,
    id: PropTypes.string,
    className: PropTypes.string,
};
