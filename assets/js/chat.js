// ============================================
// chat.js - Complete Chat System With Message Reactions
// This file handles all real-time chat functionality including:
// - One-to-one messaging
// - Typing indicators
// - Message reactions (like, love, etc.)
// - Emoji picker
// - File uploads
// - Auto-refresh messages
// ============================================

console.log('🔧 chat.js loading...');

// ============================================
// CRITICAL: Check if required variables exist
// These variables MUST be defined in the HTML before this script loads
// ============================================
if (typeof currentUserId === 'undefined') {
    console.error('❌ FATAL ERROR: currentUserId is not defined!');
}

console.log('=== CHAT.JS VARIABLE CHECK ===');
console.log('currentUserId:', typeof currentUserId !== 'undefined' ? currentUserId : 'NOT FOUND');
console.log('csrfToken:', typeof csrfToken !== 'undefined' ? 'FOUND' : 'NOT FOUND');
console.log('baseUrl:', typeof baseUrl !== 'undefined' ? baseUrl : 'NOT FOUND');
console.log('==============================');

// ============================================
// SimpleChat Class - Main chat functionality handler
// This class manages all chat operations
// ============================================
class SimpleChat {
    constructor() {
        // Store current user's ID (the person using the app)
        this.currentUserId = typeof currentUserId !== 'undefined' ? currentUserId : window.currentUserId;
        
        // Validate that we have a user ID before proceeding
        if (!this.currentUserId) {
            console.error('❌ CRITICAL: SimpleChat initialized without currentUserId!');
            alert('Error: User ID not found. Please refresh the page.');
            return;
        }
        
        console.log('✅ SimpleChat initialized with currentUserId:', this.currentUserId);
        
        // Chat state variables
        this.currentChatUser = null; // ID of the user we're currently chatting with
        this.pollInterval = null; // Timer for auto-refreshing messages
        this.typingTimeout = null; // Timer for "user is typing" indicator
        this.typingCheckInterval = null; // Timer for checking if other user is typing
        this.isTyping = false; // Whether current user is typing
        this.isUserScrolling = false; // Whether user manually scrolled up (to prevent auto-scroll)
        this.lastMessageCount = 0; // Track number of messages to detect new ones
        
        // Reaction emojis mapping
        // Maps reaction type names to their emoji characters
        this.reactionEmojis = {
            'like': '👍',     // Thumbs up
            'love': '❤️',     // Red heart
            'haha': '😂',     // Laughing face
            'wow': '😮',      // Surprised face
            'sad': '😢',      // Crying face
            'angry': '😠'     // Angry face
        };
        
        // Initialize the chat system
        this.init();
    }

    // ============================================
    // INITIALIZATION
    // Sets up all event listeners and features
    // ============================================
    init() {
        console.log('🔄 Initializing chat...');
        this.bindEvents(); // Attach event listeners
        this.initPullToRefresh(); // Enable pull-to-refresh on mobile
        this.initEmojiPicker(); // Set up emoji selection
        this.initScrollDetection(); // Track if user is manually scrolling
        console.log('✅ Chat initialized');
    }

    // ============================================
    // EVENT BINDING
    // Attaches all necessary event listeners
    // ============================================
    bindEvents() {
        console.log('🔗 Binding events...');
        this.bindUserMenu(); // User dropdown menu
        this.bindUserClicks(); // Clicking on a user to chat
        this.bindMessageSending(); // Sending messages
        this.bindSearch(); // Searching users
        this.bindTypingIndicator(); // "User is typing..." feature
        this.bindFileUpload(); // File attachment feature
    }

    // ============================================
    // SCROLL DETECTION
    // Detects if user manually scrolled up
    // If they did, we won't auto-scroll on new messages
    // ============================================
    initScrollDetection() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        let scrollTimeout;
        chatMessages.addEventListener('scroll', () => {
            // User is scrolling
            this.isUserScrolling = true;
            
            // Clear previous timeout
            if (scrollTimeout) clearTimeout(scrollTimeout);
            
            // After 2 seconds of no scrolling, check if at bottom
            scrollTimeout = setTimeout(() => {
                const container = document.getElementById('chatMessages');
                if (container) {
                    // Check if user is within 50px of bottom
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                    // If at bottom, enable auto-scroll again
                    this.isUserScrolling = !isAtBottom;
                }
            }, 2000);
        });
    }

    // ============================================
    // TYPING INDICATOR
    // Shows when the other user is typing
    // ============================================
    bindTypingIndicator() {
        const messageInput = document.getElementById('messageInput');
        if (!messageInput) return;
        
        // When user types in the input box
        messageInput.addEventListener('input', () => {
            if (!this.currentChatUser) return; // Only if chat is open
            
            // Send typing status to server
            this.sendTypingStatus(true);
            
            // Clear previous timeout
            if (this.typingTimeout) {
                clearTimeout(this.typingTimeout);
            }
            
            // After 3 seconds of no typing, send "stopped typing"
            this.typingTimeout = setTimeout(() => {
                this.sendTypingStatus(false);
            }, 3000);
        });
        
        // When user sends message, stop typing indicator
        const form = document.getElementById('messageForm');
        if (form) {
            form.addEventListener('submit', () => {
                this.sendTypingStatus(false);
            });
        }
    }

    // ============================================
    // SEND TYPING STATUS
    // Tells server if current user is typing
    // @param {boolean} isTyping - true if typing, false if stopped
    // ============================================
    async sendTypingStatus(isTyping) {
        if (!this.currentChatUser) return; // Must have active chat
        if (this.isTyping === isTyping) return; // Don't send duplicate status
        this.isTyping = isTyping;
        
        try {
            // Prepare form data
            const formData = new FormData();
            formData.append('chat_with', this.currentChatUser); // Who we're chatting with
            formData.append('is_typing', isTyping ? '1' : '0'); // Typing status
            
            // Send to server (fire and forget - we don't wait for response)
            await fetch('chat/typing_status.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('❌ Typing status error:', error);
        }
    }

    // ============================================
    // CHECK IF OTHER USER IS TYPING
    // Periodically checks if the other person is typing
    // @param {number} userId - ID of user we're chatting with
    // ============================================
    startTypingCheck(userId) {
        // Clear any existing check interval
        if (this.typingCheckInterval) {
            clearInterval(this.typingCheckInterval);
        }
        
        // Check every 1 second
        this.typingCheckInterval = setInterval(async () => {
            // Only check if still chatting with same user
            if (this.currentChatUser !== userId) return;
            
            try {
                // Ask server if other user is typing
                const response = await fetch(`chat/check_typing.php?chat_with=${userId}`);
                const data = await response.json();
                
                if (data.success && data.is_typing) {
                    // Show typing indicator
                    this.showTypingIndicator(data.user_name);
                } else {
                    // Hide typing indicator
                    this.hideTypingIndicator();
                }
            } catch (error) {
                console.error('❌ Check typing error:', error);
            }
        }, 1000); // Check every 1000ms (1 second)
    }

    // ============================================
    // SHOW TYPING INDICATOR
    // Displays "User is typing..." animation
    // @param {string} userName - Name of the user who is typing
    // ============================================
    showTypingIndicator(userName) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        // Remove existing indicator if any
        const existing = document.getElementById('typingIndicator');
        if (existing) existing.remove();
        
        // Create new typing indicator element
        const indicator = document.createElement('div');
        indicator.id = 'typingIndicator';
        indicator.className = 'flex justify-start mb-4';
        indicator.innerHTML = `
            <div class="bg-gray-200 text-gray-600 rounded-lg p-3 shadow">
                <div class="flex items-center space-x-2">
                    <!-- Three bouncing dots animation -->
                    <div class="typing-dots flex space-x-1">
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                    <span class="text-xs">${userName || 'User'} is typing...</span>
                </div>
            </div>
        `;
        
        // Add to chat
        container.appendChild(indicator);
        
        // Auto-scroll if user is at bottom
        if (!this.isUserScrolling) {
            this.scrollToBottom();
        }
    }

    // ============================================
    // HIDE TYPING INDICATOR
    // Removes the "User is typing..." message
    // ============================================
    hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.remove();
    }

    // ============================================
    // EMOJI PICKER INITIALIZATION
    // Sets up the emoji selection feature
    // ============================================
    initEmojiPicker() {
        // Define emoji categories
        // Each category contains an array of emoji characters
        this.emojis = {
            'Smileys': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔'],
            'Gestures': ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '🤝', '🙏', '💪'],
            'Hearts': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟'],
            'Objects': ['📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '📷', '📸', '📹', '🎥', '📞', '☎️', '📺', '📻', '🎙️', '⏰', '🔋', '🔌', '💡', '🔦'],
            'Symbols': ['✅', '❌', '⭐', '🌟', '💫', '✨', '🔥', '💯', '🎉', '🎊', '🎈', '🎁', '🏆', '🥇', '🥈', '🥉', '⚡', '💥']
        };
        
        // Create the emoji button HTML
        this.createEmojiButton();
    }

    // ============================================
    // CREATE EMOJI BUTTON
    // HTML for the emoji picker button
    // ============================================
    createEmojiButton() {
        this.emojiButtonHTML = `
            <button type="button" id="emojiPickerBtn" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        `;
    }

    // ============================================
    // SHOW EMOJI PICKER
    // Displays the emoji selection popup
    // ============================================
    showEmojiPicker() {
        // Check if picker already exists (to toggle it)
        const existing = document.getElementById('emojiPickerModal');
        if (existing) {
            existing.remove(); // Close if already open
            return;
        }
        
        // Create picker modal
        const picker = document.createElement('div');
        picker.id = 'emojiPickerModal';
        picker.className = 'fixed bottom-20 right-4 md:bottom-24 md:right-8 bg-white rounded-lg shadow-2xl z-50 w-80 max-h-96 overflow-hidden border border-gray-200';
        
        // Build HTML for emoji picker
        let html = '<div class="p-4">';
        
        // Header with close button
        html += '<div class="flex justify-between items-center mb-3">';
        html += '<h3 class="font-bold text-gray-800">Emojis</h3>';
        html += '<button onclick="window.simpleChat.hideEmojiPicker()" class="text-gray-500 hover:text-gray-700">';
        html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        html += '</button></div>';
        
        // Scrollable emoji grid
        html += '<div class="overflow-y-auto max-h-80 space-y-4">';
        
        // Loop through each emoji category
        for (const [category, emojis] of Object.entries(this.emojis)) {
            html += `<div>`;
            html += `<h4 class="text-xs font-semibold text-gray-500 mb-2">${category}</h4>`;
            html += `<div class="grid grid-cols-8 gap-2">`;
            
            // Add each emoji as a clickable button
            emojis.forEach(emoji => {
                html += `<button type="button" onclick="window.simpleChat.insertEmoji('${emoji}')" class="text-2xl hover:bg-gray-100 rounded p-1 transition">${emoji}</button>`;
            });
            
            html += `</div></div>`;
        }
        
        html += '</div></div>';
        picker.innerHTML = html;
        
        // Add picker to page
        document.body.appendChild(picker);
    }

    // ============================================
    // HIDE EMOJI PICKER
    // Closes the emoji selection popup
    // ============================================
    hideEmojiPicker() {
        const picker = document.getElementById('emojiPickerModal');
        if (picker) picker.remove();
    }

    // ============================================
    // INSERT EMOJI
    // Adds selected emoji to message input at cursor position
    // @param {string} emoji - The emoji character to insert
    // ============================================
    insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        if (!input) return;
        
        // Get current cursor position
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const text = input.value;
        
        // Insert emoji at cursor position
        input.value = text.substring(0, start) + emoji + text.substring(end);
        
        // Move cursor after inserted emoji
        const newPos = start + emoji.length;
        input.setSelectionRange(newPos, newPos);
        input.focus();
        
        // Trigger typing indicator
        this.sendTypingStatus(true);
    }

    // ============================================
    // FILE UPLOAD INITIALIZATION
    // Sets up file attachment feature
    // ============================================
    bindFileUpload() {
        // HTML for file upload button
        this.fileUploadButtonHTML = `
            <button type="button" id="fileUploadBtn" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                </svg>
            </button>
            <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf,.doc,.docx,.txt,.zip">
        `;
    }

    // ============================================
    // INITIALIZE FILE UPLOAD HANDLER
    // Attaches event listeners for file upload
    // @param {number} receiverId - ID of user receiving the file
    // ============================================
    initFileUpload(receiverId) {
        const fileBtn = document.getElementById('fileUploadBtn');
        const fileInput = document.getElementById('fileInput');
        
        if (fileBtn && fileInput) {
            // Click button triggers file selection dialog
            fileBtn.onclick = () => fileInput.click();
            
            // When file is selected
            fileInput.onchange = async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                // Check file size (10MB limit = 10,485,760 bytes)
                if (file.size > 10485760) {
                    alert('File size exceeds 10MB limit');
                    return;
                }
                
                // Prepare file for upload
                const formData = new FormData();
                formData.append('file', file);
                formData.append('receiver_id', receiverId);
                
                try {
                    // Upload file to server
                    const response = await fetch('chat/upload_file.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        // Refresh messages to show uploaded file
                        this.loadMessages(receiverId);
                    } else {
                        alert('Failed to upload file: ' + data.message);
                    }
                } catch (error) {
                    console.error('File upload error:', error);
                    alert('Failed to upload file');
                }
                
                // Clear file input for next upload
                fileInput.value = '';
            };
        }
    }

    // ============================================
    // PULL TO REFRESH
    // Enables pull-down-to-refresh on mobile
    // ============================================
    initPullToRefresh() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        let startY = 0; // Track touch start position
        let isPulling = false; // Track if user is pulling
        
        // Touch start event
        chatMessages.addEventListener('touchstart', (e) => {
            // Only start if scrolled to top
            if (chatMessages.scrollTop === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        });
        
        // Touch move event
        chatMessages.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            const currentY = e.touches[0].clientY;
            const pullDistance = currentY - startY;
            
            // If pulled down more than 80px
            if (pullDistance > 80 && this.currentChatUser) {
                isPulling = false;
                // Reload messages
                this.loadMessages(this.currentChatUser);
                // Show visual feedback
                this.showRefreshFeedback();
            }
        });
        
        // Touch end event
        chatMessages.addEventListener('touchend', () => {
            isPulling = false;
        });
    }

    // ============================================
    // SHOW REFRESH FEEDBACK
    // Shows visual confirmation of refresh action
    // ============================================
    showRefreshFeedback() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        // Create temporary refresh message
        const feedback = document.createElement('div');
        feedback.className = 'text-center py-2 text-sm text-gray-500';
        feedback.textContent = '🔄 Refreshing messages...';
        
        chatMessages.insertBefore(feedback, chatMessages.firstChild);
        
        // Remove after 2 seconds
        setTimeout(() => feedback.remove(), 2000);
    }

    // ============================================
    // MESSAGE REACTIONS - SHOW PICKER
    // Displays reaction selection popup when user clicks reaction button
    // @param {number} messageId - ID of message to react to
    // @param {Event} event - Click event (to position popup)
    // ============================================
    showReactionPicker(messageId, event) {
        event.stopPropagation(); // Prevent event bubbling
        
        // Remove existing picker if any (toggle behavior)
        const existing = document.getElementById('reactionPicker');
        if (existing) {
            existing.remove();
            return;
        }
        
        // Create reaction picker element
        const picker = document.createElement('div');
        picker.id = 'reactionPicker';
        picker.className = 'absolute bg-white rounded-lg shadow-2xl p-2 z-50 border border-gray-200 flex space-x-1';
        
        // Position picker near click location
        picker.style.left = event.pageX + 'px';
        picker.style.top = (event.pageY - 50) + 'px';
        
        // Build HTML with reaction buttons
        let html = '';
        for (const [type, emoji] of Object.entries(this.reactionEmojis)) {
            html += `<button 
                onclick="window.simpleChat.addReaction(${messageId}, '${type}')" 
                class="text-2xl hover:scale-125 transition-transform p-1 rounded hover:bg-gray-100"
                title="${type}"
            >${emoji}</button>`;
        }
        
        picker.innerHTML = html;
        document.body.appendChild(picker);
        
        // Auto-close picker when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closeReactionPicker(e) {
                if (!e.target.closest('#reactionPicker')) {
                    picker.remove();
                    document.removeEventListener('click', closeReactionPicker);
                }
            });
        }, 100);
    }

    // ============================================
    // ADD REACTION
    // Sends reaction to server and updates UI
    // @param {number} messageId - ID of message to react to
    // @param {string} reactionType - Type of reaction (like, love, etc.)
    // ============================================
    async addReaction(messageId, reactionType) {
        // Close reaction picker
        const picker = document.getElementById('reactionPicker');
        if (picker) picker.remove();
        
        try {
            // Prepare reaction data
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('reaction_type', reactionType);
            formData.append('action', 'add'); // Could be 'add' or 'remove'
            
            // Send to server
            const response = await fetch('chat/add_reaction.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                // Update reactions in UI immediately
                this.updateMessageReactions(messageId, data.reactions, data.user_reactions);
            } else {
                console.error('Failed to add reaction:', data.message);
            }
        } catch (error) {
            console.error('Reaction error:', error);
        }
    }

    // ============================================
    // UPDATE MESSAGE REACTIONS
    // Updates reaction display for a specific message
    // @param {number} messageId - Message ID
    // @param {Array} reactions - Array of reaction objects with counts
    // @param {Array} userReactions - Array of reaction types user has made
    // ============================================
    updateMessageReactions(messageId, reactions, userReactions) {
        // Find message element in DOM
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageElement) return;
        
        // Find reactions container within message
        const reactionContainer = messageElement.querySelector('.message-reactions');
        if (!reactionContainer) return;
        
        // If no reactions, clear container
        if (!reactions || reactions.length === 0) {
            reactionContainer.innerHTML = '';
            return;
        }
        
        // Build HTML for each reaction
        let html = '';
        reactions.forEach(reaction => {
            // Check if current user reacted with this type
            const isUserReaction = userReactions.includes(reaction.reaction_type);
            // Get emoji for this reaction type
            const emoji = this.reactionEmojis[reaction.reaction_type];
            
            // Create reaction badge
            // Highlighted (purple) if user reacted, gray otherwise
            html += `
                <span 
                    class="inline-flex items-center space-x-1 px-2 py-1 rounded-full text-xs ${isUserReaction ? 'bg-purple-100 border-purple-300' : 'bg-gray-100 border-gray-300'} border cursor-pointer hover:scale-110 transition-transform"
                    onclick="window.simpleChat.addReaction(${messageId}, '${reaction.reaction_type}')"
                    title="${reaction.users}"
                >
                    <span>${emoji}</span>
                    <span class="font-semibold">${reaction.count}</span>
                </span>
            `;
        });
        
        // Update container HTML
        reactionContainer.innerHTML = html;
    }

    // ============================================
    // USER MENU TOGGLE
    // Opens/closes user profile dropdown menu
    // ============================================
    bindUserMenu() {
        const menuBtn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userMenu');
        
        if (menuBtn && menu) {
            // Toggle menu on button click
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent document click handler
                menu.classList.toggle('hidden');
            });
            
            // Close menu when clicking anywhere else
            document.addEventListener('click', (e) => {
                // Only close if clicking outside the menu and button
                if (!menu.contains(e.target) && !menuBtn.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            });
        }
    }

    // ============================================
    // USER LIST CLICKS
    // Handles clicking on users to open chat
    // ============================================
    bindUserClicks() {
        console.log('👥 Binding user clicks...');
        
        // Use event delegation for efficiency
        document.addEventListener('click', (e) => {
            const userItem = e.target.closest('.user-item');
            if (userItem) {
                console.log('🎯 User item clicked!');
                this.handleUserClick(userItem);
            }
        });
    }

    // ============================================
    // HANDLE USER CLICK
    // Processes click on user to start chat
    // @param {HTMLElement} userItem - The clicked user element
    // ============================================
    handleUserClick(userItem) {
        // Extract user data from HTML data attributes
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        const profilePic = userItem.dataset.profilePicture || 'default.png';
        
        console.log('💬 Opening chat with:', { userId, fullName });
        
        // Validate user ID exists
        if (!userId) {
            console.error('❌ No user ID found');
            return;
        }
        
        // Open chat window
        this.openChat(userId, fullName, username, profilePic);
    }

    // ============================================
    // MESSAGE SENDING
    // Handles form submission for sending messages
    // ============================================
    bindMessageSending() {
        const form = document.getElementById('messageForm');
        if (!form) {
            console.warn('❌ Message form not found');
            return;
        }
        
        // Prevent default form submission and handle with JavaScript
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        console.log('✅ Message sending bound');
    }

    // ============================================
    // SEARCH USERS
    // Filters user list based on search input
    // ============================================
    bindSearch() {
        const searchInput = document.getElementById('searchUsers');
        if (!searchInput) return;
        
        // Filter on each keystroke
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            this.filterUsers(term);
        });
    }

    // ============================================
    // FILTER USERS
    // Shows/hides users based on search term
    // @param {string} searchTerm - Text to search for
    // ============================================
    filterUsers(searchTerm) {
        document.querySelectorAll('.user-item').forEach(item => {
            const fullName = (item.dataset.fullname || '').toLowerCase();
            const username = (item.dataset.username || '').toLowerCase();
            
            // Show if name or username matches
            if (fullName.includes(searchTerm) || username.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
 
            }
        });
    }

    // ============================================
    // OPEN CHAT
    // Opens chat window with selected user
    // @param {number} userId - ID of user to chat with
    // @param {string} fullName - Full name of user
    // @param {string} username - Username
    // @param {string} profilePicture - Profile picture filename
    // ============================================
    openChat(userId, fullName, username, profilePicture) {
        console.log('🚀 Opening chat with user:', userId);
        
        // Set current chat user
        this.currentChatUser = userId;
        this.lastMessageCount = 0; // Reset message counter
        this.isUserScrolling = false; // Reset scroll state
        
        // Update chat header with user info
        this.updateChatHeader(fullName, username, profilePicture);
        
        // Show message input area
        const inputArea = document.getElementById('messageInputArea');
        if (inputArea) {
            inputArea.classList.remove('hidden');
            
            // Add emoji and file buttons if not already present
            if (!document.getElementById('emojiPickerBtn')) {
                const form = document.getElementById('messageForm');
                if (form) {
                    const container = form.querySelector('.flex');
                    if (container) {
                        // Add emoji button
                        const emojiWrapper = document.createElement('div');
                        emojiWrapper.innerHTML = this.emojiButtonHTML;
                        container.insertBefore(emojiWrapper.firstElementChild, container.firstElementChild);
                        
                        // Attach emoji button click handler
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
                        
                        // Initialize file upload functionality
                        this.initFileUpload(userId);
                    }
                }
            }
        }
        
        // Set receiver ID in hidden input
        const receiverInput = document.getElementById('receiverId');
        if (receiverInput) {
            receiverInput.value = userId;
        }
        
        // Focus message input
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.focus();
        }
        
        // Load existing messages
        this.loadMessages(userId);
        
        // Start auto-refresh of messages
        this.startPolling(userId);
        
        // Start checking if other user is typing
        this.startTypingCheck(userId);
    }

    // ============================================
    // UPDATE CHAT HEADER
    // Updates header with user information
    // @param {string} fullName - User's full name
    // @param {string} username - User's username
    // @param {string} profilePicture - Profile picture filename
    // ============================================
    updateChatHeader(fullName, username, profilePicture) {
        const header = document.getElementById('chatHeader');
        if (!header) return;
        
        // Determine correct profile picture path
        let profilePicPath;
        if (profilePicture === 'default.png' || !profilePicture) {
            // Use default avatar
            profilePicPath = 'assets/images/default.png';
        } else {
            // Use uploaded profile picture
            profilePicPath = `uploads/profiles/${profilePicture}`;
        }
        
        // Build header HTML with user info and close button
        header.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <!-- Profile Picture -->
                    <img src="${profilePicPath}" 
                         alt="${fullName}" 
                         class="w-10 h-10 rounded-full"
                         onerror="this.src='assets/images/default.png'">
                    <!-- User Info -->
                    <div>
                        <p class="font-semibold text-gray-800">${fullName}</p>
                        <p class="text-xs text-gray-500">@${username}</p>
                    </div>
                </div>
                <!-- Close Chat Button -->
                <button onclick="window.simpleChat.clearChat()" class="text-gray-500 hover:text-red-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
    }

    // ============================================
    // LOAD MESSAGES
    // Fetches and displays messages for current chat
    // @param {number} userId - ID of user to load messages with
    // ============================================
    async loadMessages(userId) {
        if (!userId) return; // Must have user ID
        
        try {
            // Fetch messages from server
            const response = await fetch(`chat/get_messages.php?user_id=${userId}`);
            
            // Check response status
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Only update if message count changed (optimization)
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
    // DISPLAY MESSAGES (WITH REACTIONS)
    // Renders all messages in the chat window
    // @param {Array} messages - Array of message objects from server
    // ============================================
    displayMessages(messages) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        // Handle empty message list
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
        
        // Loop through each message
        messages.forEach(msg => {
            // Determine if message was sent by current user
            const senderId = parseInt(msg.sender_id);
            const isSent = senderId === parseInt(this.currentUserId);
            
            // Set alignment and styling based on sender
            const alignClass = isSent ? 'justify-end' : 'justify-start';
            const bgClass = isSent 
                ? 'bg-purple-600 text-white' // Sent messages: purple
                : 'bg-white text-gray-800 border border-gray-200'; // Received: white
            
            // Build reactions HTML
            let reactionsHTML = '';
            if (msg.reactions && msg.reactions.length > 0) {
                // Message has reactions
                reactionsHTML = '<div class="message-reactions flex flex-wrap gap-1 mt-2">';
                
                msg.reactions.forEach(reaction => {
                    // Check if current user reacted with this type
                    const isUserReaction = msg.user_reactions && msg.user_reactions.includes(reaction.reaction_type);
                    // Get emoji for this reaction
                    const emoji = this.reactionEmojis[reaction.reaction_type];
                    
                    // Create reaction badge
                    // Purple highlight if user reacted, gray otherwise
                    reactionsHTML += `
                        <span 
                            class="inline-flex items-center space-x-1 px-2 py-1 rounded-full text-xs ${isUserReaction ? 'bg-purple-100 border-purple-300' : 'bg-gray-100 border-gray-300'} border cursor-pointer hover:scale-110 transition-transform"
                            onclick="window.simpleChat.addReaction(${msg.message_id}, '${reaction.reaction_type}')"
                            title="${reaction.users}"
                        >
                            <span>${emoji}</span>
                            <span class="font-semibold">${reaction.count}</span>
                        </span>
                    `;
                });
                
                reactionsHTML += '</div>';
            } else {
                // Empty reactions container for future reactions
                reactionsHTML = '<div class="message-reactions"></div>';
            }
            
            // Build complete message HTML
            html += `
                <div class="flex ${alignClass} mb-4" data-message-id="${msg.message_id}">
                    <div class="message-bubble ${bgClass} rounded-lg p-3 shadow relative group">
                        <!-- Message Text -->
                        <p class="break-words">${this.escapeHtml(msg.message_text)}</p>
                        
                        <!-- Timestamp and Read Status -->
                        <p class="text-xs ${isSent ? 'text-purple-200' : 'text-gray-500'} mt-1">
                            ${this.formatTime(msg.created_at)}
                            ${isSent ? (msg.is_read ? ' ✓✓' : ' ✓') : ''}
                        </p>
                        
                        <!-- Reactions Display -->
                        ${reactionsHTML}
                        
                        <!-- Reaction Button (appears on hover) -->
                        <button 
                            onclick="window.simpleChat.showReactionPicker(${msg.message_id}, event)"
                            class="absolute -top-2 -right-2 bg-white border border-gray-300 rounded-full p-1 shadow-md opacity-0 group-hover:opacity-100 transition-opacity hover:scale-110"
                            title="Add reaction"
                        >
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        });
        
        // Update container with all messages
        container.innerHTML = html;
        
        // Auto-scroll to bottom if user isn't manually scrolling
        if (!this.isUserScrolling) {
            this.scrollToBottom();
        }
    }

    // ============================================
    // SEND MESSAGE
    // Sends new message to server
    // ============================================
    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const receiverInput = document.getElementById('receiverId');
        
        // Get values from inputs
        const message = messageInput?.value.trim();
        const receiverId = receiverInput?.value;
        
        // Validate inputs
        if (!message || !receiverId) {
            return; // Don't send empty messages
        }
        
        // Stop typing indicator
        this.sendTypingStatus(false);
        
        try {
            // Prepare form data
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message_text', message);
            formData.append('csrf_token', window.csrfToken || '');
            
            // Send to server
            const response = await fetch(`chat/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            // Check response
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('📨 Send response:', data);
            
            if (data.success) {
                // Clear input field
                messageInput.value = '';
                
                // Reload messages to show new message
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
    // START POLLING
    // Starts auto-refresh of messages every 3 seconds
    // @param {number} userId - User ID to poll messages for
    // ============================================
    startPolling(userId) {
        // Clear any existing poll interval
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        // Poll every 3 seconds
        this.pollInterval = setInterval(() => {
            // Only poll if still chatting with same user
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 3000); // 3000ms = 3 seconds
    }

    // ============================================
    // CLEAR CHAT
    // Closes current chat and resets to empty state
    // ============================================
    clearChat() {
        console.log('🧹 Clearing chat...');
        
        // Reset state
        this.currentChatUser = null;
        
        // Stop polling for messages
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        // Stop typing check
        if (this.typingCheckInterval) {
            clearInterval(this.typingCheckInterval);
            this.typingCheckInterval = null;
        }
        
        // Get UI elements
        const container = document.getElementById('chatMessages');
        const inputArea = document.getElementById('messageInputArea');
        const header = document.getElementById('chatHeader');
        
        // Reset message area
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
    // UTILITY FUNCTIONS
    // Helper functions for common tasks
    // ============================================
    
    /**
     * ESCAPE HTML
     * Prevents XSS (Cross-Site Scripting) attacks by escaping HTML characters
     * @param {string} text - Raw text that might contain HTML
     * @returns {string} - Safe HTML-escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * FORMAT TIME
     * Converts timestamp to human-readable format
     * @param {string|Date} timestamp - Raw timestamp from database
     * @returns {string} - Formatted time string (e.g., "Just now", "2:30 PM", "Jan 15")
     */
    formatTime(timestamp) {
        if (!timestamp) return 'Just now';
        
        try {
            // Convert to Date object
            let date;
            
            if (timestamp instanceof Date) {
                date = timestamp;
            } else if (typeof timestamp === 'string') {
                // Try different date formats
                date = new Date(timestamp);
                
                // If invalid, try replacing space with T (ISO format)
                if (isNaN(date.getTime())) {
                    date = new Date(timestamp.replace(' ', 'T'));
                }
            } else {
                date = new Date(timestamp);
            }
            
            // Validate date
            if (isNaN(date.getTime())) {
                console.warn('Invalid date:', timestamp);
                return 'Recently';
            }
            
            const now = new Date();
            const diff = now - date; // Difference in milliseconds
            
            // Show relative time for recent messages
            if (diff < 60000) return 'Just now'; // Less than 1 minute
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago'; // Less than 1 hour
            if (diff < 86400000) {
                // Less than 24 hours - show time
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: false // 24-hour format
                });
            }
            
            // For older messages, show date
            return date.toLocaleDateString('en-US', { 
                month: 'short', // Jan, Feb, etc.
                day: 'numeric', // 1, 2, 3, etc.
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            console.error('Error formatting time:', error, timestamp);
            return 'Recently';
        }
    }

    /**
     * SCROLL TO BOTTOM
     * Smoothly scrolls chat to show latest message
     */
    scrollToBottom() {
        const container = document.getElementById('chatMessages');
        if (container) {
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 100); // Small delay ensures content is rendered
        }
    }
}

// ============================================
// INITIALIZATION
// Initialize chat when page is fully loaded
// ============================================

console.log('🔧 Setting up DOM ready listener...');

/**
 * INITIALIZE CHAT
 * Creates global SimpleChat instance
 * Only initializes once to prevent duplicates
 */
function initializeChat() {
    // Prevent multiple initializations
    if (window.simpleChat) {
        console.warn('⚠️ simpleChat already initialized, skipping...');
        return;
    }
    
    try {
        console.log('🎉 DOM fully loaded - initializing chat...');
        
        // Create global chat instance
        // This allows calling methods from anywhere: window.simpleChat.methodName()
        window.simpleChat = new SimpleChat();
        
        console.log('✅ simpleChat initialized globally');
    } catch (error) {
        console.error('❌ Failed to initialize chat:', error);
    }
}

// Check if DOM is already loaded or still loading
if (document.readyState === 'loading') {
    // DOM still loading - wait for it
    document.addEventListener('DOMContentLoaded', initializeChat);
} else {
    // DOM already loaded - initialize immediately
    setTimeout(initializeChat, 100); // Small delay for safety
}

// ============================================
// END OF CHAT.JS
// ============================================