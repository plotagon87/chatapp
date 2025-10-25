console.log('chat.js loaded successfully');
// Global variables
let currentChatUser = null;
let messageCheckInterval = null;
let isSending = false;


// CSRF token and base URL come from the page (dashboard.php). Provide safe fallbacks.
const csrfToken = (typeof csrfToken !== 'undefined') ? csrfToken : (window.csrfToken || '');
const base = (typeof baseUrl !== 'undefined') ? baseUrl.replace(/\/$/, '') : (window.baseUrl ? window.baseUrl.replace(/\/$/, '') : '/chatapp');

// User menu toggle
document.getElementById('userMenuBtn')?.addEventListener('click', function() {
    document.getElementById('userMenu').classList.toggle('hidden');
});

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#userMenuBtn') && !e.target.closest('#userMenu')) {
        document.getElementById('userMenu').classList.add('hidden');
    }
});

// User item click
document.querySelectorAll('.user-item').forEach(item => {
    item.addEventListener('click', function() {
        const userId = this.dataset.userId;
        const fullName = this.dataset.fullname;
        const username = this.dataset.username;
        const profilePicture = this.dataset.profilePicture || 'default.png';
        
        openChat(userId, fullName, username, profilePicture);
    });
});

// Search users with debouncing
let searchTimeout;
document.getElementById('searchUsers')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = this.value.toLowerCase().trim();
        document.querySelectorAll('.user-item').forEach(item => {
            const fullName = item.dataset.fullname.toLowerCase();
            const username = item.dataset.username.toLowerCase();
            
            if (fullName.includes(searchTerm) || username.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }, 300);
});

// Open chat with user
function openChat(userId, fullName, username, profilePicture) {
    if (currentChatUser === userId) return;
    
    currentChatUser = userId;
    
    // Clear previous interval
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
    }
    
    // Update chat header
    document.getElementById('chatHeader').innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="uploads/profiles/${profilePicture}" 
                     alt="${fullName}" 
                     class="w-10 h-10 rounded-full"
                     onerror="this.src='assets/images/default.png'">
                <div>
                    <p class="font-semibold text-gray-800">${fullName}</p>
                    <p class="text-xs text-gray-500">@${username}</p>
                </div>
            // Event bindings and chat logic are handled inside ChatApp class below
        this.base = (typeof base !== 'undefined') ? base : (window.baseUrl ? window.baseUrl.replace(/\/$/, '') : '');
        this.init();
    }

    init() {
        this.bindEvents();
        console.log('Chat app initialized');
    }

    bindEvents() {
        // User menu
        this.bindUserMenu();
        
        // User interactions
        this.bindUserClicks();
        
        // Message sending (guarded)
        this.bindMessageSending();

        // File upload (guarded)
        this.bindFileUpload();
    }

    bindUserMenu() {
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userMenu = document.getElementById('userMenu');

        if (userMenuBtn && userMenu) {
            console.debug('bindUserMenu: handlers attached');
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                userMenu.classList.add('hidden');
            });
        }
    }

    bindUserClicks() {
        console.debug('bindUserClicks: attaching document click handler');
        document.addEventListener('click', (e) => {
            try {
                const userItem = e.target.closest('.user-item');
                if (userItem) {
                    console.debug('userItem clicked', userItem.dataset.userId);
                    this.handleUserItemClick(userItem);
                }

                const clearChatBtn = e.target.closest('#clearChatBtn');
                if (clearChatBtn) {
                    this.clearChat();
                }
            } catch (err) {
                console.error('Error in bindUserClicks handler', err);
            }
        });
    }

    handleUserItemClick(userItem) {
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePicture = userItem.dataset.profilePicture || 'default.png';
        
        // call the global function which is defined in this file
        this.openChat(userId, fullName, username, profilePicture);
    }

    // Open chat with user
    openChat(userId, fullName, username, profilePicture) {
        if (this.currentChatUser === userId) return;

        this.currentChatUser = userId;

        // Clear previous interval
        if (this.messageCheckInterval) {
            clearInterval(this.messageCheckInterval);
            this.messageCheckInterval = null;
        }

        // Update chat header
        const chatHeader = document.getElementById('chatHeader');
        if (chatHeader) {
            chatHeader.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img src="${this.base}/uploads/profiles/${profilePicture}" 
                             alt="${fullName}" 
                             class="w-10 h-10 rounded-full"
                             onerror="this.src='${this.base}/assets/images/default.png'">
                        <div>
                            <p class="font-semibold text-gray-800">${fullName}</p>
                            <p class="text-xs text-gray-500">@${username}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span id="typingIndicator" class="text-xs text-gray-500 hidden">typing...</span>
                        <button onclick="window.chatApp.clearChat()" class="text-gray-500 hover:text-red-600 transition duration-200">
                            // Lightweight Chat client
                            // Expects these globals to be set in the page by PHP (dashboard.php):
                            //   - currentUserId (number|null)
                            //   - csrfToken (string)
                            //   - baseUrl (string, no trailing slash)

                            (function () {
                                'use strict';

                                // Read server-provided globals with safe fallbacks
                                const currentUserId = (typeof currentUserId !== 'undefined') ? currentUserId : (window.currentUserId || null);
                                const csrfToken = (typeof csrfToken !== 'undefined') ? csrfToken : (window.csrfToken || '');
                                const base = (typeof baseUrl !== 'undefined') ? baseUrl.replace(/\/$/, '') : (window.baseUrl ? window.baseUrl.replace(/\/$/, '') : '');

                                function showNotification(message, type = 'info') {
                                    const types = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
                                    const notification = document.createElement('div');
                                    notification.className = `fixed top-4 right-4 ${types[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
                                    notification.textContent = message;
                                    document.body.appendChild(notification);
                                    setTimeout(() => notification.remove(), 4500);
                                }

                                class ChatApp {
                                    constructor() {
                                        this.currentChatUser = null;
                                        this.pollInterval = null;
                                        this.isSending = false;
                                        this.init();
                                    }

                                    init() {
                                        try {
                                            this.bindEvents();
                                            console.debug('ChatApp initialized');
                                        } catch (err) {
                                            console.error('ChatApp.init error', err);
                                        }
                                    }

                                    bindEvents() {
                                        this.bindUserMenu();
                                        this.bindUserClicks();
                                        this.bindSearch();
                                        this.bindMessageSending();
                                        this.bindFileUpload();
                                        this.bindVisibility();
                                    }

                                    bindUserMenu() {
                                        const btn = document.getElementById('userMenuBtn');
                                        const menu = document.getElementById('userMenu');
                                        if (!btn || !menu) return;
                                        // toggle with stopPropagation so document click doesn't immediately hide it
                                        btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
                                        document.addEventListener('click', (e) => {
                                            if (!e.target.closest('#userMenu') && !e.target.closest('#userMenuBtn')) menu.classList.add('hidden');
                                        });
                                    }

                                    bindUserClicks() {
                                        document.addEventListener('click', (e) => {
                                            const userItem = e.target.closest('.user-item');
                                            if (userItem) {
                                                const id = userItem.dataset.userId;
                                                const full = userItem.dataset.fullname;
                                                const username = userItem.dataset.username;
                                                const pic = userItem.dataset.profilePicture || 'default.png';
                                                this.openChat(id, full, username, pic);
                                            }
                                            const clearBtn = e.target.closest('#clearChatBtn');
                                            if (clearBtn) this.clearChat();
                                        });
                                    }

                                    bindSearch() {
                                        const input = document.getElementById('searchUsers');
                                        if (!input) return;
                                        let t;
                                        input.addEventListener('input', function () {
                                            clearTimeout(t);
                                            const val = this.value.toLowerCase().trim();
                                            t = setTimeout(() => {
                                                document.querySelectorAll('.user-item').forEach(it => {
                                                    const full = (it.dataset.fullname || '').toLowerCase();
                                                    const user = (it.dataset.username || '').toLowerCase();
                                                    it.style.display = (full.includes(val) || user.includes(val)) ? '' : 'none';
                                                });
                                            }, 180);
                                        });
                                    }

                                    bindMessageSending() {
                                        const form = document.getElementById('messageForm');
                                        if (!form) return;
                                        if (form.__bound) return; form.__bound = true;
                                        form.addEventListener('submit', async (e) => {
                                            e.preventDefault();
                                            if (this.isSending) return;
                                            const msgEl = document.getElementById('messageInput');
                                            const receiverEl = document.getElementById('receiverId');
                                            const text = msgEl ? msgEl.value.trim() : '';
                                            const receiver = receiverEl ? receiverEl.value : null;
                                            if (!text || !receiver) return;
                                            this.isSending = true;
                                            const submitBtn = form.querySelector('button[type="submit"]');
                                            const original = submitBtn ? submitBtn.innerHTML : '';
                                            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = 'Sending...'; }
                                            try {
                                                const fd = new FormData();
                                                fd.append('receiver_id', receiver);
                                                fd.append('message_text', text);
                                                fd.append('csrf_token', csrfToken);
                                                const res = await fetch(`${base}/chat/send_message.php`, { method: 'POST', body: fd });
                                                const data = await res.json();
                                                if (data.success) { if (msgEl) msgEl.value = ''; this.loadMessages(receiver); }
                                                else showNotification(data.message || 'Failed to send', 'error');
                                            } catch (err) { console.error(err); showNotification('Network error', 'error'); }
                                            finally { if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = original; } this.isSending = false; }
                                        });
                                    }

                                    bindFileUpload() {
                                        const btn = document.getElementById('fileUploadBtn');
                                        const input = document.getElementById('fileInput');
                                        if (!btn || !input) return;
                                        btn.addEventListener('click', () => input.click());
                                        input.addEventListener('change', function () {
                                            if (this.files && this.files[0]) {
                                                showNotification('File upload not implemented', 'warning');
                                            }
                                        });
                                    }

                                    bindVisibility() {
                                        document.addEventListener('visibilitychange', () => {
                                            if (document.hidden && this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
                                            else if (!document.hidden && this.currentChatUser && !this.pollInterval) this.pollInterval = setInterval(() => this.loadMessages(this.currentChatUser), 3000);
                                        });
                                    }

                                    async loadMessages(userId) {
                                        if (!userId) return;
                                        try {
                                            const res = await fetch(`${base}/chat/get_messages.php?user_id=${userId}&t=${Date.now()}`);
                                            if (!res.ok) throw new Error('HTTP ' + res.status);
                                            const json = await res.json();
                                            if (json.success) this.displayMessages(json.messages);
                                            else console.warn('loadMessages:', json.message || 'no data');
                                        } catch (err) { console.error('loadMessages err', err); }
                                    }

                                    displayMessages(messages) {
                                        const container = document.getElementById('chatMessages');
                                        if (!container) return;
                                        if (!messages || messages.length === 0) { container.innerHTML = '<p class="text-center text-gray-500 py-8">No messages yet</p>'; return; }
                                        let html = '';
                                        messages.forEach(m => {
                                            const sent = (m.sender_id == currentUserId);
                                            html += `<div class="mb-3 ${sent ? 'text-right' : 'text-left'}"><div class="inline-block bg-white p-3 rounded shadow">${this.escapeHtml(m.message_text || '')}</div></div>`;
                                        });
                                        container.innerHTML = html;
                                        setTimeout(() => container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' }), 80);
                                    }

                                    openChat(userId, fullName, username, profilePicture) {
                                        if (!userId) return;
                                        this.currentChatUser = userId;
                                        // set receiver id
                                        const receiverEl = document.getElementById('receiverId'); if (receiverEl) receiverEl.value = userId;
                                        // show input area
                                        const area = document.getElementById('messageInputArea'); if (area) area.classList.remove('hidden');
                                        // update header
                                        const header = document.getElementById('chatHeader'); if (header) header.innerText = fullName + ' (@' + username + ')';
                                        // load history
                                        this.loadMessages(userId);
                                        if (this.pollInterval) clearInterval(this.pollInterval);
                                        this.pollInterval = setInterval(() => this.loadMessages(userId), 3000);
                                    }

                                    clearChat() { this.currentChatUser = null; if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; } const area = document.getElementById('messageInputArea'); if (area) area.classList.add('hidden'); const header = document.getElementById('chatHeader'); if (header) header.innerText = 'No conversation selected'; }

                                    escapeHtml(text) { const d = document.createElement('div'); d.textContent = text || ''; return d.innerHTML; }
                                }

                                // instantiate after DOM ready
                                document.addEventListener('DOMContentLoaded', () => {
                                    try {
                                        window.chatApp = new ChatApp();
                                        // expose backwards-compatible globals
                                        window.openChat = (u,f,un,p) => window.chatApp.openChat(u,f,un,p);
                                        window.loadMessages = (u) => window.chatApp.loadMessages(u);
                                        window.clearChat = () => window.chatApp.clearChat();
                                        console.debug('chatApp ready');
                                    } catch (err) { console.error('Failed to initialize chatApp', err); }
                                });
                            })();

            if (!messageText || !receiverId) return;

            isSending = true;
            const sendButton = this.querySelector('button[type="submit"]');
            const originalHtml = sendButton ? sendButton.innerHTML : '';
            if (sendButton) {
                sendButton.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
                sendButton.disabled = true;
            }

            try {
                const formData = new FormData();
                formData.append('receiver_id', receiverId);
                formData.append('message_text', messageText);
                formData.append('csrf_token', csrfToken);

                const response = await fetch(`${base}/chat/send_message.php`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (messageInput) messageInput.value = '';
                    if (typeof loadMessages === 'function') loadMessages(receiverId);
                } else {
                    showNotification(data.message || 'Failed to send message', 'error');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showNotification('Network error: Failed to send message', 'error');
            } finally {
                if (sendButton) {
                    sendButton.innerHTML = originalHtml;
                    sendButton.disabled = false;
                }
                isSending = false;
            }
        });
    }

    bindFileUpload() {
        const fileUploadBtn = document.getElementById('fileUploadBtn');
        const fileInput = document.getElementById('fileInput');
        if (!fileUploadBtn || !fileInput) return;

        if (fileInput.__chatBindingAttached) return;
        fileInput.__chatBindingAttached = true;

        fileUploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', function () {
            if (this.files.length > 0 && typeof uploadFile === 'function') {
                uploadFile(this.files[0]);
            }
        });
    }

    // ... include all the other methods from the improved code
    // openChat(), loadMessages(), displayMessages(), etc.
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Check if required global variables exist
    if (typeof currentUserId === 'undefined') {
        console.error('currentUserId is not defined. Make sure to include the inline script in dashboard.php');
        // still create the app but without currentUserId
        window.currentUserId = null;
    }

    window.chatApp = new ChatApp();

    // Expose global compatibility wrappers (so older code that calls openChat(), loadMessages() still works)
    window.openChat = function(userId, fullName, username, profilePicture) {
        if (window.chatApp && typeof window.chatApp.openChat === 'function') {
            window.chatApp.openChat(userId, fullName, username, profilePicture);
        }
    };

    window.loadMessages = function(userId) {
        if (window.chatApp && typeof window.chatApp.loadMessages === 'function') {
            window.chatApp.loadMessages(userId);
        }
    };

    window.clearChat = function() {
        if (window.chatApp && typeof window.chatApp.clearChat === 'function') {
            window.chatApp.clearChat();
        }
    };
});