export interface Business {
    id: string;
    user_id: string;
    name: string;
    created_at: string;
    updated_at: string;
    screens_count?: number;
    playlists_count?: number;
    videos_count?: number;
}

export interface Screen {
    id: string;
    busniss_id: string | null;
    name: string;
    device_id: string;
    paired_at: string | null;
    last_seen_at: string | null;
    current_video_id: string | null;
    current_position_ms: number | null;
    position_reported_at: string | null;
    is_playing: boolean | null;
    pairing_code_expires_at: string | null;
    device_token_expires_at: string | null;
    created_at: string;
    updated_at: string;
    screen_playlists?: Playlist[];
}

export interface Playlist {
    id: string;
    busniss_id: string | null;
    name: string;
    created_at: string;
    updated_at: string;
    videos?: Video[];
    pivot?: {
        id: string;
        start_time: string | null;
        end_time: string | null;
    };
}

export interface Video {
    id: string;
    busniss_id: string | null;
    name: string | null;
    url: string;
    created_at: string;
    updated_at: string;
    pivot?: {
        id: string;
        order: number;
    };
}
