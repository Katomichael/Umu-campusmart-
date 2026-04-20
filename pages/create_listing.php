<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$me       = currentUser();
$editId   = (int)($_GET['id'] ?? 0);
$isEdit   = $editId > 0;
$listing  = null;
$errors   = [];
$form     = ['title'=>'','description'=>'','price'=>'','category_id'=>'','condition_type'=>'good','location'=>'On Campus'];

// Load existing listing for edit
if ($isEdit) {
    $listing = Database::fetchOne('SELECT * FROM listings WHERE id = ?', [$editId]);
    if (!$listing || ($listing['seller_id'] != $me['id'] && !isAdmin())) redirect('/index.php');
    $form = array_intersect_key($listing, $form);
}

// Categories
try {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
} catch (Throwable $e) {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $form['title']          = trim($_POST['title']          ?? '');
        $form['description']    = trim($_POST['description']    ?? '');
        $form['price']          = (float)($_POST['price']       ?? 0);
        $form['category_id']    = (int)($_POST['category_id']   ?? 0);
        $form['condition_type'] = $_POST['condition_type']      ?? 'good';
        $form['location']       = trim($_POST['location']       ?? 'On Campus') ?: 'On Campus';

        if (!$form['title'])       $errors[] = 'Title is required.';
        if (!$form['description']) $errors[] = 'Description is required.';
        if ($form['price'] <= 0)   $errors[] = 'Enter a valid price.';
        if (!$form['category_id']) $errors[] = 'Select a category.';

        if (!$errors) {
            if ($isEdit) {
                Database::query(
                    'UPDATE listings SET title=?, description=?, price=?, category_id=?,
                     condition_type=?, location=? WHERE id = ?',
                    [$form['title'], $form['description'], $form['price'], $form['category_id'],
                     $form['condition_type'], $form['location'], $editId]
                );
                flash('success', 'Listing updated successfully!');
                redirect('/pages/listing.php?id=' . $editId);
            } else {
                $newId = Database::insert(
                    'INSERT INTO listings (seller_id, category_id, title, description, price, condition_type, location, status)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$me['id'], $form['category_id'], $form['title'], $form['description'],
                     $form['price'], $form['condition_type'], $form['location'], 'pending']
                );

                // Handle uploaded images
                $files = $_FILES['images'] ?? [];
                if (!empty($files['name'][0])) {
                    $count = min(5, count($files['name']));
                    for ($i = 0; $i < $count; $i++) {
                        $single = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $path = uploadImage($single, 'listings');
                        if ($path) {
                            Database::insert(
                                'INSERT INTO listing_images (listing_id, image_path, is_primary, sort_order)
                                 VALUES (?,?,?,?)',
                                [$newId, $path, $i === 0 ? 1 : 0, $i]
                            );
                        }
                    }
                }

                flash('success', 'Listing submitted for review. It will appear on the home page after approval.');
                redirect('/pages/listing.php?id=' . $newId);
            }
        }
    }
}

$pageTitle = $isEdit ? 'Edit Listing' : 'Sell an Item';
include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:680px;padding:36px 16px">
  <h1 class="page-title"><?= $isEdit ? 'Edit Listing' : ' Sell Your Item' ?></h1>
  <p class="page-sub">
    <?= $isEdit ? 'Update your listing details below.' : 'List something you no longer need.' ?>
  </p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" enctype="multipart/form-data" class="card">
    <div class="card-body">
      <?= csrfField() ?>

      <div class="form-group">
        <label>Title *</label>
        <input class="form-control" name="title" value="<?= e($form['title']) ?>"
               placeholder="e.g. Canon EOS 200D Camera" required>
      </div>

      <div class="form-group">
        <label>Description </label>
        <textarea class="form-control" name="description" rows="5"
                  placeholder="Describe your item — condition, age, reason for selling…"
                  required><?= e($form['description']) ?></textarea>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label>Price (UGX) *</label>
          <input class="form-control" type="number" name="price" min="0"
                 value="<?= e((string)$form['price']) ?>" placeholder="50000" required>
        </div>
        <div class="form-group">
          <label>Category *</label>
          <select class="form-control" name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"
                      <?= $form['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['icon'] . ' ' . $cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Condition</label>
          <select class="form-control" name="condition_type">
            <?php foreach (['new'=>'New','like_new'=>'Like New','good'=>'Good','fair'=>'Fair','poor'=>'Poor'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $form['condition_type']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Location</label>
          <input class="form-control" name="location" value="<?= e($form['location']) ?>"
                 placeholder="e.g. Male Hostel Block A">
        </div>
      </div>

      <?php if (!$isEdit): ?>
        <div class="form-group">
          <label>Photos (up to 5)</label>
          <input type="file" name="images[]" id="images" accept="image/*" multiple
                 style="font-size:14px;padding:8px 0">
          <div id="image-previews" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap"></div>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:12px;margin-top:8px">
        <a href="javascript:history.back()" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" style="flex:1">
          <?= $isEdit ? '💾Update Listing' : ' Publish Listing' ?>
        </button>
      </div>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
