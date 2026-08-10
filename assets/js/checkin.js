/**
 * HR Management System - Check-in JavaScript Module
 * Handles real-time clock, Fetch API for attendance status and one-tap check-in/out
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. เริ่มทำงานนาฬิกา Realtime
    initLiveClock();

    // 2. โหลดสถานะและประวัติการเข้างาน
    loadAttendanceStatus();

    // 3. โหลดสถานะเพื่อนร่วมงานวันนี้
    loadTeamColleaguesStatus();

    // 4. ผูก Event ปุ่ม Check-in และ Check-out (2 ปุ่ม)
    const checkInBtn  = document.getElementById('checkInBtn');
    const checkOutBtn = document.getElementById('checkOutBtn');
    if (checkInBtn) {
        checkInBtn.addEventListener('click', handleCheckInSubmit);
    }
    if (checkOutBtn) {
        checkOutBtn.addEventListener('click', handleCheckInSubmit);
    }
});

/**
 * ดึงข้อมูลสถานะการเข้างานของเพื่อนร่วมงานวันนี้
 */
async function loadTeamColleaguesStatus() {
    const container = document.getElementById('teamColleaguesContainer');
    if (!container) return;

    try {
        const response = await fetch('api/get_team_status.php');
        const result = await response.json();

        if (!result.success) return;

        const members = result.data;
        if (!members || members.length === 0) {
            container.innerHTML = '<div style="color:var(--text-muted); padding:10px; text-align:center;">ไม่พบข้อมูลเพื่อนร่วมงาน</div>';
            return;
        }

        let html = '';
        members.forEach((m, idx) => {
            let cardClass = 'not-checked-in';
            let statusText = 'ยังไม่เข้างาน';

            if (m.work_state === 'working') {
                cardClass = 'checked-in';
                statusText = `เข้างาน ${m.check_in_time} น.`;
            } else if (m.work_state === 'completed') {
                cardClass = 'checked-out';
                statusText = `ออกงาน ${m.check_out_time} น.`;
            }

            const displayName = m.name.split(' ')[0] + (m.name.includes('IT') ? ' IT' : '');
            const meBadge = m.is_me ? ' (คุณ)' : '';

            const avatarHtml = m.avatar_url 
                ? `<img src="${escapeHtml(m.avatar_url)}" alt="${escapeHtml(displayName)}" style="width:52px; height:52px; border-radius:50%; object-fit:cover;">`
                : `<div class="team-avatar">${m.avatar_initial}</div>`;

            html += `
                <div class="team-card ${cardClass}" onclick="showMemberDetails(${idx})" title="${escapeHtml(m.name)} - ${statusText}">
                    <div class="team-avatar-container">
                        ${avatarHtml}
                        <div class="status-dot"></div>
                    </div>
                    <div class="team-name">${escapeHtml(displayName)}${meBadge}</div>
                </div>
            `;
        });

        container.innerHTML = html;
        window.teamMembersCache = members;

    } catch (error) {
        console.error('Error loading team colleagues:', error);
    }
}

function showMemberDetails(idx) {
    const m = window.teamMembersCache ? window.teamMembersCache[idx] : null;
    if (!m) return;

    let statusText = '⚪ ยังไม่เข้างานวันนี้';
    if (m.work_state === 'working') {
        statusText = `🟢 เข้างานแล้ว เวลา ${m.check_in_time} น. (${m.status_label})`;
    } else if (m.work_state === 'completed') {
        statusText = `🟣 ออกงานเรียบร้อย เวลา ${m.check_out_time} น.`;
    }

    Swal.fire({
        title: m.name,
        html: `
            <div style="text-align:left; font-size:0.9rem; line-height:1.6;">
                <p><strong>รหัสพนักงาน:</strong> ${m.emp_code}</p>
                <p><strong>แผนก:</strong> ${escapeHtml(m.dept_name)}</p>
                <p><strong>📞 เบอร์โทรศัพท์:</strong> ${m.phone && m.phone !== '-' ? `<a href="tel:${m.phone}" style="color:var(--primary-color); text-decoration:none; font-weight:600;">${escapeHtml(m.phone)}</a>` : 'ไม่ระบุ'}</p>
                <p><strong>สถานะวันนี้:</strong> ${statusText}</p>
            </div>
        `,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#4A90E2'
    });
}

/**
 * แสดงนาฬิกาเวลาปัจจุบัน Realtime แบบวินาทีต่อวินาที
 */
function initLiveClock() {
    const timeDisplay = document.getElementById('liveTime');
    const dateDisplay = document.getElementById('liveDate');

    function updateClock() {
        const now = new Date();
        
        // เวลา HH:mm:ss
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        if (timeDisplay) {
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }

        // วันที่ภาษาไทย
        if (dateDisplay) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateDisplay.textContent = now.toLocaleDateString('th-TH', options);
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
}

/**
 * ดึงสถานะการเข้างานวันนี้และประวัติย้อนหลังจาก API
 */
async function loadAttendanceStatus(startDate = '', endDate = '') {
    const statusBadge = document.getElementById('attendanceStatusBadge');
    const checkInTimeEl = document.getElementById('checkInTimeDisplay');
    const checkOutTimeEl = document.getElementById('checkOutTimeDisplay');
    const checkInBtn  = document.getElementById('checkInBtn');
    const checkOutBtn = document.getElementById('checkOutBtn');
    const userIpEl    = document.getElementById('userIpDisplay');
    const historyContainer = document.getElementById('attendanceHistoryTable');

    // Element สรุปชั่วโมงทำงานและ OT ประจำตัวพนักงาน
    const summaryWorkHoursEl  = document.getElementById('summaryWorkHours');
    const summaryOtHoursEl    = document.getElementById('summaryOtHours');
    const summaryOnTimeEl     = document.getElementById('summaryOnTimeCount');
    const summaryLateEl       = document.getElementById('summaryLateCount');

    try {
        const queryParams = new URLSearchParams();
        if (startDate) queryParams.append('start_date', startDate);
        if (endDate)   queryParams.append('end_date', endDate);

        const url = queryParams.toString() ? `api/get_attendance.php?${queryParams.toString()}` : 'api/get_attendance.php';
        const response = await fetch(url);
        const result = await response.json();

        if (!result.success) {
            if (response.status === 401) {
                window.location.href = 'index.php';
            }
            return;
        }

        const data = result.data;

        // แสดง IP Address และกะการทำงาน
        if (userIpEl) {
            userIpEl.textContent = `IP: ${data.user_ip}`;
        }

        const shiftBadge = document.getElementById('userShiftBadge');
        if (shiftBadge && data.user_shift) {
            shiftBadge.textContent = data.user_shift.shift_label;
        }

        // แสดงยอดสรุปชั่วโมงทำงานและเวลา OT สะสม
        if (data.summary) {
            if (summaryWorkHoursEl) summaryWorkHoursEl.textContent = `${data.summary.total_work_hours} ชม.`;
            if (summaryOtHoursEl)   summaryOtHoursEl.textContent   = `${data.summary.total_ot_hours} ชม.`;
            if (summaryOnTimeEl)    summaryOnTimeEl.textContent    = `${data.summary.on_time_count} วัน`;
            if (summaryLateEl)      summaryLateEl.textContent      = `${data.summary.late_count} วัน`;
        }

        // กรณีการเข้างานวันนี้
        const today = data.today;
        const inPhotoContainer  = document.getElementById('checkInPhotoBtnContainer');
        const outPhotoContainer = document.getElementById('checkOutPhotoBtnContainer');

        if (today) {
            if (checkInTimeEl) checkInTimeEl.textContent = today.check_in_time || '--:--';
            if (checkOutTimeEl) checkOutTimeEl.textContent = today.check_out_time || '--:--';

            if (inPhotoContainer) {
                inPhotoContainer.innerHTML = today.check_in_photo 
                    ? `<button class="btn btn-sm btn-outline" style="font-size:0.75rem; padding:3px 8px; border-radius:12px;" onclick="viewPhoto('${today.check_in_photo}', 'รูปเข้างานวันนี้ (${today.check_in_time} น.)')">ดูรูปเข้างาน</button>`
                    : '';
            }

            if (outPhotoContainer) {
                outPhotoContainer.innerHTML = today.check_out_photo 
                    ? `<button class="btn btn-sm btn-outline" style="font-size:0.75rem; padding:3px 8px; border-radius:12px;" onclick="viewPhoto('${today.check_out_photo}', 'รูปออกงานวันนี้ (${today.check_out_time} น.)')">ดูรูปออกงาน</button>`
                    : '';
            }

            // เริ่มนาฬิกาจับเวลาทำงานสะสมและคำนวณ OT
            if (today.check_in_raw) {
                startLiveWorkTimer(today.check_in_raw, today.check_out_raw, data.user_shift);
            }

            if (!today.check_out_time) {
                // มี Check-in แล้ว แต่ยังไม่ Check-out
                if (statusBadge) {
                    statusBadge.className = 'badge badge-warning';
                    statusBadge.textContent = today.status === 'late' ? 'เข้างานสาย (รอออกงาน)' : 'เข้างานแล้ว (รอออกงาน)';
                }
                if (checkInBtn) {
                    checkInBtn.disabled = true;
                    const inTitle = checkInBtn.querySelector('.btn-checkin-title');
                    if (inTitle) inTitle.textContent = `เข้างานแล้ว (${today.check_in_time})`;
                }
                if (checkOutBtn) {
                    checkOutBtn.disabled = false;
                    const outTitle = checkOutBtn.querySelector('.btn-checkin-title');
                    if (outTitle) outTitle.textContent = 'บันทึกเวลาออกงาน';
                }
            } else {
                // ลงเวลาครบทั้งเข้าและออกแล้ว
                if (statusBadge) {
                    statusBadge.className = 'badge badge-success';
                    statusBadge.textContent = `ลงเวลาเรียบร้อย (ทำงาน ${today.work_hours} ชม. | OT ${today.ot_hours} ชม.)`;
                }
                if (checkInBtn) {
                    checkInBtn.disabled = true;
                    const inTitle = checkInBtn.querySelector('.btn-checkin-title');
                    if (inTitle) inTitle.textContent = `เข้างานแล้ว (${today.check_in_time})`;
                }
                if (checkOutBtn) {
                    checkOutBtn.disabled = true;
                    const outTitle = checkOutBtn.querySelector('.btn-checkin-title');
                    if (outTitle) outTitle.textContent = `ออกงานแล้ว (${today.check_out_time})`;
                }
            }
        } else {
            // ยังไม่ได้ลงเวลาเลยในวันนี้
            if (checkInTimeEl) checkInTimeEl.textContent = '--:--';
            if (checkOutTimeEl) checkOutTimeEl.textContent = '--:--';
            if (inPhotoContainer) inPhotoContainer.innerHTML = '';
            if (outPhotoContainer) outPhotoContainer.innerHTML = '';
            startLiveWorkTimer(null, null, null);
            if (statusBadge) {
                statusBadge.className = 'badge badge-info';
                statusBadge.textContent = 'ยังไม่ได้ลงเวลาวันนี้';
            }
            if (checkInBtn) {
                checkInBtn.disabled = false;
                const inTitle = checkInBtn.querySelector('.btn-checkin-title');
                if (inTitle) inTitle.textContent = 'บันทึกเวลาเข้างาน';
            }
            if (checkOutBtn) {
                checkOutBtn.disabled = true;
                const outTitle = checkOutBtn.querySelector('.btn-checkin-title');
                if (outTitle) outTitle.textContent = 'บันทึกเวลาออกงาน';
            }
        }

        // แสดงประวัติการลงเวลา
        renderAttendanceHistory(data.history, historyContainer);

    } catch (error) {
        console.error('Error loading attendance status:', error);
    }
}

function handlePersonalHistoryFilter(e) {
    e.preventDefault();
    const start = document.getElementById('personal_start_date').value;
    const end   = document.getElementById('personal_end_date').value;
    loadAttendanceStatus(start, end);
}

/**
 * ดึงพิกัดตำแหน่ง GPS ปัจจุบันของผู้ใช้
 * @returns {Promise<{latitude: number, longitude: number}|null>}
 */
function getGPSLocation() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            (error) => {
                console.warn('GPS Error/Denied:', error);
                resolve(null);
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 10000
            }
        );
    });
}

/**
 * จัดการเมื่อพนักงานกดปุ่ม ลงเวลาเข้างาน หรือ ออกงาน
 */
async function handleCheckInSubmit(event) {
    const checkInBtn  = document.getElementById('checkInBtn');
    const checkOutBtn = document.getElementById('checkOutBtn');

    // ตรวจสอบปุ่มที่ถูกกด
    const clickedBtn = event ? event.currentTarget : null;
    if (clickedBtn && clickedBtn.disabled) return;

    if (checkInBtn)  checkInBtn.disabled = true;
    if (checkOutBtn) checkOutBtn.disabled = true;

    // 1. ถ่ายรูปเซลฟี่เพื่อบันทึกการลงเวลา
    openCameraModal(async (photoBase64) => {
        try {
            // แสดง Popup Loading บอกพนักงานว่ากำลังประมวลผล ไม่ให้เข้าใจผิดว่าค้าง
            Swal.fire({
                title: 'กำลังบันทึกเวลา...',
                text: 'ระบบกำลังตรวจสอบพิกัด GPS และบันทึกข้อมูลการลงเวลา',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // 2. ดึงตำแหน่ง GPS และส่งข้อมูลไปยัง API
            const location = await getGPSLocation();

            const payload = {
                latitude: location ? location.latitude : null,
                longitude: location ? location.longitude : null,
                photo: photoBase64
            };

            const response = await fetch('api/post_checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                const actionText = result.data.action === 'check_in' ? 'เข้างาน' : 'ออกงาน';
                
                // แจ้งเตือนสำเร็จเพียงครั้งเดียวสั้นกระชับแบบ Toast / Auto close 1.5 วินาที
                Swal.fire({
                    icon: 'success',
                    title: `บันทึกเวลา${actionText}สำเร็จ!`,
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                });

                // โหลดสถานะใหม่
                loadAttendanceStatus();
                loadTeamColleaguesStatus();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'บันทึกเวลาไม่สำเร็จ',
                    text: result.message || 'เกิดข้อผิดพลาดในการลงเวลา'
                });
                loadAttendanceStatus();
            }

        } catch (error) {
            console.error('Check-in error:', error);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                text: 'ไม่สามารถเชื่อมต่อกับเครื่องแม่ข่ายได้'
            });
            loadAttendanceStatus();
        }
    });
}

let cameraStream = null;
let onCaptureCallback = null;
let selectedMobileBase64 = null;

function triggerMobileCamera() {
    const input = document.getElementById('mobileCameraInput');
    if (input) input.click();
}

function handleMobilePhotoSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.getElementById('cameraCanvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 480;
            canvas.height = 480;

            const minDim = Math.min(img.width, img.height);
            const startX = (img.width - minDim) / 2;
            const startY = (img.height - minDim) / 2;

            ctx.drawImage(img, startX, startY, minDim, minDim, 0, 0, 480, 480);
            selectedMobileBase64 = canvas.toDataURL('image/jpeg', 0.85);

            const preview = document.getElementById('mobilePhotoPreview');
            const video = document.getElementById('cameraVideo');
            if (preview) {
                preview.src = selectedMobileBase64;
                preview.style.display = 'block';
            }
            if (video) video.style.display = 'none';

            const submitBtn = document.getElementById('captureSubmitBtn');
            if (submitBtn) submitBtn.textContent = 'บันทึกเวลาทันที';
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function enableMobileCameraFallback() {
    const video = document.getElementById('cameraVideo');
    const fallbackContainer = document.getElementById('cameraFallbackBtnContainer');
    const helpText = document.getElementById('cameraHelpText');
    if (video) video.style.display = 'none';
    if (fallbackContainer) fallbackContainer.style.display = 'block';
    if (helpText) helpText.textContent = 'เนื่องจากการเชื่อมต่อผ่าน HTTP IP บนมือถือ กรุณากดปุ่มด้านล่างเพื่อถ่ายรูปด้วยกล้องมือถือ';
}

function openCameraModal(callback) {
    onCaptureCallback = callback;
    selectedMobileBase64 = null;
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    const preview = document.getElementById('mobilePhotoPreview');
    const fallbackContainer = document.getElementById('cameraFallbackBtnContainer');
    const helpText = document.getElementById('cameraHelpText');
    const submitBtn = document.getElementById('captureSubmitBtn');

    if (!modal) return;

    modal.classList.add('active');

    if (submitBtn) submitBtn.textContent = 'ถ่าย & บันทึกเวลา';
    if (preview) {
        preview.src = '';
        preview.style.display = 'none';
    }
    if (video) video.style.display = 'block';
    if (fallbackContainer) fallbackContainer.style.display = 'none';
    if (helpText) helpText.textContent = 'มองกล้องแล้วกดปุ่มเพื่อถ่ายรูปบันทึกเวลาเข้า-ออกงาน';

    // เช็คว่าเบราว์เซอร์รองรับ WebRTC / getUserMedia หรือไม่
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        enableMobileCameraFallback();
        return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } })
        .then((stream) => {
            cameraStream = stream;
            video.srcObject = stream;
            video.setAttribute("playsinline", true);
            video.play();
        })
        .catch((err) => {
            console.warn('WebRTC Camera error/blocked, falling back to Native Mobile Camera:', err);
            enableMobileCameraFallback();
        });
}

function closeCameraModal() {
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    const preview = document.getElementById('mobilePhotoPreview');
    
    // โหลดสเตตัสปุ่มใหม่ให้ถูกต้อง
    loadAttendanceStatus();

    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    if (video) {
        video.srcObject = null;
        video.style.display = 'block';
    }
    if (preview) {
        preview.src = '';
        preview.style.display = 'none';
    }
    if (modal) {
        modal.classList.remove('active');
    }
}

function capturePhoto() {
    if (selectedMobileBase64) {
        closeCameraModal();
        if (onCaptureCallback) onCaptureCallback(selectedMobileBase64);
        return;
    }

    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    
    // หากอยู่ในโหมดมือถือหรือไม่มีการสตรีมวิดีโอ ให้เปิดกล้องมือถือ native
    if (!video || !cameraStream || video.style.display === 'none') {
        triggerMobileCamera();
        return;
    }

    const ctx = canvas.getContext('2d');
    canvas.width = 480;
    canvas.height = 480;

    const minDim = Math.min(video.videoWidth || 480, video.videoHeight || 480);
    const startX = ((video.videoWidth || 480) - minDim) / 2;
    const startY = ((video.videoHeight || 480) - minDim) / 2;

    ctx.drawImage(video, startX, startY, minDim, minDim, 0, 0, 480, 480);

    const base64Photo = canvas.toDataURL('image/jpeg', 0.85);

    closeCameraModal();

    if (onCaptureCallback) {
        onCaptureCallback(base64Photo);
    }
}

window.handleMobilePhotoSelect = handleMobilePhotoSelect;
window.triggerMobileCamera = triggerMobileCamera;

document.addEventListener('DOMContentLoaded', () => {
    const captureBtn = document.getElementById('captureSubmitBtn');
    if (captureBtn) {
        captureBtn.addEventListener('click', capturePhoto);
    }
});

/**
 * แสดงผลตารางประวัติการลงเวลา
 */
function renderAttendanceHistory(history, container) {
    if (!container) return;

    if (!history || history.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; color:var(--text-muted); padding: 20px;">
                    ไม่พบประวัติการลงเวลาในช่วงวันที่เลือก
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    history.forEach(item => {
        let badgeClass = 'badge-success';
        if (item.status === 'late') badgeClass = 'badge-warning';
        if (item.status === 'absent') badgeClass = 'badge-danger';
        if (item.status === 'leave') badgeClass = 'badge-info';

        const inPhotoBtn = item.check_in_photo 
            ? ` <button class="btn btn-sm btn-outline" style="padding:1px 4px; font-size:0.7rem;" onclick="viewPhoto('${item.check_in_photo}', 'รูปเข้างาน (${item.work_date_th})')">รูปถ่าย</button>` 
            : '';

        const outPhotoBtn = item.check_out_photo 
            ? ` <button class="btn btn-sm btn-outline" style="padding:1px 4px; font-size:0.7rem;" onclick="viewPhoto('${item.check_out_photo}', 'รูปออกงาน (${item.work_date_th})')">รูปถ่าย</button>` 
            : '';

        const otBadge = (parseFloat(item.ot_hours) > 0)
            ? `<span style="color:#D97706; font-weight:600;">${item.ot_hours} ชม.</span>`
            : '<span style="color:var(--text-muted);">-</span>';

        html += `
            <tr>
                <td><strong>${item.work_date_th}</strong></td>
                <td>${item.check_in_time}${inPhotoBtn}</td>
                <td>${item.check_out_time}${outPhotoBtn}</td>
                <td><strong>${item.work_hours} ชม.</strong></td>
                <td>${otBadge}</td>
                <td><span class="badge ${badgeClass}">${item.status_label}</span></td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

function viewPhoto(url, title) {
    Swal.fire({
        title: title,
        imageUrl: url,
        imageWidth: 320,
        imageHeight: 320,
        imageAlt: title,
        confirmButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: '#4A90E2'
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

let workTimerInterval = null;

/**
 * นาฬิกาจับเวลาทำงานสะสมวันนี้ (Stopwatch) และคำนวณ OT อัตโนมัติหลังเลิกกะ (17:00 น. สูงสุด 20:00 น.)
 */
function startLiveWorkTimer(checkInRaw, checkOutRaw, userShift) {
    if (workTimerInterval) {
        clearInterval(workTimerInterval);
        workTimerInterval = null;
    }

    const timerBox = document.getElementById('liveWorkTimerBox');
    const workDisplay = document.getElementById('workTimerDisplay');
    const otDisplay = document.getElementById('otTimerDisplay');

    if (!timerBox || !workDisplay || !checkInRaw) {
        if (timerBox) timerBox.style.display = 'none';
        return;
    }

    timerBox.style.display = 'block';

    const checkInDate = new Date(checkInRaw.replace(/-/g, '/'));
    const isNightShift = userShift && userShift.shift_type === 'night';
    
    // เวลาเลิกกะปกติ (เช่น 17:00 หรือ 05:00)
    const shiftEndParts = (userShift && userShift.shift_end_time ? userShift.shift_end_time : '17:00').split(':');
    const shiftEndDate = new Date(checkInDate);
    shiftEndDate.setHours(parseInt(shiftEndParts[0], 10), parseInt(shiftEndParts[1], 10), 0, 0);
    if (isNightShift && shiftEndDate <= checkInDate) {
        shiftEndDate.setDate(shiftEndDate.getDate() + 1);
    }

    // เวลาเพดานสูงสุดของ OT (เช่น 20:00 หรือ 08:00)
    const otCapParts = (userShift && userShift.ot_cap_time ? userShift.ot_cap_time : '20:00').split(':');
    const otCapDate = new Date(checkInDate);
    otCapDate.setHours(parseInt(otCapParts[0], 10), parseInt(otCapParts[1], 10), 0, 0);
    if (isNightShift && otCapDate <= checkInDate) {
        otCapDate.setDate(otCapDate.getDate() + 1);
    }

    function updateTimer() {
        const now = checkOutRaw ? new Date(checkOutRaw.replace(/-/g, '/')) : new Date();
        const diffMs = Math.max(0, now - checkInDate);
        const totalSec = Math.floor(diffMs / 1000);

        const h = String(Math.floor(totalSec / 3600)).padStart(2, '0');
        const m = String(Math.floor((totalSec % 3600) / 60)).padStart(2, '0');
        const s = String(totalSec % 60).padStart(2, '0');

        workDisplay.textContent = `${h} ชม. ${m} นาที ${s} วินาที`;

        // คำนวณ OT: แสดงเมื่อเวลปัจจุบันถึง 20:00 น. (กะเช้า) หรือ 08:00 น. (กะดึก)
        if (now >= otCapDate) {
            if (otDisplay) {
                otDisplay.style.display = 'block';
                otDisplay.textContent = `🔥 รวมเวลา OT: 3.00 ชม. (ทำสถิติหลัง ${userShift ? (userShift.shift_type === 'night' ? '08:00' : '20:00') : '20:00'} น.)`;
            }
        } else {
            if (otDisplay) otDisplay.style.display = 'none';
        }
    }

    updateTimer();
    if (!checkOutRaw) {
        workTimerInterval = setInterval(updateTimer, 1000);
    }
}
