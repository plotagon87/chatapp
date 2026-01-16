// assets/js/chat.js - COMPLETE FIXED VERSION WITH PROPER MESSAGE ALIGNMENT
// This file handles all chat functionality including message sending, receiving, and display

console.log('🔧 chat.js loading...');

// ============================================================================
// EMERGENCY FALLBACK FOR BASE URL
// ============================================================================
// This ensures that baseUrl is always defined, even if not set in HTML
// baseUrl is the root path of your application (e.g., http://localhost/lan_chat/)
if (typeof window.baseUrl === 'undefined' || !window.baseUrl) {
    console.warn('⚠️ baseUrl is undefined, using relative paths');
    
    // Get current page path (e.g., /lan_chat/dashboard.php)
    const currentPath = window.location.pathname;
    
    // Extract base path by removing everything after last slash
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    
    // Combine origin (http://localhost) with base path
    window.baseUrl = window.location.origin + basePath;
    console.log('🔄 Computed baseUrl:', window.baseUrl);
}

// ============================================================================
// MAIN CHAT CLASS
// ============================================================================
// This class encapsulates (wraps) all chat functionality
// It manages user selection, message sending, message receiving, and polling
class SimpleChat {
    // ------------------------------------------------------------------------
    // CONSTRUCTOR
    // ------------------------------------------------------------------------
    // This runs when you create a new SimpleChat object: new SimpleChat()
    constructor() {
        // Store which user we're currently chatting with (null = no chat open)
        this.currentChatUser = null;
        
        // Store the interval ID for polling (checking for new messages)
        // setInterval() returns an ID that we can use to stop it later
        this.pollInterval = null;
        
        console.log('✅ SimpleChat constructor called');
        
        // Call initialization method to set everything up
        this.init();
    }

    // ------------------------------------------------------------------------
    // INITIALIZATION
    // ------------------------------------------------------------------------
    // This sets up all the event listeners (click handlers, form submissions)
    init() {
        console.log('🔄 Initializing chat...');
        
        // Bind all event listeners
        this.bindEvents();
        
        console.log('✅ Chat initialized');
    }

    // ------------------------------------------------------------------------
    // BIND ALL EVENTS
    // ------------------------------------------------------------------------
    // This attaches event listeners to various elements on the page
    bindEvents() {
        console.log('🔗 Binding events...');
        
        // Bind user menu dropdown (top right corner)
        this.bindUserMenu();
        
        // Bind clicks on user items in the sidebar
        this.bindUserClicks();
        
        // Bind message form submission
        this.bindMessageSending();
        
        // Bind search functionality
        this.bindSearch();
    }

    // ------------------------------------------------------------------------
    // BIND USER MENU
    // ------------------------------------------------------------------------
    // This handles the dropdown menu in the top right (Profile, Settings, etc.)
    bindUserMenu() {
        // getElementById() finds an element by its ID attribute
        const menuBtn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userMenu');
        
        // Check if both elements exist (they should)
        if (menuBtn && menu) {
            // addEventListener() runs a function when an event occurs
            // 'click' = when user clicks on the button
            menuBtn.addEventListener('click', (e) => {
                // stopPropagation() prevents the click from bubbling up to document
                // This prevents the document click listener from immediately closing the menu
                e.stopPropagation();
                
                // toggle() adds the class if it's not there, removes it if it is
                // 'hidden' is a Tailwind CSS class that hides elements
                menu.classList.toggle('hidden');
            });
            
            // Click anywhere on the page to close the menu
            document.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        }
    }

    // ------------------------------------------------------------------------
    // BIND USER CLICKS
    // ------------------------------------------------------------------------
    // This handles when you click on a user in the sidebar to open a chat
    bindUserClicks() {
        console.log('👥 Binding user clicks...');
        
        // We use event delegation here - listen on document instead of each user item
        // This is more efficient and works even for dynamically added users
        document.addEventListener('click', (e) => {
            // closest() searches up the DOM tree for a matching element
            // It finds the nearest .user-item ancestor of what was clicked
            const userItem = e.target.closest('.user-item');
            
            if (userItem) {
                console.log('🎯 User item clicked!');
                this.handleUserClick(userItem);
            }
        });
    }

    // ------------------------------------------------------------------------
    // HANDLE USER CLICK
    // ------------------------------------------------------------------------
    // This processes a click on a user item and opens the chat with that user
    handleUserClick(userItem) {
        // dataset gives access to data-* attributes on the element
        // data-user-id becomes dataset.userId
        const userId = userItem.dataset.userId;
        const fullName = userItem.dataset.fullname;
        const username = userItem.dataset.username;
        
        // Use default.png if no profile picture is set
        const profilePic = userItem.dataset.profilePicture || 'default.png';
        
        console.log('💬 Opening chat with:', { userId, fullName });
        
        // Validate that we have a user ID
        if (!userId) {
            console.error('❌ No user ID found');
            return;
        }
        
        // Open the chat interface for this user
        this.openChat(userId, fullName, username, profilePic);
    }

    // ------------------------------------------------------------------------
    // BIND MESSAGE SENDING
    // ------------------------------------------------------------------------
    // This handles form submission when you send a message
    bindMessageSending() {
        const form = document.getElementById('messageForm');
        
        if (!form) {
            console.warn('❌ Message form not found');
            return;
        }
        
        // preventDefault() stops the form from doing a page reload (default behavior)
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        console.log('✅ Message sending bound');
    }

    // ------------------------------------------------------------------------
    // BIND SEARCH
    // ------------------------------------------------------------------------
    // This handles the search box for filtering users in the sidebar
    bindSearch() {
        const searchInput = document.getElementById('searchUsers');
        
        if (!searchInput) return;
        
        // 'input' event fires every time the text changes
        searchInput.addEventListener('input', (e) => {
            // toLowerCase() converts to lowercase for case-insensitive search
            const term = e.target.value.toLowerCase();
            this.filterUsers(term);
        });
    }

    // ------------------------------------------------------------------------
    // FILTER USERS
    // ------------------------------------------------------------------------
    // This shows/hides users in the sidebar based on search term
    filterUsers(searchTerm) {
        // querySelectorAll() returns all elements matching the selector
        document.querySelectorAll('.user-item').forEach(item => {
            const fullName = (item.dataset.fullname || '').toLowerCase();
            const username = (item.dataset.username || '').toLowerCase();
            
            // includes() checks if a string contains another string
            if (fullName.includes(searchTerm) || username.includes(searchTerm)) {
                item.style.display = 'block'; // Show the user
            } else {
                item.style.display = 'none'; // Hide the user
            }
        });
    }

    // ------------------------------------------------------------------------
    // OPEN CHAT
    // ------------------------------------------------------------------------
    // This opens a chat window with a specific user
    openChat(userId, fullName, username, profilePicture) {
        console.log('🚀 Opening chat with user:', userId);
        
        // Store the current chat user ID (we'll need this for sending messages)
        this.currentChatUser = userId;
        
        // Update the chat header to show user info
        this.updateChatHeader(fullName, username, profilePicture);
        
        // Show the message input area (it's hidden by default)
        const inputArea = document.getElementById('messageInputArea');
        if (inputArea) {
            inputArea.classList.remove('hidden');
        }
        
        // Set the receiver ID in the hidden input field
        const receiverInput = document.getElementById('receiverId');
        if (receiverInput) {
            receiverInput.value = userId;
        }
        
        // Focus the message input box so user can start typing immediately
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.focus();
        }
        
        // Load existing messages with this user
        this.loadMessages(userId);
        
        // Start polling for new messages every 3 seconds
        this.startPolling(userId);
    }

    // ------------------------------------------------------------------------
    // UPDATE CHAT HEADER
    // ------------------------------------------------------------------------
    // This updates the header bar to show the current chat user's info
    updateChatHeader(fullName, username, profilePicture) {
        const header = document.getElementById('chatHeader');
        
        if (!header) return;
        
        // IMPORTANT: Handle profile picture paths correctly
        // Default images are in assets/images/
        // User-uploaded images are in uploads/profiles/
        let profilePicPath;
        if (profilePicture === 'default.png') {
            profilePicPath = 'assets/images/default.png';
        } else {
            profilePicPath = `uploads/profiles/${profilePicture}`;
        }
        
        // Template literal (backticks) allows embedding variables with ${}
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

    // ------------------------------------------------------------------------
    // LOAD MESSAGES
    // ------------------------------------------------------------------------
    // This fetches messages from the server via AJAX (Asynchronous JavaScript And XML)
    async loadMessages(userId) {
        console.log('📨 Loading messages for user:', userId);
        
        if (!userId) return;
        
        try {
            // fetch() makes an HTTP request to the server
            // It returns a Promise, so we use await to wait for the response
            const response = await fetch(`chat/get_messages.php?user_id=${userId}`);
            
            // Check if the HTTP response was successful (status 200-299)
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Parse the JSON response body
            const data = await response.json();
            console.log('📩 Messages response:', data);
            
            if (data.success) {
                // Display the messages in the chat interface
                this.displayMessages(data.messages);
            } else {
                console.error('❌ Failed to load messages:', data.message);
            }
        } catch (error) {
            // Log any errors that occurred during the fetch
            console.error('❌ Error loading messages:', error);
        }
    }

    // ------------------------------------------------------------------------
    // DISPLAY MESSAGES - THIS IS THE CRITICAL FIX
    // ------------------------------------------------------------------------
    // This renders messages in the chat interface with CORRECT ALIGNMENT
    displayMessages(messages) {
        const container = document.getElementById('chatMessages');
        
        if (!container) return;
        
        console.log('🖥️ Displaying messages:', messages?.length);
        
        // Show placeholder if no messages exist
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
        
        // Build HTML string for all messages
        let html = '';
        
        // Loop through each message
        messages.forEach(msg => {
            // ================================================================
            // CRITICAL FIX: PROPER MESSAGE ALIGNMENT LOGIC
            // ================================================================
            
            // EXPLANATION:
            // - msg.sender_id is the ID of who sent this message (comes from database)
            // - window.currentUserId is the ID of the logged-in user (set in dashboard.php)
            // - parseInt() converts strings to numbers for reliable comparison
            // - If sender_id matches currentUserId, this is OUR message (right side)
            // - If sender_id doesn't match, it's THEIR message (left side)
            
            const isSent = parseInt(msg.sender_id) === parseInt(window.currentUserId);
            
            // IMPORTANT: Debug logging to verify IDs
            console.log('Message alignment check:', {
                senderId: msg.sender_id,
                senderIdType: typeof msg.sender_id,
                currentUserId: window.currentUserId,
                currentUserIdType: typeof window.currentUserId,
                isSent: isSent,
                messagePreview: msg.message_text.substring(0, 20)
            });
            
            // Set alignment classes based on who sent the message
            // justify-end = align right (sent messages)
            // justify-start = align left (received messages)
            const alignClass = isSent ? 'justify-end' : 'justify-start';
            
            // Set background color based on who sent the message
            // Purple background for sent messages
            // White background with border for received messages
            const bgClass = isSent 
                ? 'bg-purple-600 text-white' 
                : 'bg-white text-gray-800 border border-gray-200';
            
            // Build the HTML for this message
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
        
        // Set the built HTML into the container
        container.innerHTML = html;
        
        // Scroll to bottom to show newest messages
        // setTimeout ensures DOM has updated before scrolling
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }

    // ------------------------------------------------------------------------
    // SEND MESSAGE
    // ------------------------------------------------------------------------
    // This sends a message to the server via AJAX
    async sendMessage() {
        console.log('📤 Sending message...');
        
        const messageInput = document.getElementById('messageInput');
        const receiverInput = document.getElementById('receiverId');
        
        // trim() removes whitespace from start and end
        const message = messageInput?.value.trim();
        const receiverId = receiverInput?.value;
        
        // Validate input
        if (!message || !receiverId) {
            console.warn('❌ Cannot send: missing message or receiver');
            return;
        }
        
        console.log('💬 Sending to:', receiverId, 'Message:', message);
        
        try {
            // FormData is used to send POST data (like a form submission)
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message_text', message);
            formData.append('csrf_token', window.csrfToken || '');
            
            // Send POST request to server
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
                // Clear the input field
                messageInput.value = '';
                
                // Reload messages to show the new message
                this.loadMessages(receiverId);
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Send error:', error);
            alert('Network error: Failed to send message. Check console for details.');
        }
    }

    // ------------------------------------------------------------------------
    // START POLLING
    // ------------------------------------------------------------------------
    // This checks for new messages every 3 seconds
    startPolling(userId) {
        // Clear any existing polling interval
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        // setInterval() runs a function repeatedly at specified interval
        // Returns an ID that can be used with clearInterval() to stop it
        this.pollInterval = setInterval(() => {
            // Only poll if we're still in the same chat
            if (this.currentChatUser === userId) {
                this.loadMessages(userId);
            }
        }, 3000); // 3000 milliseconds = 3 seconds
    }

    // ------------------------------------------------------------------------
    // CLEAR CHAT
    // ------------------------------------------------------------------------
    // This closes the current chat and resets the interface
    clearChat() {
        console.log('🧹 Clearing chat...');
        
        // Reset current chat user
        this.currentChatUser = null;
        
        // Stop polling for new messages
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        const container = document.getElementById('chatMessages');
        const inputArea = document.getElementById('messageInputArea');
        const header = document.getElementById('chatHeader');
        
        // Show placeholder message
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
        
        // Hide message input area
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

    // ------------------------------------------------------------------------
    // ESCAPE HTML
    // ------------------------------------------------------------------------
    // This prevents XSS (Cross-Site Scripting) attacks by escaping HTML
    // For example: <script> becomes &lt;script&gt; (harmless text)
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text; // textContent auto-escapes
        return div.innerHTML;
    }

    // ------------------------------------------------------------------------
    // FORMAT TIME
    // ------------------------------------------------------------------------
    // This converts timestamps to human-readable format
    // Examples: "Just now", "5m ago", "14:30", "Jan 15"
    formatTime(timestamp) {
        if (!timestamp) return 'Just now';
        
        try {
            let date;
            
            // Handle different timestamp formats
            if (timestamp instanceof Date) {
                date = timestamp;
            } else if (typeof timestamp === 'string') {
                // Try parsing the timestamp
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
            if (diff < 86400000) { // Less than 24 hours
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: false // 24-hour format
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

// ============================================================================
// INITIALIZATION
// ============================================================================
// This code runs when the script loads

console.log('🔧 Setting up DOM ready listener...');

// Function to initialize the chat system
function initializeChat() {
    // Prevent multiple initializations (defensive programming)
    if (window.simpleChat) {
        console.warn('⚠️ simpleChat already initialized, skipping...');
        return;
    }
    
    try {
        console.log('🎉 DOM fully loaded - initializing chat...');
        
        // Create global SimpleChat instance
        // This makes it accessible from anywhere: window.simpleChat
        window.simpleChat = new SimpleChat();
        
        console.log('✅ simpleChat initialized globally');
    } catch (error) {
        console.error('❌ Failed to initialize chat:', error);
    }
}

// Check document ready state
// readyState can be: 'loading', 'interactive', or 'complete'
if (document.readyState === 'loading') {
    // DOM not yet loaded, wait for DOMContentLoaded event
    document.addEventListener('DOMContentLoaded', initializeChat);
} else {
    // DOM already loaded, initialize immediately
    // setTimeout ensures any other scripts have finished
    setTimeout(initializeChat, 100);
}