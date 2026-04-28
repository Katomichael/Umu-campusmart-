<?php // includes/footer.php ?>
</main><!-- /.main-content -->

<?php if (empty($hideFooter)): ?>
  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-grid" aria-label="Footer links">
        <div class="footer-col">
          <div class="footer-col-header">
            <i class="fas fa-question-circle"></i>
            <h4>Need Help?</h4>
          </div>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/pages/messages.php"><i class="fas fa-comment"></i> Chat with us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#help"><i class="fas fa-book"></i> Help Center</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#contact"><i class="fas fa-envelope"></i> Contact us</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <div class="footer-col-header">
            <i class="fas fa-info-circle"></i>
            <h4>About</h4>
          </div>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fas fa-star"></i> About us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#about-umu-campusmart"><i class="fas fa-school"></i> About UMU CampusMart</a></li>
            <li><a href="<?= APP_URL ?>/pages/create_listing.php"><i class="fas fa-plus-circle"></i> Sell with us</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php#terms"><i class="fas fa-file-contract"></i> Terms &amp; Conditions</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <div class="footer-col-header">
            <i class="fas fa-shield-alt"></i>
            <h4>Trust & Safety</h4>
          </div>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fas fa-lock"></i> Privacy Policy</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fas fa-balance-scale"></i> Fair Policy</a></li>
            <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fas fa-bug"></i> Report Issue</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="footer-branding">
          <p class="footer-title"><i class="fas fa-shopping-bag"></i> CampusMart</p>
          <p class="footer-sub">A trusted peer-to-peer marketplace for <?= e(UNIVERSITY_NAME) ?> students</p>
        </div>
        <div class="footer-copyright">
          <p>&copy; 2026 CampusMart. All rights reserved.</p>
        </div>
      </div>
    </div>
  </footer>
<?php endif; ?>

<?php $jsVersion = @filemtime(__DIR__ . '/../public/js/app.js') ?: time(); ?>
<script src="<?= APP_URL ?>/public/js/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
