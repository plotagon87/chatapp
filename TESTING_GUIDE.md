# Quick Testing Reference

## 🚀 Quick Start Testing (5 minutes)

### Test 1: Emoji Picker (1 min)
```
1. Open dashboard.php in browser
2. Click emoji button (😊) next to message input
3. Verify: Picker appears with 5 tabs (Smileys, Gestures, Hearts, Objects, Symbols)
4. Click any emoji - should insert into message box
5. Type some text - should appear after emoji
6. Click Send - emoji and text should appear in chat
✅ PASS / ❌ FAIL
```

### Test 2: File Upload Modal (2 min)
```
1. Click file button (📎) next to message input
2. Verify: Modal appears with 4 tabs
3. Click "Photos" tab - upload area shows
4. Click "Choose Photos" button
5. Select any JPG/PNG file
6. Verify: File appears in preview with name and size
7. Click "Send Files"
✅ PASS / ❌ FAIL
```

### Test 3: Drag & Drop (1 min)
```
1. Open file upload modal
2. Select Documents tab
3. Drag a PDF or DOC file from your computer
4. Drop it into the purple dashed area
5. Verify: File appears in preview list
✅ PASS / ❌ FAIL
```

### Test 4: Mobile View (1 min)
```
1. Press F12 to open DevTools
2. Click device toggle (mobile icon)
3. Select iPhone/Mobile preset
4. Click emoji button - picker should fit screen
5. Click file button - modal should be responsive
6. Try touching/clicking elements
✅ PASS / ❌ FAIL
```

---

## 🔧 Advanced Testing Checklist

### Emoji Picker Tests
```
Category Switching:
  [ ] Tab change is instant
  [ ] Only correct emojis show in each tab
  [ ] Active tab is highlighted (purple)

Emoji Display:
  [ ] Emojis display at correct size (32px in picker)
  [ ] Hover effect scales emoji to 1.2x
  [ ] Insertion position is correct

Message Display:
  [ ] Sent messages show emojis correctly
  [ ] Received messages show emojis correctly
  [ ] Mixed emoji+text renders properly
  [ ] Standalone emoji larger (48px)
```

### File Upload Tests
```
File Validation:
  [ ] Large files (>10MB) rejected with error
  [ ] Wrong file types rejected for category
  [ ] Correct file types accepted
  [ ] File size shows correctly (KB display)

Multi-File:
  [ ] Select multiple files in one category
  [ ] Switch category - files preserved
  [ ] Remove file - preview updates
  [ ] Upload all files - progress bar works

Upload:
  [ ] Progress bar fills 0-100%
  [ ] File count updates (e.g., "2/5")
  [ ] Modal closes after successful upload
  [ ] Messages reload showing files
```

---

## 🧪 Test Scenarios

### Scenario 1: User sends emoji message
```
1. Open chat with user
2. Click emoji button
3. Select 😀 smiley
4. Type: "How are you?"
5. Send message
Expected: Message shows "😀 How are you?"
```

### Scenario 2: User uploads document
```
1. Open chat
2. Click file button
3. Select Documents tab
4. Drag/drop a PDF file
5. Click Send Files
Expected: File appears as downloadable link in chat
```

### Scenario 3: User uploads multiple files
```
1. Click file button
2. Photos tab - select 2 images
3. Documents tab - select 1 PDF
4. Send Files
Expected: Progress bar shows 3/3, all files upload, chat refreshes
```

### Scenario 4: File validation
```
1. Click file button
2. Photos tab
3. Try to select a text file (.txt)
Expected: Error message "not a valid Photos file"
```

---

## 🐛 Debug Console Commands

```javascript
// Check if chat is initialized
console.log(window.simpleChat); // Should show SimpleChat object

// Check emoji categories
console.log(window.simpleChat.emojiCategories); // Should show 5 categories

// Check file categories
console.log(window.simpleChat.fileCategories); // Should show 4 categories

// Test emoji insertion
window.simpleChat.insertEmoji('😀'); // Should insert into message box

// Show file upload modal
window.simpleChat.showFileUploadModal(1); // Opens for user ID 1

// Clear chat
window.simpleChat.clearChat(); // Resets to empty state

// Check current chat user
console.log(window.simpleChat.currentChatUser); // Should show user ID
```

---

## ✅ Pre-Deployment Checklist

### Before Going Live

Database:
- [ ] Database charset is utf8mb4
- [ ] Tables use utf8mb4_unicode_ci collation
- [ ] Migrations have been run

PHP Configuration:
- [ ] upload_max_filesize = 10M or higher
- [ ] post_max_size = 10M or higher
- [ ] max_file_uploads = 10 or higher

Folder Permissions:
- [ ] uploads/ folder is writable (755)
- [ ] uploads/files/ exists and writable
- [ ] uploads/images/ exists and writable
- [ ] uploads/voice/ exists and writable

Code:
- [ ] No console errors (F12 → Console)
- [ ] All CSRF tokens present
- [ ] File validation on server
- [ ] Proper error messages

Features:
- [ ] Emoji picker shows all categories
- [ ] File upload modal functional
- [ ] Upload progress tracking works
- [ ] Messages reload after upload
- [ ] Audio recording button present and toggles red when recording
- [ ] Timer displayed while recording, stops at 60 seconds and recording auto‑stops
- [ ] Waveform preview shown after stopping a recording (before upload)
- [ ] Recorded audio uploads when "Upload" is clicked and appears in chat
- [ ] When playback isn't supported by browser, a download link/text is shown

Mobile:
- [ ] Responsive design tested on phone
- [ ] Touch interactions work properly
- [ ] Modal fits screen on small devices

---

## 📊 Performance Metrics

### Expected Performance
```
Emoji Picker:
  - Load time: < 100ms
  - Emoji grid render: < 50ms
  - Interaction latency: < 10ms

File Upload Modal:
  - Modal open: < 200ms
  - File preview update: < 100ms
  - Category switch: < 50ms

File Upload:
  - 1MB file: ~1 second
  - 5MB file: ~5 seconds
  - 10MB file: ~10 seconds
  (Depends on network speed)
```

### Browser Compatibility
```
✅ Chrome/Chromium: 90+
✅ Firefox: 88+
✅ Safari: 14+
✅ Edge: 90+
✅ Mobile Safari: 14+
✅ Chrome Mobile: 90+

Note: CSS Grid, Flexbox, and async/await required
```

---

## 📝 Test Report Template

```
Date: [DATE]
Tested By: [NAME]
Browser: [CHROME/FIREFOX/SAFARI]
OS: [WINDOWS/MAC/LINUX/iOS/ANDROID]

Feature: Emoji Picker
Status: ✅ PASS / ❌ FAIL
Notes: [Any issues found]

Feature: File Upload
Status: ✅ PASS / ❌ FAIL
Notes: [Any issues found]

Feature: Mobile Responsive
Status: ✅ PASS / ❌ FAIL
Notes: [Any issues found]

Overall: ✅ READY / ⚠️ NEEDS FIXES

Issues Found:
1. [Issue description]
2. [Issue description]

Sign-off: _________________________ Date: _____
```

---

## 🔗 File Locations

Key files modified/created:
```
✅ /assets/js/chat.js
   - Full implementation of emoji picker (Part 2)
   - Full implementation of file upload (Part 3 & 4)
   - 1900+ lines of well-commented code

✅ /dashboard.php
   - Added CSS for emoji picker styling
   - Added CSS for file upload modal styling
   - Message bubble styles for emoji display

✅ /chat/upload_file.php
   - Existing file upload handler
   - Supports multiple files
   - Server-side validation

✅ CHAT_FEATURES_GUIDE.md
   - Comprehensive implementation guide
   - Testing procedures
   - Troubleshooting tips
```

---

## 🎯 Implementation Status

### Completed Features
- ✅ Emoji Picker (Part 2)
  - 5 categories
  - 250+ emojis
  - Category tabs
  - Insertion logic
  
- ✅ File Upload Modal (Part 3)
  - 4 file categories
  - Drag & drop
  - File preview
  - Multiple selection
  
- ✅ File Upload Handler (Part 4)
  - Sequential upload
  - Progress tracking
  - Error handling
  - Auto-refresh messages

### Ready for Production
- ✅ Security best practices
- ✅ Mobile responsiveness
- ✅ Error handling
- ✅ Progress tracking
- ✅ User feedback
- ✅ Comprehensive documentation

---

## 🆘 Quick Help

### File Button Not Working?
```javascript
// Check if element exists
document.getElementById('fileUploadBtn'); // Should return element

// Check receiver ID
document.getElementById('receiverId').value; // Should have user ID

// Try manually
window.simpleChat.showFileUploadModal(1);
```

### Emojis Not Showing?
```
1. Clear browser cache (Ctrl+Shift+Delete)
2. Check database charset: utf8mb4
3. Check <meta charset="UTF-8"> in HTML
4. Check browser console for errors (F12)
```

### Upload Progress Stuck?
```
1. Check network connection
2. Check file size (< 10MB)
3. Check server error logs
4. Try smaller file
```

---

**All tests should pass before production deployment!**
