<?php
// includes/layout_footer.php
// Include at the BOTTOM of every student page, just before closing PHP
?>
    </div><!-- /page-content -->
</main><!-- /main-content -->

<footer class="main-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($extra_js)): echo $extra_js; endif; ?>
<script>
// Mark announcements seen when modal opens
const annModal = document.getElementById('announcementModal');
if (annModal) {
    annModal.addEventListener('show.bs.modal', () => {
        fetch('api/mark_announcements_seen.php', { method: 'POST' });
    });
}

// Sidebar toggle for mobile
const sidebarEl = document.querySelector('.sidebar');
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sidebarEl) sidebarEl.classList.remove('open');
});
</script>
</body>
</html>