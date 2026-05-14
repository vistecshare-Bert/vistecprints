<?php
session_start();
if (empty($_SESSION['vp_admin'])) {
    header('Location: index.php');
    exit;
}

$jsonFile = __DIR__ . '/../products.json';

function loadProducts($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveProducts($file, $products) {
    if (!is_writable($file) && !is_writable(dirname($file))) {
        die('Error: products.json is not writable. Go to File Manager, right-click products.json → Permissions → set to 666.');
    }
    file_put_contents($file, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function handleImageUpload($cat) {
    if (empty($_FILES['img_file']['name'])) return null;

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = $_FILES['img_file']['type'];
    if (!in_array($mime, $allowed)) return null;

    $uploadDir = __DIR__ . '/../images/' . $cat . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext      = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['img_file']['tmp_name'], $dest)) {
        return 'images/' . $cat . '/' . $filename;
    }
    return null;
}

$categories = [
    'fun'      => 'Fun Print',
    'anime'    => 'Anime',
    'boots'    => 'Boots on the Ground',
    'no_kings' => 'No Kings',
    'jesus'    => 'Jesus Christ',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $products = loadProducts($jsonFile);

    if ($action === 'add' || $action === 'edit') {
        $cat      = trim($_POST['cat'] ?? 'fun');
        $uploaded = handleImageUpload($cat);
        $img      = $uploaded ?? trim($_POST['img'] ?? '');

        $product = [
            'id'      => trim($_POST['id'] ?? uniqid('vp_')),
            'name'    => trim($_POST['name'] ?? ''),
            'cat'     => $cat,
            'badge'   => trim($_POST['badge'] ?? ''),
            'img'     => $img,
            'price'   => trim($_POST['price'] ?? ''),
            'garment' => trim($_POST['garment'] ?? ''),
        ];

        if ($action === 'add') {
            $products[] = $product;
            $msg = 'Product added successfully!';
        } else {
            $idx = (int)($_POST['index'] ?? -1);
            if (isset($products[$idx])) {
                if (empty($product['img'])) $product['img'] = $products[$idx]['img'];
                $products[$idx] = $product;
                $msg = 'Product updated successfully!';
            }
        }
        saveProducts($jsonFile, $products);
        $msgType = 'success';
    }

    if ($action === 'delete') {
        $idx = (int)($_POST['index'] ?? -1);
        if (isset($products[$idx])) {
            array_splice($products, $idx, 1);
            saveProducts($jsonFile, $products);
            $msg = 'Product deleted.';
            $msgType = 'warning';
        }
    }

    header('Location: dashboard.php?msg=' . urlencode($msg ?? '') . '&type=' . ($msgType ?? 'success'));
    exit;
}

$products  = loadProducts($jsonFile);
$flashMsg  = $_GET['msg'] ?? '';
$flashType = $_GET['type'] ?? 'success';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>VistecPrints &mdash; Product Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--gold:#D49848;--gold-l:#E8B96A;--black:#0a0a0a;--dark:#111;--white:#fff;--gray:#888;--red:#e05555;}
body{background:#f4f4f4;font-family:'DM Sans',sans-serif;font-size:14px;}

.sidebar{position:fixed;top:0;left:0;bottom:0;width:220px;background:var(--black);display:flex;flex-direction:column;z-index:50;}
.sb-logo{padding:24px 20px;border-bottom:1px solid #1a1a1a;font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:3px;color:var(--gold);}
.sb-logo span{color:var(--white);}
.sb-label{padding:20px 20px 8px;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#444;}
.sb-nav{list-style:none;padding:0 12px;}
.sb-nav li a{display:flex;align-items:center;gap:10px;padding:10px 12px;color:#888;text-decoration:none;font-size:13px;border-radius:3px;transition:all 0.15s;}
.sb-nav li a:hover,.sb-nav li a.active{background:rgba(212,152,72,0.1);color:var(--gold);}
.sb-nav li a svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.sb-footer{margin-top:auto;padding:16px 20px;border-top:1px solid #1a1a1a;}
.sb-footer a{color:#555;font-size:12px;text-decoration:none;display:flex;align-items:center;gap:8px;transition:color 0.15s;}
.sb-footer a:hover{color:var(--red);}
.sb-footer svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}

.main{margin-left:220px;min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid #e8e8e8;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
.topbar h1{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1px;color:#111;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;border:none;border-radius:2px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.15s;}
.btn-gold{background:var(--gold);color:#000;}
.btn-gold:hover{background:var(--gold-l);}
.btn-outline{background:transparent;border:1px solid #ddd;color:#555;}
.btn-outline:hover{border-color:#aaa;color:#111;}
.btn-red{background:transparent;border:1px solid rgba(224,85,85,0.3);color:var(--red);}
.btn-red:hover{background:rgba(224,85,85,0.08);}
.btn-sm{padding:6px 14px;font-size:11px;}
.content{padding:28px 32px;}

.flash{padding:12px 18px;border-radius:3px;margin-bottom:24px;font-size:13px;font-weight:500;}
.flash-success{background:#f0faf4;border:1px solid #a3d9b5;color:#1e7e45;}
.flash-warning{background:#fff8f0;border:1px solid #f5c88a;color:#9a5c00;}

.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.stat-card{background:#fff;border:1px solid #e8e8e8;border-radius:3px;padding:20px 24px;}
.stat-card .num{font-family:'Bebas Neue',sans-serif;font-size:36px;color:var(--gold);line-height:1;}
.stat-card .lbl{font-size:12px;color:#888;letter-spacing:1px;text-transform:uppercase;margin-top:4px;}

.table-wrap{background:#fff;border:1px solid #e8e8e8;border-radius:3px;overflow:hidden;}
.table-header{padding:16px 24px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.table-header h2{font-size:15px;font-weight:600;color:#111;}
.filter-tabs{display:flex;gap:4px;flex-wrap:wrap;}
.ftab{background:transparent;border:1px solid #e0e0e0;color:#888;padding:6px 14px;font-size:11px;letter-spacing:1px;text-transform:uppercase;cursor:pointer;font-family:'DM Sans',sans-serif;border-radius:2px;transition:all 0.15s;}
.ftab.active,.ftab:hover{background:var(--gold);border-color:var(--gold);color:#000;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#fafafa;border-bottom:2px solid #f0f0f0;}
th{padding:12px 16px;text-align:left;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888;font-weight:600;}
td{padding:12px 16px;border-bottom:1px solid #f5f5f5;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafafa;}
.prod-img{width:56px;height:56px;object-fit:cover;border-radius:3px;background:#f0f0f0;display:block;border:1px solid #eee;}
.prod-img-placeholder{width:56px;height:56px;background:#f0f0f0;border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#bbb;letter-spacing:1px;border:1px solid #eee;}
.prod-name{font-weight:500;color:#111;}
.prod-id{font-size:11px;color:#aaa;margin-top:2px;}
.cat-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;}
.cat-fun{background:#fff3e0;color:#e65c00;}
.cat-anime{background:#e8f0ff;color:#1a56cc;}
.cat-boots{background:#f0ffe0;color:#2d6a00;}
.cat-jesus{background:#fff0f8;color:#a0006e;}
.cat-no_kings{background:#f5f0ff;color:#5c00c7;}
.badge-tag{display:inline-block;padding:2px 8px;background:var(--gold);color:#000;font-size:10px;font-weight:700;letter-spacing:1px;border-radius:2px;}
.actions{display:flex;gap:6px;}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:200;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:4px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;}
.modal-head{background:var(--black);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;}
.modal-head h3{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1px;color:var(--white);}
.modal-close{background:none;border:none;color:#666;cursor:pointer;font-size:22px;line-height:1;padding:4px;}
.modal-close:hover{color:#fff;}
.modal-body{padding:24px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600;}
.form-group input,.form-group select{width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;border-radius:2px;outline:none;color:#111;}
.form-group input:focus,.form-group select:focus{border-color:var(--gold);}
.form-group .hint{font-size:11px;color:#aaa;margin-top:4px;}

/* IMAGE UPLOAD */
.img-upload-area{border:2px dashed #ddd;border-radius:4px;padding:20px;text-align:center;cursor:pointer;transition:all 0.2s;position:relative;background:#fafafa;}
.img-upload-area:hover{border-color:var(--gold);background:#fffbf5;}
.img-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.img-upload-area svg{width:32px;height:32px;stroke:#ccc;fill:none;stroke-width:1.5;margin-bottom:8px;}
.img-upload-area p{font-size:13px;color:#aaa;margin:0;}
.img-upload-area span{font-size:11px;color:#bbb;}
.upload-preview-wrap{display:none;align-items:center;gap:12px;margin-top:12px;padding:10px;background:#f5f5f5;border-radius:3px;}
.upload-preview-wrap img{width:60px;height:60px;object-fit:cover;border-radius:3px;border:1px solid #eee;}
.upload-preview-wrap .preview-info{flex:1;}
.upload-preview-wrap .preview-name{font-size:12px;font-weight:500;color:#333;}
.upload-preview-wrap .preview-size{font-size:11px;color:#aaa;}
.upload-preview-wrap .remove-img{background:none;border:none;color:var(--red);cursor:pointer;font-size:18px;line-height:1;}
.current-img-wrap{display:flex;align-items:center;gap:10px;padding:10px;background:#f5f5f5;border-radius:3px;margin-bottom:10px;}
.current-img-wrap img{width:56px;height:56px;object-fit:cover;border-radius:3px;border:1px solid #eee;}
.current-img-wrap .cur-label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:1px;}
.current-img-wrap .cur-path{font-size:12px;color:#555;word-break:break-all;}
.divider{text-align:center;color:#ccc;font-size:11px;letter-spacing:2px;text-transform:uppercase;margin:12px 0;position:relative;}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:#eee;}
.divider::before{left:0;}.divider::after{right:0;}

.modal-foot{padding:16px 24px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:10px;}
.empty-state{text-align:center;padding:60px 20px;color:#bbb;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">VISTEC<span>PRINTS</span></div>
  <div class="sb-label">Manage</div>
  <ul class="sb-nav">
    <li>
      <a href="dashboard.php" class="active">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Decorated Products
      </a>
    </li>
    <li>
      <a href="../index.html" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        View Website
      </a>
    </li>
  </ul>
  <div class="sb-footer">
    <a href="logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <h1>Decorated Products</h1>
    <button class="btn btn-gold" onclick="openModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Product
    </button>
  </div>

  <div class="content">
    <?php if ($flashMsg): ?>
    <div class="flash flash-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <div class="stats">
      <div class="stat-card"><div class="num"><?= count($products) ?></div><div class="lbl">Total Products</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($products, fn($p) => $p['cat'] === 'anime')) ?></div><div class="lbl">Anime</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($products, fn($p) => $p['cat'] === 'fun')) ?></div><div class="lbl">Fun Print</div></div>
      <div class="stat-card"><div class="num"><?= count(array_filter($products, fn($p) => !empty($p['badge']))) ?></div><div class="lbl">Featured</div></div>
    </div>

    <div class="table-wrap">
      <div class="table-header">
        <h2>All Products</h2>
        <div class="filter-tabs">
          <button class="ftab active" data-cat="all">All</button>
          <?php foreach ($categories as $key => $label): ?>
          <button class="ftab" data-cat="<?= $key ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (empty($products)): ?>
      <div class="empty-state"><p>No products yet. Click <strong>Add Product</strong> to get started.</p></div>
      <?php else: ?>
      <table id="productTable">
        <thead>
          <tr>
            <th>Image</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>Badge</th>
            <th>Price</th>
            <th>Blank Product</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $i => $p):
            $imgSrc = $p['img'] ?? '';
            $isLocal = $imgSrc && strpos($imgSrc, 'http') !== 0;
            $displaySrc = $isLocal ? '../' . $imgSrc : $imgSrc;
          ?>
          <tr data-cat="<?= htmlspecialchars($p['cat']) ?>">
            <td>
              <?php if ($imgSrc): ?>
              <img src="<?= htmlspecialchars($displaySrc) ?>" alt="" class="prod-img" onerror="this.style.display='none'"/>
              <?php else: ?>
              <div class="prod-img-placeholder">NO IMG</div>
              <?php endif; ?>
            </td>
            <td>
              <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="prod-id">ID: <?= htmlspecialchars($p['id']) ?></div>
            </td>
            <td><span class="cat-badge cat-<?= htmlspecialchars($p['cat']) ?>"><?= htmlspecialchars($categories[$p['cat']] ?? $p['cat']) ?></span></td>
            <td><?php if (!empty($p['badge'])): ?><span class="badge-tag"><?= htmlspecialchars($p['badge']) ?></span><?php else: ?><span style="color:#ccc">—</span><?php endif; ?></td>
            <td><?= htmlspecialchars($p['price'] ?? '—') ?></td>
            <td style="color:#666;max-width:160px;"><?= htmlspecialchars($p['garment'] ?? '—') ?></td>
            <td>
              <div class="actions">
                <button class="btn btn-outline btn-sm" onclick="editProduct(<?= $i ?>, <?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete this product?')" style="display:inline;">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="index" value="<?= $i ?>"/>
                  <button type="submit" class="btn btn-red btn-sm">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">Add Product</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="productForm" enctype="multipart/form-data">
        <input type="hidden" name="action" id="formAction" value="add"/>
        <input type="hidden" name="index" id="formIndex" value=""/>
        <input type="hidden" name="img" id="fImgExisting" value=""/>

        <div class="form-row">
          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" id="fName" required placeholder="e.g. Anime V3-42"/>
          </div>
          <div class="form-group">
            <label>Category *</label>
            <select name="cat" id="fCat" onchange="updateUploadHint()">
              <?php foreach ($categories as $key => $label): ?>
              <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Product Image</label>

          <!-- Current image (edit mode) -->
          <div class="current-img-wrap" id="currentImgWrap" style="display:none;">
            <img id="currentImgThumb" src="" alt=""/>
            <div>
              <div class="cur-label">Current Image</div>
              <div class="cur-path" id="currentImgPath"></div>
            </div>
          </div>

          <!-- Upload area -->
          <div class="img-upload-area" id="uploadArea">
            <input type="file" name="img_file" id="imgFileInput" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewUpload(this)"/>
            <svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            <p id="uploadHint">Click or drag to upload image</p>
            <span id="uploadSub">Saves to <strong>images/<span id="catFolder">fun</span>/</strong> &nbsp;|&nbsp; JPG, PNG, WEBP</span>
          </div>

          <!-- Upload preview -->
          <div class="upload-preview-wrap" id="uploadPreviewWrap">
            <img id="uploadPreviewImg" src="" alt=""/>
            <div class="preview-info">
              <div class="preview-name" id="uploadPreviewName"></div>
              <div class="preview-size" id="uploadPreviewSize"></div>
            </div>
            <button type="button" class="remove-img" onclick="removeUpload()" title="Remove">&times;</button>
          </div>

          <div class="divider" id="urlDivider">or use external URL</div>

          <input type="url" id="fImgUrl" placeholder="https://www.vistecprints.com/ssc/i/..." style="width:100%;border:1px solid #ddd;padding:10px 12px;font-size:13px;font-family:inherit;border-radius:2px;outline:none;color:#111;" oninput="document.getElementById('fImgExisting').value=this.value"/>
          <div class="hint" style="margin-top:4px;">Paste a URL only if not uploading a file above</div>
        </div>

        <div class="form-group">
          <label>Product ID (DecoNetwork / VistecPrints ID)</label>
          <input type="text" name="id" id="fId" placeholder="e.g. 26858611"/>
          <div class="hint">Links to vistecprints.com/shop/view_product/{id}</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Price</label>
            <input type="text" name="price" id="fPrice" placeholder="$20.00"/>
          </div>
          <div class="form-group">
            <label>Badge <span style="font-weight:300;text-transform:none;">(optional)</span></label>
            <input type="text" name="badge" id="fBadge" placeholder="New, Hot, Sale..."/>
          </div>
        </div>

        <div class="form-group">
          <label>Blank Product / Garment</label>
          <input type="text" name="garment" id="fGarment" placeholder="e.g. Heavy Cotton 100% Cotton T-Shirt"/>
        </div>

        <div class="modal-foot" style="padding:0;margin-top:8px;">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-gold" id="submitBtn">Add Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(edit) {
  document.getElementById('modalOverlay').classList.add('open');
  if (!edit) {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formIndex').value = '';
    document.getElementById('submitBtn').textContent = 'Add Product';
    document.getElementById('productForm').reset();
    document.getElementById('fImgExisting').value = '';
    document.getElementById('fImgUrl').value = '';
    document.getElementById('currentImgWrap').style.display = 'none';
    document.getElementById('uploadPreviewWrap').style.display = 'none';
    updateUploadHint();
  }
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

function editProduct(idx, data) {
  openModal(true);
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('formIndex').value = idx;
  document.getElementById('submitBtn').textContent = 'Save Changes';
  document.getElementById('fName').value    = data.name    || '';
  document.getElementById('fCat').value     = data.cat     || 'fun';
  document.getElementById('fId').value      = data.id      || '';
  document.getElementById('fPrice').value   = data.price   || '';
  document.getElementById('fBadge').value   = data.badge   || '';
  document.getElementById('fGarment').value = data.garment || '';
  document.getElementById('fImgExisting').value = data.img || '';

  // Show current image
  if (data.img) {
    const src = data.img.startsWith('http') ? data.img : '../' + data.img;
    document.getElementById('currentImgThumb').src = src;
    document.getElementById('currentImgPath').textContent = data.img;
    document.getElementById('currentImgWrap').style.display = 'flex';
    document.getElementById('fImgUrl').value = data.img.startsWith('http') ? data.img : '';
  } else {
    document.getElementById('currentImgWrap').style.display = 'none';
  }

  document.getElementById('uploadPreviewWrap').style.display = 'none';
  updateUploadHint();
}

function updateUploadHint() {
  const cat = document.getElementById('fCat').value;
  document.getElementById('catFolder').textContent = cat;
}

function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('uploadPreviewImg').src = e.target.result;
    document.getElementById('uploadPreviewName').textContent = file.name;
    document.getElementById('uploadPreviewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('uploadPreviewWrap').style.display = 'flex';
    document.getElementById('fImgExisting').value = '';
    document.getElementById('fImgUrl').value = '';
  };
  reader.readAsDataURL(file);
}

function removeUpload() {
  document.getElementById('imgFileInput').value = '';
  document.getElementById('uploadPreviewWrap').style.display = 'none';
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

document.querySelectorAll('.ftab').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const cat = this.dataset.cat;
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
      row.style.display = (cat === 'all' || row.dataset.cat === cat) ? '' : 'none';
    });
  });
});
</script>
</body>
</html>
