import PropTypes from 'prop-types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../Components/Alert';

function DismissibleAlert({ type, message }) {
    const [visible, setVisible] = useState(true);

    if (!message || !visible) {
        return null;
    }

    return (
        <Alert type={type} onDismiss={() => setVisible(false)}>
            {message}
        </Alert>
    );
}

DismissibleAlert.propTypes = {
    type: PropTypes.string.isRequired,
    message: PropTypes.string,
};

export default function FlashMessages() {
    const { flash } = usePage().props;

    // Keying on the message remounts the alert (and resets "dismissed") whenever a new flash arrives.
    return (
        <div className="space-y-3 empty:hidden">
            <DismissibleAlert key={`success:${flash?.success}`} type="success" message={flash?.success} />
            <DismissibleAlert key={`error:${flash?.error}`} type="error" message={flash?.error} />
        </div>
    );
}
