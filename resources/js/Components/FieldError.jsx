import PropTypes from 'prop-types';

export default function FieldError({ id, message }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className="mt-1.5 text-sm text-red-600" role="alert">
            {message}
        </p>
    );
}

FieldError.propTypes = {
    id: PropTypes.string,
    message: PropTypes.string,
};
