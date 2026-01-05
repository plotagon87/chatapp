// assets/js/chat.js - FIXED VERSION WITH MESSAGE ALIGNMENT AND TIME
console.log('🔧 chat.js loading...');

// Emergency fallback for baseUrl
if (typeof window.baseUrl === 'undefined' || !window.baseUrl) {
    console.warn('⚠️ baseUrl is undefined, using relative paths');
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    window.baseUrl = window.location.origin + basePath;
    console.log('🔄 Computed baseUrl:', window.baseUrl);
}

class SimpleChat {
    constructor() {
        this.currentChatUser = null;
        this.pollInterval = null;
        console.log('✅ SimpleChat constructor called');
        this.init();
    }

    init() {
        console.log('🔄 Initializing chat...');
        this.bindEvents();
        console.log('✅ Chat initialized');
    }

    bindEvents() {
        console.log('🔗 Binding events...');
        
        this.bindUserMenu();
        this.bindUserClicks();
        this.bindMessageSending();
        this.bindSearch();
    }

    bindUserMenu() {
        const menuBtn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userMenu');
        
        if (menuBtn && menu) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            
            document.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        }
    }

    bindUserClicks() {
        console.log('👥 Binding user clicks...');
        
        document.addEventListener('click', (e) => {
            const userItem = e.target.closest('.user-item');
            if (userItem) {
                console.log('🎯 User item clicked!');
                this.handleUserClick(userItem);
            }
        });
    }

    handleUserClick(userItem) {
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePic = userItem.dataset.profilePicture || 'default.png';
        
        console.log('💬 Opening chat with:', { userId, fullName });
        
        if (!userId) {
            console.error('❌ No user ID found');
            return;
        }
        
        this.openChat(userId, fullName, username, profilePic);
    }

    bindMessageSending() {
        const form = document.getElementById('messageForm');
        if (!form) {
            console.warn('❌ Message form not found');
            return;
        }
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        console.log('✅ Message sending bound');
    }

    bindSearch() {
        const searchInput = document.getElementById('searchUsers');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            this.filterUsers(term);
        });
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

    openChat(userId, fullName, username, profilePicture) {
        console.log('🚀 Opening chat with user:', userId);
        
        this.currentChatUser = userId;
        
        this.updateChatHeader(fullName, username, profilePicture);
        
        const inputArea = document.getElementById('messageInputArea');
        if (inputArea) {
            inputArea.classList.remove('hidden');
        }
        
        const receiverInput = document.getElementById('receiverId');
        if (receiverInput) {
            receiverInput.value = userId;
        }
        
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.focus();
        }
        
        this.loadMessages(userId);
        this.startPolling(userId);
    }

    updateChatHeader(fullName, username, profilePicture) {
        const header = document.getElementById('chatHeader');
        if (!header) return;
        
        // FIXED: Handle both default and uploaded profile pictures
        let profilePicPath;
        if (profilePicture === 'default.png') {
            profilePicPath = 'assets/images/default.png';
        } else {
            profilePicPath = `uploads/profiles/${profilePicture}`;
        }
        
        header.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <img src="${profilePicPath}" 
                         alt="${fullName}" 
                         class="w-10 h-10 rounded-full"
                         onerror="this.src='assets/images/default.png'">
                    <div>
                        <p class="font-semibold text-gray-800">${fullName}</p>
                        <p class="text-xs text-gray-500">@${username}</p>
                    </div>
                </div>
                <button onclick="window.simpleChat.clearChat()" class="text-gray-500 hover:text-red-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
    }

    async loadMessages(userId) {
        console.log('📨 Loading messages for user:', userId);
        
        if (!userId) return;
        
        try {
            const response = await fetch(`chat/get_messages.php?user_id=${userId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('📩 Messages response:', data);
            
            if (data.success) {
                this.displayMessages(data.messages);
            } else {
                console.error('❌ Failed to load messages:', data.message);
            }
        } catch (error) {
            console.error('❌ Error loading messages:', error);
        }
    }

    displayMessages(messages) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        console.log('🖥️ Displaying messages:', messages?.length);
        
        if (!messages || messages.length === 0) {
            container.innerHTML = `
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
        messages.forEach(msg => {
            // FIXED: Proper message alignment
            const isSent = parseInt(msg.sender_id) === parseInt(window.currentUserId);
            const alignClass = isSent ? 'justify-end' : 'justify-start';
            const bgClass = isSent ? 'bg-purple-600 text-white' : 'bg-white text-gray-800 border border-gray-200';
            
            html += `
                <div class="flex ${alignClass} mb-4">
                    <div class="max-w-xs md:max-w-md ${bgClass} rounded-lg p-3 shadow">
                        <p class="break-words">${this.escapeHtml(msg.message_text)}</p>
                        <p class="text-xs ${isSent ? 'text-purple-200' : 'text-gray-500'} mt-1">
                            ${this.formatTime(msg.created_at)}
                            ${isSent ? (msg.is_read ? ' ✓✓' : ' ✓') : ''}
                        </p>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }

    async sendMessage() {
        console.log('📤 Sending message...');
        
        const messageInput = document.getElementById('messageInput');
        const receiverInput = document.getElementById('receiverId');
        
        const message = messageInput?.value.trim();
        const receiverId = receiverInput?.value;
        
        if (!message || !receiverId) {
            console.warn('❌ Cannot send: missing message or receiver');
            return;
        }
        
        console.log('💬 Sending to:', receiverId, 'Message:', message);
        
        try {
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message_text', message);
            formData.append('csrf_token', window.csrfToken || '');
            
            const response = await fetch(`chat/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('📨 Send response:', data);
            
            if (data.success) {
                messageInput.value = '';
                this.loadMessages(receiverId);
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Send error:', error);
            alert('Network error: Failed to send message. Check console for details.');
        }
    }

    startPolling(userId) {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        this.pollInterval = setInterval(() => {
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 3000);
    }

    clearChat() {
        console.log('🧹 Clearing chat...');
        
        this.currentChatUser = null;
        
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        const container = document.getElementById('chatMessages');
        const inputArea = document.getElementById('messageInputArea');
        const header = document.getElementById('chatHeader');
        
        if (container) {
            container.innerHTML = `
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
        if (header) {
            header.innerHTML = `
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

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatTime(timestamp) {
        if (!timestamp) return 'Just now';
        
        try {
            // Handle different timestamp formats
            let date;
            
            if (timestamp instanceof Date) {
                date = timestamp;
            } else if (typeof timestamp === 'string') {
                // Try different date formats
                date = new Date(timestamp);
                
                // If still invalid, try replacing spaces with T for ISO format
                if (isNaN(date.getTime())) {
                    date = new Date(timestamp.replace(' ', 'T'));
                }
            } else {
                date = new Date(timestamp);
            }
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                console.warn('Invalid date:', timestamp);
                return 'Recently';
            }
            
            const now = new Date();
            const diff = now - date;
            
            // Show relative time for recent messages
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) {
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: false 
                });
            }
            
            // For older messages, show date
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            console.error('Error formatting time:', error, timestamp);
            return 'Recently';
        }
    }
}

// Initialize when DOM is ready
console.log('🔧 Setting up DOM ready listener...');

function initializeChat() {
    // Prevent multiple initializations
    if (window.simpleChat) {
        console.warn('⚠️ simpleChat already initialized, skipping...');
        return;
    }
    
    try {
        console.log('🎉 DOM fully loaded - initializing chat...');
        window.simpleChat = new SimpleChat();
        console.log('✅ simpleChat initialized globally');
    } catch (error) {
        console.error('❌ Failed to initialize chat:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChat);
} else {
    // DOM already loaded
    setTimeout(initializeChat, 100);
}