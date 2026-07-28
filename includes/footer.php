    <footer class="w-full py-md px-lg text-center mt-xl border-t border-surface-container">
        <p class="text-label-md font-label-md text-text-muted">Hak Cipta &copy; <?= date('Y') ?> SMA Negeri 1 Bumiayu - Portal Guru &amp; Admin</p>
    </footer>
</main>

<script>
    // Buka/tutup sidebar di tampilan mobile
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.toggle('hidden');
    }
</script>
</body>
</html>
