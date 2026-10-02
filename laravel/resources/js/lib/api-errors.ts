import { toast } from 'sonner';

/**
 * Extract a human-readable message from a failed API call.
 *
 * Handles Laravel validation responses ({ message, errors: { field: [...] } }),
 * plain { message } payloads, and anything unexpected.
 */
export function apiErrorMessage(error: unknown, fallback: string): string {
    if (statusOf(error) === 413) {
        return 'File is too large for the server to accept.';
    }

    const data =
        typeof error === 'object' && error !== null && 'response' in error
            ? ((error as { response?: { data?: unknown } }).response?.data ??
              error)
            : error;

    if (typeof data === 'string' && data) {
        return data;
    }

    if (typeof data === 'object' && data !== null) {
        const message = (data as { message?: unknown }).message;
        if (typeof message === 'string' && message) {
            return message;
        }

        const errors = (data as { errors?: unknown }).errors;
        if (errors && typeof errors === 'object') {
            const first = Object.values(
                errors as Record<string, unknown>,
            ).flat()[0];
            if (typeof first === 'string' && first) {
                return first;
            }
        }
    }

    return fallback;
}

/**
 * Best-effort HTTP status extraction from the various error shapes thrown
 * by the HTTP client (HttpResponseError, axios-style errors, Responses).
 */
function statusOf(error: unknown): number | null {
    if (typeof error !== 'object' || error === null) {
        return null;
    }

    const record = error as Record<string, unknown>;

    for (const key of ['status', 'statusCode']) {
        if (typeof record[key] === 'number') {
            return record[key];
        }
    }

    const response = record['response'];
    if (typeof response === 'object' && response !== null) {
        return statusOf(response);
    }

    return null;
}

/**
 * Build a `useHttp` onError handler that toasts the server's message,
 * falling back to the given text when none is available.
 *
 * Usage: `post(url, { onError: toastApiError('Could not save') })`
 */
export function toastApiError(fallback: string) {
    return (error: unknown) => {
        toast.error(apiErrorMessage(error, fallback));
    };
}
