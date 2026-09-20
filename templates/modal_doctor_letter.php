<?php
// templates/modal_doctor_letter.php
?>
<!-- Modal Pratinjau Surat Keterangan Dokter -->
<div class="modal-backdrop" id="doctorLetterModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                    <i class="ti ti-file-certificate" style="color: var(--primary);"></i>
                    Lampiran Surat Keterangan Dokter
                </h3>
                <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 2px;">
                    Pemohon: <strong id="doctorLetterEmployeeName" style="color: var(--text-heading);">-</strong> | Tanggal: <span id="doctorLetterDate">-</span>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('doctorLetterModal')" style="padding: 4px 8px;">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="doctorLetterContainer" style="min-height: 250px; display: flex; align-items: center; justify-content: center;">
                <!-- Konten berkas gambar atau PDF akan diinjeksikan secara dinamis -->
            </div>
        </div>
        <div class="modal-footer">
            <a href="#" id="doctorLetterDownloadBtn" target="_blank" class="btn btn-secondary btn-sm">
                <i class="ti ti-download"></i> Unduh / Buka Dokumen Penuh
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('doctorLetterModal')">
                Tutup Pratinjau
            </button>
        </div>
    </div>
</div>
