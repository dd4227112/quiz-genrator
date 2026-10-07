/**
 * Turn an axios error into a short, user-facing message.
 */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const response = error?.response;

    if (!response) {
        return 'Network error. Check your connection and try again.';
    }

    const data = response.data;

    if (response.status === 422 && data?.errors) {
        const first = Object.values(data.errors)[0];
        return Array.isArray(first) ? first[0] : String(first);
    }

    if (response.status === 429) {
        return data?.message || 'Too many requests. Please wait a while and try again.';
    }

    if (response.status === 403) {
        return 'You do not have access to this question.';
    }

    return data?.message || fallback;
}
