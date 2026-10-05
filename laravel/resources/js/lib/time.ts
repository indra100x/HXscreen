/**
 * Schedule times cross two zones: the browser (whatever the user sees on
 * their clock) and the server (UTC, which is what the playback filter
 * compares against). The API carries naive "YYYY-MM-DD HH:mm:ss" strings,
 * so both directions must convert explicitly — never rely on the
 * Date constructor's local-time assumption for server values.
 */

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

/** Parse a server UTC value ("YYYY-MM-DD HH:mm:ss" or ISO-8601) into a Date. */
export function parseServerUtc(raw: string | null | undefined): Date | null {
    if (!raw || !raw.trim() || raw.trim() === 'null') {
        return null;
    }
    const trimmed = raw.trim();
    // Already zoned (ends with Z or carries an offset)? Trust it as-is,
    // otherwise the value is UTC and needs an explicit marker.
    const zoned = /([zZ]|[+-]\d{2}:?\d{2})$/.test(trimmed)
        ? trimmed
        : `${trimmed.replace('T', ' ').split('.')[0]}Z`;
    const date = new Date(zoned.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
}

/** Server UTC value -> browser-local `datetime-local` input value. */
export function serverUtcToLocalInput(raw: string | null | undefined): string {
    const date = parseServerUtc(raw);
    if (!date) {
        return '';
    }
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Browser-local `datetime-local` value -> server UTC "YYYY-MM-DD HH:mm:ss". */
export function localInputToServerUtc(local: string): string {
    if (!local) {
        return '';
    }
    const date = new Date(local);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())} ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:00`;
}

/** Server UTC value -> browser-local display string. */
export function formatServerUtc(raw: string | null | undefined): string {
    const date = parseServerUtc(raw);
    return date ? date.toLocaleString() : '';
}
