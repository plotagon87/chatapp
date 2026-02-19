// Crypto helpers for end-to-end encryption

// Feature detection: some browsers/insecure contexts may not expose crypto.subtle
const e2eeEnabled = !!(window.crypto && crypto.subtle);
// also expose globally so other scripts can inspect it
window.e2eeEnabled = e2eeEnabled;
if (!e2eeEnabled) {
    console.warn('E2EE disabled: WebCrypto API unavailable (crypto.subtle missing).');
}

function arrayBufferToBase64(buf) {
    let bin = '';
    new Uint8Array(buf).forEach(b => bin += String.fromCharCode(b));
    return btoa(bin);
}
function base64ToArrayBuffer(b64) {
    const bin = atob(b64);
    const arr = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    return arr.buffer;
}

async function savePrivateKey(pk) {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    const jwk = await crypto.subtle.exportKey('jwk', pk);
    localStorage.setItem('e2ee_private_jwk', JSON.stringify(jwk));
}
async function getPrivateKey() {
    if (!e2eeEnabled) return null;
    const data = localStorage.getItem('e2ee_private_jwk');
    if (!data) return null;
    return crypto.subtle.importKey(
        'jwk', JSON.parse(data),
        { name: 'ECDH', namedCurve: 'P-256' },
        true, ['deriveKey']
    );
}

async function generateKeyPair() {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    const pair = await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey','deriveBits']
    );
    await savePrivateKey(pair.privateKey);
    return pair;
}

async function generateKeyPairAndUpload() {
    if (!e2eeEnabled) {
        console.warn('generateKeyPairAndUpload called but E2EE disabled');
        return null;
    }
    const pair = await generateKeyPair();
    const pubSpki = await crypto.subtle.exportKey('spki', pair.publicKey);
    const pubB64 = arrayBufferToBase64(pubSpki);
    // send to server
    try {
        await fetch(`${window.baseUrl}api/save_public_key.php`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                public_key: pubB64,
                csrf_token: window.csrfToken
            })
        });
        // also update hidden field if form exists
        const hidden = document.getElementById('public_key');
        if (hidden) hidden.value = pubB64;
    } catch (e) {
        console.error('Failed to upload public key', e);
    }
}

async function importPublicKey(b64) {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    const buf = base64ToArrayBuffer(b64);
    return crypto.subtle.importKey(
        'spki', buf,
        { name: 'ECDH', namedCurve: 'P-256' },
        true, []
    );
}

async function deriveSharedKey(otherPublicKey) {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    let priv = await getPrivateKey();
    if (!priv) {
        await generateKeyPairAndUpload();
        priv = await getPrivateKey();
    }
    return crypto.subtle.deriveKey(
        { name: 'ECDH', public: otherPublicKey },
        priv,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt','decrypt']
    );
}

async function encryptWithKey(key, plain) {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    const enc = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        enc.encode(plain)
    );
    return { iv: arrayBufferToBase64(iv), ct: arrayBufferToBase64(ct) };
}

async function decryptWithKey(key, ivB64, ctB64) {
    if (!e2eeEnabled) throw new Error('WebCrypto unavailable');
    const dec = new TextDecoder();
    const iv = base64ToArrayBuffer(ivB64);
    const ct = base64ToArrayBuffer(ctB64);
    const plain = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: new Uint8Array(iv) },
        key,
        ct
    );
    return dec.decode(plain);
}
