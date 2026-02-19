// presentation.js - handles presenter UI and viewer polling
console.log('presentation.js loaded');

document.addEventListener('DOMContentLoaded', function() {
    const saveTitleBtn = document.getElementById('saveTitleBtn');
    const toggleActiveBtn = document.getElementById('toggleActiveBtn');
    const allowDownload = document.getElementById('allowDownload');
    const uploadForm = document.getElementById('uploadForm');
    const slideList = document.getElementById('slideList');
    const userSelect = document.getElementById('userSelect');
    const authorizedList = document.getElementById('authorizedList');
    const announcementForm = document.getElementById('announcementForm');
    const announcementContent = document.getElementById('announcementContent');
    const announcementList = document.getElementById('announcementList');
    const prevSlideBtn = document.getElementById('prevSlideBtn');
    const nextSlideBtn = document.getElementById('nextSlideBtn');
    const currentSlideDisplay = document.getElementById('currentSlideDisplay');

    if (saveTitleBtn) {
        saveTitleBtn.addEventListener('click', () => {
            const title = document.getElementById('presentationTitle').value.trim();
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `action=save_title&title=${encodeURIComponent(title)}&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(r=>r.json()).then(res=>{
                if (res.success) alert('Title saved');
            });
        });
    }
    if (toggleActiveBtn) {
        toggleActiveBtn.addEventListener('click', () => {
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=toggle_active&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(r=>r.json()).then(res=>{
                if (res.success) {
                    toggleActiveBtn.textContent = res.is_active ? 'Stop Presentation' : 'Start Presentation';
                    alert('Presentation ' + (res.is_active ? 'started' : 'stopped'));
                }
            });
        });
    }
    if (allowDownload) {
        allowDownload.addEventListener('change', () => {
            const allow = allowDownload.checked ? '1' : '0';
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=toggle_download&allow=${allow}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
        });
    }
    if (uploadForm) {
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(uploadForm);
            fd.append('action','upload_file');
            fd.append('csrf_token',csrfToken);
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method:'POST',
                body: fd
            }).then(r=>r.json()).then(res=>{
                if (res.success && res.file) {
                    const li = document.createElement('li');
                    li.dataset.fileId = res.file.id;
                    li.textContent = `${res.file.name} (slide ${res.file.slide})`;
                    slideList.appendChild(li);
                }
            });
        });
    }
    if (userSelect) {
        userSelect.addEventListener('change', () => {
            const uid = userSelect.value;
            if (!uid) return;
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=add_viewer&user_id=${uid}&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(r=>r.json()).then(res=>{
                if (res.success) {
                    // refresh the authorized list
                    location.reload();
                } else {
                    alert('Error adding viewer: ' + (res.error || 'unknown'));
                }
            });
        });
    }
    
    // Group selection
    const groupSelect = document.getElementById('groupSelect');
    if (groupSelect) {
        groupSelect.addEventListener('change', () => {
            const gid = groupSelect.value;
            if (!gid) return;
            fetch(`${window.baseUrl}api/presentation_api.php`, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=add_group&group_id=${gid}&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(r=>r.json()).then(res=>{
                if (res.success) {
                    alert('Group added! Notifications sent to ' + res.members_notified + ' members.');
                    location.reload();
                } else {
                    alert('Error adding group: ' + (res.error || 'unknown'));
                }
            });
        });
    }
    
    // Handle viewer approval/removal
    const authorizedContainer = document.getElementById('authorizedContainer');
    if (authorizedContainer) {
        authorizedContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const viewerDiv = btn.closest('[data-viewer-id]');
            if (!viewerDiv) return;
            const viewerId = viewerDiv.dataset.viewerId;
            const viewerType = viewerDiv.dataset.viewerType;
            
            if (btn.classList.contains('approveBtn')) {
                // Toggle approval
                fetch(`${window.baseUrl}api/presentation_api.php`, {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:`action=toggle_approval_id&viewer_id=${viewerId}&csrf_token=${encodeURIComponent(csrfToken)}`
                }).then(r=>r.json()).then(res=>{
                    if (res.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (res.error || 'unknown'));
                    }
                });
            } else if (btn.classList.contains('removeBtn')) {
                if (confirm('Remove this ' + viewerType + '?')) {
                    fetch(`${window.baseUrl}api/presentation_api.php`, {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:`action=remove_viewer_id&viewer_id=${viewerId}&csrf_token=${encodeURIComponent(csrfToken)}`
                    }).then(r=>r.json()).then(res=>{
                        if (res.success) {
                            viewerDiv.remove();
                        } else {
                            alert('Error: ' + (res.error || 'unknown'));
                        }
                    });
                }
            }
        });
    }
    if (announcementForm) {
        announcementForm.addEventListener('submit',(e)=>{
            e.preventDefault();
            const content = announcementContent.value.trim();
            if (!content) return;
            fetch(`${window.baseUrl}api/presentation_api.php`,{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=add_announcement&content=${encodeURIComponent(content)}&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(r=>r.json()).then(res=>{
                if (res.success) {
                    const li=document.createElement('li');
                    li.textContent = res.announcement.content + ' ';
                    announcementList.prepend(li);
                    announcementContent.value='';
                }
            });
        });
    }
    const changeSlide = (dir) => {
        fetch(`${window.baseUrl}api/presentation_api.php`,{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=change_slide&dir=${dir}&csrf_token=${encodeURIComponent(csrfToken)}`
        }).then(r=>r.json()).then(res=>{
            if (res.success) {
                currentSlideDisplay.textContent = res.current_slide;
            }
        });
    };
    if (prevSlideBtn) prevSlideBtn.addEventListener('click',()=>changeSlide('prev'));
    if (nextSlideBtn) nextSlideBtn.addEventListener('click',()=>changeSlide('next'));

    // presenter preview polling (same as viewer but only slide embed)
    if (typeof presentationId !== 'undefined' && document.getElementById('previewArea')) {
        const updatePreview = (status) => {
            const slide = status.files.find(f=>f.slide_number == status.presentation.current_slide);
            const preview = document.getElementById('previewArea');
            if (slide && preview) {
                preview.innerHTML = `<embed src="${window.baseUrl}${slide.file_path}" type="application/pdf" width="100%" height="400px" />`;
            }
        };
        const pollPreview = () => {
            fetch(`${window.baseUrl}api/presentation_api.php?action=get_status&presentation_id=${presentationId}`)
                .then(r=>r.json()).then(res=>{
                    if (res.success) {
                        updatePreview(res);
                    }
                }).catch(()=>{});
        };
        // only poll when presenter is active (controls exist)
        setInterval(pollPreview,2000);
        pollPreview();
    }
});

// viewer polling (if on presentation_view page)
if (window.location.pathname.endsWith('presentation_view.php')) {
    let presentationId = new URLSearchParams(window.location.search).get('pid');
    if (presentationId) {
        const poll = () => {
            console.log(`[presentation] polling status at ${new Date().toISOString()}`);
            fetch(`${window.baseUrl}api/presentation_api.php?action=get_status&presentation_id=${presentationId}`)
                .then(r=>r.json()).then(res=>{
                    console.log('[presentation] poll response', res);
                    if (res.success) {
                        // update slide or announcements
                        const cs = document.getElementById('currentSlideDisplay');
                        if (cs && res.presentation.current_slide) cs.textContent = res.presentation.current_slide;
                        // show slides
                        const viewerArea = document.getElementById('viewerArea');
                        if (viewerArea) {
                            const slide = res.files.find(f=>f.slide_number == res.presentation.current_slide);
                            if (slide) {
                                // log download/embedding activity for debugging
                                console.log(`[presentation] embedding slide ${slide.slide_number} (${slide.file_path}) at ${new Date().toISOString()}`);
                                viewerArea.innerHTML = `<embed src="${window.baseUrl}${slide.file_path}" type="application/pdf" width="100%" height="600px" />`;
                            }
                        }
                        // announcements
                        const annContainer = document.getElementById('announcementsContainer');
                        if (annContainer) {
                            annContainer.innerHTML = '';
                            res.announcements.forEach(a=>{
                                const div=document.createElement('div');
                                div.className='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-2 relative';
                                div.innerHTML=`${a.content}<button class="absolute top-0 right-0 mt-1 mr-2 text-yellow-900 text-sm dismissBtn">×</button>`;
                                annContainer.appendChild(div);
                                // auto-remove after 30s
                                setTimeout(()=>{if(div.parentNode)div.remove();},30000);
                            });
                        }
                    }
                }).catch(err=>{});
        };
        setInterval(poll,2000);
        poll();
        // dismiss listener
        document.addEventListener('click', function(e){
            if (e.target.classList.contains('dismissBtn')) {
                e.target.closest('div').remove();
            }
        });
    }
}
