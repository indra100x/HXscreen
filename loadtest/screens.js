import { check, sleep } from 'k6';
import exec from 'k6/execution';
import http from 'k6/http';

// One VU == one TV screen. Each 16s cycle mirrors the real player:
// 1x content feed + 1x heartbeat + 8x command polls (every 2s).
// => ~0.625 req/s per screen.
export const options = {
    scenarios: {
        screens: {
            executor: 'ramping-vus',
            startVUs: 50,
            stages: [
                { duration: '1m', target: 100 },
                { duration: '2m', target: 200 },
                { duration: '2m', target: 400 },
                { duration: '2m', target: 800 },
            ],
        },
    },
};

const BASE = __ENV.BASE_URL || 'http://localhost:8080';

function token() {
    return `loadtest-token-${(exec.vu.idInTest - 1) % 1000}`;
}

export default function () {
    const tk = token();

    const content = http.get(`${BASE}/api/device/content?device_token=${tk}`);
    check(content, { 'content 200': (r) => r.status === 200 });
    let screenId = '';
    try {
        screenId = content.json('screen.id') || '';
    } catch (e) {
        screenId = '';
    }

    if (screenId) {
        const hb = http.post(
            `${BASE}/api/screens/${screenId}/heartbeat`,
            JSON.stringify({ device_token: tk }),
            { headers: { 'Content-Type': 'application/json' } },
        );
        check(hb, { 'heartbeat 200': (r) => r.status === 200 });
    }

    for (let i = 0; i < 8; i++) {
        const cmds = http.get(`${BASE}/api/device/commands?device_token=${tk}`);
        check(cmds, { 'commands 200': (r) => r.status === 200 });
        sleep(2);
    }
}
