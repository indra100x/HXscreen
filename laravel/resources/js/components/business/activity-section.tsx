import { History } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { parseServerUtc } from '@/lib/time';

export type ActivityEntry = {
    id: string;
    action: string;
    user?: { name: string } | null;
    subject_type?: string | null;
    created_at: string;
};

const ACTION_LABELS: Record<string, string> = {
    'business.created': 'created the business',
    'business.updated': 'renamed the business',
    'business.deleted': 'deleted the business',
    'business.member_invited': 'invited a team member',
    'business.member_created': 'created a team login',
    'business.member_removed': 'removed a team member',
    'screen.created': 'added a screen',
    'screen.updated': 'edited a screen',
    'screen.deleted': 'deleted a screen',
    'screen.paired': 'paired a screen',
    'screen.playlist_attached': 'assigned a playlist',
    'screen.playlist_detached': 'removed a playlist',
    'screen.schedule_updated': 'changed a schedule',
    'screen.command_sent': 'sent a remote command',
    'playlist.created': 'created a playlist',
    'playlist.updated': 'renamed a playlist',
    'playlist.deleted': 'deleted a playlist',
    'playlist.video_added': 'added a video to a playlist',
    'playlist.video_removed': 'removed a video from a playlist',
    'playlist.videos_ordered': 'reordered a playlist',
    'video.created': 'uploaded a video',
    'video.updated': 'edited a video',
    'video.deleted': 'deleted a video',
};

function formatWhen(iso: string): string {
    return parseServerUtc(iso)?.toLocaleString() ?? iso;
}

export default function ActivitySection({
    entries,
}: {
    entries: ActivityEntry[];
}) {
    return (
        <section className="space-y-4">
            <h2 className="text-lg font-semibold">Activity</h2>
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <History className="size-4" />
                        Latest changes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {entries.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            Nothing yet — actions taken here will appear.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {entries.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-baseline justify-between gap-4 text-sm"
                                >
                                    <span className="min-w-0">
                                        <span className="font-medium">
                                            {entry.user?.name ?? 'Someone'}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {ACTION_LABELS[entry.action] ??
                                                entry.action}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {formatWhen(entry.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </section>
    );
}
