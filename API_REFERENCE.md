# Implementation API Reference

## 📚 Complete Method Documentation

### Emoji Picker API

#### `initEmojiPicker()`
Initializes the emoji picker with categories and emojis.

**Type:** `void`
**Called from:** `init()`
**Example:**
```javascript
// Automatically called on chat initialization
this.initEmojiPicker();
```

**Emoji Categories:**
- Smileys (50+ emojis)
- Gestures (37+ emojis)  
- Hearts (21+ emojis)
- Objects (50+ emojis)
- Symbols (56+ emojis)

---

#### `showEmojiPicker()`
Displays the emoji picker modal with category tabs.

**Type:** `void`
**Called from:** User clicks emoji button
**DOM Modifications:** Creates and appends `#emojiPickerModal` to `body`

**Features:**
- Displays all 5 emoji categories
- Category tabs for switching
- Close button (X)
- Scrollable grid layout
- Toggle behavior (click again to close)

**Example:**
```javascript
// User clicks emoji button
document.getElementById('emojiPickerBtn').addEventListener('click', () => {
    window.simpleChat.showEmojiPicker();
});
```

**HTML Structure:**
```html
<div id="emojiPickerModal" class="fixed bottom-20 right-4 ...">
    <div class="header">
        <h3>Emojis</h3>
        <button onclick="window.simpleChat.hideEmojiPicker()">X</button>
    </div>
    <div class="category-tabs">
        <!-- 5 category tabs -->
    </div>
    <div class="emoji-grids">
        <!-- 5 emoji grids (only 1 visible) -->
    </div>
</div>
```

---

#### `switchEmojiCategory(category)`
Switches between emoji categories in the picker.

**Parameters:**
- `category` (string): Category name ('Smileys', 'Gestures', 'Hearts', 'Objects', 'Symbols')

**Type:** `void`
**Example:**
```javascript
window.simpleChat.switchEmojiCategory('Gestures');
// Shows gesture emojis, hides others
```

**Logic:**
1. Hide all emoji grids (add `hidden` class)
2. Deactivate all tabs (remove `active` class)
3. Show selected grid (remove `hidden` class)
4. Activate selected tab (add `active` class)

---

#### `hideEmojiPicker()`
Removes the emoji picker modal from DOM.

**Type:** `void`
**Example:**
```javascript
window.simpleChat.hideEmojiPicker();
// Modal is removed from page
```

---

#### `insertEmoji(emoji)`
Inserts emoji at cursor position in message input.

**Parameters:**
- `emoji` (string): Emoji character (e.g., '😀', '❤️', '👍')

**Type:** `void`
**Returns:** Nothing
**Side Effects:** 
- Modifies `#messageInput` value
- Updates cursor position
- Triggers typing indicator

**Example:**
```javascript
window.simpleChat.insertEmoji('😊');
// Inserts 😊 into message input at cursor

// Input before: "Hello|"
// Input after: "Hello😊|"
```

**Algorithm:**
```javascript
1. Get message input element
2. Get cursor position (selectionStart, selectionEnd)
3. Get current text
4. Split text at cursor: before + emoji + after
5. Update input value with new text
6. Move cursor after emoji
7. Focus input
8. Send typing status
```

---

### File Upload API

#### `bindFileUpload()`
Initializes file upload system and defines file categories.

**Type:** `void`
**Called from:** `init()`
**Sets:** `this.fileCategories`, `this.selectedFiles`

**File Categories:**
```javascript
{
    'Photos': {
        icon: '📷',
        extensions: ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        maxSize: 10485760 // 10MB
    },
    'Documents': {
        icon: '📄',
        extensions: ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'xls', 'ppt', 'pptx'],
        maxSize: 10485760
    },
    'Audio': {
        icon: '🎵',
        extensions: ['mp3', 'wav', 'ogg', 'm4a', 'flac'],
        maxSize: 10485760
    },
    'Other': {
        icon: '📎',
        extensions: ['zip', 'rar', '7z', 'tar', 'gz', 'exe', 'sh'],
        maxSize: 10485760
    }
}
```

---

#### `initFileUpload(receiverId)`
Attaches event listeners for file upload button.

**Parameters:**
- `receiverId` (number): ID of recipient user

**Type:** `void`
**Called from:** `openChat()`
**Binds:** Click handler to `#fileUploadBtn`

**Example:**
```javascript
// Called when opening chat with user ID 5
this.initFileUpload(5);

// Now clicking file button opens modal for user 5
```

---

#### `showFileUploadModal(receiverId)`
Displays the file upload modal with category tabs.

**Parameters:**
- `receiverId` (number): ID of recipient user

**Type:** `void`
**DOM Modifications:** Creates and appends `#fileUploadModal` to `body`

**Modal Structure:**
```html
<div id="fileUploadModal">
    <header>Upload File</header>
    <div class="content">
        <div class="tabs">
            <!-- 4 category tabs -->
        </div>
        <div class="upload-areas">
            <!-- 4 upload areas (only 1 visible) -->
        </div>
    </div>
    <footer>
        <button>Cancel</button>
        <button>Send Files</button>
    </footer>
</div>
```

**Features:**
- 4 category tabs (Photos, Documents, Audio, Other)
- Drag & drop zones
- File browse buttons
- File preview list
- Send and Cancel buttons

---

#### `switchFileCategory(category)`
Switches between file upload categories.

**Parameters:**
- `category` (string): Category name ('Photos', 'Documents', 'Audio', 'Other')

**Type:** `void`
**Example:**
```javascript
window.simpleChat.switchFileCategory('Photos');
// Shows photo upload area, hides others
```

---

#### `handleFileSelect(event, category)`
Processes files selected through file input dialog.

**Parameters:**
- `event` (Event): File input change event
- `category` (string): File category

**Type:** `void`
**Called from:** File input `onchange` handler
**Flow:**
1. Extract files from event
2. Call `processFiles()`

**Example:**
```html
<input type="file" 
       onchange="window.simpleChat.handleFileSelect(event, 'Photos')"
       accept=".jpg, .jpeg, .png, .gif, .bmp, .webp">
```

---

#### `handleFileDrop(event, category)`
Processes files dropped into the upload zone.

**Parameters:**
- `event` (DragEvent): Drag drop event
- `category` (string): File category

**Type:** `void`
**Called from:** Drop zone `ondrop` handler

**Example:**
```html
<div ondrop="window.simpleChat.handleFileDrop(event, 'Photos')"
     ondragover="event.preventDefault()">
    Drag files here
</div>
```

---

#### `processFiles(files, category)`
Validates and adds files to selection.

**Parameters:**
- `files` (FileList|Array): Files to process
- `category` (string): File category

**Type:** `void`
**Validations:**
1. Check file size (max 10MB)
2. Check file extension
3. Prevent duplicates

**Example:**
```javascript
const files = [...event.target.files];
this.processFiles(files, 'Photos');
```

**Validation Logic:**
```javascript
for each file:
  1. Check file.size <= 10485760
  2. Get file extension from file.name
  3. Check extension in category.extensions
  4. Add to selectedFiles[category] if no duplicate
  5. Call updateFilePreview()
```

---

#### `updateFilePreview(category)`
Updates the file preview list for a category.

**Parameters:**
- `category` (string): File category

**Type:** `void`
**DOM Target:** `#preview-[category]`

**Displays for each file:**
- File icon (based on category)
- File name
- File size (in KB)
- Remove button (X)

**Example Preview:**
```
Selected Files:
┌─ 🖼️ photo.jpg
│  256.45 KB [X]
└─ 🖼️ vacation.png
   1024.32 KB [X]
```

---

#### `removeFile(category, index)`
Removes a file from the selection.

**Parameters:**
- `category` (string): File category
- `index` (number): File index in array

**Type:** `void`
**Example:**
```javascript
window.simpleChat.removeFile('Photos', 0);
// Removes first photo from selection
// Preview updates automatically
```

---

#### `hideFileUploadModal()`
Removes the file upload modal and clears selections.

**Type:** `void`
**Side Effects:**
- Removes `#fileUploadModal` from DOM
- Clears `this.selectedFiles`

---

#### `uploadSelectedFiles(receiverId)`
Uploads all selected files sequentially.

**Parameters:**
- `receiverId` (number): Recipient user ID

**Type:** `Promise<void>`
**Flow:**
1. Collect all selected files from all categories
2. Show progress indicator
3. Upload each file (sequential)
4. Update progress after each
5. Hide progress and modal
6. Reload messages
7. Show success/failure feedback

**Example:**
```javascript
await window.simpleChat.uploadSelectedFiles(5);
// Uploads all files to user 5
```

**Algorithm:**
```
1. Collect all files from this.selectedFiles
2. Validate count > 0
3. Call showUploadProgress(count)
4. For each file:
   a. Call uploadFile()
   b. Increment success count
   c. Call updateUploadProgress()
5. Call hideUploadProgress()
6. Call hideFileUploadModal()
7. Call loadMessages()
8. Show success alert
```

---

#### `uploadFile(file, receiverId)`
Uploads a single file to the server.

**Parameters:**
- `file` (File): File to upload
- `receiverId` (number): Recipient user ID

**Type:** `Promise<boolean>`
**Returns:** `true` if successful, `false` if failed

**Server Endpoint:** `chat/upload_file.php`

---

#### Utility Methods (Client-side)

- `canPlayAudioType(mime)` – returns `true` if the browser can play the specified audio MIME type.
- `drawWaveform(file, canvas)` – renders a simple waveform preview of the audio file into the given `<canvas>` element.
- `updateRecordingTimer()` – refreshes the text of the timer element adjacent to the microphone button.

---

#### `startRecording()` / `stopRecording()`
Control microphone capture for voice messages. Recording is toggled automatically when the audio button is clicked; these methods can also be called directly from the console.

**Behavior:**
1. `startRecording()` requests microphone access and begins capturing audio chunks.
2. When `stopRecording()` is invoked (or button clicked again), recording stops, the audio blob is converted to a `File`, and `uploadFile()` is called to send the voice message to the current chat user.
3. Button icon updates to indicate recording state.

**Returns:**
- `startRecording()` may throw if access denied
- `stopRecording()` returns immediately

**Example:**
```javascript
// start manually
window.simpleChat.startRecording();
// ...speak...
window.simpleChat.stopRecording();
// voice message will be uploaded automatically
```

**Notes:**
- Only available when a chat is open (`currentChatUser` set).
- Requires browser support for `navigator.mediaDevices.getUserMedia` and `MediaRecorder`.
- Maximum recording duration is **60 seconds**; a timer is displayed while capturing.
- After stopping the recording a waveform preview appears before upload; the user may cancel or manually trigger `uploadSelectedFiles()`.
- Audio is encoded as WebM/Opus (fallback to Ogg if WebM unsupported). Playback will fall back to a download link when the browser cannot play the format.

**Method:** POST
**FormData:**
- `file`: The file object
- `receiver_id`: Recipient user ID
- `csrf_token`: CSRF protection token

**Response (JSON):**
```json
{
    "success": true/false,
    "message": "Success or error message",
    "file_id": 123,
    "filename": "uploaded_filename.ext"
}
```

**Example:**
```javascript
const result = await this.uploadFile(fileObject, 5);
// Returns: true or false
```

---

#### `showUploadProgress(totalFiles)`
Displays upload progress indicator.

**Parameters:**
- `totalFiles` (number): Total files to upload

**Type:** `void`
**DOM:** Creates `#uploadProgress` div

**Progress Bar:**
```html
<div id="uploadProgress">
    <h4>Uploading files...</h4>
    <div class="progress-bar">
        <div id="progressBar" style="width: 0%"></div>
    </div>
    <p><span id="progressCount">0</span>/5 files</p>
</div>
```

---

#### `updateUploadProgress(current, total)`
Updates progress bar during upload.

**Parameters:**
- `current` (number): Current file index
- `total` (number): Total files

**Type:** `void`
**Updates:**
- Progress bar width: `(current / total) * 100%`
- Count text: `"current/total"`

**Example:**
```javascript
// After uploading 3 of 5 files
this.updateUploadProgress(3, 5);
// Progress bar shows 60%, text shows "3/5 files"
```

---

#### `hideUploadProgress()`
Removes the progress indicator.

**Type:** `void`
**Delay:** 500ms (fade out animation)

---

### Utility Methods

#### `escapeHtml(text)`
Prevents XSS attacks by escaping HTML characters.

**Parameters:**
- `text` (string): Text to escape

**Type:** `string`
**Returns:** HTML-safe text

**Example:**
```javascript
const escaped = this.escapeHtml('<script>alert("XSS")</script>');
// Returns: &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;
```

---

#### `formatTime(timestamp)`
Converts timestamp to human-readable format.

**Parameters:**
- `timestamp` (string|Date|number): Timestamp

**Type:** `string`
**Returns:** Formatted time string

**Examples:**
```javascript
this.formatTime(new Date()); 
// Returns: "Just now" (if < 1 minute old)

this.formatTime("2024-01-20 14:30:00");
// Returns: "2 hours ago" or "14:30" depending on age
```

**Time Format Rules:**
- < 1 minute: "Just now"
- < 1 hour: "Xm ago"
- < 24 hours: "HH:mm"
- > 24 hours: "Mon 20, 14:30"

---

#### `scrollToBottom()`
Smoothly scrolls chat to latest message.

**Type:** `void`
**Target:** `#chatMessages` container
**Delay:** 100ms

**Example:**
```javascript
this.scrollToBottom();
// Scrolls chat to bottom with animation
```

---

## 🔗 Event Flow

### Emoji Insertion Flow
```
User clicks emoji button
    ↓
showEmojiPicker() called
    ↓
Modal appears with categories
    ↓
User clicks category tab
    ↓
switchEmojiCategory() updates display
    ↓
User clicks emoji
    ↓
insertEmoji(emoji) called
    ↓
Updates input value at cursor
    ↓
Modal stays open (user can add more)
    ↓
User clicks outside → hideEmojiPicker()
```

### File Upload Flow
```
User clicks file button
    ↓
showFileUploadModal(receiverId) called
    ↓
Modal appears with categories
    ↓
User selects category tab
    ↓
switchFileCategory() updates display
    ↓
User selects files (browse or drag)
    ↓
handleFileSelect/handleFileDrop() called
    ↓
processFiles() validates and stores
    ↓
updateFilePreview() shows files
    ↓
User clicks "Send Files"
    ↓
uploadSelectedFiles(receiverId) called
    ↓
showUploadProgress() shows progress bar
    ↓
uploadFile() uploads each file sequentially
    ↓
updateUploadProgress() updates bar
    ↓
hideUploadProgress() and hideFileUploadModal()
    ↓
loadMessages() reloads chat
    ↓
Success alert shown
```

---

## � Pinned Messages API

- `GET /chat/get_pinned_messages.php` - retrieve messages you've pinned.
  - query parameters:
    - `chat_with` (user ID) when `is_group` is omitted or `0`.
    - `is_group=1&group_id=Y` for a group conversation.
  - response example:
    ```json
    {
      "success": true,
      "messages": [
        {"message_id":1,"sender_id":2,"sender_name":"Alice","message_text":"Hello","created_at":"2025-01-01 12:00:00"},
        ...
      ]
    }
    ```

## �🔐 Security Methods

### CSRF Protection
```javascript
// Included in all POST requests
formData.append('csrf_token', window.csrfToken || '');

// Verified on server in:
// - upload_file.php
// - send_message.php
// - add_reaction.php
// - save_public_key.php
```

### End-to-End Encryption (E2EE)
The client maintains a private key and shares the public key via these simple APIs:

- `GET /api/get_public_key.php?user_id=X` – returns `{ success: true, public_key: "..." }` or an error if none exists.
- `POST /api/save_public_key.php` – authenticated JSON request with `{ public_key: "...", csrf_token: "..." }` to store the caller’s public key.

Clients derive a shared AES‑GCM key using ECDH (P-256) and encrypt/decrypt messages locally.


### XSS Prevention
```javascript
// All user input escaped before display
escapeHtml(userText);

// Database uses prepared statements
// Example: chat/send_message.php
$stmt = $conn->prepare("INSERT INTO messages (...) VALUES (?, ?, ?)");
$stmt->execute([$sender, $receiver, $text]);
```

### File Validation
```javascript
// Client-side
- Check file size: file.size <= 10485760
- Check extension: extension in allowedList

// Server-side (upload_file.php)
- Re-validate all checks
- Don't trust client validation
- Use whitelist, not blacklist
- Store outside web root if possible
```

---

## 📊 State Management

### Class Properties
```javascript
this.currentChatUser = null;           // Current chat recipient
this.currentUserId = userId;           // Logged-in user ID
this.isUserScrolling = false;          // Manual scroll detection
this.selectedFiles = {};               // Files selected for upload
this.emojiCategories = { ... };        // Emoji data
this.fileCategories = { ... };         // File category data
this.pollInterval = null;              // Message auto-refresh timer
this.typingCheckInterval = null;       // Typing status timer
```

### Local Storage (Optional)
```javascript
// Could be added for persistence
localStorage.setItem('recentEmojis', JSON.stringify(emojis));
localStorage.getItem('recentEmojis');

localStorage.setItem('uploadPreferences', JSON.stringify(settings));
```

---

## 🚀 Performance Optimization

### Lazy Loading
```javascript
// Emoji categories only render when tab clicked
// Not all 5 grids rendered at once
```

### Debouncing
```javascript
// Typing status sent max every 3 seconds
// Not on every keystroke
```

### Polling
```javascript
// Messages checked every 3 seconds
// Optimization: only refresh if count changed
if (messageCount !== this.lastMessageCount) {
    this.displayMessages(messages);
}
```

### DOM Caching
```javascript
// Same element queried multiple times - cache it
const input = document.getElementById('messageInput');
// Use `input` variable instead of re-querying
```

---

## 🐛 Error Handling

### Try-Catch Blocks
```javascript
try {
    // Upload file
    const response = await fetch(...);
    const data = await response.json();
    // Handle response
} catch (error) {
    console.error('Upload error:', error);
    alert('Upload failed. Check console for details.');
}
```

### Validation Errors
```javascript
// File too large
if (file.size > 10485760) {
    alert(`File "${file.name}" exceeds 10MB limit`);
    return;
}

// Wrong file type
if (!categoryData.extensions.includes(fileExt)) {
    alert(`File "${file.name}" is not a valid ${category} file`);
    return;
}
```

### User Feedback
```javascript
// Progress tracking
this.showUploadProgress(totalFiles);

// Success message
console.log(`✅ Uploaded: ${file.name}`);

// Failure message
alert(`Uploaded ${successCount} of ${totalFiles} files`);
```

---

## 📚 Integration Examples

### Add Emoji to Profile Update
```javascript
// In profile.php
const aboutInput = document.getElementById('aboutMe');
document.getElementById('emojiBtn').onclick = () => {
    // Create temporary emoji picker
    window.simpleChat.showEmojiPicker();
    // Modify insertEmoji to target aboutInput instead
};
```

### Add File Upload to Document Sharing
```javascript
// In documents.php
document.getElementById('uploadBtn').onclick = () => {
    window.simpleChat.showFileUploadModal(0); // 0 = document library
};
```

### Custom Emoji Categories
```javascript
// Extend categories
window.simpleChat.emojiCategories['Custom'] = {
    emojis: ['🎓', '🏫', '📚', '✏️'],
    icon: '🎓'
};
```

---

**Complete API documentation for emoji picker and file upload implementation.**
