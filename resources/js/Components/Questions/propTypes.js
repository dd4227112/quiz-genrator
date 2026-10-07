import PropTypes from 'prop-types';

export const attemptShape = PropTypes.shape({
    id: PropTypes.number,
    response: PropTypes.object,
    is_correct: PropTypes.bool,
    score: PropTypes.number,
    feedback: PropTypes.string,
    graded_by: PropTypes.string,
});

export const questionShape = PropTypes.shape({
    id: PropTypes.number.isRequired,
    type: PropTypes.string.isRequired,
    type_label: PropTypes.string,
    position: PropTypes.number.isRequired,
    prompt: PropTypes.string.isRequired,
    payload: PropTypes.object,
    latest_attempt: attemptShape,
});

export const revealShape = PropTypes.shape({
    answer: PropTypes.object,
    explanation: PropTypes.string,
});

/** Props shared by every type-specific question component. */
export const answerInputProps = {
    question: questionShape.isRequired,
    value: PropTypes.object,
    onChange: PropTypes.func.isRequired,
    disabled: PropTypes.bool,
    attempt: attemptShape,
    revealed: PropTypes.bool,
    reveal: revealShape,
};
