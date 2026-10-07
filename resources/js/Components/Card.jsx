import PropTypes from 'prop-types';

export default function Card({ as: Tag = 'div', className = '', children, ...props }) {
    return (
        <Tag className={`rounded-xl bg-white shadow-sm ring-1 ring-slate-200 ${className}`} {...props}>
            {children}
        </Tag>
    );
}

Card.propTypes = {
    as: PropTypes.elementType,
    className: PropTypes.string,
    children: PropTypes.node,
};
