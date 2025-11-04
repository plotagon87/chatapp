// assets/js/chat.js - Improved version with better error handling

console.log('chat.js loaded - checking initialization...');

// Global variables with safe fallbacks
const CURRENT_USER_ID = window.currentUserId || null;
const CSRF_TOKEN = window.csrfToken || '';
const BASE_URL = window.baseUrl ? window.baseUrl.replace(/\/$/, '') : '';

// Debug info
console.log('Chat initialized with:', { CURRENT_USER_ID, BASE_URL: BASE_URL || 'Not set' });

class ChatApp {
    constructor() {
        this.currentChatUser = null;
        this.pollInterval = null;
        this.isSending = false;
        
        // Wait a bit for DOM to be fully ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            setTimeout(() => this.init(), 100);
        }
    }

    init() {
        console.log('Initializing ChatApp...');
        try {
            this.bindEvents();
            this.startStatusUpdates();
            console.log('ChatApp initialized successfully');
        } catch (error) {
            console.error('Failed to initialize ChatApp:', error);
        }
    }

    bindEvents() {
        console.log('Binding events...');
        this.bindUserMenu();
        this.bindUserClicks();
        this.bindSearch();
        this.bindMessageSending();
        this.bindFileUpload();
        this.bindPageVisibility();
    }

    bindUserMenu() {
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userMenu = document.getElementById('userMenu');
        
        if (userMenuBtn && userMenu) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!userMenu.contains(e.target) && e.target !== userMenuBtn) {
                    userMenu.classList.add('hidden');
                }
            });
        }
    }

    bindUserClicks() {
        // Use event delegation for dynamic elements
        document.addEventListener('click', (e) => {
            const userItem = e.target.closest('.user-item');
            if (userItem) {
                this.handleUserClick(userItem);
            }
        });
    }

    handleUserClick(userItem) {
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePicture = userItem.dataset.profilePicture || 'default.png';
        
        if (userId) {
            this.openChat(userId, fullName, username, profilePicture);
        }
    }

    bindSearch() {
        const searchInput = document.getElementById('searchUsers');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filterUsers(e.target.value.toLowerCase());
                }, 300);
            });
        }
    }

    filterUsers(searchTerm) {
        document.querySelectorAll('.user-item').forEach(item => {
            const fullName = (item.dataset.fullname || '').toLowerCase();
            const username = (item.dataset.username || '').toLowerCase();
            
            if (fullName.includes(searchTerm) || username.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    bindMessageSending() {
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
        }
    }

    bindFileUpload() {
        const fileBtn = document.getElementById('fileUploadBtn');
        const fileInput = document.getElementById('fileInput');
        
        if (fileBtn && fileInput) {
            fileBtn.addEventListener('click', () => fileInput.click());
        }
    }

    bindPageVisibility() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else if (this.currentChatUser) {
                this.startPolling(this.currentChatUser);
            }
        });
    }

    openChat(userId, fullName, username, profilePicture) {
        if (this.currentChatUser === userId) return;
        
        console.log('Opening chat with:', { userId, fullName });
        this.currentChatUser = userId;
        
        // Update UI
        this.updateChatHeader(fullName, username, profilePicture);
        this.showMessageInput();
        
        // Load messages and start polling
        this.loadMessages(userId);
        this.startPolling(userId);
    }

    updateChatHeader(fullName, username, profilePicture) {
        const chatHeader = document.getElementById('chatHeader');
        if (chatHeader) {
            chatHeader.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img src="${BASE_URL}/uploads/profiles/${profilePicture}" 
                             alt="${fullName}" 
                             class="w-10 h-10 rounded-full"
                             onerror="this.src='${BASE_URL}/assets/images/default.png'">
                        <div>
                            <p class="font-semibold text-gray-800">${fullName}</p>
                            <p class="text-xs text-gray-500">@${username}</p>
                        </div>
                    </div>
                    <button onclick="window.chatApp.clearChat()" class="text-gray-500 hover:text-red-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
        }
    }

    showMessageInput() {
        const inputArea = document.getElementById('messageInputArea');
        const receiverInput = document.getElementById('receiverId');
        
        if (inputArea) inputArea.classList.remove('hidden');
        if (receiverInput) receiverInput.value = this.currentChatUser;
    }

    async loadMessages(userId) {
        if (!userId) return;
        
        try {
            const response = await fetch(`${BASE_URL}/chat/get_messages.php?user_id=${userId}&_=${Date.now()}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayMessages(data.messages);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    displayMessages(messages) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        if (!messages || messages.length === 0) {
            chatMessages.innerHTML = `
                <div class="flex items-center justify-center h-full text-gray-400">
                    <div class="text-center">
                        <p class="text-lg">No messages yet</p>
                        <p class="text-sm">Start the conversation!</p>
                    </div>
                </div>
            `;
            return;
        }
        
        let html = '';
        messages.forEach(message => {
            const isSent = parseInt(message.sender_id) === parseInt(CURRENT_USER_ID);
            const alignment = isSent ? 'justify-end' : 'justify-start';
            const bgColor = isSent ? 'bg-purple-600 text-white' : 'bg-white text-gray-800 border border-gray-200';
            
            html += `
                <div class="flex ${alignment} mb-4">
                    <div class="max-w-xs md:max-w-md ${bgColor} rounded-lg p-3 shadow">
                        <p class="break-words">${this.escapeHtml(message.message_text)}</p>
                        <p class="text-xs ${isSent ? 'text-purple-200' : 'text-gray-500'} mt-1">
                            ${this.formatTime(message.created_at)}
                        </p>
                    </div>
                </div>
            `;
        });
        
        chatMessages.innerHTML = html;
        
        // Scroll to bottom
        setTimeout(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }, 100);
    }

    async sendMessage() {
        if (this.isSending || !this.currentChatUser) return;
        
        const messageInput = document.getElementById('messageInput');
        const messageText = messageInput?.value.trim();
        
        if (!messageText) return;
        
        this.isSending = true;
        
        try {
            const formData = new FormData();
            formData.append('receiver_id', this.currentChatUser);
            formData.append('message_text', messageText);
            formData.append('csrf_token', CSRF_TOKEN);
            
            const response = await fetch(`${BASE_URL}/chat/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                messageInput.value = '';
                this.loadMessages(this.currentChatUser);
            } else {
                this.showNotification(data.message || 'Failed to send message', 'error');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showNotification('Network error', 'error');
        } finally {
            this.isSending = false;
        }
    }

    startPolling(userId) {
        this.stopPolling();
        this.pollInterval = setInterval(() => {
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 3000);
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    clearChat() {
        this.currentChatUser = null;
        this.stopPolling();
        
        const chatMessages = document.getElementById('chatMessages');
        const inputArea = document.getElementById('messageInputArea');
        const chatHeader = document.getElementById('chatHeader');
        
        if (chatMessages) {
            chatMessages.innerHTML = `
                <div class="flex items-center justify-center h-full text-gray-400">
                    <div class="text-center">
                        <svg class="w-20 h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-lg">No conversation selected</p>
                        <p class="text-sm">Choose a user from the list to start messaging</p>
                    </div>
                </div>
            `;
        }
        
        if (inputArea) inputArea.classList.add('hidden');
        if (chatHeader) {
            chatHeader.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="text-gray-500 flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span>Select a user to start chatting</span>
                    </div>
                </div>
            `;
        }
    }

    startStatusUpdates() {
        // Update user status every 30 seconds
        setInterval(() => {
            if (CURRENT_USER_ID) {
                fetch(`${BASE_URL}/api/update_status.php`, { method: 'POST' })
                    .catch(err => console.error('Status update failed:', err));
            }
        }, 30000);
    }

    showNotification(message, type = 'info') {
        // Simple notification implementation
        console.log(`${type.toUpperCase()}: ${message}`);
    }

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) {
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false 
            });
        }
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
    }
}

// Initialize the chat app when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.chatApp = new ChatApp();
    });
} else {
    window.chatApp = new ChatApp();
}