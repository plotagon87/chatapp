    <script>
        let selectedFile = null;
        // audio recording state
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        // recording helpers
        let maxRecordingSeconds = 60; // 1 minute cap
        let recordingTimer = null;
        let recordingTimeElapsed = 0;

        // Toggle members panel
        function toggleMembersPanel() {
            document.getElementById('membersPanel').classList.toggle('hidden');
        }

        // start voice recording
        function startRecordingGroup() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Audio recording not supported');
                return;
            }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                recordedChunks = [];
                // choose mime type
                let mime = 'audio/webm';
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    mime = 'audio/webm;codecs=opus';
                } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                    mime = 'audio/ogg;codecs=opus';
                }
                try {
                    mediaRecorder = new MediaRecorder(stream, { mimeType: mime });
                } catch (e) {
                    console.warn('could not set mime type for recorder', e);
                    mediaRecorder = new MediaRecorder(stream);
                }
                mediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) recordedChunks.push(e.data);
                };
                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    handleRecordingCompleteGroup();
                };
                mediaRecorder.start();
                isRecording = true;
                recordingTimeElapsed = 0;
                recordingTimer = setInterval(() => {
                    recordingTimeElapsed++;
                    updateRecordingTimerGroup();
                    if (recordingTimeElapsed >= maxRecordingSeconds) {
                        stopRecordingGroup();
                        alert('Maximum recording duration reached');
                    }
                }, 1000);
                updateRecordingButtonGroup();
            }).catch(err => {
                console.error('mic error', err);
                alert('Microphone access denied');
            });
        }

        function stopRecordingGroup() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                isRecording = false;
                if (recordingTimer) {
                    clearInterval(recordingTimer);
                    recordingTimer = null;
                }
                updateRecordingButtonGroup();
                updateRecordingTimerGroup();
            }
        }

        function handleRecordingCompleteGroup() {
            if (recordedChunks.length === 0) return;
            // determine mime/extension from recorder if available
            let mime = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : 'audio/webm';
            const ext = mime.includes('ogg') ? 'ogg' : 'webm';
            const blob = new Blob(recordedChunks, { type: mime });
            const file = new File([blob], `voice_${Date.now()}.${ext}`, { type: mime });
            selectedFile = file;
            // show in preview area with waveform
            document.getElementById('filePreview').classList.remove('hidden');
            document.getElementById('filePreview').innerHTML = `
                <audio controls class="w-full"><source src="${URL.createObjectURL(file)}" type="${mime}">Your browser does not support audio playback.</audio>
                <canvas class="waveform-canvas mt-2 w-full h-12"></canvas>`;
            drawWaveformGroup(file, document.querySelector('#filePreview .waveform-canvas'));
            document.getElementById('uploadBtn').disabled = false;
        }

        function updateRecordingButtonGroup() {
            const btn = document.getElementById('audioRecordBtn');
            if (!btn) return;
            if (isRecording) {
                btn.innerHTML = `<svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>`;
                btn.title = 'Stop recording';
            } else {
                btn.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1v4m0 14v4m4-10a4 4 0 01-8 0V7a4 4 0 018 0z"/></svg>`;
                btn.title = 'Record audio';
            }
            updateRecordingTimerGroup();
        }


        // Auto-scroll to bottom
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
            renderEmojiPicker();

            // bind audio button if present
            const audioBtn = document.getElementById('audioRecordBtn');
            if (audioBtn) {
                audioBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (isRecording) {
                        stopRecordingGroup();
                    } else {
                        startRecordingGroup();
                    }
                });
                updateRecordingButtonGroup();
            }
        });

        // waveform helper, timer, compatibility
        function updateRecordingTimerGroup() {
            const span = document.getElementById('recordingTimer');
            if (!span) return;
            span.textContent = isRecording ? `${recordingTimeElapsed}s` : '';
        }

        function canPlayAudioTypeGroup(mime) {
            const a = document.createElement('audio');
            return !!(a.canPlayType && a.canPlayType(mime));
        }

        function drawWaveformGroup(file, canvas) {
            if (!canvas || !file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                audioCtx.decodeAudioData(e.target.result, (buffer) => {
                    const data = buffer.getChannelData(0);
                    const step = Math.ceil(data.length / canvas.width);
                    const amp = canvas.height / 2;
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle = '#a78bfa';
                    for (let i = 0; i < canvas.width; i++) {
                        let min = 1.0;
                        let max = -1.0;
                        for (let j = 0; j < step; j++) {
                            const datum = data[(i * step) + j];
                            if (datum < min) min = datum;
                            if (datum > max) max = datum;
                        }
                        ctx.fillRect(i, (1 + min) * amp, 1, Math.max(1, (max - min) * amp));
                    }
                });
            };
            reader.readAsArrayBuffer(file);
        }

        // ============================================
        // MESSAGE REACTIONS (group-specific globals)
        // ============================================
        function showReactionPicker(messageId, event) {
            event.stopPropagation();
            const existing = document.getElementById('reactionPicker');
            if (existing) {
                existing.remove();
                return;
            }
            const picker = document.createElement('div');
            picker.id = 'reactionPicker';
            picker.className = 'absolute bg-white rounded-lg shadow-2xl p-2 z-50 border border-gray-200 flex space-x-1';
            // position near click but keep within viewport
            let left = event.pageX;
            let top = event.pageY - 50;
            const pad = 10;
            const pickerWidth = 200; // approximate
            const pickerHeight = 60;
            if (left + pickerWidth + pad > window.innerWidth) {
                left = window.innerWidth - pickerWidth - pad;
            }
            if (top < pad) {
                top = pad;
            } else if (top + pickerHeight + pad > window.innerHeight) {
                top = window.innerHeight - pickerHeight - pad;
            }
            picker.style.left = left + 'px';
            picker.style.top = top + 'px';
            const reactionEmojis = { 'like':'ðŸ‘','love':'â¤ï¸','haha':'ðŸ˜‚','wow':'ðŸ˜®','sad':'ðŸ˜¢','angry':'ðŸ˜ ' };
            let html = '';
            for (const [type, emoji] of Object.entries(reactionEmojis)) {
                html += `<button onclick="addReaction(${messageId}, '${type}')" class="text-2xl hover:scale-125 transition-transform p-1 rounded hover:bg-gray-100" title="${type}">${emoji}</button>`;
            }
            picker.innerHTML = html;
            document.body.appendChild(picker);
            setTimeout(() => {
                document.addEventListener('click', function closeReactionPicker(e) {
                    if (!e.target.closest('#reactionPicker')) {
                        picker.remove();
                        document.removeEventListener('click', closeReactionPicker);
                    }
                });
            }, 100);
        }

        async function addReaction(messageId, reactionType) {
            const picker = document.getElementById('reactionPicker');
            if (picker) picker.remove();

            // disable while processing
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"] .message-reactions`);
            if (msgElem) {
                msgElem.classList.add('opacity-50','pointer-events-none');
            }

            try {
                let action = 'add';
                if (msgElem) {
                    const badge = msgElem.querySelector(`span[onclick*="addReaction(${messageId}, '${reactionType}')"]`);
                    if (badge && badge.classList.contains('bg-purple-100')) {
                        action = 'remove';
                    }
                }

                const formData = new FormData();
                formData.append('message_id', messageId);
                formData.append('reaction_type', reactionType);
                formData.append('action', action);
                formData.append('csrf_token', csrfToken);
                const response = await fetch(`${baseUrl}chat/add_reaction.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    updateMessageReactions(messageId, data.reactions, data.user_reactions);
                } else {
                    console.error('Failed to add reaction:', data.message);
                }
            } catch (error) {
                console.error('Reaction error:', error);
            } finally {
                if (msgElem) {
                    msgElem.classList.remove('opacity-50','pointer-events-none');
                }
            }
        }

        // ------------------------------------------------
        // CONTEXT MENU FOR GROUP MESSAGES
        // ------------------------------------------------
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('chatMessages');
            if (container) {
                container.addEventListener('contextmenu', function(e) {
                    const bubble = e.target.closest('.message-bubble');
                    if (!bubble) return;
                    e.preventDefault();
                    const msgElem = bubble.closest('[data-msg-id]');
                    if (!msgElem) return;
                    const msgId = msgElem.getAttribute('data-msg-id');
                    const isSent = bubble.classList.contains('bg-purple-600');
                    showGroupContextMenu(msgId, e.pageX, e.pageY, isSent);
                });
                document.addEventListener('click', () => { const m=document.getElementById('groupContextMenu'); if(m) m.remove(); });
            }
        });

        function showGroupContextMenu(messageId, x, y, isSent) {
            const existing = document.getElementById('groupContextMenu');
            if (existing) existing.remove();
            const menu = document.createElement('div');
            menu.id = 'groupContextMenu';
            menu.className = 'absolute bg-white border border-gray-200 rounded shadow-lg z-50 text-sm';
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
            const ul = document.createElement('ul');
            ul.className = 'py-1';
            const addItem = (label, handler, disabled=false) => {
                const li = document.createElement('li');
                li.className = `px-4 py-2 hover:bg-gray-100 cursor-pointer ${disabled?'opacity-50 pointer-events-none':''}`;
                li.textContent = label;
                li.addEventListener('click', e => { e.stopPropagation(); handler(); });
                ul.appendChild(li);
            };
            let allowEdit = true;
            if (isSent) {
                // compute timestamp on element
                const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
                if (msgElem) {
                    const timeElem = msgElem.querySelector('.text-xs');
                    if (timeElem) {
                        const parsed = new Date(timeElem.textContent.trim());
                        if (!isNaN(parsed) && Date.now() - parsed.getTime() > 3 * 60 * 1000) {
                            allowEdit = false;
                        }
                    }
                }
                addItem('Edit', () => editGroupMessage(messageId), !allowEdit);
            }
            addItem('Reply', () => replyToMessage(messageId));
            // show history if message has edits
            if ((() => {
                const el = document.querySelector(`[data-msg-id="${messageId}"]`);
                return el && el.dataset.edited;
            })()) {
                addItem('View Edit History', () => showEditHistoryGroup(messageId));
            }
            // pin/unpin label based on state
            {
                const el = document.querySelector(`[data-msg-id="${messageId}"]`);
                const pinned = el && el.dataset.pinned;
                addItem(pinned ? 'Unpin' : 'Pin', () => togglePin(messageId, true));
            }
            addItem('Delete', () => deleteMessage(messageId, true));
            addItem('Message Info', () => showGroupMessageInfo(messageId));
            menu.appendChild(ul);
            document.body.appendChild(menu);
        }

        async function editGroupMessage(messageId) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"] p.break-words`);
            if (!msgElem) return;
            const old = msgElem.textContent;
            const nxt = prompt('Edit your message (3 minutes allowed):', old);
            if (nxt === null || nxt === old) return;
            const form = new FormData();
            form.append('group_message_id', messageId);
            form.append('new_text', nxt);
            form.append('csrf_token', csrfToken);
            const res = await fetch(`${baseUrl}chat/edit_group_message.php`, { method: 'POST', body: form });
            const d = await res.json();
            if (d.success) {
                msgElem.textContent = nxt + ' (edited)';
            } else {
                alert(d.message || 'Failed to edit');
            }
        }

        function showGroupMessageInfo(messageId) {
            fetch(`${baseUrl}chat/group_message_info.php?group_message_id=${messageId}`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let text = 'Status:\n';
                        d.status.forEach(s=>{
                            text += `${s.full_name}: delivered=${s.is_delivered}?${s.delivered_at}: read=${s.is_read}?${s.read_at}\n`;
                        });
                        showInfoModal('Message Info', text);
                    }
                });
        }
        function showEditHistoryGroup(messageId) {
            fetch(`${baseUrl}chat/message_edit_history.php?message_id=${messageId}&is_group=1`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let txt = 'Edit history:\n';
                        d.edits.forEach(e=>{
                            txt += `${e.edited_at} by ${e.edited_by}: ${e.old_text} â†’ ${e.new_text}\n`;
                        });
                        showInfoModal('Edit History', txt);
                    }
                });
        }

        function showInfoModal(title, content) {
            const modal = document.getElementById('infoModal');
            if (!modal) return;
            document.getElementById('infoModalTitle').textContent = title;
            document.getElementById('infoModalContent').textContent = content;
            modal.classList.remove('hidden');
        }
        function hideInfoModal() {
            const modal = document.getElementById('infoModal');
            if (modal) modal.classList.add('hidden');
        }
        // close modal when clicking outside content
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('infoModal');
            if (modal && e.target === modal) {
                hideInfoModal();
            }
        });

        function showPinnedMessages(groupId) {
            fetch(`${baseUrl}chat/get_pinned_messages.php?is_group=1&group_id=${groupId}`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let txt = '';
                        d.messages.forEach(m=>{
                            txt += `${m.sender_name} (${m.created_at}): ${m.message_text}\n`;
                        });
                        showInfoModal('Pinned Messages', txt || 'No pinned messages');
                    }
                });
        }
        async function togglePin(messageId, isGroup) {
            try {
                const form = new FormData();
                form.append('message_id', messageId);
                form.append('is_group', isGroup ? '1' : '0');
                form.append('csrf_token', csrfToken);
                const res = await fetch(`${baseUrl}chat/toggle_pin.php`, {method:'POST', body: form});
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Pin failed');
                }
            } catch (err) {
                console.error(err);
            }
        }
        function replyToMessage(messageId) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
            if (!msgElem) return;
            const text = msgElem.querySelector('p.break-words')?.textContent || '';
            const inputArea = document.getElementById('messageInputArea');
            const existing = document.getElementById('replyBanner');
            if (existing) existing.remove();
            const banner = document.createElement('div');
            banner.id = 'replyBanner';
            banner.className = 'bg-gray-100 border-l-4 border-gray-400 p-2 mb-2 flex justify-between items-center';
            banner.innerHTML = `<span class="text-sm truncate">Replying to: ${text}</span><button class="text-red-500 ml-2">Ã—</button>`;
            banner.querySelector('button').addEventListener('click', () => banner.remove());
            inputArea.insertBefore(banner, inputArea.firstChild);
            inputArea.dataset.replyTo = messageId;
            document.getElementById('messageInput').focus();
        }
        async function deleteMessage(messageId, isGroup) {
            if (!confirm('Are you sure you want to delete this message?')) return;
            try {
                const form = new FormData();
                form.append('message_id', messageId);
                form.append('is_group', isGroup ? '1' : '0');
                form.append('csrf_token', csrfToken);
                const res = await fetch(`${baseUrl}chat/delete_message.php`, {method:'POST', body: form});
                const d = await res.json();
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Delete failed');
                }
            } catch (err) {
                console.error(err);
            }
        }

        // reuse deleteMessage and replyToMessage from above (works same)
                formData.append('action', action);
                formData.append('csrf_token', csrfToken);
                const response = await fetch(`${baseUrl}chat/add_reaction.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    updateMessageReactions(messageId, data.reactions, data.user_reactions);
                } else {
                    console.error('Failed to add reaction:', data.message);
                }
            } catch (error) {
                console.error('Reaction error:', error);
            } finally {
                if (msgElem) {
                    msgElem.classList.remove('opacity-50','pointer-events-none');
                }
            }
        }

        function updateMessageReactions(messageId, reactions, userReactions) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
            if (!msgElem) return;
            let container = msgElem.querySelector('.message-reactions');
            if (!container) {
                container = document.createElement('div');
                container.className = 'message-reactions flex flex-wrap gap-1 mt-2';
                msgElem.appendChild(container);
            }
            container.innerHTML = '';
            reactions.forEach(r => {
                const isUser = userReactions && userReactions.includes(r.reaction_type);
                const emojiMap = { 'like':'ðŸ‘','love':'â¤ï¸','haha':'ðŸ˜‚','wow':'ðŸ˜®','sad':'ðŸ˜¢','angry':'ðŸ˜ ' };
                const emoji = emojiMap[r.reaction_type] || r.reaction_type;
                const badge = document.createElement('span');
                badge.className = `inline-flex items-center space-x-1 px-2 py-1 rounded-full text-xs ${isUser? 'bg-purple-100 border-purple-300' : 'bg-gray-100 border-gray-300'} border cursor-pointer hover:scale-110 transition-transform`;
                badge.onclick = () => addReaction(messageId, r.reaction_type);
                badge.title = r.reaction_type;
                badge.innerHTML = `<span>${emoji}</span><span class="font-semibold">${r.count}</span>`;
                container.appendChild(badge);
            });
        }
        // Render emoji picker
        function renderEmojiPicker() {
            const grid = document.getElementById('emojiGrid');
            const firstCat = Object.keys(emojiCategories)[0];
            let html = '<div id="emoji-grid-' + firstCat + '" class="grid grid-cols-8 gap-1">';
            emojiCategories[firstCat].emojis.forEach(emoji => {
                html += '<button type="button" onclick="insertEmoji(\'' + emoji + '\')" class="text-2xl p-1 hover:bg-gray-100 rounded">' + emoji + '</button>';
            });
            html += '</div>';
            Object.keys(emojiCategories).slice(1).forEach(cat => {
                html += '<div id="emoji-grid-' + cat + '" class="grid grid-cols-8 gap-1 hidden">';
                emojiCategories[cat].emojis.forEach(emoji => {
                    html += '<button type="button" onclick="insertEmoji(\'' + emoji + '\')" class="text-2xl p-1 hover:bg-gray-100 rounded">' + emoji + '</button>';
                });
                html += '</div>';
            });
            grid.innerHTML = html;
        }

        // Toggle emoji picker
        function toggleEmojiPicker() {
            document.getElementById('emojiPicker').classList.toggle('hidden');
        }

        // Switch emoji category
        function switchEmojiCategory(category) {
            document.querySelectorAll('.emoji-grid').forEach(g => g.classList.add('hidden'));
            document.getElementById('emoji-grid-' + category).classList.remove('hidden');
            document.querySelectorAll('.emoji-category-tab').forEach(t => {
                t.classList.remove('bg-gray-200');
                if (t.dataset.category === category) t.classList.add('bg-gray-200');
            });
        }

        // Insert emoji
        function insertEmoji(emoji) {
            const input = document.getElementById('messageInput');
            input.value += emoji;
            input.focus();
        }

        // File upload modal
        function showFileUploadModal() {
            document.getElementById('fileUploadModal').classList.remove('hidden');
        }

        function hideFileUploadModal() {
            document.getElementById('fileUploadModal').classList.add('hidden');
            clearFileSelection();
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 10485760) {
                    alert('File size exceeds 10MB limit');
                    return;
                }
                selectedFile = file;
                document.getElementById('filePreview').classList.remove('hidden');
                // if audio, show player + waveform else just show file name
                if (file.type.startsWith('audio/')) {
                    document.getElementById('filePreview').innerHTML = `
                        <audio controls class="w-full">
                            <source src="${URL.createObjectURL(file)}" type="${file.type}">
                            Your browser does not support audio playback.
                        </audio>
                        <canvas class="waveform-canvas mt-2 w-full h-12"></canvas>`;
                    const canvas = document.querySelector('#filePreview .waveform-canvas');
                    drawWaveformGroup(file, canvas);
                } else {
                    document.getElementById('filePreview').innerHTML = '';
                    document.getElementById('fileName').textContent = file.name;
                }
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function clearFileSelection() {
            selectedFile = null;
            document.getElementById('filePreview').classList.add('hidden');
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadBtn').disabled = true;
        }

        function uploadFile() {
            if (!selectedFile) return;
            
            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('group_id', groupId);
            formData.append('csrf_token', csrfToken);
            
            document.getElementById('uploadBtn').textContent = 'Uploading...';
            document.getElementById('uploadBtn').disabled = true;
            
            fetch(baseUrl + 'chat/upload_group_file.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    hideFileUploadModal();
                    location.reload();
                } else {
                    alert(data.message || 'Upload failed');
                }
                document.getElementById('uploadBtn').textContent = 'Upload to Group';
                document.getElementById('uploadBtn').disabled = !selectedFile;
            })
            .catch(err => {
                alert('Upload failed');
                document.getElementById('uploadBtn').textContent = 'Upload to Group';
                document.getElementById('uploadBtn').disabled = !selectedFile;
            });
        }

        // Send message form
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            const formData = new FormData();
            formData.append('ajax_send_message', '1');
            formData.append('message_text', message);
            // include reply id if present
            const replyTo = document.getElementById('messageInputArea')?.dataset.replyTo;
            if (replyTo) {
                formData.append('reply_to', replyTo);
            }
            formData.append('csrf_token', csrfToken);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    location.reload();
                }
            });
        });

        // Auto-refresh
        setInterval(function() {
            if (window.location.search.includes('id=')) {
                location.reload();
            }
        }, 5000);
    </script>
    <script src="assets/js/e2ee.js?v=<?php echo time(); ?>"></script>
