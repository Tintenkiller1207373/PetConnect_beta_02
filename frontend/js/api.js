/**
 * PetConnect - API Client & Helper Functions
 * Zentrale Stelle für alle Backend-API Aufrufe
 */

const API_BASE_URL = 'http://localhost:8001/api';
const AI_SERVICE_URL = 'http://localhost:8002/api';

/**
 * Session Management
 */
class SessionManager {
    constructor() {
        this.syncFromStorage();
    }

    syncFromStorage() {
        this.sessionToken = localStorage.getItem('sessionToken');
        this.userId = localStorage.getItem('userId');
    }
    
    setSession(token, userId) {
        this.sessionToken = token;
        this.userId = userId;
        localStorage.setItem('sessionToken', token);
        localStorage.setItem('userId', userId);
    }
    
    getSession() {
        this.syncFromStorage();
        return {
            token: this.sessionToken,
            userId: this.userId
        };
    }
    
    clearSession() {
        this.sessionToken = null;
        this.userId = null;
        localStorage.removeItem('sessionToken');
        localStorage.removeItem('userId');
    }
    
    isAuthenticated() {
        this.syncFromStorage();
        return this.sessionToken !== null;
    }
}

const session = new SessionManager();

/**
 * API Client mit automatischer Error-Behandlung
 */
class APIClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }
    
    async request(endpoint, options = {}) {
        // Remove leading slash from endpoint if present
        endpoint = endpoint.replace(/^\/+/, '');
        
        const url = `${this.baseUrl}/${endpoint}`;
        
        // DEBUG: Log the URL being called
        console.log('API Request URL:', url);
        console.log('API Request options:', options);
        
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        };

        const config = { ...defaultOptions, ...options };

        // Add session token if available
        if (session.isAuthenticated()) {
            config.headers = {
                ...defaultOptions.headers,
                ...(options.headers || {}),
                'Authorization': `Bearer ${session.sessionToken}`
            };

            if (['POST', 'PUT', 'PATCH'].includes(String(config.method).toUpperCase()) && config.body && typeof config.body === 'object') {
                config.body.session_token = session.sessionToken;
            }
        }

        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            console.log('Fetching:', url, 'with config:', config);
            const response = await fetch(url, config);
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            const text = await response.text();
            console.log('Response text:', text);
            
            const data = JSON.parse(text);
            console.log('Parsed data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    get(endpoint, params = {}) {
        // Add session token to params for GET requests
        if (session.isAuthenticated()) {
            params.session_token = session.sessionToken;
        }
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }
    
    post(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body
        });
    }
    
    put(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body
        });
    }
    
    async postFormData(endpoint, formData) {
        // Remove leading slash from endpoint if present
        endpoint = endpoint.replace(/^\/+/, '');
        
        const url = `${this.baseUrl}/${endpoint}`;
        
        // Add session token to FormData
        if (session.isAuthenticated()) {
            formData.append('session_token', session.sessionToken);
        }
        
        console.log('FormData Request URL:', url);
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
                // NO Content-Type header - browser sets multipart/form-data automatically
            });
            
            console.log('FormData Response status:', response.status);
            
            const text = await response.text();
            console.log('FormData Response text:', text);
            
            const data = JSON.parse(text);
            console.log('FormData Parsed data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('FormData API Error:', error);
            throw error;
        }
    }
}

const api = new APIClient(API_BASE_URL);

function createNavItem(href, label, options = {}) {
    const listItem = document.createElement('li');
    const link = document.createElement('a');
    link.href = href;
    link.textContent = label;

    if (options.active) {
        link.style.color = 'var(--primary)';
        const strong = document.createElement('strong');
        strong.textContent = label;
        link.textContent = '';
        link.appendChild(strong);
    }

    if (options.onClick) {
        link.setAttribute('onclick', options.onClick);
    }

    listItem.appendChild(link);
    return listItem;
}

function updateAuthNavigation() {
    const navList = document.querySelector('.nav-links');
    if (!navList) {
        return;
    }

    const authenticated = session.isAuthenticated();
    const currentPage = (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();
    const loginItem = navList.querySelector('a[href="login.html"]')?.closest('li');
    const dashboardItem = navList.querySelector('a[href="dashboard.html"]')?.closest('li');
    const logoutItem = navList.querySelector('a[onclick*="logout"]')?.closest('li');

    if (authenticated) {
        if (loginItem) {
            loginItem.remove();
        }

        if (!dashboardItem) {
            navList.appendChild(createNavItem('dashboard.html', 'Dashboard', {
                active: currentPage === 'dashboard.html'
            }));
        }

        if (!logoutItem) {
            navList.appendChild(createNavItem('#', 'Logout', {
                onClick: 'logout(); return false;'
            }));
        }

        return;
    }

    if (logoutItem) {
        logoutItem.remove();
    }

    if (dashboardItem) {
        dashboardItem.remove();
    }

    if (!loginItem) {
        navList.appendChild(createNavItem('login.html', 'Login', {
            active: currentPage === 'login.html'
        }));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateAuthNavigation);
} else {
    updateAuthNavigation();
}

/**
 * Authentication Functions
 */
async function register(username, email, password, firstName, lastName) {
    try {
        const response = await api.post('auth/register', {
            username,
            email,
            password,
            first_name: firstName,
            last_name: lastName
        });
        
        if (response.success) {
            showAlert('Registrierung erfolgreich! Bitte melden Sie sich an.', 'success');
            return true;
        } else {
            showAlert(response.message, 'error');
            return false;
        }
    } catch (error) {
        showAlert('Registrierungsfehler: ' + error.message, 'error');
        return false;
    }
}

async function login(email, password) {
    try {
        const response = await api.post('auth/login', {
            email,
            password
        });
        
        if (response.success) {
            session.setSession(response.session_token, response.user_id);
            showAlert('Login erfolgreich!', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
            return true;
        } else {
            showAlert(response.message, 'error');
            return false;
        }
    } catch (error) {
        showAlert('Login-Fehler: ' + error.message, 'error');
        return false;
    }
}

async function logout() {
    try {
        await api.post('auth/logout', {
            session_token: session.sessionToken
        });
        session.clearSession();
        window.location.href = 'index.html';
    } catch (error) {
        console.error('Logout error:', error);
    }
}

/**
 * Pet Management Functions
 */
async function createPet(data) {
    try {
        if (!session.isAuthenticated()) {
            showAlert('Bitte melden Sie sich an', 'error');
            return false;
        }
        
        const response = await api.post('pets/pet/create', {
            session_token: session.sessionToken,
            ...data
        });
        
        if (response.success) {
            showAlert('Tier erfolgreich registriert!', 'success');
            return response.pet_id;
        } else {
            showAlert(response.message, 'error');
            return false;
        }
    } catch (error) {
        showAlert('Fehler: ' + error.message, 'error');
        return false;
    }
}

async function getMissingPets(limit = 50, offset = 0) {
    try {
        const response = await api.get('pets/missing', { limit, offset });
        if (!response.success) {
            throw new Error(response.message || 'Fehler beim Laden vermisster Tiere');
        }
        return response.pets || [];
    } catch (error) {
        console.error('Error fetching missing pets:', error);
        throw error;
    }
}

async function getUserPets() {
    try {
        const response = await api.get('pets/list', {
            session_token: session.sessionToken
        });
        return response.success ? response.pets : [];
    } catch (error) {
        console.error('Error fetching user pets:', error);
        return [];
    }
}

async function markPetAsMissing(petId, location, dateMissing, description) {
    try {
        const response = await api.post('pets/missing', {
            session_token: session.sessionToken,
            pet_id: petId,
            location,
            date_missing: dateMissing,
            description
        });
        
        if (response.success) {
            showAlert('Tier als vermisst markiert', 'success');
            return true;
        } else {
            showAlert(response.message, 'error');
            return false;
        }
    } catch (error) {
        showAlert('Fehler: ' + error.message, 'error');
        return false;
    }
}

/**
 * Finder Report Functions (PUBLIC - no auth required)
 */
async function submitFinderReport(formData) {
    try {
        console.log('=== SUBMITTING FINDER REPORT ===');
        console.log('FormData entries:');
        for (let [key, value] of formData.entries()) {
            if (key === 'image') {
                console.log(key + ':', value.name, value.size + ' bytes');
            } else {
                console.log(key + ':', value);
            }
        }
        
        const response = await fetch(`${API_BASE_URL}/finder/submit`, {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Response data:', data);
        
        if (data.success) {
            showAlert('Bericht erfolgreich eingereicht!', 'success');
            return {
                success: true,
                finder_report_id: data.finder_report_id,
                matches: data.matches
            };
        } else {
            showAlert(data.message || 'Fehler beim Einreichen', 'error');
            return { success: false };
        }
    } catch (error) {
        console.error('Submit error:', error);
        showAlert('Fehler beim Hochladen: ' + error.message, 'error');
        return { success: false };
    }
}

/**
 * UI Helper Functions
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;" onclick="this.parentElement.remove()">×</button>
    `;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove nach 5 Sekunden
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function showSpinner(show = true) {
    let spinner = document.getElementById('spinner');
    if (show) {
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.id = 'spinner';
            spinner.className = 'spinner';
            spinner.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;';
            document.body.appendChild(spinner);
        }
    } else if (spinner) {
        spinner.remove();
    }
}

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('de-DE', options);
}

function truncate(text, length = 100) {
    return text.length > length ? text.substring(0, length) + '...' : text;
}

/**
 * Drag & Drop Image Handler
 */
class ImageUploader {
    constructor(dropZoneId, fileInputId, onFileSelected) {
        this.dropZone = document.getElementById(dropZoneId);
        this.fileInput = document.getElementById(fileInputId);
        this.onFileSelected = onFileSelected;
        
        this.initEventListeners();
    }
    
    initEventListeners() {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, this.preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => this.highlight(), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => this.unhighlight(), false);
        });
        
        this.dropZone.addEventListener('drop', (e) => this.handleDrop(e), false);
        this.dropZone.addEventListener('click', () => this.fileInput.click());
        this.fileInput.addEventListener('change', (e) => this.handleFiles(e.target.files));
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    highlight() {
        this.dropZone.classList.add('dragover');
    }
    
    unhighlight() {
        this.dropZone.classList.remove('dragover');
    }
    
    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        this.handleFiles(files);
    }
    
    handleFiles(files) {
        if (files.length === 0) return;
        
        const file = files[0];
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            showAlert('Bitte wählen Sie ein Bild aus', 'error');
            return;
        }
        
        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            showAlert('Datei zu groß (max. 10MB)', 'error');
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            this.onFileSelected(file);
        };
        reader.readAsDataURL(file);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if user is logged in and redirect if needed
    if (!session.isAuthenticated()) {
        const protectedPages = ['dashboard.html', 'my-pets.html', 'profile.html'];
        const currentPage = window.location.pathname.split('/').pop();
        
        if (protectedPages.includes(currentPage)) {
            window.location.href = 'login.html';
        }
    }
});
