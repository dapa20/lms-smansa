<?php
/**
 * Partial: Tab "Data Guru" di halaman data_siswa.php — khusus Admin.
 * Variabel yang sudah tersedia: $pdo, $user (admin yang login).
 */

$kata = trim($_GET['q'] ?? '');
$where = ['1=1'];
$params = [];
if ($kata !== '') {
    $where[] = '(nama_lengkap LIKE ? OR email LIKE ? OR nip LIKE ?)';
    $params[] = "%$kata%"; $params[] = "%$kata%"; $params[] = "%$kata%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT * FROM users WHERE $whereSql ORDER BY role DESC, nama_lengkap ASC");
$stmt->execute($params);
$daftarGuru = $stmt->fetchAll();

$totalGuru = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='guru'")->fetch()['c'];
$totalAdmin = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];

// Mode edit
$editData = null;
if (!empty($_GET['edit_guru'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmtEdit->execute([(int)$_GET['edit_guru']]);
    $editData = $stmtEdit->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_guru']) || $editData !== null;
?>
<div class="flex justify-end mb-md -mt-2">
    <a href="?tab=guru&tambah_guru=1" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
        <span class="material-symbols-outlined">person_add</span> Tambah Guru / Admin
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-12 gap-6">
    <div class="md:col-span-3 flex flex-col gap-6">
        <div class="bg-surface-white rounded-xl p-lg shadow-[0px_4px_20px_rgba(0,0,0,0.05)]">
            <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 border-b border-outline-variant pb-2">Ringkasan</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-body-md font-body-md text-text-muted">Total Guru</span>
                    <span class="text-label-lg font-label-lg font-semibold text-text-main"><?= $totalGuru ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-body-md font-body-md text-text-muted">Total Admin/TU</span>
                    <span class="text-label-lg font-label-lg font-semibold text-primary"><?= $totalAdmin ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="md:col-span-9">
        <div class="bg-surface-white rounded-xl shadow-[0px_4px_20px_rgba(0,0,0,0.05)] overflow-hidden border border-surface-variant">
            <div class="p-4 border-b border-outline-variant flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between bg-surface-bright">
                <form method="get" class="relative flex-1 max-w-sm">
                    <input type="hidden" name="tab" value="guru">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                    <input type="text" name="q" value="<?= h($kata) ?>" placeholder="Cari nama, email, atau NIP..." class="w-full pl-10 pr-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </form>
                <span class="text-label-md font-label-md text-text-muted"><?= count($daftarGuru) ?> akun ditemukan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider">
                            <th class="p-4 font-medium">Nama</th>
                            <th class="p-4 font-medium hidden sm:table-cell">Email</th>
                            <th class="p-4 font-medium hidden md:table-cell">Peran</th>
                            <th class="p-4 font-medium">Status</th>
                            <th class="p-4 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-body-sm font-body-sm text-text-main divide-y divide-outline-variant">
                        <?php if (empty($daftarGuru)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-text-muted">Tidak ada akun yang cocok.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($daftarGuru as $g): ?>
                            <tr class="hover:bg-surface-bright transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-label-md flex-shrink-0">
                                            <?= h(inisialNama($g['nama_lengkap'])) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-text-main group-hover:text-primary transition-colors"><?= h($g['nama_lengkap']) ?></div>
                                            <div class="text-text-muted text-label-md font-label-md"><?= h($g['mapel_keahlian'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 hidden sm:table-cell text-text-muted"><?= h($g['email']) ?></td>
                                <td class="p-4 hidden md:table-cell">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium <?= $g['role'] === 'admin' ? 'bg-tertiary/10 text-tertiary' : 'bg-primary/10 text-primary' ?>"><?= $g['role'] === 'admin' ? 'Admin' : 'Guru' ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium <?= $g['status'] === 'aktif' ? 'bg-primary/10 text-primary' : 'bg-surface-container text-text-muted' ?>"><?= $g['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?></span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <a href="?tab=guru&edit_guru=<?= (int)$g['id'] ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Edit">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </a>
                                        <?php if ((int)$g['id'] !== (int)$user['id']): ?>
                                        <form action="../actions/guru/guru_hapus.php" method="post" onsubmit="return confirm('Hapus akun \'<?= h(addslashes($g['nama_lengkap'])) ?>\'?\n\nPERHATIAN: seluruh materi, tugas/ujian, dan jadwal yang dibuat akun ini juga akan ikut terhapus permanen. Jika hanya ingin menonaktifkan sementara, gunakan tombol Edit lalu ubah Status menjadi Nonaktif.');">
                                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                            <button type="submit" class="text-text-muted hover:text-error transition-colors p-1" title="Hapus">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-outline-variant p-1" title="Tidak bisa menghapus akun sendiri"><span class="material-symbols-outlined text-[20px]">block</span></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL TAMBAH / EDIT GURU / ADMIN                              -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='data_siswa.php?tab=guru'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <form action="../actions/guru/guru_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main"><?= $editData ? 'Edit Akun' : 'Tambah Guru / Admin' ?></h3>
                <a href="data_siswa.php?tab=guru" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Nama Lengkap</label>
                    <input required name="nama_lengkap" value="<?= h($editData['nama_lengkap'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Dra. Siti Aminah">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Email (untuk login)</label>
                        <input required type="email" name="email" value="<?= h($editData['email'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">NIP</label>
                        <input name="nip" value="<?= h($editData['nip'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">
                        Kata Sandi <?= $editData ? '(kosongkan jika tidak ingin mengubah)' : '' ?>
                    </label>
                    <input type="password" name="password" <?= $editData ? '' : 'required' ?> minlength="6" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Minimal 6 karakter">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Peran</label>
                        <select name="role" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="guru" <?= ($editData['role'] ?? 'guru') === 'guru' ? 'selected' : '' ?>>Guru</option>
                            <option value="admin" <?= ($editData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Bidang Keahlian</label>
                    <input name="mapel_keahlian" value="<?= h($editData['mapel_keahlian'] ?? '') ?>" placeholder="Contoh: Guru Matematika" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="data_siswa.php?tab=guru" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
