/**
 * Resolve a stored file path to a playable URL.
 *
 * Paths stored on the local `public` disk are relative (e.g.
 * `videos/clip.mp4`) and served through the storage symlink, while older
 * rows may hold absolute (e.g. S3) URLs.
 */
export function publicFileUrl(path: string): string {
    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
}
