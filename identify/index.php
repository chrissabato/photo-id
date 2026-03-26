<?php
require_once __DIR__ . '/../db.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') { http_response_code(404); die('Not found.'); }

$db = get_db();
$gallery = $db->prepare("SELECT * FROM galleries WHERE token = ?");
$gallery->execute([$token]);
$gallery = $gallery->fetch();

if (!$gallery) { http_response_code(404); die('Gallery not found.'); }

$photos = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order");
$photos->execute([$gallery['id']]);
$photos = $photos->fetchAll();

$already_done = $gallery['completed_at'] !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Identify Photos — <?= htmlspecialchars($gallery['name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f8f9fa; }
  #photo-container { max-width: 680px; margin: 0 auto; }
  #main-photo {
    width: 100%;
    max-height: 480px;
    object-fit: contain;
    border-radius: 8px;
    background: #000;
  }
  .thumb-strip { display: flex; gap: 6px; overflow-x: auto; padding: 6px 0; }
  .thumb {
    flex-shrink: 0;
    width: 64px; height: 64px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    opacity: .6;
    border: 2px solid transparent;
    transition: opacity .2s, border-color .2s;
  }
  .thumb.active   { opacity: 1; border-color: #0d6efd; }
  .thumb.saved    { border-color: #198754; opacity: 1; }
  .thumb.active.saved { border-color: #0d6efd; }
  #progress { height: 6px; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container justify-content-center">
    <span class="navbar-brand fw-bold"><i class="bi bi-camera"></i> <?= htmlspecialchars($gallery['name']) ?></span>
  </div>
</nav>

<div class="container" id="photo-container">

  <?php if ($already_done): ?>
    <div class="alert alert-success text-center">
      <i class="bi bi-check-circle-fill"></i> This gallery has already been completed. Thank you!
    </div>
  <?php elseif (empty($photos)): ?>
    <div class="alert alert-warning text-center">No photos in this gallery yet.</div>
  <?php else: ?>

  <!-- Step 1: enter identifier name -->
  <div id="step-name">
    <div class="card shadow-sm">
      <div class="card-body text-center py-5">
        <h5 class="mb-3">Welcome! Please enter your name to begin.</h5>
        <div class="d-flex gap-2 justify-content-center" style="max-width:380px;margin:0 auto">
          <input type="text" id="identifier-name" class="form-control" placeholder="Your name" autofocus>
          <button class="btn btn-primary" id="start-btn">Start <i class="bi bi-arrow-right"></i></button>
        </div>
        <p id="name-error" class="text-danger mt-2 d-none">Please enter your name.</p>
      </div>
    </div>
  </div>

  <!-- Step 2: identify photos -->
  <div id="step-photos" class="d-none">

    <div class="d-flex justify-content-between align-items-center mb-2">
      <span id="photo-counter" class="text-muted small"></span>
      <span id="saved-count" class="text-muted small"></span>
    </div>

    <div class="progress mb-3" style="height:6px">
      <div id="progress-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body p-2 text-center">
        <img id="main-photo" src="" alt="Photo">
      </div>
    </div>

    <div class="mb-2">
      <label class="form-label fw-semibold">Who is in this photo?</label>
      <input type="text" id="people-input" class="form-control form-control-lg"
             placeholder="e.g. John Smith, Mary Jones">
      <div class="form-text">Enter all names, separated by commas. Leave blank if unknown.</div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <button class="btn btn-outline-secondary" id="prev-btn"><i class="bi bi-chevron-left"></i> Prev</button>
      <button class="btn btn-primary flex-grow-1" id="save-btn">Save &amp; Next <i class="bi bi-chevron-right"></i></button>
    </div>

    <div class="thumb-strip mb-4" id="thumb-strip"></div>

    <div class="text-center mt-2 mb-5">
      <button class="btn btn-success btn-lg" id="done-btn" style="display:none">
        <i class="bi bi-check-lg"></i> I'm Done — Submit All
      </button>
      <p class="text-muted small mt-1" id="done-hint" style="display:none">
        You can still go back and change answers before submitting.
      </p>
    </div>
  </div>

  <!-- Step 3: thank you -->
  <div id="step-done" class="d-none text-center py-5">
    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
    <h4 class="mt-3">Thank you!</h4>
    <p class="text-muted">Your identifications have been submitted.</p>
  </div>

  <?php endif; ?>
</div>

<script>
const GALLERY_ID = <?= json_encode($gallery['id']) ?>;
const TOKEN      = <?= json_encode($token) ?>;
const PHOTOS     = <?= json_encode(array_map(function($p) { return [
    'id'       => $p['id'],
    'filename' => $p['filename'],
]; }, $photos)) ?>;

const BASE_UPLOAD = '../uploads/' + GALLERY_ID + '/';

let identifierName = '';
let current = 0;
const answers = {};  // photo_id -> text

// DOM refs
const stepName   = document.getElementById('step-name');
const stepPhotos = document.getElementById('step-photos');
const stepDone   = document.getElementById('step-done');

document.getElementById('start-btn').addEventListener('click', startIdentifying);
document.getElementById('identifier-name').addEventListener('keydown', e => {
  if (e.key === 'Enter') startIdentifying();
});

function startIdentifying() {
  identifierName = document.getElementById('identifier-name').value.trim();
  if (!identifierName) {
    document.getElementById('name-error').classList.remove('d-none');
    return;
  }
  stepName.classList.add('d-none');
  stepPhotos.classList.remove('d-none');
  buildThumbs();
  showPhoto(0);
}

// Build thumbnail strip
function buildThumbs() {
  const strip = document.getElementById('thumb-strip');
  PHOTOS.forEach((p, i) => {
    const img = document.createElement('img');
    img.className = 'thumb';
    img.src = BASE_UPLOAD + p.filename;
    img.dataset.index = i;
    img.addEventListener('click', () => {
      saveCurrentAnswer();
      showPhoto(i);
    });
    strip.appendChild(img);
  });
}

function showPhoto(idx) {
  current = idx;
  const p = PHOTOS[idx];
  document.getElementById('main-photo').src = BASE_UPLOAD + p.filename;
  document.getElementById('people-input').value = answers[p.id] ?? '';
  document.getElementById('people-input').focus();

  document.getElementById('photo-counter').textContent = `Photo ${idx + 1} of ${PHOTOS.length}`;

  // Progress
  const saved = Object.keys(answers).length;
  document.getElementById('saved-count').textContent = `${saved} of ${PHOTOS.length} answered`;
  const pct = (saved / PHOTOS.length) * 100;
  document.getElementById('progress-bar').style.width = pct + '%';

  // Thumbnails
  document.querySelectorAll('.thumb').forEach((th, i) => {
    th.classList.toggle('active', i === idx);
    const pid = PHOTOS[i].id;
    th.classList.toggle('saved', answers[pid] !== undefined);
  });

  // Prev / done
  document.getElementById('prev-btn').disabled = idx === 0;

  const allAnswered = PHOTOS.every(ph => answers[ph.id] !== undefined);
  document.getElementById('done-btn').style.display    = allAnswered ? 'inline-block' : 'none';
  document.getElementById('done-hint').style.display   = allAnswered ? 'block' : 'none';
}

function saveCurrentAnswer() {
  const input = document.getElementById('people-input').value.trim();
  answers[PHOTOS[current].id] = input;
}

document.getElementById('save-btn').addEventListener('click', () => {
  saveCurrentAnswer();
  if (current < PHOTOS.length - 1) {
    showPhoto(current + 1);
  } else {
    showPhoto(current); // refresh to show done button
  }
});

document.getElementById('prev-btn').addEventListener('click', () => {
  saveCurrentAnswer();
  if (current > 0) showPhoto(current - 1);
});

document.getElementById('people-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('save-btn').click();
});

document.getElementById('done-btn').addEventListener('click', async () => {
  document.getElementById('done-btn').disabled = true;
  document.getElementById('done-btn').textContent = 'Submitting…';

  // Save all answers
  const payload = PHOTOS.map(p => ({
    photo_id: p.id,
    people: answers[p.id] ?? ''
  }));

  try {
    const res = await fetch('../api/complete.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        gallery_id: GALLERY_ID,
        token: TOKEN,
        identifier_name: identifierName,
        identifications: payload
      })
    });
    const data = await res.json();
    if (data.ok) {
      stepPhotos.classList.add('d-none');
      stepDone.classList.remove('d-none');
    } else {
      alert('Error: ' + (data.error ?? 'Unknown error'));
      document.getElementById('done-btn').disabled = false;
      document.getElementById('done-btn').innerHTML = '<i class="bi bi-check-lg"></i> I\'m Done — Submit All';
    }
  } catch (e) {
    alert('Network error. Please try again.');
    document.getElementById('done-btn').disabled = false;
    document.getElementById('done-btn').innerHTML = '<i class="bi bi-check-lg"></i> I\'m Done — Submit All';
  }
});
</script>
</body>
</html>
