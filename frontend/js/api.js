/**
 * PetConnect - API-Client und gemeinsame UI-Bausteine
 * Wird von allen Seiten eingebunden.
 */

// Relativ, damit es lokal (localhost:8001) und hinter einer Domain funktioniert
const API_BASE_URL = '/api';

/* ==========================================================================
   Session
   ========================================================================== */
const session = {
    get token() { return localStorage.getItem('sessionToken'); },
    get userId() { return localStorage.getItem('userId'); },
    set(token, userId) {
        localStorage.setItem('sessionToken', token);
        localStorage.setItem('userId', userId);
    },
    clear() {
        localStorage.removeItem('sessionToken');
        localStorage.removeItem('userId');
    },
    isAuthenticated() { return !!this.token; }
};

/* ==========================================================================
   API-Client
   ========================================================================== */
class APIClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }

    async request(endpoint, { method = 'GET', body = null, params = null } = {}) {
        let url = `${this.baseUrl}/${endpoint.replace(/^\/+/, '')}`;
        const headers = {};
        const token = session.token;

        if (token) headers['Authorization'] = `Bearer ${token}`;
        if (params) {
            const query = new URLSearchParams({ ...params, ...(token ? { session_token: token } : {}) });
            url += `?${query}`;
        } else if (method === 'GET' && token) {
            url += `?session_token=${encodeURIComponent(token)}`;
        }

        let payload = body;
        if (body instanceof FormData) {
            if (token) body.append('session_token', token);
        } else if (body) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(token ? { ...body, session_token: token } : body);
        }

        let response;
        try {
            response = await fetch(url, { method, headers, body: payload });
        } catch (e) {
            throw new Error('Keine Verbindung zum Server. Prüfe deine Internetverbindung.');
        }

        let data;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error(`Der Server hat unerwartet geantwortet (HTTP ${response.status}).`);
        }

        if (!response.ok || data.success === false) {
            const error = new Error(data.message || `Anfrage fehlgeschlagen (HTTP ${response.status})`);
            error.data = data;
            throw error;
        }
        return data;
    }

    get(endpoint, params) { return this.request(endpoint, { params }); }
    post(endpoint, body = {}) { return this.request(endpoint, { method: 'POST', body }); }
    put(endpoint, body = {}) { return this.request(endpoint, { method: 'PUT', body }); }
}

const api = new APIClient(API_BASE_URL);

/* ==========================================================================
   Helfer
   ========================================================================== */
function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function speciesLabel(species) {
    return species === 'cat' ? 'Katze' : species === 'dog' ? 'Hund' : 'Tier';
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date)) return '';
    return date.toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' });
}

function formatMoney(value) {
    const amount = Number(value);
    if (!amount) return '';
    return amount.toLocaleString('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
}

function isMissing(pet) {
    return Number(pet.is_missing) === 1 || pet.is_missing === true;
}

// Ort lesbar machen: "Koordinaten: 48.1, 11.5" -> "48.1000, 11.5000"
function formatPlace(text) {
    if (!text) return 'Ort unbekannt';
    const m = String(text).match(/^Koordinaten:\s*(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/);
    return m ? `Nähe ${Number(m[1]).toFixed(4)}, ${Number(m[2]).toFixed(4)}` : text;
}

const PLACEHOLDER_PHOTO = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#ebe8f7"/>' +
    '<g fill="#b9b1e0" transform="translate(200 150)"><ellipse cx="0" cy="22" rx="34" ry="28"/>' +
    '<ellipse cx="-38" cy="-14" rx="13" ry="17"/><ellipse cx="-14" cy="-38" rx="13" ry="17"/>' +
    '<ellipse cx="14" cy="-38" rx="13" ry="17"/><ellipse cx="38" cy="-14" rx="13" ry="17"/></g></svg>'
);

function petPhotoUrl(pet) {
    const file = pet && pet.profile_image;
    if (!file || file === 'default.jpg' || file === 'null' || !String(file).trim()) return PLACEHOLDER_PHOTO;
    if (/^(https?:|data:|\/)/.test(file)) return file;
    if (file.startsWith('uploads/')) return '/' + file;
    return '/uploads/pets/' + encodeURIComponent(file);
}

function photoTag(src, alt, attrs = '') {
    return `<img src="${esc(src)}" alt="${esc(alt)}" loading="lazy" ${attrs} onerror="this.onerror=null;this.src=PLACEHOLDER_PHOTO">`;
}

function setBusy(button, busy, busyLabel) {
    if (busy) {
        button.dataset.label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `<span class="spin" aria-hidden="true"></span> ${esc(busyLabel || 'Bitte warten …')}`;
    } else {
        button.disabled = false;
        if (button.dataset.label) button.innerHTML = button.dataset.label;
    }
}

/* ==========================================================================
   Icons (inline SVG, erben die Textfarbe)
   ========================================================================== */
const ICONS = {
    logo: '<svg viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M6 13a4 4 0 0 1 4-4h4l2.5-4h7L26 9h4a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4V13z" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><g fill="currentColor"><ellipse cx="20" cy="25" rx="4.6" ry="3.8"/><ellipse cx="14.3" cy="20" rx="1.9" ry="2.4"/><ellipse cx="17.8" cy="16.6" rx="1.9" ry="2.4"/><ellipse cx="22.2" cy="16.6" rx="1.9" ry="2.4"/><ellipse cx="25.7" cy="20" rx="1.9" ry="2.4"/></g></svg>',
    camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 0 1 2-2h2.5l1.5-2h6l1.5 2H19a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.5"/></svg>',
    paw: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><ellipse cx="12" cy="16" rx="4.5" ry="3.8"/><ellipse cx="6" cy="10.5" rx="1.9" ry="2.5"/><ellipse cx="9.6" cy="6.8" rx="1.9" ry="2.5"/><ellipse cx="14.4" cy="6.8" rx="1.9" ry="2.5"/><ellipse cx="18" cy="10.5" rx="1.9" ry="2.5"/></svg>',
    dog: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 8c-2 0-4 2-5 6l3 2 2-4v10a5 5 0 0 0 5 5h4a5 5 0 0 0 5-5V12l2 4 3-2c-1-4-3-6-5-6z"/><circle cx="13" cy="15" r=".8" fill="currentColor"/><circle cx="19" cy="15" r=".8" fill="currentColor"/><path d="M14.5 20h3l-1.5 1.5z" fill="currentColor"/></svg>',
    cat: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 5l4 6h10l4-6v13a9 9 0 0 1-18 0z"/><circle cx="12.5" cy="17" r=".8" fill="currentColor"/><circle cx="19.5" cy="17" r=".8" fill="currentColor"/><path d="M14.5 21h3L16 22.5z" fill="currentColor"/><path d="M4 20h5M4 23h5M23 20h5M23 23h5"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    locate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>',
    search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
    plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5v.01"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    menu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.1 6.1l1.4-2.3L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 3.5 5.6 1.5 1.5 0 0 1 5 4z"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg>'
};

function icon(name, cls = '') {
    return cls ? ICONS[name].replace('<svg ', `<svg class="${cls}" `) : ICONS[name];
}

/* ==========================================================================
   Kopf- und Fußzeile
   ========================================================================== */
function renderChrome() {
    const page = document.body.dataset.page;
    if (!page) return;

    const loggedIn = session.isAuthenticated();
    const link = (href, label, key) =>
        `<a href="${href}"${page === key ? ' aria-current="page"' : ''}>${label}</a>`;

    const header = document.createElement('header');
    header.className = 'site-header';
    header.innerHTML = `
        <div class="wrap">
            <a class="brand" href="index.html" aria-label="PetConnect Startseite">${icon('logo')}PetConnect</a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menü öffnen">${icon('menu')}</button>
            <nav class="nav" id="site-nav" aria-label="Hauptnavigation">
                ${link('missing-pets.html', 'Vermisste Tiere', 'missing')}
                ${loggedIn
                    ? link('dashboard.html', 'Meine Tiere', 'dashboard') + '<a href="#" data-action="logout">Abmelden</a>'
                    : link('login.html', 'Anmelden', 'login')}
                <a class="btn btn-signal btn-sm" href="found.html"${page === 'found' ? ' aria-current="page"' : ''}>${icon('camera')}Tier gefunden</a>
            </nav>
        </div>`;
    document.body.prepend(header);

    const toggle = header.querySelector('.nav-toggle');
    const nav = header.querySelector('.nav');
    toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open);
    });
    header.querySelector('[data-action="logout"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });

    const footer = document.createElement('footer');
    footer.className = 'site-footer';
    footer.innerHTML = `
        <div class="wrap">
            <span>© ${new Date().getFullYear()} PetConnect – gemeinsam Haustiere nach Hause bringen</span>
            <nav aria-label="Fußzeile">
                <a href="missing-pets.html">Vermisste Tiere</a>
                <a href="found.html">Fund melden</a>
                <a href="index.html#ablauf">So funktioniert's</a>
            </nav>
        </div>`;
    document.body.append(footer);
}

/* ==========================================================================
   Meldungen (Toasts)
   ========================================================================== */
function toast(message, type = 'info', timeout = 6000) {
    let region = document.querySelector('.toasts');
    if (!region) {
        region = document.createElement('div');
        region.className = 'toasts';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        document.body.append(region);
    }
    const iconName = { success: 'check', error: 'alert', info: 'info' }[type] || 'info';
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `${icon(iconName)}<span>${esc(message)}</span><button type="button" aria-label="Meldung schließen">×</button>`;
    el.querySelector('button').addEventListener('click', () => el.remove());
    region.append(el);
    if (timeout) setTimeout(() => el.remove(), timeout);
}

/* ==========================================================================
   Modal
   ========================================================================== */
let lastFocus = null;

function openModal(html) {
    let modal = document.getElementById('modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal';
        modal.className = 'modal';
        modal.innerHTML = `<div class="modal-dialog" role="dialog" aria-modal="true" tabindex="-1">
            <button class="modal-close" type="button" aria-label="Schließen">${icon('close')}</button>
            <div class="modal-content"></div></div>`;
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        modal.querySelector('.modal-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
        document.body.append(modal);
    }
    lastFocus = document.activeElement;
    modal.querySelector('.modal-content').innerHTML = html;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    modal.querySelector('.modal-dialog').focus();
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (!modal || !modal.classList.contains('open')) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
    lastFocus?.focus();
}

/* ==========================================================================
   Vermisst-Aushang
   ========================================================================== */
function posterHTML(pet) {
    const tab = pet.phone ? esc(pet.phone) : 'petconnect';
    const reward = formatMoney(pet.reward_amount);
    const since = formatDate(pet.date_missing);
    return `
        <button type="button" class="poster" data-pet-id="${esc(pet.id)}" aria-label="${esc(pet.name)} – Details ansehen">
            <div class="poster-head" aria-hidden="true">VERMISST</div>
            <div class="poster-photo">${photoTag(petPhotoUrl(pet), pet.name)}</div>
            <div class="poster-body">
                <div class="poster-name">${esc(pet.name)}</div>
                <div class="poster-meta">${speciesLabel(pet.species)}${pet.breed ? ', ' + esc(pet.breed) : ''}${since ? ' · seit ' + since : ''}</div>
                <div class="poster-where">${icon('pin')}<span>${esc(formatPlace(pet.location_last_seen))}</span></div>
                ${reward ? `<span class="poster-reward">Finderlohn ${reward}</span>` : ''}
            </div>
            <div class="poster-tabs" aria-hidden="true">${`<span>${tab}</span>`.repeat(6)}</div>
        </button>`;
}

function missingPetDetailHTML(pet) {
    const reward = formatMoney(pet.reward_amount);
    const facts = [
        ['Tierart', speciesLabel(pet.species)],
        ['Rasse', pet.breed],
        ['Farbe', pet.color],
        ['Alter', pet.age ? `${pet.age} Jahre` : ''],
        ['Gewicht', Number(pet.weight) ? `${Number(pet.weight).toLocaleString('de-DE')} kg` : ''],
        ['Vermisst seit', formatDate(pet.date_missing)],
        ['Zuletzt gesehen', formatPlace(pet.location_last_seen)],
        ['Finderlohn', reward]
    ].filter(([, v]) => v);

    return `
        ${photoTag(petPhotoUrl(pet), pet.name, 'class="modal-photo"')}
        <div class="modal-body">
            <span class="status status-missing">Vermisst</span>
            <h2 class="mt-1">${esc(pet.name)}</h2>
            <dl class="facts">${facts.map(([k, v]) => `<dt>${k}</dt><dd>${esc(v)}</dd>`).join('')}</dl>
            ${pet.distinguishing_features ? `<h4>Besondere Merkmale</h4><p class="muted">${esc(pet.distinguishing_features)}</p>` : ''}
            ${pet.description ? `<h4 class="mt-1">Was passiert ist</h4><p class="muted">${esc(pet.description)}</p>` : ''}
            <div class="note mt-2">${icon('info')}<div>
                <strong>Du hast ${esc(pet.name)} gesehen?</strong><br>
                ${pet.phone
                    ? `Ruf ${esc([pet.first_name, pet.last_name].filter(Boolean).join(' ') || 'die Besitzer')} direkt an: <a href="tel:${esc(pet.phone)}">${esc(pet.phone)}</a>. Oder melde den Fund mit Foto.`
                    : 'Fotografiere das Tier und melde den Fund. Die Besitzer sehen deine Meldung in ihrem Konto.'}
            </div></div>
            <div class="form-actions">
                ${pet.phone ? `<a class="btn btn-ghost" href="tel:${esc(pet.phone)}">${icon('phone')}Anrufen</a>` : ''}
                <a class="btn btn-signal" href="found.html">${icon('camera')}Fund mit Foto melden</a>
            </div>
        </div>`;
}

/* ==========================================================================
   Auth
   ========================================================================== */
async function requireAuth() {
    if (!session.isAuthenticated()) {
        redirectToLogin();
        return null;
    }
    try {
        const response = await api.get('auth/check');
        if (response.data && response.data.is_logged_in) return response.data.user;
    } catch (e) {
        // fällt durch zum Login
    }
    session.clear();
    redirectToLogin();
    return null;
}

function redirectToLogin() {
    const next = encodeURIComponent(location.pathname.split('/').pop() + location.search);
    location.href = `login.html?next=${next}`;
}

async function logout() {
    try {
        await api.post('auth/logout');
    } catch (e) {
        // Session ist serverseitig evtl. schon abgelaufen
    }
    session.clear();
    location.href = 'index.html';
}

/* ==========================================================================
   Foto-Auswahl mit Vorschau
   ========================================================================== */
function setupPhotoInput(dropzone, onChange) {
    const input = dropzone.querySelector('input[type="file"]');
    const original = dropzone.innerHTML;

    const show = (file) => {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            toast('Bitte wähle eine Bilddatei (JPG, PNG oder GIF).', 'error');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            toast('Das Foto ist größer als 10 MB. Bitte wähle ein kleineres Bild.', 'error');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            dropzone.classList.add('has-image');
            dropzone.querySelectorAll(':scope > :not(input)').forEach(el => el.remove());
            dropzone.insertAdjacentHTML('afterbegin', `<img src="${e.target.result}" alt="Ausgewähltes Foto"><span class="change-photo">Foto ändern</span>`);
            onChange(file);
        };
        reader.readAsDataURL(file);
    };

    input.addEventListener('change', () => show(input.files[0]));
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); }));
    dropzone.addEventListener('drop', (e) => show(e.dataTransfer.files[0]));

    return {
        reset() {
            dropzone.classList.remove('has-image');
            dropzone.innerHTML = original;
            setupPhotoInput(dropzone, onChange);
            onChange(null);
        }
    };
}

/* ==========================================================================
   Karte zum Markieren eines Ortes (Leaflet)
   ========================================================================== */
function createMapPicker({ mapId, statusId, locateId, popupText }) {
    const status = document.getElementById(statusId);
    const state = { lat: null, lng: null, address: null };
    let geoTimer = null;
    const map = L.map(mapId).setView([51.1657, 10.4515], 6);
    let marker = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);
    setTimeout(() => map.invalidateSize(), 200);

    const markerIcon = L.icon({
        iconUrl: '../assets/leaflet/images/marker-icon-2x-red.png',
        shadowUrl: '../assets/leaflet/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });

    function set(lat, lng) {
        state.lat = lat;
        state.lng = lng;
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true, icon: markerIcon }).addTo(map);
            marker.bindPopup(popupText);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                set(p.lat, p.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
        status.className = 'map-status set';
        status.innerHTML = `${icon('check')}Ort markiert – Marker lässt sich verschieben`;
        lookupAddress(lat, lng);
    }

    // Adresse zum Punkt holen (OpenStreetMap Nominatim), damit Meldungen lesbar sind
    function lookupAddress(lat, lng) {
        state.address = null;
        clearTimeout(geoTimer);
        geoTimer = setTimeout(async () => {
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&accept-language=de&lat=${lat}&lon=${lng}`);
                const a = (await r.json()).address || {};
                const street = a.road ? a.road + (a.house_number ? ' ' + a.house_number : '') : (a.park || a.suburb || a.neighbourhood || '');
                const town = [a.postcode, a.city || a.town || a.village || a.municipality].filter(Boolean).join(' ');
                const text = [street, town].filter(Boolean).join(', ');
                if (text && state.lat === lat && state.lng === lng) {
                    state.address = text;
                    status.innerHTML = `${icon('check')}${esc(text)} – Marker lässt sich verschieben`;
                }
            } catch (e) {
                // ohne Adresse werden die Koordinaten gespeichert
            }
        }, 600);
    }

    map.on('click', (e) => set(e.latlng.lat, e.latlng.lng));

    document.getElementById(locateId).addEventListener('click', () => {
        if (!navigator.geolocation) {
            toast('Dein Browser kann den Standort nicht ermitteln. Tippe stattdessen auf die Karte.', 'info');
            return;
        }
        status.className = 'map-status';
        status.textContent = 'Standort wird ermittelt …';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                map.setView([pos.coords.latitude, pos.coords.longitude], 16);
                set(pos.coords.latitude, pos.coords.longitude);
            },
            () => {
                status.className = 'map-status error';
                status.textContent = 'Standort nicht verfügbar. Tippe auf die Karte, um den Ort zu markieren.';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    return {
        get lat() { return state.lat; },
        get lng() { return state.lng; },
        get place() { return state.address || `Koordinaten: ${state.lat.toFixed(6)}, ${state.lng.toFixed(6)}`; },
        hasLocation() { return state.lat !== null; },
        flagMissing() {
            status.className = 'map-status error';
            status.innerHTML = `${icon('alert')}Bitte markiere den Ort auf der Karte.`;
            document.getElementById(mapId).scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        reset() {
            if (marker) { map.removeLayer(marker); marker = null; }
            state.lat = state.lng = state.address = null;
            status.className = 'map-status';
            status.textContent = 'Noch kein Ort markiert';
        }
    };
}

renderChrome();
