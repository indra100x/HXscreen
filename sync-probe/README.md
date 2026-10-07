# sync-probe

Measures the A/V offset between two live HLS streams by comparing their
program-date-time clocks. Positive result = picture ahead of sound
(advance the picture / delay the audio by that much); negative = late.

```bash
python3 sync_probe.py <video_url> <audio_url> [--watch SECONDS] [--json]
```

Examples:

```bash
# One-shot, human readable
python3 sync_probe.py https://cdn.example.com/cam-video.m3u8 https://cdn.example.com/cam-audio.m3u8

# Watch a drift over time, JSON per line (pipe into a calibrator)
python3 sync_probe.py https://a.example.com/live.m3u8 https://b.example.com/live.m3u8 --watch 5 --json
```

Requirements: both playlists must carry `EXT-X-PROGRAM-DATE-TIME` tags
(any real transcoder/segmenter emits them; ffmpeg does with
`-mpegts_flags +initial_discontinuity` … check your segmenter docs —
without them there is no shared clock to compare and the tool exits 2).

Precision notes:

- Clocks are averaged over the last 3 dated segments to smooth playlist
  update timing; expect roughly ± one fetch round-trip of noise.
- The result is only as fine as your segment length: with 6s segments you
  cannot resolve below ~seconds. Use 2s segments (or LL-HLS) for
  lip-sync-grade calibration.
- For ExoPlayer cross-muxing: feed the measured offset once as a fixed
  correction, then keep a lightweight speed-trim loop for jitter —
  seeking audio causes audible pops, `setPlaybackSpeed(1.02)` briefly
  does not.

No dependencies beyond the Python 3 standard library.
