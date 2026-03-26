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
  body { background: #f8f9fa; padding-bottom: 80px; }
  #submit-bar {
    display: none;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: #198754;
    color: #fff;
    padding: .85rem 1.5rem;
    z-index: 1000;
    box-shadow: 0 -2px 12px rgba(0,0,0,.2);
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  #submit-bar.visible { display: flex; }
  #main-photo {
    width: 100%;
    max-height: 480px;
    object-fit: contain;
    border-radius: 8px;
    background: #000;
    cursor: zoom-in;
  }
  #lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.92);
    z-index: 9999;
    cursor: zoom-out;
    align-items: center;
    justify-content: center;
  }
  #lightbox.open { display: flex; }
  #lightbox img {
    max-width: 95vw;
    max-height: 95vh;
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 0 40px rgba(0,0,0,.8);
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
  .thumb.active       { opacity: 1; border-color: #0d6efd; }
  .thumb.saved        { border-color: #198754; opacity: 1; }
  .thumb.active.saved { border-color: #0d6efd; }

  /* Names sidebar */
  #name-sidebar {
    position: sticky;
    top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
  }
  #name-list { display: flex; flex-wrap: wrap; gap: 4px; }
  .quick-name-btn { font-size: .8rem; }
  .quick-name-btn.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container-fluid justify-content-between">
    <span class="navbar-brand fw-bold"><i class="bi bi-camera"></i> <?= htmlspecialchars($gallery['name']) ?></span>
    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
      <i class="bi bi-question-circle"></i> Help
    </button>
  </div>
</nav>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle"></i> How to identify photos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <h6 class="fw-bold"><i class="bi bi-image text-primary"></i> Viewing Photos</h6>
            <ul class="small ps-3">
              <li>Photos are shown one at a time in the main area.</li>
              <li><strong>Click a photo</strong> to view it full screen. Click again or press <kbd>Esc</kbd> to close.</li>
              <li>Use the <strong>filmstrip</strong> at the bottom to jump to any photo directly.</li>
              <li>Filmstrip thumbnails turn <span class="text-success fw-bold">green</span> once a name has been entered for that photo.</li>
            </ul>

            <h6 class="fw-bold mt-3"><i class="bi bi-arrow-left-right text-primary"></i> Navigation</h6>
            <ul class="small ps-3">
              <li>Click <i class="bi bi-chevron-left"></i> / <i class="bi bi-chevron-right"></i> or press the <kbd>←</kbd> <kbd>→</kbd> arrow keys to move between photos.</li>
              <li>Pressing <kbd>→</kbd> saves the current name and advances.</li>
            </ul>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold"><i class="bi bi-pencil text-primary"></i> Entering Names</h6>
            <ul class="small ps-3">
              <li>Type names in the text field separated by <strong>commas</strong>: <em>Jane Smith, John Doe</em></li>
              <li>Leave the field blank if you don't recognise anyone in the photo.</li>
              <li>Press <kbd>Enter</kbd> or click <i class="bi bi-chevron-right"></i> to save and move on.</li>
            </ul>

            <h6 class="fw-bold mt-3"><i class="bi bi-people text-primary"></i> Known Names Panel</h6>
            <ul class="small ps-3">
              <li>As you enter names they appear as <strong>quick-add buttons</strong> on the right.</li>
              <li><strong>Click a button</strong> to add that name to the current photo. Click it again to remove it.</li>
              <li>Buttons turn <span class="text-primary fw-bold">blue</span> when that person is tagged in the current photo.</li>
              <li>Click <strong>Load Roster</strong> to pre-populate the panel from a saved name list.</li>
            </ul>

            <h6 class="fw-bold mt-3"><i class="bi bi-check-circle text-success"></i> Finishing Up</h6>
            <ul class="small ps-3">
              <li>Once every photo has been visited a <strong>green bar</strong> appears at the bottom — click it to submit.</li>
              <li>You can go back and change any answer before submitting.</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid px-3">

  <?php if ($already_done): ?>
    <div class="alert alert-success text-center">
      <i class="bi bi-check-circle-fill"></i> This gallery has already been completed. Thank you!
    </div>
  <?php elseif (empty($photos)): ?>
    <div class="alert alert-warning text-center">No photos in this gallery yet.</div>
  <?php else: ?>

  <!-- Identify photos -->
  <div id="step-photos">
    <div class="row g-3">

      <!-- Main column -->
      <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span id="photo-counter" class="text-muted small"></span>
          <span id="saved-count" class="text-muted small"></span>
        </div>

        <div class="progress mb-3" style="height:6px">
          <div id="progress-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-body p-2 text-center">
            <img id="main-photo" src="" alt="Photo" title="Click to enlarge">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Who is in this photo?</label>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-lg" id="prev-btn" title="Previous photo"><i class="bi bi-chevron-left"></i></button>
            <input type="text" id="people-input" class="form-control form-control-lg flex-grow-1"
                   placeholder="e.g. John Smith, Mary Jones">
            <button class="btn btn-primary btn-lg" id="save-btn" title="Save and go to next photo"><i class="bi bi-chevron-right"></i></button>
          </div>
          <div class="form-text">Enter all names, separated by commas. Leave blank if unknown.</div>
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

      <!-- Names sidebar -->
      <div class="col-lg-3">
        <div id="name-sidebar" class="card shadow-sm">
          <div class="card-header fw-semibold small py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people"></i> Known Names</span>
            <button class="btn btn-outline-secondary btn-sm py-0" id="load-roster-btn" title="Load a roster">
              <i class="bi bi-upload"></i> Load Roster
            </button>
          </div>
          <div class="px-2 pt-2 d-none" id="roster-picker">
            <select class="form-select form-select-sm mb-2" id="roster-select">
              <option value="">— select a roster —</option>
            </select>
          </div>
          <div class="card-body p-2" id="name-list">
            <p class="text-muted small mb-0" id="name-list-empty">Names you enter will appear here as quick-add buttons.</p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Sticky submit bar -->
  <div id="submit-bar">
    <span><i class="bi bi-check-circle"></i> All photos answered — ready to submit!</span>
    <button class="btn btn-light text-success fw-bold" id="submit-bar-btn">
      Submit All <i class="bi bi-arrow-right"></i>
    </button>
  </div>

  <!-- Step 3: thank you -->
  <div id="step-done" class="d-none text-center py-5">
    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
    <h4 class="mt-3">Thank you!</h4>
    <p class="text-muted">Your identifications have been submitted.</p>
  </div>

  <!-- Lightbox -->
  <div id="lightbox">
    <img id="lightbox-img" src="" alt="Full size photo">
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

let current = 0;
const answers    = {};   // photo_id -> text
const knownNames = [];   // ordered list of unique names seen

// DOM refs
const stepPhotos = document.getElementById('step-photos');
const stepDone   = document.getElementById('step-done');
const nameList   = document.getElementById('name-list');
const nameListEmpty = document.getElementById('name-list-empty');
const peopleInput   = document.getElementById('people-input');

// Start immediately
buildThumbs();
showPhoto(0);

// Build thumbnail strip
function buildThumbs() {
  const strip = document.getElementById('thumb-strip');
  PHOTOS.forEach(function(p, i) {
    const img = document.createElement('img');
    img.className = 'thumb';
    img.src = BASE_UPLOAD + p.filename;
    img.dataset.index = i;
    img.addEventListener('click', function() {
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
  peopleInput.value = answers[p.id] !== undefined ? answers[p.id] : '';
  peopleInput.focus();
  syncNameButtons();

  document.getElementById('photo-counter').textContent = 'Photo ' + (idx + 1) + ' of ' + PHOTOS.length;

  const saved = Object.keys(answers).length;
  document.getElementById('saved-count').textContent = saved + ' of ' + PHOTOS.length + ' answered';
  document.getElementById('progress-bar').style.width = ((saved / PHOTOS.length) * 100) + '%';

  document.querySelectorAll('.thumb').forEach(function(th, i) {
    th.classList.toggle('active', i === idx);
    th.classList.toggle('saved', !!answers[PHOTOS[i].id]);
  });

  document.getElementById('prev-btn').disabled = idx === 0;

  const allAnswered = PHOTOS.every(function(ph) { return answers[ph.id] !== undefined; });
  document.getElementById('done-btn').style.display  = allAnswered ? 'inline-block' : 'none';
  document.getElementById('done-hint').style.display = allAnswered ? 'block' : 'none';
  document.getElementById('submit-bar').classList.toggle('visible', allAnswered);
}

function saveCurrentAnswer() {
  const input = peopleInput.value.trim();
  answers[PHOTOS[current].id] = input;
  // Extract and register any new names
  if (input) {
    input.split(',').forEach(function(raw) {
      const name = raw.trim();
      if (name && knownNames.indexOf(name) === -1) {
        knownNames.push(name);
        addNameButton(name);
      }
    });
  }
}

function addNameButton(name) {
  nameListEmpty.style.display = 'none';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn btn-outline-secondary btn-sm quick-name-btn';
  btn.dataset.name = name;
  btn.title = name;
  btn.textContent = name;
  btn.addEventListener('click', function() {
    appendName(name);
  });
  nameList.appendChild(btn);
}

function appendName(name) {
  const existing = peopleInput.value.split(',').map(function(n) { return n.trim(); }).filter(Boolean);
  const idx = existing.indexOf(name);
  if (idx === -1) {
    existing.push(name);
  } else {
    existing.splice(idx, 1);
  }
  peopleInput.value = existing.join(', ');
  syncNameButtons();
  peopleInput.blur();
}

function syncNameButtons() {
  const active = peopleInput.value.split(',').map(function(n) { return n.trim(); }).filter(Boolean);
  document.querySelectorAll('.quick-name-btn').forEach(function(btn) {
    btn.classList.toggle('active', active.indexOf(btn.dataset.name) !== -1);
  });
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('save-btn').addEventListener('click', function() {
  saveCurrentAnswer();
  if (current < PHOTOS.length - 1) {
    showPhoto(current + 1);
  } else {
    showPhoto(current);
  }
});

document.getElementById('prev-btn').addEventListener('click', function() {
  saveCurrentAnswer();
  if (current > 0) showPhoto(current - 1);
});

peopleInput.addEventListener('input', syncNameButtons);

peopleInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') document.getElementById('save-btn').click();
});

document.getElementById('submit-bar-btn').addEventListener('click', function() {
  document.getElementById('done-btn').click();
});

document.getElementById('done-btn').addEventListener('click', async function() {
  document.getElementById('done-btn').disabled = true;
  document.getElementById('done-btn').textContent = 'Submitting…';

  const payload = PHOTOS.map(function(p) {
    return { photo_id: p.id, people: answers[p.id] !== undefined ? answers[p.id] : '' };
  });

  try {
    const res = await fetch('../api/complete.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        gallery_id: GALLERY_ID,
        token: TOKEN,
        identifications: payload
      })
    });
    const data = await res.json();
    if (data.ok) {
      stepPhotos.classList.add('d-none');
      stepDone.classList.remove('d-none');
    } else {
      alert('Error: ' + (data.error || 'Unknown error'));
      document.getElementById('done-btn').disabled = false;
      document.getElementById('done-btn').innerHTML = '<i class="bi bi-check-lg"></i> I\'m Done — Submit All';
    }
  } catch (e) {
    alert('Network error. Please try again.');
    document.getElementById('done-btn').disabled = false;
    document.getElementById('done-btn').innerHTML = '<i class="bi bi-check-lg"></i> I\'m Done — Submit All';
  }
});

// Roster loader
const rosterBtn    = document.getElementById('load-roster-btn');
const rosterPicker = document.getElementById('roster-picker');
const rosterSelect = document.getElementById('roster-select');

rosterBtn.addEventListener('click', function() {
  if (rosterPicker.classList.contains('d-none')) {
    // Load roster list if not already loaded
    if (rosterSelect.options.length === 1) {
      fetch('../api/rosters.php')
        .then(function(r) { return r.json(); })
        .then(function(d) {
          d.rosters.forEach(function(r) {
            var opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name;
            rosterSelect.appendChild(opt);
          });
        });
    }
    rosterPicker.classList.remove('d-none');
  } else {
    rosterPicker.classList.add('d-none');
  }
});

rosterSelect.addEventListener('change', function() {
  var id = this.value;
  if (!id) return;
  fetch('../api/rosters.php?id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      d.names.forEach(function(name) {
        if (knownNames.indexOf(name) === -1) {
          knownNames.push(name);
          addNameButton(name);
        }
      });
      rosterPicker.classList.add('d-none');
      rosterSelect.value = '';
    });
});

// Lightbox
const lightbox    = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');

document.getElementById('main-photo').addEventListener('click', function() {
  lightboxImg.src = this.src;
  lightbox.classList.add('open');
});

lightbox.addEventListener('click', function() {
  lightbox.classList.remove('open');
  lightboxImg.src = '';
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') lightbox.classList.remove('open');
  if (e.target === peopleInput) return; // don't hijack typing
  if (e.key === 'ArrowRight') { document.getElementById('save-btn').click(); peopleInput.blur(); }
  if (e.key === 'ArrowLeft')  { document.getElementById('prev-btn').click(); peopleInput.blur(); }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
new bootstrap.Modal(document.getElementById('helpModal')).show();
</script>
</body>
</html>
