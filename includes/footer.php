<?php // includes/footer.php ?>
</main><!-- /.main-content -->

<?php if (empty($hideFooter)): ?>
  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-grid" aria-label="Footer links">
        <div class="footer-col">
          <h4>NEED HELP</h4>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/pages/messages.php">Chat with us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#help">Help Center</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#contact">Contact us</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>ABOUT</h4>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/pages/about.php">About us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#about-umu-campusmart">About UMU CampusMart</a></li>
            <li><a href="<?= APP_URL ?>/pages/create_listing.php">Sell with us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#terms">Terms &amp; Conditions</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>🛒 <strong>CampusMart</strong> — <?= e(UNIVERSITY_NAME) ?></p>
        <p class="footer-sub">A trusted peer-to-peer marketplace for UMU students</p>
      </div>
    </div>
  </footer>
<?php endif; ?>

<?php $jsVersion = @filemtime(__DIR__ . '/../public/js/app.js') ?: time(); ?>
<script src="<?= APP_URL ?>/public/js/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
