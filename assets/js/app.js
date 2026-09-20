// assets/js/app.js - Interactive HR & Shift Features

// ==========================================================================
// 1. PREMIUM CENTER TOAST ENGINE (MOBILE & DESKTOP)
// ==========================================================================
(function() {
    let toastTimeout = null;
    let progressAnim = null;

    function createToastDOM() {
        let overlay = document.getElementById('toastCenterOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'toastCenterOverlay';
            overlay.className = 'toast-center-overlay';
            overlay.innerHTML = `
                <div class="toast-center-card" id="toastCenterCard" onclick="event.stopPropagation()">
                    <button type="button" class="toast-close-x" onclick="window.hideToast()" aria-label="Tutup">✕</button>
                    <div class="toast-icon-badge" id="toastIconBadge">
                        <i id="toastIcon"></i>
                    </div>
                    <h3 class="toast-title" id="toastTitle"></h3>
                    <p class="toast-message" id="toastMessage"></p>
                    <button type="button" class="toast-btn-action" id="toastBtnAction" onclick="window.hideToast()">Mengerti</button>
                    <div class="toast-progress-track">
                        <div class="toast-progress-fill" id="toastProgressFill"></div>
                    </div>
                </div>
            `;
            // Klik background luar untuk menutup
            overlay.addEventListener('click', () => {
                window.hideToast();
            });
            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && overlay.classList.contains('active')) {
                    window.hideToast();
                }
            });
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    window.hideToast = function() {
        const overlay = document.getElementById('toastCenterOverlay');
        if (!overlay || !overlay.classList.contains('active')) return;

        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }
        if (progressAnim) {
            cancelAnimationFrame(progressAnim);
            progressAnim = null;
        }

        overlay.classList.add('leaving');
        setTimeout(() => {
            overlay.classList.remove('active', 'leaving');
        }, 220);
    };

    window.showToast = function(message, type = 'info', title = null, duration = 3800) {
        if (!message) return;

        if (!document.body) {
            document.addEventListener('DOMContentLoaded', () => {
                window.showToast(message, type, title, duration);
            });
            return;
        }

        const overlay = createToastDOM();
        const card = document.getElementById('toastCenterCard');
        const iconBadge = document.getElementById('toastIconBadge');
        const iconEl = document.getElementById('toastIcon');
        const titleEl = document.getElementById('toastTitle');
        const msgEl = document.getElementById('toastMessage');
        const progressFill = document.getElementById('toastProgressFill');

        if (toastTimeout) clearTimeout(toastTimeout);
        if (progressAnim) cancelAnimationFrame(progressAnim);
        overlay.classList.remove('leaving');

        card.classList.remove('toast-type-success', 'toast-type-error', 'toast-type-warning', 'toast-type-info');

        const normType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
        card.classList.add('toast-type-' + normType);

        let defaultTitle = 'Informasi';
        let iconClass = 'ti ti-info-circle';

        if (normType === 'success') {
            defaultTitle = 'Berhasil!';
            iconClass = 'ti ti-circle-check';
        } else if (normType === 'error') {
            defaultTitle = 'Perhatian / Terjadi Kesalahan';
            iconClass = 'ti ti-alert-triangle';
        } else if (normType === 'warning') {
            defaultTitle = 'Peringatan';
            iconClass = 'ti ti-alert-circle';
        }

        titleEl.textContent = title || defaultTitle;
        msgEl.innerHTML = message;
        iconEl.className = iconClass;

        overlay.classList.add('active');

        if (duration > 0) {
            progressFill.style.width = '100%';
            const startTime = performance.now();

            function updateProgress(currentTime) {
                const elapsed = currentTime - startTime;
                const remainingRatio = Math.max(0, 1 - (elapsed / duration));
                progressFill.style.width = (remainingRatio * 100) + '%';

                if (elapsed < duration) {
                    progressAnim = requestAnimationFrame(updateProgress);
                } else {
                    window.hideToast();
                }
            }

            progressAnim = requestAnimationFrame(updateProgress);
            toastTimeout = setTimeout(() => {
                window.hideToast();
            }, duration);
        } else {
            progressFill.style.width = '0%';
        }
    };

    window.toast = {
        success: (msg, title, duration) => window.showToast(msg, 'success', title, duration),
        error: (msg, title, duration) => window.showToast(msg, 'error', title, duration),
        warning: (msg, title, duration) => window.showToast(msg, 'warning', title, duration),
        info: (msg, title, duration) => window.showToast(msg, 'info', title, duration)
    };

    // Global Alert Override
    window.alert = function(msg) {
        let str = String(msg || '');
        let lower = str.toLowerCase();
        let type = 'info';
        if (lower.includes('berhasil') || lower.includes('sukses')) type = 'success';
        else if (lower.includes('gagal') || lower.includes('salah') || lower.includes('error')) type = 'error';
        else if (lower.includes('peringatan') || lower.includes('maksimal') || lower.includes('perhatian') || lower.includes('harus') || lower.includes('wajib')) type = 'warning';
        window.showToast(str, type);
    };
})();

// Avatar Preview Handlers
window.handleProfileAvatarSelected = function(input) {
    if (!input || !input.files || !input.files[0]) return;
    const file = input.files[0];

    // Max 2MB check
    if (file.size > 2 * 1024 * 1024) {
        window.showToast('Ukuran file foto maksimal adalah 2MB!', 'warning', 'Ukuran Berkas Terlalu Besar');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const previewImg = document.getElementById('profileAvatarPreview');
        const initialEl = document.getElementById('profileAvatarInitial');
        const indicator = document.getElementById('profileFileIndicator');
        const fileNameEl = document.getElementById('profileFileName');

        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        if (initialEl) {
            initialEl.style.display = 'none';
        }
        if (indicator && fileNameEl) {
            fileNameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
            indicator.style.display = 'inline-flex';
        }
    };
    reader.readAsDataURL(file);
};

window.cancelProfileAvatarSelected = function(e) {
    if (e) e.stopPropagation();
    const input = document.getElementById('profileAvatarInput');
    const previewImg = document.getElementById('profileAvatarPreview');
    const initialEl = document.getElementById('profileAvatarInitial');
    const indicator = document.getElementById('profileFileIndicator');
    const originalSrc = previewImg ? previewImg.getAttribute('data-original-src') : '';

    if (input) input.value = '';
    if (indicator) indicator.style.display = 'none';

    if (originalSrc) {
        if (previewImg) {
            previewImg.src = originalSrc;
            previewImg.style.display = 'block';
        }
        if (initialEl) initialEl.style.display = 'none';
    } else {
        if (previewImg) {
            previewImg.src = '';
            previewImg.style.display = 'none';
        }
        if (initialEl) initialEl.style.display = 'flex';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Digital Clock
    const liveClockEl = document.getElementById('liveClockTime');
    if (liveClockEl) {
        const updateClock = () => {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            liveClockEl.textContent = `${hours}:${minutes}:${seconds} WIB`;
        };
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 2. Modal Handler
    window.openModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // 3. Preview Surat Dokter Modal
    window.viewDoctorLetter = (fileUrl, employeeName, requestDate) => {
        const modal = document.getElementById('doctorLetterModal');
        const container = document.getElementById('doctorLetterContainer');
        const titleEl = document.getElementById('doctorLetterEmployeeName');
        const dateEl = document.getElementById('doctorLetterDate');
        const downloadBtn = document.getElementById('doctorLetterDownloadBtn');

        if (!modal || !container) return;

        if (titleEl) titleEl.textContent = employeeName;
        if (dateEl) dateEl.textContent = requestDate;
        if (downloadBtn) downloadBtn.href = fileUrl;

        const isPdf = fileUrl.toLowerCase().endsWith('.pdf');
        if (isPdf) {
            container.innerHTML = `
                <div style="height: 500px; width: 100%;">
                    <iframe src="${fileUrl}" style="width: 100%; height: 100%; border: none; border-radius: 8px;"></iframe>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div style="text-align: center; background: #0f172a10; padding: 12px; border-radius: 8px;">
                    <img src="${fileUrl}" alt="Surat Dokter" style="max-width: 100%; max-height: 500px; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                </div>
            `;
        }

        openModal('doctorLetterModal');
    };

    // 4. Client-side input file preview
    const doctorInput = document.getElementById('doctorLetterInput');
    const doctorPreview = document.getElementById('doctorLetterPreview');
    if (doctorInput && doctorPreview) {
        doctorInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        doctorPreview.innerHTML = `
                            <div style="margin-top: 10px; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 8px; text-align: center;">
                                <img src="${e.target.result}" style="max-height: 180px; max-width: 100%; border-radius: 4px;">
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 6px;">Pratinjau: ${file.name} (${(file.size/1024).toFixed(1)} KB)</div>
                            </div>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    doctorPreview.innerHTML = `
                        <div style="margin-top: 10px; padding: 10px; background: #f1f5f9; border-radius: 8px; font-size: 0.8125rem; color: #334155;">
                            <i class="ti ti-file-text"></i> Dokumen terpilih: <strong>${file.name}</strong> (${(file.size/1024).toFixed(1)} KB)
                        </div>
                    `;
                }
            }
        });
    }
});
