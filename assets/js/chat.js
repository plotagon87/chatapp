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
            </div>
            <div class="flex items-center space-x-2">
                <span id="typingIndicator" class="text-xs text-gray-500 hidden">typing...</span>
                <button onclick="clearChat()" class="text-gray-500 hover:text-red-600 transition duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    // Show message input
    document.getElementById('messageInputArea').classList.remove('hidden');
    document.getElementById('receiverId').value = userId;
    
    // Focus on input
    document.getElementById('messageInput').focus();
    
    // Load messages
    loadMessages(userId);
    
    // Start polling for new messages (consider WebSockets for better performance)
    messageCheckInterval = setInterval(() => {
        if (currentChatUser === userId) {
            loadMessages(userId);
        }
    }, 3000);
}

// Load messages with error handling
async function loadMessages(userId) {
    try {
    const response = await fetch(`${base}/chat/get_messages.php?user_id=${userId}&t=${Date.now()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            displayMessages(data.messages);
        } else {
            showNotification('Failed to load messages', 'error');
        }
    } catch (error) {
        console.error('Error loading messages:', error);
        // Don't show notification for every failed poll to avoid spam
    }
}

// Display messages with improved formatting
function displayMessages(messages) {
    const chatMessages = document.getElementById('chatMessages');
    
    if (messages.length === 0) {
        chatMessages.innerHTML = `
            <div class="flex items-center justify-center h-full text-gray-400">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-lg font-medium">No messages yet</p>
                    <p class="text-sm">Start the conversation!</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    let lastDate = null;
    
    messages.forEach(msg => {
        const isSent = msg.sender_id == currentUserId;
        const messageDate = new Date(msg.created_at).toDateString();
        
        // Add date separator if date changed
        if (lastDate !== messageDate) {
            lastDate = messageDate;
            html += `
                <div class="flex justify-center my-4">
                    <span class="bg-gray-200 text-gray-600 text-xs px-3 py-1 rounded-full">
                        ${formatDate(msg.created_at)}
                    </span>
                </div>
            `;
        }
        
        const alignClass = isSent ? 'justify-end' : 'justify-start';
        const bgClass = isSent ? 'bg-purple-600 text-white' : 'bg-white text-gray-800 border border-gray-200';
        const readStatus = isSent ? (msg.is_read ? '✓✓ Read' : '✓ Delivered') : '';
        
        html += `
            <div class="flex ${alignClass} mb-3">
                <div class="message-bubble ${bgClass} rounded-2xl p-3 shadow-sm max-w-xs lg:max-w-md">
                    ${msg.message_text ? `<p class="break-words">${escapeHtml(msg.message_text)}</p>` : ''}
                    ${msg.file_path ? `<div class="mt-2"><a href="${msg.file_path}" target="_blank" class="text-blue-400 hover:underline">📎 Attachment</a></div>` : ''}
                    <p class="text-xs ${isSent ? 'text-purple-200' : 'text-gray-500'} mt-1 flex justify-between items-center">
                        <span>${formatTime(msg.created_at)}</span>
                        ${readStatus ? `<span class="ml-2">${readStatus}</span>` : ''}
                    </p>
                </div>
            </div>
        `;
    });
    
    chatMessages.innerHTML = html;
    
    // Scroll to bottom smoothly
    setTimeout(() => {
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: 'smooth'
        });
    }, 100);
}

// Send message with improved error handling and loading state
document.getElementById('messageForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (isSending) return;
    
    const messageInput = document.getElementById('messageInput');
    const receiverId = document.getElementById('receiverId').value;
    const messageText = messageInput.value.trim();
    
    if (!messageText || !receiverId) return;
    
    isSending = true;
    const sendButton = this.querySelector('button[type="submit"]');
    const originalHtml = sendButton.innerHTML;
    
    // Show loading state
    sendButton.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    `;
    sendButton.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('message_text', messageText);
        formData.append('csrf_token', csrfToken); // Add CSRF protection
        
    const response = await fetch(`${base}/chat/send_message.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            messageInput.value = '';
            loadMessages(receiverId); // Reload messages to show the new one
        } else {
            showNotification(data.message || 'Failed to send message', 'error');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('Network error: Failed to send message', 'error');
    } finally {
        // Reset button state
        sendButton.innerHTML = originalHtml;
        sendButton.disabled = false;
        isSending = false;
    }
});

// File upload handler
document.getElementById('fileUploadBtn')?.addEventListener('click', function() {
    document.getElementById('fileInput').click();
});

document.getElementById('fileInput')?.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        uploadFile(this.files[0]);
    }
});

async function uploadFile(file) {
    // Implement file upload logic here
    showNotification('File upload functionality not implemented yet', 'warning');
}

// Notification system
function showNotification(message, type = 'info') {
    const types = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${types[type]} text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

// Helper functions
function clearChat() {
    currentChatUser = null;
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
        messageCheckInterval = null;
    }
    
    document.getElementById('chatMessages').innerHTML = `
        <div class="flex items-center justify-center h-full text-gray-400">
            <div class="text-center">
                <svg class="w-20 h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-lg font-medium">No conversation selected</p>
                <p class="text-sm">Choose a user from the list to start messaging</p>
            </div>
        </div>
    `;
    document.getElementById('messageInputArea').classList.add('hidden');
    document.getElementById('chatHeader').innerHTML = `
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDate(timestamp) {
    const date = new Date(timestamp);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    } else {
        return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
    }
}

// Update user status every 30 seconds
setInterval(() => {
    fetch(`${base}/api/update_status.php`, { method: 'POST' }).catch(() => {});
}, 30000);

// Handle page visibility to reduce polling when tab is not active
document.addEventListener('visibilitychange', function() {
    if (document.hidden && messageCheckInterval) {
        clearInterval(messageCheckInterval);
        messageCheckInterval = null;
    } else if (!document.hidden && currentChatUser && !messageCheckInterval) {
        messageCheckInterval = setInterval(() => loadMessages(currentChatUser), 3000);
    }
});

// Enter key to send message (Shift+Enter for new line)
document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').dispatchEvent(new Event('submit'));
    }
});

// chat.js - Main chat functionality

class ChatApp {
    constructor() {
        this.currentChatUser = null;
        this.messageCheckInterval = null;
        this.isSending = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.startStatusUpdates();
        console.log('Chat app initialized');
    }

    bindEvents() {
        // User menu
        this.bindUserMenu();
        
        // User interactions
        this.bindUserClicks();
        
        // Search functionality
        this.bindSearch();
        
        // Message sending
        this.bindMessageSending();
        
        // File upload
        this.bindFileUpload();
        
        // Page visibility
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

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                userMenu.classList.add('hidden');
            });
        }
    }

    bindUserClicks() {
        document.addEventListener('click', (e) => {
            const userItem = e.target.closest('.user-item');
            if (userItem) {
                this.handleUserItemClick(userItem);
            }

            const clearChatBtn = e.target.closest('#clearChatBtn');
            if (clearChatBtn) {
                this.clearChat();
            }
        });
    }

    handleUserItemClick(userItem) {
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePicture = userItem.dataset.profilePicture || 'default.png';
        
        this.openChat(userId, fullName, username, profilePicture);
    }

    // ... include all the other methods from the improved code
    // openChat(), loadMessages(), displayMessages(), etc.
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Check if required global variables exist
    if (typeof currentUserId === 'undefined') {
        console.error('currentUserId is not defined. Make sure to include the inline script in dashboard.php');
        return;
    }

    window.chatApp = new ChatApp();
});