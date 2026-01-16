// ============================================
// chat.js - LAN Chat Application
// Fixed: Auto-scroll, Polling, Profile Pictures
// ============================================

console.log('🔧 chat.js loading...');

if (typeof currentUserId === 'undefined') {
    console.error('❌ FATAL ERROR: currentUserId is not defined!');
}

console.log('=== CHAT.JS VARIABLE CHECK ===');
console.log('currentUserId:', typeof currentUserId !== 'undefined' ? currentUserId : 'NOT FOUND');
console.log('csrfToken:', typeof csrfToken !== 'undefined' ? 'FOUND' : 'NOT FOUND');
console.log('baseUrl:', typeof baseUrl !== 'undefined' ? baseUrl : 'NOT FOUND');
console.log('==============================');

class SimpleChat {
    constructor() {
        this.currentUserId = typeof currentUserId !== 'undefined' ? currentUserId : window.currentUserId;
        
        if (!this.currentUserId) {
            console.error('❌ CRITICAL: SimpleChat initialized without currentUserId!');
            alert('Error: User ID not found. Please refresh the page.');
            return;
        }
        
        console.log('✅ SimpleChat initialized with currentUserId:', this.currentUserId);
        
        this.currentChatUser = null;
        this.pollInterval = null;
        this.typingTimeout = null;
        this.typingCheckInterval = null;
        this.isTyping = false;
        this.isUserScrolling = false; // NEW: Track if user is manually scrolling
        this.lastMessageCount = 0; // NEW: Track message count to prevent unnecessary updates
        
        this.init();
    }

    init() {
        console.log('🔄 Initializing chat...');
        this.bindEvents();
        this.initPullToRefresh();
        this.initEmojiPicker();
        this.initScrollDetection(); // NEW: Detect manual scrolling
        console.log('✅ Chat initialized');
    }

    bindEvents() {
        console.log('🔗 Binding events...');
        this.bindUserMenu();
        this.bindUserClicks();
        this.bindMessageSending();
        this.bindSearch();
        this.bindTypingIndicator();
        this.bindFileUpload(); // NEW: File upload functionality
    }

    // ============================================
    // NEW: Detect Manual Scrolling
    // ============================================
    initScrollDetection() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        let scrollTimeout;
        chatMessages.addEventListener('scroll', () => {
            // User is scrolling
            this.isUserScrolling = true;
            
            // Clear existing timeout
            if (scrollTimeout) clearTimeout(scrollTimeout);
            
            // After 2 seconds of no scrolling, check if at bottom
            scrollTimeout = setTimeout(() => {
                const container = document.getElementById('chatMessages');
                if (container) {
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                    this.isUserScrolling = !isAtBottom;
                }
            }, 2000);
        });
    }

    // ============================================
    // Typing Indicator
    // ============================================
    bindTypingIndicator() {
        const messageInput = document.getElementById('messageInput');
        if (!messageInput) return;
        
        messageInput.addEventListener('input', () => {
            if (!this.currentChatUser) return;
            
            this.sendTypingStatus(true);
            
            if (this.typingTimeout) {
                clearTimeout(this.typingTimeout);
            }
            
            this.typingTimeout = setTimeout(() => {
                this.sendTypingStatus(false);
            }, 3000);
        });
        
        const form = document.getElementById('messageForm');
        if (form) {
            form.addEventListener('submit', () => {
                this.sendTypingStatus(false);
            });
        }
    }

    async sendTypingStatus(isTyping) {
        if (!this.currentChatUser) return;
        if (this.isTyping === isTyping) return;
        this.isTyping = isTyping;
        
        try {
            const formData = new FormData();
            formData.append('chat_with', this.currentChatUser);
            formData.append('is_typing', isTyping ? '1' : '0');
            
            await fetch('chat/typing_status.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('❌ Typing status error:', error);
        }
    }

    startTypingCheck(userId) {
        if (this.typingCheckInterval) {
            clearInterval(this.typingCheckInterval);
        }
        
        this.typingCheckInterval = setInterval(async () => {
            if (this.currentChatUser !== userId) return;
            
            try {
                const response = await fetch(`chat/check_typing.php?chat_with=${userId}`);
                const data = await response.json();
                
                if (data.success && data.is_typing) {
                    this.showTypingIndicator(data.user_name);
                } else {
                    this.hideTypingIndicator();
                }
            } catch (error) {
                console.error('❌ Check typing error:', error);
            }
        }, 1000);
    }

    showTypingIndicator(userName) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        const existing = document.getElementById('typingIndicator');
        if (existing) existing.remove();
        
        const indicator = document.createElement('div');
        indicator.id = 'typingIndicator';
        indicator.className = 'flex justify-start mb-4';
        indicator.innerHTML = `
            <div class="bg-gray-200 text-gray-600 rounded-lg p-3 shadow">
                <div class="flex items-center space-x-2">
                    <div class="typing-dots flex space-x-1">
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                    <span class="text-xs">${userName || 'User'} is typing...</span>
                </div>
            </div>
        `;
        
        container.appendChild(indicator);
        if (!this.isUserScrolling) {
            this.scrollToBottom();
        }
    }

    hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.remove();
    }

    // ============================================
    // Emoji Picker
    // ============================================
    initEmojiPicker() {
        this.emojis = {
            'Smileys': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔'],
            'Gestures': ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '🤝', '🙏', '💪'],
            'Hearts': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟'],
            'Objects': ['📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '📷', '📸', '📹', '🎥', '📞', '☎️', '📺', '📻', '🎙️', '⏰', '🔋', '🔌', '💡', '🔦'],
            'Symbols': ['✅', '❌', '⭐', '🌟', '💫', '✨', '🔥', '💯', '🎉', '🎊', '🎈', '🎁', '🏆', '🥇', '🥈', '🥉', '⚡', '💥']
        };
        
        this.createEmojiButton();
    }

    createEmojiButton() {
        this.emojiButtonHTML = `
            <button type="button" id="emojiPickerBtn" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        `;
    }

    showEmojiPicker() {
        const existing = document.getElementById('emojiPickerModal');
        if (existing) {
            existing.remove();
            return;
        }
        
        const picker = document.createElement('div');
        picker.id = 'emojiPickerModal';
        picker.className = 'fixed bottom-20 right-4 md:bottom-24 md:right-8 bg-white rounded-lg shadow-2xl z-50 w-80 max-h-96 overflow-hidden border border-gray-200';
        
        let html = '<div class="p-4">';
        html += '<div class="flex justify-between items-center mb-3">';
        html += '<h3 class="font-bold text-gray-800">Emojis</h3>';
        html += '<button onclick="window.simpleChat.hideEmojiPicker()" class="text-gray-500 hover:text-gray-700">';
        html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        html += '</button></div>';
        
        html += '<div class="overflow-y-auto max-h-80 space-y-4">';
        
        for (const [category, emojis] of Object.entries(this.emojis)) {
            html += `<div>`;
            html += `<h4 class="text-xs font-semibold text-gray-500 mb-2">${category}</h4>`;
            html += `<div class="grid grid-cols-8 gap-2">`;
            
            emojis.forEach(emoji => {
                html += `<button type="button" onclick="window.simpleChat.insertEmoji('${emoji}')" class="text-2xl hover:bg-gray-100 rounded p-1 transition">${emoji}</button>`;
            });
            
            html += `</div></div>`;
        }
        
        html += '</div></div>';
        picker.innerHTML = html;
        
        document.body.appendChild(picker);
    }

    hideEmojiPicker() {
        const picker = document.getElementById('emojiPickerModal');
        if (picker) picker.remove();
    }

    insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        if (!input) return;
        
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const text = input.value;
        
        input.value = text.substring(0, start) + emoji + text.substring(end);
        
        const newPos = start + emoji.length;
        input.setSelectionRange(newPos, newPos);
        input.focus();
        
        this.sendTypingStatus(true);
    }

    // ============================================
    // NEW: File Upload
    // ============================================
    bindFileUpload() {
        this.fileUploadButtonHTML = `
            <button type="button" id="fileUploadBtn" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                </svg>
            </button>
            <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf,.doc,.docx,.txt,.zip">
        `;
    }

    initFileUpload(receiverId) {
        const fileBtn = document.getElementById('fileUploadBtn');
        const fileInput = document.getElementById('fileInput');
        
        if (fileBtn && fileInput) {
            fileBtn.onclick = () => fileInput.click();
            
            fileInput.onchange = async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                if (file.size > 10485760) { // 10MB
                    alert('File size exceeds 10MB limit');
                    return;
                }
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('receiver_id', receiverId);
                
                try {
                    const response = await fetch('chat/upload_file.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        this.loadMessages(receiverId);
                    } else {
                        alert('Failed to upload file: ' + data.message);
                    }
                } catch (error) {
                    console.error('File upload error:', error);
                    alert('Failed to upload file');
                }
                
                fileInput.value = '';
            };
        }
    }

    // ============================================
    // User Menu Toggle
    // ============================================
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

    // ============================================
    // User List Click Handler
    // ============================================
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

    // ============================================
    // Message Sending
    // ============================================
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

    // ============================================
    // Search Functionality
    // ============================================
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

    // ============================================
    // Open Chat Window
    // ============================================
    openChat(userId, fullName, username, profilePicture) {
        console.log('🚀 Opening chat with user:', userId);
        
        this.currentChatUser = userId;
        this.lastMessageCount = 0; // Reset message count
        this.isUserScrolling = false; // Reset scroll state
        this.updateChatHeader(fullName, username, profilePicture);
        
        const inputArea = document.getElementById('messageInputArea');
        if (inputArea) {
            inputArea.classList.remove('hidden');
            
            // Add emoji and file buttons
            if (!document.getElementById('emojiPickerBtn')) {
                const form = document.getElementById('messageForm');
                if (form) {
                    const container = form.querySelector('.flex');
                    if (container) {
                        // Add emoji button
                        const emojiWrapper = document.createElement('div');
                        emojiWrapper.innerHTML = this.emojiButtonHTML;
                        container.insertBefore(emojiWrapper.firstElementChild, container.firstElementChild);
                        
                        document.getElementById('emojiPickerBtn').addEventListener('click', () => {
                            this.showEmojiPicker();
                        });
                        
                        // Add file upload button
                        const fileWrapper = document.createElement('div');
                        fileWrapper.innerHTML = this.fileUploadButtonHTML;
                        const fileBtn = fileWrapper.querySelector('button');
                        const fileInput = fileWrapper.querySelector('input');
                        container.insertBefore(fileBtn, container.firstElementChild);
                        container.insertBefore(fileInput, container.firstElementChild);
                        
                        this.initFileUpload(userId);
                    }
                }
            }
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
        this.startTypingCheck(userId);
    }

    updateChatHeader(fullName, username, profilePicture) {
        const header = document.getElementById('chatHeader');
        if (!header) return;
        
        let profilePicPath;
        if (profilePicture === 'default.png' || !profilePicture) {
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

    // ============================================
    // Load Messages (FIXED)
    // ============================================
    async loadMessages(userId) {
        if (!userId) return;
        
        try {
            const response = await fetch(`chat/get_messages.php?user_id=${userId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Only update if message count changed
                if (data.messages.length !== this.lastMessageCount) {
                    this.lastMessageCount = data.messages.length;
                    this.displayMessages(data.messages);
                }
            } else {
                console.error('❌ Failed to load messages:', data.message);
            }
        } catch (error) {
            console.error('❌ Error loading messages:', error);
        }
    }

    // ============================================
    // Display Messages (FIXED)
    // ============================================
    displayMessages(messages) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
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
            const senderId = parseInt(msg.sender_id);
            const isSent = senderId === parseInt(this.currentUserId);
            
            const alignClass = isSent ? 'justify-end' : 'justify-start';
            const bgClass = isSent 
                ? 'bg-purple-600 text-white'
                : 'bg-white text-gray-800 border border-gray-200';
            
            html += `
                <div class="flex ${alignClass} mb-4">
                    <div class="message-bubble ${bgClass} rounded-lg p-3 shadow">
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
        
        // Only auto-scroll if user is NOT manually viewing old messages
        if (!this.isUserScrolling) {
            this.scrollToBottom();
        }
    }

    // ============================================
    // Send Message
    // ============================================
    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const receiverInput = document.getElementById('receiverId');
        
        const message = messageInput?.value.trim();
        const receiverId = receiverInput?.value;
        
        if (!message || !receiverId) {
            return;
        }
        
        this.sendTypingStatus(false);
        
        try {
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message_text', message);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : '');
            
            const response = await fetch(`chat/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                messageInput.value = '';
                this.isUserScrolling = false; // Reset scroll state when sending
                this.loadMessages(receiverId);
                this.hideEmojiPicker();
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Send error:', error);
            alert('Network error: Failed to send message.');
        }
    }

    // ============================================
    // Polling (FIXED - Reduced frequency)
    // ============================================
    startPolling(userId) {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        // Reduced from 3 seconds to 5 seconds
        this.pollInterval = setInterval(() => {
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 5000); // Changed from 3000 to 5000
    }

    // ============================================
    // Clear Chat
    // ============================================
    clearChat() {
        this.currentChatUser = null;
        this.lastMessageCount = 0;
        this.isUserScrolling = false;
        
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        if (this.typingCheckInterval) {
            clearInterval(this.typingCheckInterval);
            this.typingCheckInterval = null;
        }
        
        this.hideEmojiPicker();
        this.hideTypingIndicator();
        
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

    // ============================================
    // Scroll to Bottom (FIXED)
    // ============================================
    scrollToBottom(smooth = true) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        if (smooth && 'scrollBehavior' in document.documentElement.style) {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth'
            });
        } else {
            container.scrollTop = container.scrollHeight;
        }
    }

    // ============================================
    // Pull to Refresh
    // ============================================
    initPullToRefresh() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        let touchStartY = 0;
        let pulling = false;
        
        chatMessages.addEventListener('touchstart', (e) => {
            if (chatMessages.scrollTop === 0) {
                touchStartY = e.touches[0].clientY;
                pulling = true;
            }
        });
        
        chatMessages.addEventListener('touchmove', (e) => {
            if (!pulling) return;
            
            const touchY = e.touches[0].clientY;
            const pullDistance = touchY - touchStartY;
            
            if (pullDistance > 100 && this.currentChatUser) {
                this.loadMessages(this.currentChatUser);
                pulling = false;
            }
        });
        
        chatMessages.addEventListener('touchend', () => {
            pulling = false;
        });
    }

    // ============================================
    // Utilities
    // ============================================
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatTime(timestamp) {
        if (!timestamp) return 'Just now';
        
        try {
            let date;
            
            if (timestamp instanceof Date) {
                date = timestamp;
            } else if (typeof timestamp === 'string') {
                date = new Date(timestamp);
                
                if (isNaN(date.getTime())) {
                    date = new Date(timestamp.replace(' ', 'T'));
                }
            } else {
                date = new Date(timestamp);
            }
            
            if (isNaN(date.getTime())) {
                console.warn('Invalid date:', timestamp);
                return 'Recently';
            }
            
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

// ============================================
// Initialize Chat
// ============================================
console.log('🔧 Setting up DOM ready listener...');

function initializeChat() {
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
    setTimeout(initializeChat, 100);
}