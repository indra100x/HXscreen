#!/usr/bin/env python3
"""Measure the A/V offset between two live HLS streams.

Compares the program-date-time clocks of both playlists: the difference
tells you how far the picture (video URL) sits ahead of (+) or behind (-)
the sound (audio URL), in milliseconds.

Needs EXT-X-PROGRAM-DATE-TIME tags in both playlists (any decent
transcoder/segmenter emits them). Pure standard library, no ffmpeg.

Usage:
    python3 sync_probe.py <video_url> <audio_url> [--watch SECONDS] [--json]

Exit code 0 when a delay could be computed, 2 when it could not.
"""

import argparse
import datetime as dt
import json
import sys
import time
import urllib.request
from urllib.parse import urljoin

USER_AGENT = "HXscreen-sync-probe/1.0"
TIMEOUT = 15


class ProbeError(Exception):
    """The delay cannot be determined for a stream."""


def fetch_text(url: str) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=TIMEOUT) as response:
        return response.read().decode("utf-8", errors="replace")


def pick_media_playlist(master_url: str, master: str, want_audio: bool) -> str:
    """Resolve a master playlist to one media playlist URL.

    Picture side: first variant with a RESOLUTION (video rendition).
    Sound side: first EXT-X-MEDIA audio rendition, else first variant.
    A URL that is already a media playlist is returned unchanged.
    """
    audio_group = None
    variants = []
    for line in master.splitlines():
        line = line.strip()
        if line.startswith("#EXT-X-MEDIA:") and 'TYPE=AUDIO' in line:
            for part in line.split(','):
                if part.strip().startswith('URI='):
                    audio_group = part.split('=', 1)[1].strip().strip('"')
        elif line.startswith("#EXT-X-STREAM-INF:"):
            pending = line
            variants.append((pending, None))
        elif line and not line.startswith("#") and variants and variants[-1][1] is None:
            variants[-1] = (variants[-1][0], line)

    if '#EXT-X-STREAM-INF' not in master and '#EXTINF' in master:
        return master_url  # already a media playlist

    if want_audio and audio_group:
        return urljoin(master_url, audio_group)
    for info, uri in variants:
        if uri is None:
            continue
        if want_audio or 'RESOLUTION=' in info:
            return urljoin(master_url, uri)
    if variants and variants[0][1]:
        return urljoin(master_url, variants[0][1])
    raise ProbeError("no playable rendition found in master playlist")


def parse_pdt(value: str) -> dt.datetime:
    """Parse an EXT-X-PROGRAM-DATE-TIME value into an aware datetime."""
    text = value.strip()
    if text.endswith('Z'):
        text = text[:-1] + '+00:00'
    parsed = dt.datetime.fromisoformat(text)
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=dt.timezone.utc)
    return parsed


def stream_clock(playlist_url: str, label: str) -> dict:
    """Return the stream's current clock: PDT at the end of its newest
    segment, averaged over the last three dated segments to smooth out
    playlist update timing."""
    try:
        master = fetch_text(playlist_url)
    except Exception as exc:
        raise ProbeError(f"{label}: download failed ({exc})")
    media_url = pick_media_playlist(playlist_url, master, want_audio=(label == 'audio'))
    try:
        media = fetch_text(media_url)
    except Exception as exc:
        raise ProbeError(f"{label}: media playlist failed ({exc})")

    ends = []
    pending_start = None
    for line in media.splitlines():
        line = line.strip()
        if line.startswith("#EXT-X-PROGRAM-DATE-TIME:"):
            try:
                pending_start = parse_pdt(line.split(':', 1)[1])
            except ValueError:
                pending_start = None
        elif line.startswith("#EXTINF:"):
            try:
                duration = float(line.split(':')[1].split(',')[0])
            except ValueError:
                duration = 0.0
            if pending_start is not None:
                ends.append(pending_start + dt.timedelta(seconds=duration))
                pending_start = None

    if not ends:
        raise ProbeError(
            f"{label}: no EXT-X-PROGRAM-DATE-TIME tags "
            "(transcoder must emit program date time)"
        )
    newest = ends[-3:]
    clock = sum((e.timestamp() for e in newest), 0.0) / len(newest)
    return {"clock": clock, "segments": len(ends), "playlist": media_url}


def measure(video_url: str, audio_url: str) -> dict:
    """Delay in ms: positive = picture ahead of sound, negative = late."""
    video = stream_clock(video_url, 'video')
    audio = stream_clock(audio_url, 'audio')
    now = time.time()
    delay_ms = round((video['clock'] - audio['clock']) * 1000)
    return {
        "delay_ms": delay_ms,
        "video_latency_ms": round((now - video['clock']) * 1000),
        "audio_latency_ms": round((now - audio['clock']) * 1000),
        "video_segments": video['segments'],
        "audio_segments": audio['segments'],
    }


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(
        description="Report the delay between two live HLS streams in ms "
        "(+ = picture ahead of sound, - = picture late)."
    )
    parser.add_argument('video_url', help='live URL carrying the picture')
    parser.add_argument('audio_url', help='live URL carrying the sound')
    parser.add_argument(
        '--watch', type=float, default=0, metavar='SECONDS',
        help='repeat the measurement every N seconds',
    )
    parser.add_argument('--json', action='store_true', help='machine-readable output')
    args = parser.parse_args(argv)

    def show(result: dict) -> None:
        if args.json:
            print(json.dumps(result))
            return
        sign = '+' if result['delay_ms'] >= 0 else ''
        direction = (
            'picture ahead of sound' if result['delay_ms'] >= 0
            else 'picture late behind sound'
        )
        print(
            f"{sign}{result['delay_ms']}ms ({direction}) | "
            f"video latency {result['video_latency_ms']}ms, "
            f"audio latency {result['audio_latency_ms']}ms"
        )

    while True:
        try:
            show(measure(args.video_url, args.audio_url))
        except ProbeError as exc:
            print(f"cannot measure: {exc}", file=sys.stderr)
            if not args.watch:
                return 2
        if not args.watch:
            return 0
        time.sleep(args.watch)


if __name__ == '__main__':
    raise SystemExit(main())
