<?php // includes/footer.php ?>
</main><!-- /.main-content -->

<?php if (empty($hideFooter)): ?>
  <footer class="site-footer">
    <div class="container footer-inner">
      <p>🛒 <strong>CampusMart</strong> — <?= UNIVERSITY_NAME ?></p>
      <p class="footer-sub">A trusted peer-to-peer marketplace for UMU students</p>
    </div>
  </footer>
<?php endif; ?>

<?php $jsVersion = @filemtime(__DIR__ . '/../public/js/app.js') ?: time(); ?>
<script src="<?= APP_URL ?>/public/js/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
