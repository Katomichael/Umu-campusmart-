<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'About Us';
include __DIR__ . '/../includes/header.php';
?>

<section class="hero about-hero">
  <div class="container">
    <div class="about-hero-inner">
      <div class="about-hero-badge">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        About
      </div>
      <h1>About UMU CampusMart</h1>
      <p>CampusMart is a student-to-student marketplace for Uganda Martyrs University (UMU) — built to help you buy, sell, and connect safely on campus.</p>

      <div class="about-hero-actions">
        <a href="<?= APP_URL ?>/index.php" class="btn btn-accent btn-sm">Browse items</a>
        <a href="<?= APP_URL ?>/pages/create_listing.php" class="btn btn-ghost btn-sm">Sell an item</a>
        <a href="<?= APP_URL ?>/pages/messages.php" class="btn btn-ghost btn-sm">Chat with us</a>
      </div>
    </div>
  </div>
</section>

<div class="container">
  <div class="about-page">
    <div class="card info-card mb-4">
      <div class="card-body">
        <section id="about-us">
          <div class="info-card-head">
            <span class="info-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
            <h2>About us</h2>
          </div>
          <p>
            We help UMU students list items, discover good deals, and communicate directly using in-app messages.
            Whether you’re clearing out your room, upgrading a gadget, or looking for something quick and affordable, CampusMart keeps it simple.
          </p>
        </section>
      </div>
    </div>

    <div class="card info-card mb-4">
      <div class="card-body">
        <section id="about-umu-campusmart">
          <div class="info-card-head">
            <span class="info-icon" aria-hidden="true"><i class="fa-solid fa-graduation-cap"></i></span>
            <h2>About UMU CampusMart</h2>
          </div>
          <ul>
            <li>Browse listings by category, search keywords, condition, and price.</li>
            <li>Open a listing to view details, photos, and the seller’s profile.</li>
            <li>Use <strong>Messages</strong> to ask questions and agree on a meetup on campus.</li>
            <li>Post your own items using <strong>Sell</strong> and manage them from your profile.</li>
          </ul>
        </section>
      </div>
    </div>

    <div class="card info-card mb-4">
      <div class="card-body">
        <section id="help">
          <div class="info-card-head">
            <span class="info-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
            <h2>Help Center: how to use CampusMart</h2>
          </div>

          <h3>If you want to buy</h3>
          <ol>
            <li>Go to <a class="text-link" href="<?= APP_URL ?>/index.php">Browse</a> and search for what you need.</li>
            <li>Use filters (category, condition, price) to narrow results.</li>
            <li>Open a listing and tap <strong>Messages</strong> to chat with the seller.</li>
            <li>Meet in a safe public spot on campus and inspect the item before paying.</li>
          </ol>

          <h3>If you want to sell</h3>
          <ol>
            <li>Create an account (or log in).</li>
            <li>Click <a class="text-link" href="<?= APP_URL ?>/pages/create_listing.php">Sell with us</a> to post a new listing.</li>
            <li>Add clear photos, an honest description, and a fair price.</li>
            <li>Respond to messages quickly and agree on a campus meetup.</li>
          </ol>

          <h3>Safety tips</h3>
          <ul>
            <li>Meet in well-lit public areas on campus.</li>
            <li>Avoid sharing sensitive personal information.</li>
            <li>Confirm the item condition matches the listing before completing the deal.</li>
          </ul>
        </section>
      </div>
    </div>

    <div class="card info-card mb-4">
      <div class="card-body">
        <section id="terms">
          <div class="info-card-head">
            <span class="info-icon" aria-hidden="true"><i class="fa-solid fa-file-contract"></i></span>
            <h2>Terms &amp; Conditions (summary)</h2>
          </div>
          <ul>
            <li>You’re responsible for your listings and the accuracy of what you post.</li>
            <li>Keep communication respectful and follow campus rules and local laws.</li>
            <li>Report suspicious behavior and avoid risky meetups.</li>
          </ul>
          <p class="about-note">This is a simple summary to guide safe use of CampusMart.</p>
        </section>
      </div>
    </div>

    <div class="card info-card">
      <div class="card-body">
        <section id="contact">
          <div class="info-card-head">
            <span class="info-icon" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span>
            <h2>Contact us</h2>
          </div>
          <p class="about-lead">Need help? The fastest way is to use in-app messages.</p>
          <div class="about-actions">
            <a href="<?= APP_URL ?>/pages/messages.php" class="btn btn-accent btn-sm">Chat with us</a>
            <a href="<?= APP_URL ?>/pages/create_listing.php" class="btn btn-outline btn-sm">Sell with us</a>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
