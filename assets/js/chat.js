// ============================================
// chat.js - LAN Chat Application
// ============================================
// This file handles all chat functionality including:
// - Sending messages
// - Receiving messages
// - Message display and alignment
// - User selection
// - Real-time polling for new messages
// ============================================

console.log('🔧 chat.js loading...');

// ============================================
// IMPORTANT: Check if global variables exist
// ============================================
// These variables MUST be set by dashboard.php BEFORE this script loads
// If they're not set, something went wrong with the page load

if (typeof currentUserId === 'undefined') {
    console.error('❌ FATAL ERROR: currentUserId is not defined!');
    console.error('This means dashboard.php did not properly set the global variable.');
    console.error('Message alignment will fail completely.');
}

if (typeof window.currentUserId === 'undefined') {
    console.error('❌ FATAL ERROR: window.currentUserId is also not defined!');
}

// Log what we have access to
console.log('=== CHAT.JS VARIABLE CHECK ===');
console.log('currentUserId (direct):', typeof currentUserId !== 'undefined' ? currentUserId : 'NOT FOUND');
console.log('window.currentUserId:', typeof window.currentUserId !== 'undefined' ? window.currentUserId : 'NOT FOUND');
console.log('csrfToken:', typeof csrfToken !== 'undefined' ? 'FOUND' : 'NOT FOUND');
console.log('baseUrl:', typeof baseUrl !== 'undefined' ? baseUrl : 'NOT FOUND');
console.log('==============================');

// ============================================
// SimpleChat Class
// ============================================
// This class manages all chat operations
class SimpleChat {
    constructor() {
        // Store the current user ID for message alignment
        // Try to get it from global scope (set by dashboard.php)
        this.currentUserId = typeof currentUserId !== 'undefined' ? currentUserId : window.currentUserId;
        
        // Validate that we have a current user ID
        if (!this.currentUserId) {
            console.error('❌ CRITICAL: SimpleChat initialized without currentUserId!');
            console.error('currentUserId:', this.currentUserId);
            alert('Error: User ID not found. Please refresh the page.');
            return;
        }
        
        console.log('✅ SimpleChat initialized with currentUserId:', this.currentUserId);
        
        // The user we're currently chatting with
        this.currentChatUser = null;
        
        // Interval for polling new messages
        this.pollInterval = null;
        
        // Initialize the chat interface
        this.init();
        // Add to SimpleChat class

// Improve scroll behavior for mobile
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

// Add pull-to-refresh functionality
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
            // Refresh messages
            this.loadMessages(this.currentChatUser);
            pulling = false;
        }
    });
    
        chatMessages.addEventListener('touchend', () => {
            pulling = false;
        });
    }

    // Call in init()
    init() {
        console.log('🔄 Initializing chat...');
        this.bindEvents();
        this.initPullToRefresh(); // Add this
        console.log('✅ Chat initialized');
    }
    }

    init() {
        console.log('🔄 Initializing chat...');
        this.bindEvents();
        console.log('✅ Chat initialized');
    }

    bindEvents() {
        console.log('🔗 Binding events...');
        
        // Bind all event listeners
        this.bindUserMenu();       // Dropdown menu
        this.bindUserClicks();     // User list clicks
        this.bindMessageSending(); // Send message form
        this.bindSearch();         // Search box
    }

    // ============================================
    // User Menu Toggle
    // ============================================
    // Opens/closes the dropdown menu in the top-right
    bindUserMenu() {
        const menuBtn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userMenu');
        
        if (menuBtn && menu) {
            // Toggle menu when button clicked
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Don't trigger document click
                menu.classList.toggle('hidden');
            });
            
            // Close menu when clicking anywhere else
            document.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        }
    }

    // ============================================
    // User List Click Handler
    // ============================================
    // When a user is clicked in the sidebar, open chat with them
    bindUserClicks() {
        console.log('👥 Binding user clicks...');
        
        // Use event delegation for better performance
        // This catches clicks on any .user-item, even if added dynamically
        document.addEventListener('click', (e) => {
            const userItem = e.target.closest('.user-item');
            if (userItem) {
                console.log('🎯 User item clicked!');
                this.handleUserClick(userItem);
            }
        });
    }

    // ============================================
    // Handle User Selection
    // ============================================
    // Opens a chat with the selected user
    handleUserClick(userItem) {
        // Get user data from data-* attributes
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePic = userItem.dataset.profilePicture || 'default.png';
        
        console.log('💬 Opening chat with:', { userId, fullName });
        
        // Validate we have a user ID
        if (!userId) {
            console.error('❌ No user ID found');
            return;
        }
        
        // Open the chat
        this.openChat(userId, fullName, username, profilePic);
    }

    // ============================================
    // Message Sending
    // ============================================
    // Binds the message form submit event
    bindMessageSending() {
        const form = document.getElementById('messageForm');
        if (!form) {
            console.warn('❌ Message form not found');
            return;
        }
        
        // Prevent form from submitting normally (no page reload)
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        console.log('✅ Message sending bound');
    }

    // ============================================
    // Search Functionality
    // ============================================
    // Filters the user list based on search input
    bindSearch() {
        const searchInput = document.getElementById('searchUsers');
        if (!searchInput) return;
        
        // Filter users as they type
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            this.filterUsers(term);
        });
    }

    // ============================================
    // Filter Users
    // ============================================
    // Shows/hides users based on search term
    filterUsers(searchTerm) {
        document.querySelectorAll('.user-item').forEach(item => {
            const fullName = (item.dataset.fullname || '').toLowerCase();
            const username = (item.dataset.username || '').toLowerCase();
            
            // Show if matches either name or username
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
    // Opens a chat with a specific user
    openChat(userId, fullName, username, profilePicture) {
        console.log('🚀 Opening chat with user:', userId);
        
        // Store who we're chatting with
        this.currentChatUser = userId;
        
        // Update the header to show their info
        this.updateChatHeader(fullName, username, profilePicture);
        
        // Show the message input area
        const inputArea = document.getElementById('messageInputArea');
        if (inputArea) {
            inputArea.classList.remove('hidden');
        }
        
        // Set the receiver ID in the hidden input
        const receiverInput = document.getElementById('receiverId');
        if (receiverInput) {
            receiverInput.value = userId;
        }
        
        // Focus the message input
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.focus();
        }
        
        // Load messages with this user
        this.loadMessages(userId);
        
        // Start polling for new messages
        this.startPolling(userId);
    }

    // ============================================
    // Update Chat Header
    // ============================================
    // Updates the header to show the selected user's info
    updateChatHeader(fullName, username, profilePicture) {
        const header = document.getElementById('chatHeader');
        if (!header) return;
        
        // Determine the correct path for the profile picture
        let profilePicPath;
        if (profilePicture === 'default.png') {
            profilePicPath = 'assets/images/default.png';
        } else {
            profilePicPath = `uploads/profiles/${profilePicture}`;
        }
        
        // Build the header HTML
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
    // Load Messages
    // ============================================
    // Fetches messages with a specific user from the server
    async loadMessages(userId) {
        console.log('📨 Loading messages for user:', userId);
        
        if (!userId) return;
        
        try {
            // Make AJAX request to get messages
            const response = await fetch(`chat/get_messages.php?user_id=${userId}`);
            
            // Check if request was successful
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Parse JSON response
            const data = await response.json();
            console.log('📩 Messages response:', data);
            
            // Display the messages if successful
            if (data.success) {
                this.displayMessages(data.messages);
            } else {
                console.error('❌ Failed to load messages:', data.message);
            }
        } catch (error) {
            console.error('❌ Error loading messages:', error);
        }
    }

    // ============================================
    // Display Messages
    // ============================================
    // Renders messages in the chat window
    displayMessages(messages) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        console.log('🖥️ Displaying messages:', messages?.length);
        
        // Show empty state if no messages
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
        
        // Build HTML for all messages
        let html = '';
        messages.forEach(msg => {
            // ============================================
            // CRITICAL: Message Alignment Logic
            // ============================================
            // Determine if this message was sent BY the current user
            // We compare the message's sender_id with this.currentUserId
            
            const senderId = parseInt(msg.sender_id);  // Convert to number
            const isSent = senderId === parseInt(this.currentUserId); // Compare as numbers
            
            // Debug log to verify alignment
            console.log('Message alignment check:', {
                senderId: senderId,
                senderIdType: typeof senderId,
                currentUserId: this.currentUserId,
                currentUserIdType: typeof this.currentUserId,
                isSent: isSent,
                message: msg.message_text
            });
            
            // Set CSS classes based on who sent the message
            const alignClass = isSent ? 'justify-end' : 'justify-start';  // Right or left
            const bgClass = isSent 
                ? 'bg-purple-600 text-white'           // Sent: purple background
                : 'bg-white text-gray-800 border border-gray-200'; // Received: white background
            
            // Build message bubble HTML
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
        
        // Insert all messages into the container
        container.innerHTML = html;
        
        // Scroll to bottom to show latest messages
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }

    // ============================================
    // Send Message
    // ============================================
    // Sends a message to the server
    async sendMessage() {
        console.log('📤 Sending message...');
        
        const messageInput = document.getElementById('messageInput');
        const receiverInput = document.getElementById('receiverId');
        
        // Get message text and receiver ID
        const message = messageInput?.value.trim();
        const receiverId = receiverInput?.value;
        
        // Validate inputs
        if (!message || !receiverId) {
            console.warn('❌ Cannot send: missing message or receiver');
            return;
        }
        
        console.log('💬 Sending to:', receiverId, 'Message:', message);
        
        try {
            // Create form data for POST request
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message_text', message);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : '');
            
            // Send POST request
            const response = await fetch(`chat/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            // Check if request was successful
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Parse response
            const data = await response.json();
            console.log('📨 Send response:', data);
            
            if (data.success) {
                // Clear input field
                messageInput.value = '';
                
                // Reload messages to show the new one
                this.loadMessages(receiverId);
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Send error:', error);
            alert('Network error: Failed to send message. Check console for details.');
        }
    }

    // ============================================
    // Start Polling
    // ============================================
    // Checks for new messages every 3 seconds
    startPolling(userId) {
        // Clear any existing poll interval
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        // Set up new polling interval
        this.pollInterval = setInterval(() => {
            // Only poll if we're still chatting with this user
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 3000); // Check every 3 seconds
    }

    // ============================================
    // Clear Chat
    // ============================================
    // Closes the current chat and returns to empty state
    clearChat() {
        console.log('🧹 Clearing chat...');
        
        // Clear current chat user
        this.currentChatUser = null;
        
        // Stop polling
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        const container = document.getElementById('chatMessages');
        const inputArea = document.getElementById('messageInputArea');
        const header = document.getElementById('chatHeader');
        
        // Reset messages area
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
        
        // Hide input area
        if (inputArea) inputArea.classList.add('hidden');
        
        // Reset header
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
    // Utility: Escape HTML
    // ============================================
    // Prevents XSS attacks by escaping HTML characters
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // Utility: Format Time
    // ============================================
    // Converts timestamp to human-readable format
    formatTime(timestamp) {
        if (!timestamp) return 'Just now';
        
        try {
            let date;
            
            // Handle different timestamp formats
            if (timestamp instanceof Date) {
                date = timestamp;
            } else if (typeof timestamp === 'string') {
                date = new Date(timestamp);
                
                // Try alternative format if invalid
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
            if (diff < 60000) return 'Just now';                          // < 1 minute
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago'; // < 1 hour
            if (diff < 86400000) {                                         // < 1 day
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

// ============================================
// Initialize Chat When Page Loads
// ============================================
console.log('🔧 Setting up DOM ready listener...');

function initializeChat() {
    // Prevent multiple initializations
    if (window.simpleChat) {
        console.warn('⚠️ simpleChat already initialized, skipping...');
        return;
    }
    
    try {
        console.log('🎉 DOM fully loaded - initializing chat...');
        
        // Create new SimpleChat instance
        window.simpleChat = new SimpleChat();
        
        console.log('✅ simpleChat initialized globally');
    } catch (error) {
        console.error('❌ Failed to initialize chat:', error);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    // DOM still loading, wait for it
    document.addEventListener('DOMContentLoaded', initializeChat);
} else {
    // DOM already loaded, initialize immediately
    setTimeout(initializeChat, 100);
}
// Add to SimpleChat class

// Improve scroll behavior for mobile
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

// Add pull-to-refresh functionality
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
            // Refresh messages
            this.loadMessages(this.currentChatUser);
            pulling = false;
        }
    });
    
    chatMessages.addEventListener('touchend', () => {
        pulling = false;
    });
}

// Call in init()
init() {
    console.log('🔄 Initializing chat...');
    this.bindEvents();
    this.initPullToRefresh(); // Add this
    console.log('✅ Chat initialized');
}