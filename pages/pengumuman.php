<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';

$pageTitle   = 'Pengumuman';
$currentPage = 'pengumuman';
$user        = currentUser();
$isAdmin     = isAdmin();

// ─── Handle Edit Mode ────────────────────────────────────────────────
$editPengumuman = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM pengumuman WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editPengumuman = $stmtEdit->fetch() ?: null;
}

// ─── Ambil Semua Pengumuman ──────────────────────────────────────────
$stmtList = $pdo->query("SELECT p.*, u.nama_lengkap AS pembuat
                          FROM pengumuman p
                          JOIN users u ON u.id = p.dibuat_oleh
                          ORDER BY p.created_at DESC");
$daftarPengumuman = $stmtList->fetchAll();

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-14 md:ml-[280px] min-h-screen bg-[#f3f4f6]">
    <div class="p-4 md:p-6 max-w-[1100px] mx-auto space-y-5">
        <?php renderFlash(); ?>

        <!-- ══ CARD 1: Form Tulis Info/Pengumuman (Persis Gambar Referensi) ═════ -->
        <?php if ($isAdmin): ?>
        <div class="bg-white rounded-xl shadow-xs border border-gray-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-white">
                <h3 class="text-base font-bold text-gray-800 tracking-tight">
                    <?= $editPengumuman ? 'Edit Pengumuman' : 'Tulis Info/Pengumuman' ?>
                </h3>
            </div>

            <form action="../actions/pengumuman/pengumuman_simpan.php" method="POST" id="formPengumuman" class="p-6 space-y-4">
                <?php if ($editPengumuman): ?>
                    <input type="hidden" name="id" value="<?= (int)$editPengumuman['id'] ?>">
                    <input type="hidden" name="redirect_to" value="../pages/pengumuman.php">
                <?php else: ?>
                    <input type="hidden" name="redirect_to" value="../pages/pengumuman.php">
                <?php endif; ?>

                <!-- Field Kepada -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                    <label for="kepada" class="sm:w-24 font-semibold text-gray-700 text-sm flex-shrink-0">Kepada:</label>
                    <input type="text" name="kepada" id="kepada"
                           value="<?= h($editPengumuman['kepada'] ?? '') ?>"
                           placeholder="Semua / Kelas X MIPA 1 / Seluruh Guru..."
                           class="flex-1 border border-gray-300 rounded-md px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all bg-white">
                </div>

                <!-- Rich Text Editor Box -->
                <div class="border border-gray-300 rounded-lg overflow-hidden bg-white shadow-2xs" id="editorWrapper">
                    <!-- Toolbar (Persis Gambar Referensi) -->
                    <div class="bg-[#f8f9fa] border-b border-gray-200 p-2 flex flex-wrap items-center gap-1 text-gray-700 text-sm select-none">
                        <button type="button" onclick="formatDoc('removeFormat')" class="toolbar-btn" title="Format Otomatis">
                            <span class="material-symbols-outlined text-[18px]">auto_fix_high</span>
                        </button>
                        <div class="toolbar-sep"></div>

                        <button type="button" onclick="formatDoc('bold')" class="toolbar-btn font-bold px-2.5" title="Tebal (Ctrl+B)">B</button>
                        <button type="button" onclick="formatDoc('underline')" class="toolbar-btn underline px-2.5" title="Garis Bawah (Ctrl+U)">U</button>
                        <button type="button" onclick="formatDoc('removeFormat')" class="toolbar-btn" title="Hapus Format">
                            <span class="material-symbols-outlined text-[18px]">ink_eraser</span>
                        </button>
                        <div class="toolbar-sep"></div>

                        <!-- Font Family -->
                        <select onchange="formatDoc('fontName', this.value); this.selectedIndex=0;"
                                class="text-xs border border-gray-300 rounded px-2 py-1 bg-white focus:outline-none cursor-pointer">
                            <option disabled selected>Poppins ▾</option>
                            <option value="Poppins">Poppins</option>
                            <option value="Inter">Inter</option>
                            <option value="Arial">Arial</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Times New Roman">Times New Roman</option>
                        </select>

                        <!-- Warna Teks -->
                        <label class="toolbar-btn relative cursor-pointer flex items-center gap-0.5" title="Warna Teks">
                            <span class="font-bold text-black bg-amber-300 px-1 rounded text-xs">A</span>
                            <span class="text-[10px]">▾</span>
                            <input type="color" onchange="formatDoc('foreColor', this.value)" class="sr-only">
                        </label>
                        <div class="toolbar-sep"></div>

                        <button type="button" onclick="formatDoc('insertUnorderedList')" class="toolbar-btn" title="Daftar Simbol">
                            <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                        </button>
                        <button type="button" onclick="formatDoc('insertOrderedList')" class="toolbar-btn" title="Daftar Angka">
                            <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
                        </button>
                        <button type="button" onclick="cycleAlign()" class="toolbar-btn" title="Perataan Teks">
                            <span class="material-symbols-outlined text-[18px]">format_align_left</span>
                        </button>
                        <div class="toolbar-sep"></div>

                        <button type="button" onclick="insertTable()" class="toolbar-btn" title="Sisipkan Tabel">
                            <span class="material-symbols-outlined text-[18px]">grid_on</span>
                        </button>
                        <button type="button" onclick="insertLink()" class="toolbar-btn" title="Sisipkan Tautan">
                            <span class="material-symbols-outlined text-[18px]">link</span>
                        </button>
                        <button type="button" onclick="insertImage()" class="toolbar-btn" title="Sisipkan Gambar URL">
                            <span class="material-symbols-outlined text-[18px]">image</span>
                        </button>
                        <button type="button" onclick="insertVideo()" class="toolbar-btn" title="Sisipkan Video Embed">
                            <span class="material-symbols-outlined text-[18px]">videocam</span>
                        </button>
                        <div class="toolbar-sep"></div>

                        <button type="button" onclick="toggleFullscreen()" class="toolbar-btn" title="Layar Penuh">
                            <span class="material-symbols-outlined text-[18px]">fullscreen</span>
                        </button>
                        <button type="button" onclick="toggleCodeView()" class="toolbar-btn font-mono font-bold text-xs px-1" title="Lihat Kode HTML">
                            &lt;/&gt;
                        </button>
                        <button type="button" onclick="showHelp()" class="toolbar-btn font-bold px-1.5" title="Bantuan Editor">?</button>
                    </div>

                    <!-- Content Editable Area -->
                    <div id="editor" contenteditable="true"
                         class="p-4 min-h-[160px] text-sm text-gray-800 focus:outline-none bg-white leading-relaxed"
                         data-placeholder="Tulis Pengumuman"><?= $editPengumuman ? $editPengumuman['isi'] : '' ?></div>
                    <textarea name="isi" id="hiddenIsi" class="hidden"></textarea>
                </div>

                <!-- Tombol Simpan -->
                <div class="flex items-center justify-between pt-1">
                    <?php if ($editPengumuman): ?>
                        <a href="pengumuman.php" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Batal Edit
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <button type="submit"
                            class="bg-[#007bff] hover:bg-blue-700 text-white font-semibold text-sm px-5 py-2 rounded-md flex items-center gap-2 transition-colors shadow-xs">
                        <span class="material-symbols-outlined text-[17px]">save</span>
                        <?= $editPengumuman ? 'Update Pengumuman' : 'Simpan' ?>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ══ CARD 2: Daftar Pengumuman ══════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-xs border border-gray-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-800 tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 text-[20px]">campaign</span>
                    <?= $isAdmin ? 'Pengumuman Anda' : 'Pengumuman Sekolah' ?>
                </h3>
                <span class="text-xs text-gray-500 font-medium bg-gray-100 px-3 py-1 rounded-full">
                    <?= count($daftarPengumuman) ?> pengumuman
                </span>
            </div>

            <div class="divide-y divide-gray-100">
                <?php if (empty($daftarPengumuman)): ?>
                    <div class="text-center py-16">
                        <span class="material-symbols-outlined text-[52px] text-gray-300 block mb-3">campaign</span>
                        <p class="text-gray-400 font-medium text-sm">Belum ada pengumuman.</p>
                        <?php if ($isAdmin): ?>
                            <p class="text-gray-400 text-xs mt-1">Tulis pengumuman pertama di form di atas.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($daftarPengumuman as $p): ?>
                        <?php $penting = $p['kategori'] === 'penting'; ?>
                        <div class="px-6 py-5 hover:bg-gray-50/80 transition-colors group">
                            <div class="flex flex-col md:flex-row md:items-start gap-4">
                                <!-- Konten Pengumuman -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <!-- Badge Kategori -->
                                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-full border
                                            <?= $penting ? 'bg-red-50 text-red-700 border-red-200' : 'bg-blue-50 text-blue-700 border-blue-200' ?>">
                                            <span class="material-symbols-outlined text-[13px]"><?= $penting ? 'priority_high' : 'info' ?></span>
                                            <?= $penting ? 'PENTING' : 'INFORMASI' ?>
                                        </span>
                                        <!-- Badge Kepada -->
                                        <?php if (!empty($p['kepada'])): ?>
                                        <span class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full font-medium">
                                            Kepada: <?= h($p['kepada']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <!-- Waktu -->
                                        <span class="text-xs text-gray-400">
                                            <?= h(waktuRelatif($p['created_at'])) ?> &bull; <?= date('d M Y, H:i', strtotime($p['created_at'])) ?>
                                        </span>
                                    </div>

                                    <h4 class="font-bold text-gray-900 text-sm mb-1.5 leading-snug"><?= h($p['judul']) ?></h4>
                                    <div class="text-gray-600 text-sm leading-relaxed line-clamp-3 prose-sm max-w-none">
                                        <?= strip_tags($p['isi']) ?>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2 font-medium">
                                        Oleh: <?= h($p['pembuat']) ?>
                                    </p>
                                </div>

                                <!-- Aksi (hanya Admin) -->
                                <?php if ($isAdmin): ?>
                                <div class="flex items-center gap-2 flex-shrink-0 self-start md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                    <a href="pengumuman.php?edit=<?= $p['id'] ?>"
                                       class="flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">edit</span> Edit
                                    </a>
                                    <a href="../actions/pengumuman/pengumuman_hapus.php?id=<?= $p['id'] ?>&redirect_to=../pages/pengumuman.php"
                                       onclick="return confirm('Yakin hapus pengumuman ini?')"
                                       class="flex items-center gap-1.5 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">delete</span> Hapus
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<style>
    .toolbar-btn {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 5px 6px; border-radius: 4px; border: 1px solid transparent;
        cursor: pointer; transition: all .15s;
    }
    .toolbar-btn:hover { background-color: #e5e7eb; border-color: #d1d5db; }
    .toolbar-sep { width: 1px; height: 20px; background: #d1d5db; margin: 0 2px; }

    #editor:empty:before {
        content: attr(data-placeholder);
        color: #9ca3af;
        pointer-events: none;
    }
</style>

<script>
    let alignIdx = 0;
    const aligns = ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'];

    function formatDoc(cmd, value) {
        document.execCommand(cmd, false, value ?? null);
        document.getElementById('editor').focus();
    }

    function cycleAlign() {
        alignIdx = (alignIdx + 1) % aligns.length;
        formatDoc(aligns[alignIdx]);
    }

    function insertTable() {
        const r = prompt("Jumlah Baris:", "2"), c = prompt("Jumlah Kolom:", "2");
        if (!r || !c) return;
        let html = '<table style="width:100%;border-collapse:collapse;margin:8px 0"><tbody>';
        for (let i = 0; i < +r; i++) {
            html += '<tr>';
            for (let j = 0; j < +c; j++) {
                html += '<td style="border:1px solid #d1d5db;padding:6px 10px">Teks</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table><p></p>';
        formatDoc('insertHTML', html);
    }

    function insertLink() {
        const url = prompt("URL Tautan:", "https://");
        if (url) formatDoc('createLink', url);
    }

    function insertImage() {
        const url = prompt("URL Gambar:", "https://");
        if (url) formatDoc('insertImage', url);
    }

    function insertVideo() {
        const url = prompt("URL Embed Video (YouTube):", "");
        if (!url) return;
        const embedUrl = url.includes('watch?v=') ? url.replace('watch?v=', 'embed/') : url;
        formatDoc('insertHTML',
            `<iframe width="100%" height="315" src="${embedUrl}" frameborder="0" allowfullscreen
             style="border-radius:8px;margin:8px 0;display:block"></iframe><p></p>`);
    }

    let isFullscreen = false;
    function toggleFullscreen() {
        const wrapper = document.getElementById('editorWrapper');
        isFullscreen = !isFullscreen;
        if (isFullscreen) {
            wrapper.style.cssText = 'position:fixed;inset:20px;z-index:9999;border-radius:12px;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)';
            document.getElementById('editor').style.minHeight = 'calc(100vh - 120px)';
        } else {
            wrapper.style.cssText = '';
            document.getElementById('editor').style.minHeight = '160px';
        }
    }

    let isCode = false;
    function toggleCodeView() {
        const ed = document.getElementById('editor');
        if (!isCode) { ed.innerText = ed.innerHTML; isCode = true; }
        else         { ed.innerHTML = ed.innerText; isCode = false; }
    }

    function showHelp() {
        alert("Gunakan toolbar di atas:\n• B = Tebal\n• U = Garis Bawah\n• Daftar: bullet / angka\n• Tabel, Link, Gambar, Video tersedia.\n• </> = mode kode HTML.");
    }

    // Saat form submit, salin isi editor ke textarea tersembunyi
    document.getElementById('formPengumuman')?.addEventListener('submit', function() {
        const ed = document.getElementById('editor');
        document.getElementById('hiddenIsi').value = isCode ? ed.innerText : ed.innerHTML.trim();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
