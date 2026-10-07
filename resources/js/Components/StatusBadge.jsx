import PropTypes from 'prop-types';
import Badge from './Badge';

const map = {
    pending: { color: 'yellow', label: 'Generating' },
    completed: { color: 'green', label: 'Completed' },
    failed: { color: 'red', label: 'Failed' },
};

export default function StatusBadge({ status }) {
    const { color, label } = map[status] || { color: 'gray', label: status };

    return <Badge color={color}>{label}</Badge>;
}

StatusBadge.propTypes = {
    status: PropTypes.string.isRequired,
};
