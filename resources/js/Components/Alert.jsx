import PropTypes from 'prop-types';

const styles = {
    success: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    error: 'bg-red-50 text-red-800 ring-red-200',
    warning: 'bg-amber-50 text-amber-900 ring-amber-200',
    info: 'bg-sky-50 text-sky-800 ring-sky-200',
};

export default function Alert({ type = 'info', title, children, onDismiss, className = '' }) {
    return (
        <div
            role={type === 'error' ? 'alert' : 'status'}
            className={`flex items-start gap-3 rounded-lg p-4 text-sm ring-1 ring-inset ${styles[type]} ${className}`}
        >
            <div className="flex-1">
                {title && <p className="font-semibold">{title}</p>}
                {children && <div className={title ? 'mt-1' : ''}>{children}</div>}
            </div>
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    className="-m-1 rounded p-1 opacity-70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current"
                    aria-label="Dismiss message"
                >
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            )}
        </div>
    );
}

Alert.propTypes = {
    type: PropTypes.oneOf(Object.keys(styles)),
    title: PropTypes.node,
    children: PropTypes.node,
    onDismiss: PropTypes.func,
    className: PropTypes.string,
};
