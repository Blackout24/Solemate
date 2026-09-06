<div class="customizer-layout">

  <!-- 3D Canvas -->
  <div class="customizer-canvas-wrap">
    <canvas id="shoe-canvas"></canvas>
    <div class="canvas-loading" id="loading-screen">
      <div class="loading-ring"></div>
      <div class="loading-text">Loading 3D model…</div>
    </div>
    <div class="canvas-hint" id="canvas-hint">Drag to rotate · Scroll to zoom</div>
    <!-- Live Price Badge on canvas -->
    <div class="canvas-price-badge" id="canvas-price-badge">R1,200</div>
  </div>

  <!-- Customization Panel -->
  <div class="customizer-panel">

    <!-- ═══ ACCORDION PANEL ═══ -->
    <div class="accordion">

      <!-- ① COLOUR -->
      <div class="ac-section open" id="ac-colour">
        <div class="ac-header" data-ac="colour">
          <span class="ac-icon">🎨</span><span class="ac-title">Colour</span><span class="ac-arrow">▲</span>
        </div>
        <div class="ac-body">
          <div class="ac-label">Select a Part</div>
          <div class="parts-grid" id="parts-grid"></div>
          <div class="color-picker-area" id="color-picker-area" style="display:none">
            <div class="color-picker-label" id="picker-label">Part colour</div>
            <div class="color-swatches" id="color-swatches"></div>
            <div class="color-hex-row">
              <input type="text" class="color-hex-input" id="hex-input" placeholder="#ffffff" maxlength="7">
              <input type="color" class="native-picker" id="native-picker">
            </div>
          </div>
          <div class="ac-divider"></div>
          <div class="ac-label">Style Presets</div>
          <div class="preset-grid" id="preset-grid"></div>
        </div>
      </div>

      <!-- ② MATERIAL -->
      <div class="ac-section" id="ac-material">
        <div class="ac-header" data-ac="material">
          <span class="ac-icon">🧵</span><span class="ac-title">Material</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="material-grid" id="material-grid"></div>
          <div class="material-price-note" id="material-price-note"></div>
        </div>
      </div>

      <!-- ③ TEXT -->
      <div class="ac-section" id="ac-text">
        <div class="ac-header" data-ac="text">
          <span class="ac-icon">✍️</span><span class="ac-title">Custom Text</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="fg">
            <label>Text (name, initials, message)</label>
            <input type="text" id="custom-text-input" placeholder="e.g. SIYA or #1 MOM" maxlength="20">
          </div>
          <div class="fg">
            <label>Font Style</label>
            <div class="font-grid" id="font-grid"></div>
          </div>
          <div class="fg">
            <label>Text Colour</label>
            <div class="text-color-swatches" id="text-color-swatches"></div>
          </div>
          <div class="fg">
            <label>Placement</label>
            <select id="text-placement">
              <option value="heel">Heel Tab</option>
              <option value="tongue">Tongue</option>
              <option value="side">Side Panel</option>
              <option value="insole">Insole</option>
            </select>
          </div>
          <div class="text-preview-box" id="text-preview-box">
            <span id="text-preview-content" style="font-family:serif;color:#fff">Your text here</span>
          </div>
          <div class="add-cost-note" id="text-cost-note" style="display:none">+R80 for custom text</div>
        </div>
      </div>

      <!-- ④ LACES -->
      <div class="ac-section" id="ac-lace">
        <div class="ac-header" data-ac="lace">
          <span class="ac-icon">🪢</span><span class="ac-title">Laces</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="ac-label">Lace Style</div>
          <div class="lace-style-grid" id="lace-style-grid"></div>
          <div class="ac-divider"></div>
          <div class="ac-label">Lace Colour</div>
          <div class="lace-color-swatches" id="lace-color-swatches"></div>
        </div>
      </div>

      <!-- ⑤ SOLE -->
      <div class="ac-section" id="ac-sole">
        <div class="ac-header" data-ac="sole">
          <span class="ac-icon">👟</span><span class="ac-title">Sole</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="fg">
            <label>Sole Colour</label>
            <div class="sole-color-swatches" id="sole-color-swatches"></div>
          </div>
          <div class="fg" style="margin-top:1rem">
            <label>Special Effects</label>
            <div class="sole-effects-grid" id="sole-effects-grid"></div>
          </div>
        </div>
      </div>

      <!-- ⑥ SIZE -->
      <div class="ac-section" id="ac-size">
        <div class="ac-header" data-ac="size">
          <span class="ac-icon">📏</span><span class="ac-title">Size</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="size-system-tabs">
            <button class="size-sys-btn active-sys" data-sys="uk">UK</button>
            <button class="size-sys-btn" data-sys="mens">Men's US</button>
            <button class="size-sys-btn" data-sys="womens">Women's US</button>
            <button class="size-sys-btn" data-sys="kids">Kids</button>
          </div>
          <div class="size-grid" id="size-grid"></div>
          <div class="size-note">Selected: <strong id="selected-size-display">UK 6</strong></div>
        </div>
      </div>

      <!-- ⑦ SCENE -->
      <div class="ac-section" id="ac-env">
        <div class="ac-header" data-ac="env">
          <span class="ac-icon">🌅</span><span class="ac-title">Scene</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="env-btns" id="env-btns">
            <button class="env-btn active-env" data-env="studio"><span class="env-icon">🏢</span><span class="env-label">Studio</span></button>
            <button class="env-btn" data-env="sunset"><span class="env-icon">🌅</span><span class="env-label">Sunset</span></button>
            <button class="env-btn" data-env="night"><span class="env-icon">🌃</span><span class="env-label">Night</span></button>
            <button class="env-btn" data-env="neon"><span class="env-icon">💜</span><span class="env-label">Neon</span></button>
            <button class="env-btn" data-env="forest"><span class="env-icon">🌲</span><span class="env-label">Forest</span></button>
            <button class="env-btn" data-env="ice"><span class="env-icon">❄️</span><span class="env-label">Ice</span></button>
            <button class="env-btn" data-env="desert"><span class="env-icon">🏜️</span><span class="env-label">Desert</span></button>
            <button class="env-btn" data-env="beach"><span class="env-icon">🏖️</span><span class="env-label">Beach</span></button>
            <button class="env-btn" data-env="snow"><span class="env-icon">🏔️</span><span class="env-label">Snow Mtn</span></button>
            <button class="env-btn" data-env="court"><span class="env-icon">🏀</span><span class="env-label">Court</span></button>
            <button class="env-btn" data-env="soccer"><span class="env-icon">⚽</span><span class="env-label">Soccer</span></button>
            <button class="env-btn" data-env="city"><span class="env-icon">🌆</span><span class="env-label">City</span></button>
            <button class="env-btn" data-env="graffiti"><span class="env-icon">🎨</span><span class="env-label">Graffiti</span></button>
            <button class="env-btn" data-env="luxury"><span class="env-icon">💎</span><span class="env-label">Luxury</span></button>
            <button class="env-btn" data-env="space"><span class="env-icon">🚀</span><span class="env-label">Space</span></button>
            <button class="env-btn" data-env="rainy"><span class="env-icon">🌧️</span><span class="env-label">Rainy</span></button>
            <button class="env-btn" data-env="racing"><span class="env-icon">🏎️</span><span class="env-label">Racing</span></button>
          </div>
        </div>
      </div>

      <!-- ⑧ ACCESSORIES -->
      <div class="ac-section" id="ac-accessories">
        <div class="ac-header" data-ac="accessories">
          <span class="ac-icon">🔮</span><span class="ac-title">Accessories</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="ac-label">Add-ons</div>
          <div class="accessory-grid" id="accessory-grid"></div>
        </div>
      </div>

      <!-- ⑨ STITCHING -->
      <div class="ac-section" id="ac-stitching">
        <div class="ac-header" data-ac="stitching">
          <span class="ac-icon">🪡</span><span class="ac-title">Stitching</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="ac-label">Stitching Colour</div>
          <div class="stitch-color-swatches" id="stitch-color-swatches"></div>
        </div>
      </div>

      <!-- ⑩ PATTERN GENERATOR -->
      <div class="ac-section" id="ac-pattern">
        <div class="ac-header" data-ac="pattern">
          <span class="ac-icon">🎭</span><span class="ac-title">Pattern Generator</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="ac-label">Body Pattern</div>
          <div class="pattern-grid" id="pattern-grid"></div>
          <div class="add-cost-note" id="pattern-cost-note" style="display:none"></div>
        </div>
      </div>

      <!-- ⑪ DECALS & STICKERS -->
      <div class="ac-section" id="ac-decals">
        <div class="ac-header" data-ac="decals">
          <span class="ac-icon">⭐</span><span class="ac-title">Decals &amp; Stickers</span><span class="ac-arrow">▼</span>
        </div>
        <div class="ac-body" style="display:none">
          <div class="ac-label">Choose a Decal</div>
          <div class="decal-grid" id="decal-grid"></div>
          <div class="fg" style="margin-top:10px">
            <label>Placement</label>
            <select id="decal-placement">
              <option value="toe">Toe Cap</option>
              <option value="heel">Heel</option>
              <option value="side" selected>Side Panel</option>
              <option value="tongue">Tongue</option>
              <option value="outsole">Outsole</option>
            </select>
          </div>
          <div class="text-preview-box" id="decal-preview-box" style="display:none">
            <span id="decal-preview-content" style="font-size:28px">⭐</span>
          </div>
          <div class="add-cost-note" id="decal-cost-note" style="display:none">+R45 per decal</div>
        </div>
      </div>

    </div>
    <!-- ═══ END ACCORDION ═══ -->

    <!-- Actions (always visible) -->
    <div class="cp-card actions-card">
      <div class="live-price-bar">
        <div class="price-breakdown-toggle" id="price-toggle">
          <span>Total</span><span id="live-total-display">R1,200</span>
          <span class="toggle-arrow" id="toggle-arrow">▼</span>
        </div>
        <div class="price-breakdown" id="price-breakdown" style="display:none">
          <div class="pb-row"><span>Base shoe</span><span>R1,200</span></div>
          <div class="pb-row" id="pb-material" style="display:none"><span id="pb-mat-label">Material</span><span id="pb-mat-cost"></span></div>
          <div class="pb-row" id="pb-text" style="display:none"><span>Custom text</span><span>+R80</span></div>
          <div class="pb-row" id="pb-lace" style="display:none"><span id="pb-lace-label">Lace style</span><span id="pb-lace-cost"></span></div>
          <div class="pb-row" id="pb-sole" style="display:none"><span id="pb-sole-label">Sole effect</span><span id="pb-sole-cost"></span></div>
          <div class="pb-row" id="pb-accessories" style="display:none"><span>Accessories</span><span id="pb-acc-cost"></span></div>
          <div class="pb-row" id="pb-pattern" style="display:none"><span id="pb-pattern-label">Pattern</span><span id="pb-pattern-cost"></span></div>
          <div class="pb-row" id="pb-decal" style="display:none"><span>Decal / sticker</span><span id="pb-decal-cost"></span></div>
          <div class="pb-row" id="pb-image" style="display:none"><span>Custom logo</span><span>+R120</span></div>
        </div>
      </div>
      <button class="reset-btn" id="reset-btn">↺ Reset to defaults</button>
      <button class="cta-buy-btn" id="open-buy-modal" style="margin-top:0.75rem">
        🛒 Buy Custom Shoes
      </button>
    </div>

  </div>
</div>

<!-- ═══════════════ UPLOAD LOGO MODAL ═══════════════ -->
<!-- Accessible via logo button in the panel -->

<!-- ═══════════════════════════════════════════ BUY MODAL ═══════════════════════════════════════════ -->
<div class="buy-modal-overlay" id="buy-modal-overlay">
  <div class="buy-modal" id="buy-modal">
    <button class="modal-close" id="close-buy-modal">✕</button>

    <div class="modal-header">
      <div class="modal-icon">👟</div>
      <div>
        <div class="modal-title">Order Your Custom Shoes</div>
        <div class="modal-sub">Your unique design, crafted to order</div>
      </div>
    </div>

    <!-- Design preview strip -->
    <div class="design-preview">
      <div class="dp-label">Your Design</div>
      <div class="dp-swatches" id="dp-swatches"></div>
      <div class="dp-extra" id="dp-extra"></div>
    </div>

    <form method="POST" action="solemate_process_custom_order.php" id="buy-form">
      <input type="hidden" name="customization_data" id="customization_data_field">
      <input type="hidden" name="shoe_size" id="shoe_size_field">

      <div class="modal-section">
        <div class="modal-section-title">📋 Your Details</div>
        <div class="form-row">
          <div class="fg">
            <label>Full Name *</label>
            <input type="text" name="customer_name" placeholder="e.g. Siya Khoza" required>
          </div>
          <div class="fg">
            <label>Phone</label>
            <input type="tel" name="phone" placeholder="e.g. 082 123 4567">
          </div>
        </div>
        <div class="fg">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="your@email.com" required>
        </div>
      </div>

      <div class="modal-section">
        <div class="modal-section-title">📦 Delivery Details</div>
        <div class="fg">
          <label>Delivery Method *</label>
          <select name="collection" id="modal-collection" required>
            <option value="drop-off">Drop-off / Collect in store (Durban)</option>
            <option value="courier">Courier delivery (R85 flat rate)</option>
            <option value="home">Home delivery (Durban metro only)</option>
          </select>
        </div>
        <div class="fg" id="address-fg" style="display:none">
          <label>Delivery Address *</label>
          <input type="text" name="delivery_address" id="delivery-address" placeholder="Street, suburb, city">
        </div>
        <div class="fg">
          <label>Expected Collection / Delivery Date *</label>
          <input type="date" name="drop_date" min="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
        </div>
      </div>

      <div class="modal-section">
        <div class="modal-section-title">💬 Additional Notes</div>
        <div class="fg">
          <textarea name="notes" placeholder="Any special requests, monogram initials, specific material preferences…" rows="3"></textarea>
        </div>
      </div>

      <!-- Price summary -->
      <div class="price-summary">
        <div class="price-row"><span>Custom shoe (1 pair)</span><span>R1,200.00</span></div>
        <div class="price-row" id="modal-material-row" style="display:none"><span id="modal-mat-label">Material upgrade</span><span id="modal-mat-cost"></span></div>
        <div class="price-row" id="modal-text-row" style="display:none"><span>Custom text</span><span>+R80.00</span></div>
        <div class="price-row" id="modal-lace-row" style="display:none"><span id="modal-lace-label">Lace style</span><span id="modal-lace-cost"></span></div>
        <div class="price-row" id="modal-sole-row" style="display:none"><span id="modal-sole-label">Sole effect</span><span id="modal-sole-cost"></span></div>
        <div class="price-row" id="modal-acc-row" style="display:none"><span>Accessories</span><span id="modal-acc-cost"></span></div>
        <div class="price-row" id="modal-pattern-row" style="display:none"><span id="modal-pattern-label">Pattern</span><span id="modal-pattern-cost"></span></div>
        <div class="price-row" id="modal-decal-row" style="display:none"><span>Decal / sticker</span><span id="modal-decal-cost"></span></div>
        <div class="price-row" id="courier-row" style="display:none"><span>Courier fee</span><span>R85.00</span></div>
        <div class="price-row price-total"><span>Total</span><span id="modal-total">R1,200.00</span></div>
        <div class="price-note">* Production takes 7–14 business days. Full payment due on collection/delivery.</div>
      </div>

      <button type="submit" class="modal-submit-btn">
        ✅ Place Order
      </button>
    </form>
  </div>
</div>

<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>

<script type="module">

import * as THREE from 'three';
import { GLTFLoader }    from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader }   from 'three/addons/loaders/DRACOLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/* ── Renderer / Scene ─────────────────────────────────────────── */
const canvas    = document.getElementById('shoe-canvas');
const container = canvas.parentElement;

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x1a1209);

const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
camera.position.set(0, 1.5, 4);

const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(window.devicePixelRatio);
renderer.outputColorSpace = THREE.SRGBColorSpace;

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.minDistance   = 2;
controls.maxDistance   = 8;

/* ── Lights ───────────────────────────────────────────────────── */
const ambient     = new THREE.AmbientLight(0xffffff, 1.5);
const directional = new THREE.DirectionalLight(0xffffff, 2.0);
directional.position.set(5, 10, 7);
const fillLight   = new THREE.DirectionalLight(0xffffff, 0.6);
fillLight.position.set(-5, 2, -5);
scene.add(ambient, directional, fillLight);

/* ── Parts ────────────────────────────────────────────────────── */
const PARTS = [
  { id: 'laces',   label: 'Laces',   defaultColor: '#e8e8e8' },
  { id: 'mesh',    label: 'Body',    defaultColor: '#c8a97e' },
  { id: 'caps',    label: 'Toe Cap', defaultColor: '#1a1209' },
  { id: 'inner',   label: 'Inner',   defaultColor: '#c8a97e' },
  { id: 'sole',    label: 'Sole',    defaultColor: '#1a1209' },
  { id: 'stripes', label: 'Stripes', defaultColor: '#ffffff' },
  { id: 'band',    label: 'Band',    defaultColor: '#111111' },
  { id: 'patch',   label: 'Patch',   defaultColor: '#8b6914' },
  { id: 'stitching', label: 'Stitching', defaultColor: '#ffffff' },
];

const PRESETS = [
  { label: 'Classic',    colors: { laces:'#e8e8e8', mesh:'#c8a97e', caps:'#1a1209', inner:'#c8a97e', sole:'#1a1209', stripes:'#ffffff', band:'#111111', patch:'#8b6914' } },
  { label: 'All White',  colors: { laces:'#f0f0f0', mesh:'#ffffff', caps:'#e0e0e0', inner:'#f5f5f5', sole:'#d8d8d8', stripes:'#ffffff', band:'#e0e0e0', patch:'#cccccc' } },
  { label: 'All Black',  colors: { laces:'#222222', mesh:'#111111', caps:'#000000', inner:'#1a1a1a', sole:'#0a0a0a', stripes:'#333333', band:'#000000', patch:'#1a1a1a' } },
  { label: 'Midnight',   colors: { laces:'#4a90d9', mesh:'#1a1a2e', caps:'#16213e', inner:'#1a1a2e', sole:'#0d0d1a', stripes:'#4a90d9', band:'#16213e', patch:'#2a2a4e' } },
  { label: 'Crimson',    colors: { laces:'#ffffff', mesh:'#8b0000', caps:'#1a0000', inner:'#8b0000', sole:'#1a0000', stripes:'#ff4444', band:'#1a0000', patch:'#cc0000' } },
  { label: 'Forest',     colors: { laces:'#d4c5a9', mesh:'#2d4a2d', caps:'#1a2a1a', inner:'#2d4a2d', sole:'#1a2a1a', stripes:'#5a8a5a', band:'#1a2a1a', patch:'#3d6b3d' } },
  { label: 'Ocean',      colors: { laces:'#ffffff', mesh:'#006994', caps:'#003d5c', inner:'#006994', sole:'#003d5c', stripes:'#00b4d8', band:'#003d5c', patch:'#0077b6' } },
  { label: 'Sand Dune',  colors: { laces:'#f5deb3', mesh:'#c2955a', caps:'#8b6914', inner:'#c2955a', sole:'#5c4a1e', stripes:'#f5deb3', band:'#5c4a1e', patch:'#a0752a' } },
  { label: 'Rose Gold',  colors: { laces:'#ffd7c0', mesh:'#b76e79', caps:'#7d3c46', inner:'#b76e79', sole:'#4a2030', stripes:'#f4c2c2', band:'#7d3c46', patch:'#c9818a' } },
  { label: 'Neon Pop',   colors: { laces:'#ffffff', mesh:'#0d0d0d', caps:'#000000', inner:'#0d0d0d', sole:'#000000', stripes:'#39ff14', band:'#000000', patch:'#ff00ff' } },
  { label: 'Caramel',    colors: { laces:'#fff8e7', mesh:'#c68642', caps:'#7b4f2e', inner:'#c68642', sole:'#3e2723', stripes:'#ffe0b2', band:'#4e342e', patch:'#a0522d' } },
  { label: 'Ice Blue',   colors: { laces:'#ffffff', mesh:'#cce7f5', caps:'#5bc0eb', inner:'#ddeeff', sole:'#a8d8ea', stripes:'#ffffff', band:'#5bc0eb', patch:'#87ceeb' } },
];

const ENV_SETTINGS = {
  studio:   { bg: 0x1a1209, ambientColor: 0xffffff, ambientInt: 1.5, dirColor: 0xffffff, dirInt: 2.0, fillInt: 0.6 },
  sunset:   { bg: 0x2a1005, ambientColor: 0xff9966, ambientInt: 1.4, dirColor: 0xff7733, dirInt: 2.2, fillInt: 0.3 },
  night:    { bg: 0x050510, ambientColor: 0x3355aa, ambientInt: 0.7, dirColor: 0x6688cc, dirInt: 1.0, fillInt: 0.2 },
  neon:     { bg: 0x080810, ambientColor: 0xff00ff, ambientInt: 0.9, dirColor: 0x00ffff, dirInt: 1.6, fillInt: 0.8 },
  forest:   { bg: 0x102010, ambientColor: 0x55aa55, ambientInt: 1.2, dirColor: 0x88cc88, dirInt: 1.8, fillInt: 0.5 },
  ice:      { bg: 0xddeeff, ambientColor: 0xaaddff, ambientInt: 1.4, dirColor: 0xffffff, dirInt: 2.0, fillInt: 0.8 },
  desert:   { bg: 0xc2955a, ambientColor: 0xffcc88, ambientInt: 1.3, dirColor: 0xffaa55, dirInt: 2.0, fillInt: 0.4 },
  beach:    { bg: 0x89cff0, ambientColor: 0xfff5e4, ambientInt: 1.6, dirColor: 0xffe4a0, dirInt: 2.2, fillInt: 0.7 },
  snow:     { bg: 0xe8f4f8, ambientColor: 0xd0eeff, ambientInt: 1.3, dirColor: 0xffffff, dirInt: 1.8, fillInt: 0.9 },
  court:    { bg: 0xcc6633, ambientColor: 0xffcc88, ambientInt: 1.4, dirColor: 0xffffff, dirInt: 2.0, fillInt: 0.5 },
  soccer:   { bg: 0x228b22, ambientColor: 0xaaddaa, ambientInt: 1.3, dirColor: 0xffffff, dirInt: 2.1, fillInt: 0.6 },
  city:     { bg: 0x2d3436, ambientColor: 0x8899aa, ambientInt: 1.1, dirColor: 0xccddee, dirInt: 1.5, fillInt: 0.4 },
  graffiti: { bg: 0x1a1a1a, ambientColor: 0xff6600, ambientInt: 1.0, dirColor: 0xffcc00, dirInt: 1.8, fillInt: 0.7 },
  luxury:   { bg: 0x0a0806, ambientColor: 0xffd700, ambientInt: 1.2, dirColor: 0xfff8dc, dirInt: 2.0, fillInt: 0.5 },
  space:    { bg: 0x000005, ambientColor: 0x334466, ambientInt: 0.5, dirColor: 0x8899ff, dirInt: 1.2, fillInt: 0.3 },
  rainy:    { bg: 0x3d5a70, ambientColor: 0x778899, ambientInt: 0.9, dirColor: 0xaabbcc, dirInt: 1.3, fillInt: 0.4 },
  racing:   { bg: 0x111111, ambientColor: 0xff2200, ambientInt: 1.0, dirColor: 0xffffff, dirInt: 2.2, fillInt: 0.6 },
};

/* ── Materials data ───────────────────────────────────────────── */
const MATERIALS = [
  { id: 'standard', label: 'Standard', desc: 'Synthetic upper', cost: 0, emoji: '👟' },
  { id: 'canvas',   label: 'Canvas',   desc: 'Breathable woven', cost: 0, emoji: '🟫' },
  { id: 'mesh',     label: 'Mesh',     desc: 'Ultra-lightweight', cost: 50, emoji: '🔲' },
  { id: 'leather',  label: 'Leather',  desc: 'Full-grain premium', cost: 350, emoji: '🟤' },
  { id: 'suede',    label: 'Suede',    desc: 'Buttery soft nap', cost: 280, emoji: '🧸' },
  { id: 'patent',   label: 'Patent',   desc: 'High-gloss lacquer', cost: 420, emoji: '✨' },
];

/* ── Font options ─────────────────────────────────────────────── */
const FONTS = [
  { id: 'serif',        label: 'Classic',   css: 'Georgia, serif' },
  { id: 'sans',         label: 'Modern',    css: "'DM Sans', sans-serif" },
  { id: 'mono',         label: 'Tech',      css: 'monospace' },
  { id: 'cursive',      label: 'Script',    css: 'cursive' },
  { id: 'impact',       label: 'Bold',      css: 'Impact, sans-serif' },
];

/* ── Lace styles ──────────────────────────────────────────────── */
const LACE_STYLES = [
  { id: 'standard',   label: 'Standard',   cost: 0 },
  { id: 'flat',       label: 'Flat',       cost: 0 },
  { id: 'oval',       label: 'Oval',       cost: 30 },
  { id: 'rope',       label: 'Rope',       cost: 50 },
  { id: 'elastic',    label: 'Elastic',    cost: 40 },
  { id: 'no-tie',     label: 'No-Tie',     cost: 80 },
];

/* ── Sole effects ─────────────────────────────────────────────── */
const SOLE_EFFECTS = [
  { id: 'standard',   label: 'Standard',        cost: 0 },
  { id: 'transparent',label: 'Transparent',     cost: 150 },
  { id: 'glow',       label: 'Glow-in-Dark',    cost: 220 },
  { id: 'colour',     label: 'Coloured',        cost: 60 },
];

/* ── Accessories ──────────────────────────────────────────────── */
const ACCESSORIES = [
  { id: 'charms',  label: 'Charms',  emoji: '🔮', cost: 45 },
  { id: 'chains',  label: 'Chains',  emoji: '⛓️', cost: 90 },
  { id: 'tags',    label: 'Tags',    emoji: '🏷️', cost: 35 },
  { id: 'buckles', label: 'Buckles', emoji: '🔗', cost: 65 },
];

/* ── Pattern generator ────────────────────────────────────────── */
const PATTERNS = [
  { id: 'none',        label: 'None',          emoji: '⬜', cost: 0   },
  // ── Original ───────────────────────────────────────────────
  { id: 'camo',        label: 'Camo',           emoji: '🪖', cost: 120 },
  { id: 'flames',      label: 'Flames',         emoji: '🔥', cost: 150 },
  { id: 'zebra',       label: 'Zebra',          emoji: '🦓', cost: 110 },
  { id: 'carbon',      label: 'Carbon Fibre',   emoji: '⚙️', cost: 180 },
  { id: 'polka',       label: 'Polka Dots',     emoji: '🔵', cost: 90  },
  // ── New patterns ───────────────────────────────────────────
  { id: 'stripes',     label: 'Diagonal Stripes', emoji: '🔶', cost: 80  },
  { id: 'checker',     label: 'Checkerboard',   emoji: '♟️', cost: 80  },
  { id: 'leopard',     label: 'Leopard',         emoji: '🐆', cost: 140 },
  { id: 'geo',         label: 'Geometric',       emoji: '🔷', cost: 120 },
  { id: 'circuit',     label: 'Circuit Board',   emoji: '🔌', cost: 200 },
  { id: 'waves',       label: 'Waves',           emoji: '🌊', cost: 100 },
  { id: 'houndstooth', label: 'Houndstooth',     emoji: '🔲', cost: 110 },
  { id: 'splatter',    label: 'Paint Splatter',  emoji: '🎨', cost: 130 },
  { id: 'galaxy',      label: 'Galaxy',          emoji: '🌌', cost: 180 },
  { id: 'brick',       label: 'Brick',           emoji: '🧱', cost: 90  },
  { id: 'tiedye',      label: 'Tie-Dye',         emoji: '🌈', cost: 150 },
  { id: 'snake',       label: 'Snakeskin',       emoji: '🐍', cost: 160 },
];
const PATTERN_TARGET = 'mesh'; // patterns apply to the Body material
const PATTERN_SOURCE_PARTS = ['mesh', 'stripes', 'patch']; // colour-tab parts that feed the pattern palette

/* ── Decals & stickers ────────────────────────────────────────── */
const DECALS = [
  { id: 'none',      label: 'None',      emoji: '∅'  },
  { id: 'star',      label: 'Star',      emoji: '⭐' },
  { id: 'lightning', label: 'Lightning', emoji: '⚡' },
  { id: 'flame',     label: 'Flame',     emoji: '🔥' },
  { id: 'skull',     label: 'Skull',     emoji: '💀' },
  { id: 'flower',    label: 'Flower',    emoji: '🌸' },
  { id: 'paw',       label: 'Paw',       emoji: '🐾' },
];
const DECAL_COST = 45;

/* ── Size data ────────────────────────────────────────────────── */
const SIZES = {
  uk:     ['UK 2','UK 3','UK 4','UK 5','UK 6','UK 7','UK 8','UK 9','UK 10','UK 11','UK 12','UK 13','UK 14'],
  mens:   ['US 3','US 4','US 5','US 6','US 7','US 8','US 9','US 10','US 11','US 12','US 13','US 14','US 15'],
  womens: ['US 4','US 5','US 6','US 7','US 8','US 9','US 10','US 11','US 12','US 13'],
  kids:   ['K 1','K 2','K 3','K 4','K 5','K 6','K 7','K 8','K 9','K 10','K 11','K 12','K 13'],
};

/* ── Swatch colors ────────────────────────────────────────────── */
const SWATCHES = [
  '#ffffff','#f5f5dc','#ffe0b2','#ffd7c0','#f4c2c2',
  '#ffcc00','#ff9900','#e74c3c','#8b0000','#c0392b',
  '#c8a97e','#a0752a','#8b6914','#5c4a1e','#3e2723',
  '#39ff14','#00b4d8','#4a90d9','#a71bda','#853b60',
  '#2d4a2d','#006994','#b76e79','#8e44ad','#000000',
];

const TEXT_COLORS = ['#ffffff','#000000','#ffcc00','#c8a97e','#e74c3c','#4a90d9','#39ff14','#ff00ff','#00b4d8','#ff9900'];

/* ── State ────────────────────────────────────────────────────── */
const materialMap  = {};
const colorState   = {};
let selectedPart   = null;
let selectedMat    = 'standard';
let customText     = '';
let customTextFont = 'serif';
let customTextColor= '#ffffff';
let customTextPlace= 'heel';
let selectedLaceStyle = 'standard';
let selectedSoleEffect = 'standard';
let selectedSoleColor  = '#1a1209';
let selectedLaceColor  = '#e8e8e8';
let selectedSizeSystem = 'uk';
let selectedSize       = 'UK 6';
let hasCustomImage     = false;
const accessoryState   = {};
ACCESSORIES.forEach(a => { accessoryState[a.id] = false; });
let selectedStitchColor = '#ffffff';
let selectedPattern     = 'none';
let selectedDecal       = 'none';
let selectedDecalPlace  = 'side';

PARTS.forEach(p => { colorState[p.id] = p.defaultColor; });

/* ── Load GLB ─────────────────────────────────────────────────── */
const dracoLoader = new DRACOLoader();
dracoLoader.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.5.6/');
const loader = new GLTFLoader();
loader.setDRACOLoader(dracoLoader);

loader.load(
  'includes/model.glb',
  gltf => {
    const model = gltf.scene;
    model.scale.set(2, 2, 2);
    model.position.set(0, -1, 0);
    scene.add(model);
    model.traverse(child => {
      if (!child.isMesh) return;
      const mats = Array.isArray(child.material) ? child.material : [child.material];
      mats.forEach(mat => {
        if (mat && mat.name) materialMap[mat.name.toLowerCase()] = mat;
      });
    });
    applyAllColors();
    buildPartsGrid();
    buildPresetGrid();
    document.getElementById('loading-screen').style.display = 'none';
  },
  undefined,
  err => {
    console.error('GLB Error:', err);
    const ls = document.getElementById('loading-screen');
    if (ls) ls.querySelector('.loading-text').textContent = 'Failed to load model.';
  }
);

/* ── Colour helpers ───────────────────────────────────────────── */
function applyColor(partId, hex) {
  colorState[partId] = hex;
  const mat = materialMap[partId];
  if (mat) {
    mat.color.set(new THREE.Color(hex));
    mat.needsUpdate = true;
  }
  // Keep an active pattern in sync with the colours it's drawn from
  if (selectedPattern && selectedPattern !== 'none' && PATTERN_SOURCE_PARTS.includes(partId)) {
    applyPattern(selectedPattern);
  }
}
function applyAllColors() { PARTS.forEach(p => applyColor(p.id, colorState[p.id])); }

/* ── Parts grid ───────────────────────────────────────────────── */
function buildPartsGrid() {
  const grid = document.getElementById('parts-grid');
  grid.innerHTML = '';
  PARTS.forEach(part => {
    const btn = document.createElement('button');
    btn.className    = 'part-btn';
    btn.dataset.part = part.id;
    btn.innerHTML    = `<span class="part-swatch" id="swatch-${part.id}" style="background-color:${colorState[part.id]};color-scheme:only light"></span>${part.label}`;
    btn.addEventListener('click', () => selectPart(part.id));
    grid.appendChild(btn);
  });
}

function selectPart(partId) {
  selectedPart = partId;
  const part = PARTS.find(p => p.id === partId);
  document.querySelectorAll('.part-btn').forEach(b => b.classList.toggle('active-part', b.dataset.part === partId));
  const area = document.getElementById('color-picker-area');
  area.style.display = 'block';
  document.getElementById('picker-label').textContent = part.label + ' colour';
  syncInputs(colorState[partId]);
  buildSwatches();
}

/* ── Swatches ─────────────────────────────────────────────────── */
function buildSwatches() {
  const wrap = document.getElementById('color-swatches');
  wrap.innerHTML = '';
  SWATCHES.forEach(hex => {
    const sw = document.createElement('button');
    sw.className = 'color-swatch';
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    const lc = hex.toLowerCase();
    sw.style.border = lc === '#000000' ? '1px solid #666' : lc === '#ffffff' ? '1px solid rgba(0,0,0,0.25)' : '1px solid transparent';
    sw.title = hex;
    if (hex === colorState[selectedPart]) sw.classList.add('active-swatch');
    sw.addEventListener('click', () => pickColor(hex));
    wrap.appendChild(sw);
  });
}

function pickColor(hex) {
  if (!selectedPart) return;
  applyColor(selectedPart, hex);
  syncInputs(hex);
  updatePartSwatch(selectedPart, hex);
  buildSwatches();
}

function syncInputs(hex) {
  document.getElementById('hex-input').value     = hex;
  document.getElementById('native-picker').value = hex;
}

function updatePartSwatch(partId, hex) {
  const sw = document.getElementById('swatch-' + partId);
  if (sw) {
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    const lc = hex.toLowerCase();
    sw.style.border = lc === '#000000' ? '1px solid #666' : lc === '#ffffff' ? '1px solid rgba(0,0,0,0.25)' : '1px solid transparent';
  }
}

document.getElementById('hex-input').addEventListener('input', e => {
  let v = e.target.value.trim();
  if (!v.startsWith('#')) v = '#' + v;
  if (/^#[0-9a-fA-F]{6}$/.test(v) && selectedPart) {
    applyColor(selectedPart, v);
    document.getElementById('native-picker').value = v;
    updatePartSwatch(selectedPart, v);
    buildSwatches();
  }
});

document.getElementById('native-picker').addEventListener('input', e => {
  const hex = e.target.value;
  if (selectedPart) {
    applyColor(selectedPart, hex);
    document.getElementById('hex-input').value = hex;
    updatePartSwatch(selectedPart, hex);
    buildSwatches();
  }
});

/* ── Presets ──────────────────────────────────────────────────── */
function buildPresetGrid() {
  const grid = document.getElementById('preset-grid');
  grid.innerHTML = '';
  PRESETS.forEach(preset => {
    const btn = document.createElement('button');
    btn.className   = 'preset-btn';
    btn.textContent = preset.label;
    btn.addEventListener('click', () => {
      Object.assign(colorState, preset.colors);
      applyAllColors();
      PARTS.forEach(p => updatePartSwatch(p.id, colorState[p.id]));
      if (selectedPart) { syncInputs(colorState[selectedPart]); buildSwatches(); }
    });
    grid.appendChild(btn);
  });
}

/* ── Material ─────────────────────────────────────────────────── */
function buildMaterialGrid() {
  const grid = document.getElementById('material-grid');
  grid.innerHTML = '';
  MATERIALS.forEach(mat => {
    const btn = document.createElement('button');
    btn.className = 'material-btn' + (mat.id === selectedMat ? ' active-material' : '');
    btn.dataset.mat = mat.id;
    btn.innerHTML = `
      <span class="mat-emoji">${mat.emoji}</span>
      <span class="mat-label">${mat.label}</span>
      <span class="mat-desc">${mat.desc}</span>
      <span class="mat-cost">${mat.cost === 0 ? 'Included' : '+R' + mat.cost}</span>
    `;
    btn.addEventListener('click', () => {
      selectedMat = mat.id;
      document.querySelectorAll('.material-btn').forEach(b => b.classList.remove('active-material'));
      btn.classList.add('active-material');
      document.getElementById('material-price-note').textContent = mat.cost > 0 ? `${mat.label} adds +R${mat.cost} to your order` : `${mat.label} — included in base price`;
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
  document.getElementById('material-price-note').textContent = 'Standard material — included in base price';
}

/* ── Text customization ───────────────────────────────────────── */
function buildFontGrid() {
  const grid = document.getElementById('font-grid');
  grid.innerHTML = '';
  FONTS.forEach(f => {
    const btn = document.createElement('button');
    btn.className = 'font-btn' + (f.id === customTextFont ? ' active-font' : '');
    btn.style.fontFamily = f.css;
    btn.textContent = f.label;
    btn.addEventListener('click', () => {
      customTextFont = f.id;
      document.querySelectorAll('.font-btn').forEach(b => b.classList.remove('active-font'));
      btn.classList.add('active-font');
      updateTextPreview();
    });
    grid.appendChild(btn);
  });
}

function buildTextColorSwatches() {
  const wrap = document.getElementById('text-color-swatches');
  wrap.innerHTML = '';
  TEXT_COLORS.forEach(hex => {
    const sw = document.createElement('button');
    sw.className = 'text-color-swatch' + (hex === customTextColor ? ' active-tc' : '');
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    sw.style.border = hex === '#ffffff' ? '1px solid rgba(0,0,0,0.3)' : hex === '#000000' ? '1px solid #555' : '1px solid transparent';
    sw.addEventListener('click', () => {
      customTextColor = hex;
      document.querySelectorAll('.text-color-swatch').forEach(b => b.classList.remove('active-tc'));
      sw.classList.add('active-tc');
      updateTextPreview();
    });
    wrap.appendChild(sw);
  });
}

function updateTextPreview() {
  const fontMap = { serif: 'Georgia,serif', sans: "'DM Sans',sans-serif", mono: 'monospace', cursive: 'cursive', impact: 'Impact,sans-serif' };
  const preview = document.getElementById('text-preview-content');
  const costNote = document.getElementById('text-cost-note');
  preview.style.fontFamily = fontMap[customTextFont] || 'serif';
  preview.style.color = customTextColor;
  preview.textContent = customText || 'Preview';
  costNote.style.display = customText ? 'block' : 'none';
  updateLivePrice();
}

document.getElementById('custom-text-input').addEventListener('input', e => {
  customText = e.target.value.trim();
  updateTextPreview();
});

document.getElementById('text-placement').addEventListener('change', e => {
  customTextPlace = e.target.value;
});

/* ── Lace builder ─────────────────────────────────────────────── */
function buildLaceStyleGrid() {
  const grid = document.getElementById('lace-style-grid');
  grid.innerHTML = '';
  LACE_STYLES.forEach(ls => {
    const btn = document.createElement('button');
    btn.className = 'lace-style-btn' + (ls.id === selectedLaceStyle ? ' active-lace-style' : '');
    btn.innerHTML = `<span>${ls.label}</span><span class="lace-cost">${ls.cost === 0 ? '' : '+R' + ls.cost}</span>`;
    btn.addEventListener('click', () => {
      selectedLaceStyle = ls.id;
      document.querySelectorAll('.lace-style-btn').forEach(b => b.classList.remove('active-lace-style'));
      btn.classList.add('active-lace-style');
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
}

function buildLaceColorSwatches() {
  const wrap = document.getElementById('lace-color-swatches');
  wrap.innerHTML = '';
  SWATCHES.forEach(hex => {
    const sw = document.createElement('button');
    sw.className = 'lace-color-swatch' + (hex === selectedLaceColor ? ' active-lc' : '');
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    sw.style.border = hex === '#000000' ? '1px solid #666' : hex === '#ffffff' ? '1px solid rgba(0,0,0,0.25)' : '1px solid transparent';
    sw.addEventListener('click', () => {
      selectedLaceColor = hex;
      document.querySelectorAll('.lace-color-swatch').forEach(b => b.classList.remove('active-lc'));
      sw.classList.add('active-lc');
      applyColor('laces', hex);
      updatePartSwatch('laces', hex);
    });
    wrap.appendChild(sw);
  });
}

/* ── Sole builder ─────────────────────────────────────────────── */
function buildSoleColorSwatches() {
  const wrap = document.getElementById('sole-color-swatches');
  wrap.innerHTML = '';
  SWATCHES.forEach(hex => {
    const sw = document.createElement('button');
    sw.className = 'sole-color-swatch' + (hex === selectedSoleColor ? ' active-sc' : '');
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    sw.style.border = hex === '#000000' ? '1px solid #666' : hex === '#ffffff' ? '1px solid rgba(0,0,0,0.25)' : '1px solid transparent';
    sw.addEventListener('click', () => {
      selectedSoleColor = hex;
      document.querySelectorAll('.sole-color-swatch').forEach(b => b.classList.remove('active-sc'));
      sw.classList.add('active-sc');
      applyColor('sole', hex);
      updatePartSwatch('sole', hex);
    });
    wrap.appendChild(sw);
  });
}

function buildSoleEffectsGrid() {
  const grid = document.getElementById('sole-effects-grid');
  grid.innerHTML = '';
  SOLE_EFFECTS.forEach(se => {
    const btn = document.createElement('button');
    btn.className = 'sole-effect-btn' + (se.id === selectedSoleEffect ? ' active-sole-effect' : '');
    btn.innerHTML = `<span>${se.label}</span><span class="sole-cost">${se.cost === 0 ? '' : '+R' + se.cost}</span>`;
    btn.addEventListener('click', () => {
      selectedSoleEffect = se.id;
      document.querySelectorAll('.sole-effect-btn').forEach(b => b.classList.remove('active-sole-effect'));
      btn.classList.add('active-sole-effect');
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
}

/* ── Accessories ──────────────────────────────────────────────── */
function buildAccessoryGrid() {
  const grid = document.getElementById('accessory-grid');
  grid.innerHTML = '';
  ACCESSORIES.forEach(acc => {
    const btn = document.createElement('button');
    btn.className = 'accessory-btn' + (accessoryState[acc.id] ? ' active-accessory' : '');
    btn.dataset.acc = acc.id;
    btn.innerHTML = `<span class="acc-emoji">${acc.emoji}</span><span class="acc-label">${acc.label}</span><span class="acc-cost">+R${acc.cost}</span>`;
    btn.addEventListener('click', () => {
      accessoryState[acc.id] = !accessoryState[acc.id];
      btn.classList.toggle('active-accessory', accessoryState[acc.id]);
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
}

/* ── Stitching colour ─────────────────────────────────────────── */
function buildStitchColorSwatches() {
  const wrap = document.getElementById('stitch-color-swatches');
  wrap.innerHTML = '';
  SWATCHES.forEach(hex => {
    const sw = document.createElement('button');
    sw.className = 'stitch-color-swatch' + (hex === selectedStitchColor ? ' active-stc' : '');
    sw.style.backgroundColor = hex;
    sw.style.colorScheme = 'only light';
    sw.style.border = hex === '#000000' ? '1px solid #666' : hex === '#ffffff' ? '1px solid rgba(0,0,0,0.25)' : '1px solid transparent';
    sw.addEventListener('click', () => {
      selectedStitchColor = hex;
      document.querySelectorAll('.stitch-color-swatch').forEach(b => b.classList.remove('active-stc'));
      sw.classList.add('active-stc');
      applyColor('stitching', hex);
      updatePartSwatch('stitching', hex);
    });
    wrap.appendChild(sw);
  });
}

/* ── Pattern generator ────────────────────────────────────────── */
function shadeColor(hex, percent) {
  const h = (hex || '#808080').replace('#', '');
  let r = parseInt(h.substring(0, 2), 16) || 0;
  let g = parseInt(h.substring(2, 4), 16) || 0;
  let b = parseInt(h.substring(4, 6), 16) || 0;
  const amt = Math.round(2.55 * percent);
  r = Math.min(255, Math.max(0, r + amt));
  g = Math.min(255, Math.max(0, g + amt));
  b = Math.min(255, Math.max(0, b + amt));
  return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

function generatePatternTexture(id) {
  const size = 512;
  const c = document.createElement('canvas');
  c.width = c.height = size;
  const ctx = c.getContext('2d');

  // Pull the palette straight from the Colour tab's current selections
  const base    = colorState['mesh']    || '#c8a97e'; // Body colour
  const accent  = colorState['stripes'] || '#ffffff'; // Stripes colour
  const accent2 = colorState['patch']   || '#8b6914'; // Patch colour

  switch (id) {
    case 'camo': {
      ctx.fillStyle = shadeColor(base, -15); ctx.fillRect(0, 0, size, size);
      const blobColors = [shadeColor(base, -35), shadeColor(base, 15), accent2];
      for (let i = 0; i < 40; i++) {
        ctx.fillStyle = blobColors[i % blobColors.length];
        const x = Math.random() * size, y = Math.random() * size, r = 20 + Math.random() * 45;
        ctx.beginPath();
        ctx.ellipse(x, y, r, r * 0.6, Math.random() * Math.PI, 0, Math.PI * 2);
        ctx.fill();
      }
      break;
    }
    case 'flames': {
      ctx.fillStyle = shadeColor(base, -75); ctx.fillRect(0, 0, size, size);
      const flameColors = [base, shadeColor(base, 25), accent];
      for (let i = 0; i < 26; i++) {
        const x = Math.random() * size, baseY = size - Math.random() * 40;
        const h = 80 + Math.random() * 160, w = 18 + Math.random() * 28;
        ctx.fillStyle = flameColors[i % flameColors.length];
        ctx.beginPath();
        ctx.moveTo(x, baseY);
        ctx.quadraticCurveTo(x - w, baseY - h * 0.5, x, baseY - h);
        ctx.quadraticCurveTo(x + w, baseY - h * 0.5, x, baseY);
        ctx.fill();
      }
      break;
    }
    case 'zebra': {
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      ctx.strokeStyle = accent; ctx.lineCap = 'round';
      for (let i = -size; i < size * 2; i += 26) {
        ctx.lineWidth = 10 + Math.random() * 10;
        ctx.beginPath();
        ctx.moveTo(i, 0);
        ctx.bezierCurveTo(i + 60, size * 0.33, i - 60, size * 0.66, i + 20, size);
        ctx.stroke();
      }
      break;
    }
    case 'carbon': {
      const dark1 = shadeColor(base, -55), dark2 = shadeColor(base, -75);
      ctx.fillStyle = dark2; ctx.fillRect(0, 0, size, size);
      const cell = 16;
      for (let y = 0; y < size; y += cell) {
        for (let x = 0; x < size; x += cell) {
          const odd = ((x / cell) + (y / cell)) % 2 === 0;
          ctx.fillStyle = odd ? dark1 : dark2;
          ctx.fillRect(x, y, cell, cell);
        }
      }
      ctx.strokeStyle = 'rgba(255,255,255,0.04)';
      for (let i = 0; i < size; i += cell) { ctx.beginPath(); ctx.moveTo(0, i); ctx.lineTo(size, i); ctx.stroke(); }
      break;
    }
    case 'polka': {
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = accent;
      const step = 64;
      for (let y = 0; y < size + step; y += step) {
        for (let x = 0; x < size + step; x += step) {
          const offset = (Math.floor(y / step) % 2) * (step / 2);
          ctx.beginPath();
          ctx.arc(x + offset, y, 14, 0, Math.PI * 2);
          ctx.fill();
        }
      }
      break;
    }

    case 'stripes': {
      // Diagonal stripes — adapts to base + accent colours
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      const sw = 36;
      ctx.lineWidth = sw * 0.6;
      ctx.lineCap   = 'square';
      ctx.strokeStyle = accent;
      for (let i = -size; i < size * 2; i += sw) {
        ctx.beginPath(); ctx.moveTo(i, 0); ctx.lineTo(i + size, size); ctx.stroke();
      }
      ctx.strokeStyle = accent2; ctx.lineWidth = 2;
      for (let i = -size + sw * 0.5; i < size * 2; i += sw) {
        ctx.beginPath(); ctx.moveTo(i, 0); ctx.lineTo(i + size, size); ctx.stroke();
      }
      break;
    }

    case 'checker': {
      const cell = 52;
      for (let y = 0; y < size; y += cell) {
        for (let x = 0; x < size; x += cell) {
          ctx.fillStyle = ((x / cell + y / cell) % 2 === 0) ? base : accent;
          ctx.fillRect(x, y, cell, cell);
        }
      }
      // Thin divider lines
      ctx.strokeStyle = accent2; ctx.lineWidth = 1.5;
      for (let i = 0; i < size; i += cell) {
        ctx.beginPath(); ctx.moveTo(0, i); ctx.lineTo(size, i); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(i, 0); ctx.lineTo(i, size); ctx.stroke();
      }
      break;
    }

    case 'leopard': {
      ctx.fillStyle = shadeColor(base, 10); ctx.fillRect(0, 0, size, size);
      for (let i = 0; i < 32; i++) {
        const x = Math.random() * size, y = Math.random() * size;
        const rx = 14 + Math.random() * 22, ry = 9 + Math.random() * 14;
        const angle = Math.random() * Math.PI;
        // Dark outer ring — broken arc segments give leopard look
        ctx.strokeStyle = shadeColor(accent2, -55); ctx.lineWidth = 6 + Math.random() * 4;
        for (let a = 0; a < Math.PI * 2; a += Math.PI * 0.4) {
          if (Math.random() > 0.35) {
            ctx.beginPath();
            ctx.ellipse(x, y, rx, ry, angle, a, a + Math.PI * 0.32);
            ctx.stroke();
          }
        }
        // Inner light patch
        ctx.fillStyle = shadeColor(base, 28);
        ctx.beginPath(); ctx.ellipse(x, y, rx * 0.45, ry * 0.45, angle, 0, Math.PI * 2); ctx.fill();
      }
      break;
    }

    case 'geo': {
      // Equilateral triangle grid
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      const s = 58, h = s * 0.866;
      const cols = [accent, accent2, shadeColor(base, -22)];
      for (let row = 0; row < Math.ceil(size / h) + 1; row++) {
        const y = row * h;
        const offset = (row % 2) * s * 0.5;
        for (let col = -1; col < Math.ceil(size / s) + 1; col++) {
          const x = col * s + offset;
          ctx.fillStyle = cols[(row + col + 5) % 3];
          // Up triangle
          ctx.beginPath(); ctx.moveTo(x, y + h); ctx.lineTo(x + s, y + h); ctx.lineTo(x + s * 0.5, y); ctx.closePath(); ctx.fill();
          ctx.strokeStyle = shadeColor(base, -40); ctx.lineWidth = 1.2; ctx.stroke();
        }
      }
      break;
    }

    case 'circuit': {
      const dark = shadeColor(base, -68);
      ctx.fillStyle = dark; ctx.fillRect(0, 0, size, size);
      const grid = 32;
      // Traces
      ctx.strokeStyle = accent; ctx.lineWidth = 2;
      for (let y = grid; y < size; y += grid) {
        for (let x = grid; x < size; x += grid) {
          if (Math.random() > 0.38) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + grid, y); ctx.stroke(); }
          if (Math.random() > 0.38) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x, y + grid); ctx.stroke(); }
          if (Math.random() > 0.38) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + grid, y + grid); ctx.stroke(); }
        }
      }
      // Pads and chips
      for (let y = grid; y < size; y += grid) {
        for (let x = grid; x < size; x += grid) {
          if (Math.random() > 0.55) {
            ctx.fillStyle = accent2;
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2); ctx.fill();
          }
          if (Math.random() > 0.82) {
            ctx.fillStyle = shadeColor(accent2, -20);
            ctx.fillRect(x - 8, y - 8, 16, 16);
            ctx.fillStyle = dark; ctx.fillRect(x - 5, y - 5, 10, 10);
          }
        }
      }
      break;
    }

    case 'waves': {
      ctx.fillStyle = shadeColor(base, -22); ctx.fillRect(0, 0, size, size);
      const waveColors = [base, accent, shadeColor(accent, -18), accent2, shadeColor(base, 20)];
      const layers = 9;
      for (let l = 0; l < layers; l++) {
        ctx.fillStyle = waveColors[l % waveColors.length];
        ctx.globalAlpha = 0.72;
        const offsetY = (size / layers) * l + size / layers * 0.6;
        ctx.beginPath(); ctx.moveTo(0, size);
        for (let x = 0; x <= size; x += 3) {
          const y = offsetY + Math.sin((x / size) * Math.PI * 5 + l * 1.1) * 20;
          x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.lineTo(size, size); ctx.lineTo(0, size); ctx.closePath(); ctx.fill();
      }
      ctx.globalAlpha = 1;
      break;
    }

    case 'houndstooth': {
      const cell = 44;
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = accent;
      for (let row = 0; row < Math.ceil(size / cell) + 2; row++) {
        for (let col = 0; col < Math.ceil(size / cell) + 2; col++) {
          const ox = (row % 2) * (cell / 2);
          const x = col * cell + ox - cell, y = row * cell - cell;
          const h = cell * 0.5, q = cell * 0.25;
          // Classic houndstooth tooth — two L-shapes
          ctx.beginPath();
          ctx.moveTo(x, y); ctx.lineTo(x + h, y); ctx.lineTo(x + h, y + h);
          ctx.lineTo(x + cell, y + h); ctx.lineTo(x + cell, y + cell);
          ctx.lineTo(x + h, y + cell); ctx.closePath(); ctx.fill();
          ctx.fillStyle = accent2;
          ctx.beginPath();
          ctx.moveTo(x + h, y); ctx.lineTo(x + cell, y); ctx.lineTo(x + cell, y + h);
          ctx.lineTo(x + h, y + h); ctx.closePath(); ctx.fill();
          ctx.fillStyle = accent;
        }
      }
      break;
    }

    case 'splatter': {
      ctx.fillStyle = shadeColor(base, -8); ctx.fillRect(0, 0, size, size);
      const cols = [accent, accent2, shadeColor(base, 35), shadeColor(accent2, 25), shadeColor(accent, -15)];
      for (let i = 0; i < 65; i++) {
        const x = Math.random() * size, y = Math.random() * size;
        const r = 3 + Math.random() * 28;
        ctx.fillStyle = cols[i % cols.length];
        ctx.globalAlpha = 0.65 + Math.random() * 0.35;
        ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
        if (r > 14) {
          const drops = 2 + Math.floor(Math.random() * 4);
          for (let d = 0; d < drops; d++) {
            const angle = Math.random() * Math.PI * 2;
            const dist  = r + 5 + Math.random() * 18;
            const sr    = 2 + Math.random() * 7;
            ctx.beginPath(); ctx.arc(x + Math.cos(angle)*dist, y + Math.sin(angle)*dist, sr, 0, Math.PI*2); ctx.fill();
          }
        }
      }
      ctx.globalAlpha = 1;
      break;
    }

    case 'galaxy': {
      const dark = shadeColor(base, -82);
      ctx.fillStyle = dark; ctx.fillRect(0, 0, size, size);
      // Nebula glow bands
      [{x:.45,y:.4,r:.5,c:accent2},{x:.65,y:.6,r:.35,c:accent}].forEach(nb=>{
        const g = ctx.createRadialGradient(size*nb.x,size*nb.y,0,size*nb.x,size*nb.y,size*nb.r);
        g.addColorStop(0, nb.c + '55'); g.addColorStop(0.5, nb.c + '22'); g.addColorStop(1,'transparent');
        ctx.fillStyle = g; ctx.fillRect(0,0,size,size);
      });
      // Stars
      for (let i = 0; i < 220; i++) {
        const x = Math.random()*size, y = Math.random()*size, r = Math.random()*2.2;
        ctx.fillStyle = i%8===0 ? accent : i%11===0 ? accent2 : '#ffffff';
        ctx.globalAlpha = 0.3 + Math.random() * 0.7;
        ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI*2); ctx.fill();
      }
      // Larger sparkles
      for (let i = 0; i < 8; i++) {
        const x = Math.random()*size, y = Math.random()*size;
        ctx.fillStyle = '#ffffff'; ctx.globalAlpha = 0.9;
        ctx.fillRect(x-1, y-6, 2, 12); ctx.fillRect(x-6, y-1, 12, 2);
      }
      ctx.globalAlpha = 1;
      break;
    }

    case 'brick': {
      const bW = 68, bH = 32, gap = 4;
      ctx.fillStyle = shadeColor(base, -35); ctx.fillRect(0, 0, size, size); // mortar
      for (let row = 0; row < Math.ceil(size / bH) + 1; row++) {
        const offset = (row % 2) * (bW / 2);
        for (let col = -1; col < Math.ceil(size / bW) + 1; col++) {
          const x = col * bW + offset, y = row * bH;
          ctx.fillStyle = (row + col) % 3 === 0 ? shadeColor(base, 12)
                        : (row + col) % 3 === 1 ? base
                        : shadeColor(base, -12);
          ctx.fillRect(x + gap, y + gap, bW - gap * 2, bH - gap * 2);
          // Subtle highlight on top edge
          ctx.fillStyle = 'rgba(255,255,255,0.07)';
          ctx.fillRect(x + gap, y + gap, bW - gap * 2, 3);
        }
      }
      break;
    }

    case 'tiedye': {
      ctx.fillStyle = base; ctx.fillRect(0, 0, size, size);
      const centres = [
        [size*0.3, size*0.3], [size*0.7, size*0.3],
        [size*0.5, size*0.62],[size*0.2, size*0.72],[size*0.8, size*0.72],
      ];
      const ringCols = [accent, accent2, shadeColor(base, 32), shadeColor(accent,-22), shadeColor(accent2,18), base];
      centres.forEach(([cx,cy]) => {
        for (let r = 90; r > 2; r -= 11) {
          ctx.strokeStyle = ringCols[Math.floor(r/11) % ringCols.length];
          ctx.lineWidth = 9; ctx.globalAlpha = 0.65;
          ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2); ctx.stroke();
        }
      });
      ctx.globalAlpha = 1;
      break;
    }

    case 'snake': {
      // Snakeskin — interlocking scale shapes
      ctx.fillStyle = shadeColor(base, -18); ctx.fillRect(0, 0, size, size);
      const scaleW = 30, scaleH = 20;
      for (let row = 0; row < Math.ceil(size / scaleH) + 1; row++) {
        const offset = (row % 2) * (scaleW / 2);
        for (let col = -1; col < Math.ceil(size / scaleW) + 1; col++) {
          const x = col * scaleW + offset, y = row * scaleH;
          ctx.fillStyle = (row+col)%2===0 ? shadeColor(base,14) : base;
          ctx.beginPath();
          ctx.moveTo(x + scaleW/2, y);
          ctx.quadraticCurveTo(x + scaleW, y, x + scaleW, y + scaleH);
          ctx.quadraticCurveTo(x + scaleW, y + scaleH, x + scaleW/2, y + scaleH);
          ctx.quadraticCurveTo(x, y + scaleH, x, y + scaleH);
          ctx.quadraticCurveTo(x, y, x + scaleW/2, y);
          ctx.closePath(); ctx.fill();
          ctx.strokeStyle = shadeColor(base,-40); ctx.lineWidth = 0.8; ctx.stroke();
          // Shimmer highlight
          ctx.fillStyle = 'rgba(255,255,255,0.06)';
          ctx.beginPath();
          ctx.ellipse(x + scaleW*0.35, y + scaleH*0.28, scaleW*0.22, scaleH*0.18, -0.3, 0, Math.PI*2);
          ctx.fill();
        }
      }
      break;
    }

        default:
      return null;
  }
  const tex = new THREE.CanvasTexture(c);
  tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
  tex.repeat.set(2, 2);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

function applyPattern(id) {
  selectedPattern = id;
  const mat = materialMap[PATTERN_TARGET];
  if (!mat) return;
  if (id === 'none') {
    mat.map = null;
    mat.color.set(new THREE.Color(colorState[PATTERN_TARGET]));
  } else {
    mat.map = generatePatternTexture(id);
    mat.color.set(0xffffff);
  }
  mat.needsUpdate = true;
}

function buildPatternGrid() {
  const grid = document.getElementById('pattern-grid');
  grid.innerHTML = '';
  PATTERNS.forEach(p => {
    const btn = document.createElement('button');
    btn.className = 'pattern-btn' + (p.id === selectedPattern ? ' active-pattern' : '');
    btn.innerHTML = `<span class="pattern-emoji">${p.emoji}</span><span class="pattern-label">${p.label}</span>` + (p.cost ? `<span class="pattern-cost">+R${p.cost}</span>` : '');
    btn.addEventListener('click', () => {
      applyPattern(p.id);
      document.querySelectorAll('.pattern-btn').forEach(b => b.classList.remove('active-pattern'));
      btn.classList.add('active-pattern');
      const note = document.getElementById('pattern-cost-note');
      if (p.cost > 0) { note.style.display = 'block'; note.textContent = `+R${p.cost} for ${p.label} pattern`; }
      else note.style.display = 'none';
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
}

/* ── Decals & stickers ────────────────────────────────────────── */
function buildDecalGrid() {
  const grid = document.getElementById('decal-grid');
  grid.innerHTML = '';
  DECALS.forEach(d => {
    const btn = document.createElement('button');
    btn.className = 'decal-btn' + (d.id === selectedDecal ? ' active-decal' : '');
    btn.innerHTML = `<span class="decal-emoji">${d.emoji}</span><span class="decal-label">${d.label}</span>`;
    btn.addEventListener('click', () => {
      selectedDecal = d.id;
      document.querySelectorAll('.decal-btn').forEach(b => b.classList.remove('active-decal'));
      btn.classList.add('active-decal');
      updateDecalPreview();
      updateLivePrice();
    });
    grid.appendChild(btn);
  });
}

function updateDecalPreview() {
  const box  = document.getElementById('decal-preview-box');
  const content = document.getElementById('decal-preview-content');
  const note = document.getElementById('decal-cost-note');
  const d = DECALS.find(x => x.id === selectedDecal);
  if (!d || d.id === 'none') {
    box.style.display = 'none';
    note.style.display = 'none';
  } else {
    box.style.display = 'flex';
    content.textContent = d.emoji;
    note.style.display = 'block';
  }
}

document.getElementById('decal-placement').addEventListener('change', e => {
  selectedDecalPlace = e.target.value;
});

/* ── Size selector ────────────────────────────────────────────── */
function buildSizeGrid() {
  const grid = document.getElementById('size-grid');
  grid.innerHTML = '';
  const sizes = SIZES[selectedSizeSystem];
  sizes.forEach(sz => {
    const btn = document.createElement('button');
    btn.className = 'size-btn' + (sz === selectedSize ? ' active-size' : '');
    btn.textContent = sz.replace('UK ','').replace('US ','').replace('K ','');
    btn.addEventListener('click', () => {
      selectedSize = sz;
      document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active-size'));
      btn.classList.add('active-size');
      document.getElementById('selected-size-display').textContent = sz;
    });
    grid.appendChild(btn);
  });
}

document.querySelectorAll('.size-sys-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    selectedSizeSystem = btn.dataset.sys;
    document.querySelectorAll('.size-sys-btn').forEach(b => b.classList.remove('active-sys'));
    btn.classList.add('active-sys');
    // Reset selected size to first of new system
    selectedSize = SIZES[selectedSizeSystem][selectedSizeSystem === 'uk' ? 4 : 0];
    buildSizeGrid();
    document.getElementById('selected-size-display').textContent = selectedSize;
  });
});

/* ── Environment ──────────────────────────────────────────────── */
document.getElementById('env-btns').addEventListener('click', e => {
  const btn = e.target.closest('.env-btn');
  if (!btn) return;
  const env = ENV_SETTINGS[btn.dataset.env];
  if (!env) return;
  scene.background = new THREE.Color(env.bg);
  ambient.color.set(new THREE.Color(env.ambientColor));
  ambient.intensity     = env.ambientInt;
  directional.color.set(new THREE.Color(env.dirColor));
  directional.intensity = env.dirInt;
  fillLight.intensity   = env.fillInt;
  document.querySelectorAll('.env-btn').forEach(b => b.classList.remove('active-env'));
  btn.classList.add('active-env');
});

/* ── Accordion ────────────────────────────────────────────────── */
document.querySelectorAll('.ac-header').forEach(header => {
  header.addEventListener('click', () => {
    const section = header.closest('.ac-section');
    const body    = section.querySelector('.ac-body');
    const arrow   = header.querySelector('.ac-arrow');
    const isOpen  = section.classList.contains('open');
    section.classList.toggle('open', !isOpen);
    body.style.display  = isOpen ? 'none' : 'block';
    arrow.textContent   = isOpen ? '▼' : '▲';
  });
});

/* ── Live price calculator ────────────────────────────────────── */
function calcTotal() {
  let total = 1200;
  const mat = MATERIALS.find(m => m.id === selectedMat);
  if (mat) total += mat.cost;
  if (customText) total += 80;
  const ls = LACE_STYLES.find(l => l.id === selectedLaceStyle);
  if (ls) total += ls.cost;
  const se = SOLE_EFFECTS.find(s => s.id === selectedSoleEffect);
  if (se) total += se.cost;
  ACCESSORIES.forEach(a => { if (accessoryState[a.id]) total += a.cost; });
  const pat = PATTERNS.find(p => p.id === selectedPattern);
  if (pat) total += pat.cost;
  if (selectedDecal !== 'none') total += DECAL_COST;
  if (hasCustomImage) total += 120;
  return total;
}

function formatRand(n) { return 'R' + n.toLocaleString('en-ZA'); }

function updateLivePrice() {
  const total = calcTotal();
  const fmt = formatRand(total);
  document.getElementById('live-total-display').textContent = fmt;
  document.getElementById('canvas-price-badge').textContent = fmt;

  // Breakdown rows
  const mat = MATERIALS.find(m => m.id === selectedMat);
  const ls  = LACE_STYLES.find(l => l.id === selectedLaceStyle);
  const se  = SOLE_EFFECTS.find(s => s.id === selectedSoleEffect);

  const pbMat = document.getElementById('pb-material');
  if (mat && mat.cost > 0) {
    pbMat.style.display = '';
    document.getElementById('pb-mat-label').textContent = mat.label + ' material';
    document.getElementById('pb-mat-cost').textContent = '+R' + mat.cost;
  } else { pbMat.style.display = 'none'; }

  document.getElementById('pb-text').style.display = customText ? '' : 'none';

  const pbLace = document.getElementById('pb-lace');
  if (ls && ls.cost > 0) {
    pbLace.style.display = '';
    document.getElementById('pb-lace-label').textContent = ls.label + ' laces';
    document.getElementById('pb-lace-cost').textContent = '+R' + ls.cost;
  } else { pbLace.style.display = 'none'; }

  const pbSole = document.getElementById('pb-sole');
  if (se && se.cost > 0) {
    pbSole.style.display = '';
    document.getElementById('pb-sole-label').textContent = se.label + ' sole';
    document.getElementById('pb-sole-cost').textContent = '+R' + se.cost;
  } else { pbSole.style.display = 'none'; }

  let accTotal = 0;
  ACCESSORIES.forEach(a => { if (accessoryState[a.id]) accTotal += a.cost; });
  const pbAcc = document.getElementById('pb-accessories');
  if (accTotal > 0) { pbAcc.style.display = ''; document.getElementById('pb-acc-cost').textContent = '+R' + accTotal; }
  else { pbAcc.style.display = 'none'; }

  const pat = PATTERNS.find(p => p.id === selectedPattern);
  const pbPattern = document.getElementById('pb-pattern');
  if (pat && pat.cost > 0) {
    pbPattern.style.display = '';
    document.getElementById('pb-pattern-label').textContent = pat.label + ' pattern';
    document.getElementById('pb-pattern-cost').textContent = '+R' + pat.cost;
  } else { pbPattern.style.display = 'none'; }

  const pbDecal = document.getElementById('pb-decal');
  if (selectedDecal !== 'none') { pbDecal.style.display = ''; document.getElementById('pb-decal-cost').textContent = '+R' + DECAL_COST; }
  else { pbDecal.style.display = 'none'; }

  document.getElementById('pb-image').style.display = hasCustomImage ? '' : 'none';
}

/* ── Price breakdown toggle ───────────────────────────────────── */
document.getElementById('price-toggle').addEventListener('click', () => {
  const bd = document.getElementById('price-breakdown');
  const arrow = document.getElementById('toggle-arrow');
  const open = bd.style.display === 'none';
  bd.style.display = open ? '' : 'none';
  arrow.textContent = open ? '▲' : '▼';
});

/* ── Reset ────────────────────────────────────────────────────── */
document.getElementById('reset-btn').addEventListener('click', () => {
  PARTS.forEach(p => { colorState[p.id] = p.defaultColor; });
  applyAllColors();
  PARTS.forEach(p => updatePartSwatch(p.id, colorState[p.id]));
  if (selectedPart) { syncInputs(colorState[selectedPart]); buildSwatches(); }
  selectedMat = 'standard';
  customText  = '';
  customTextFont = 'serif';
  customTextColor = '#ffffff';
  selectedLaceStyle = 'standard';
  selectedSoleEffect = 'standard';
  selectedSoleColor = '#1a1209';
  selectedLaceColor = '#e8e8e8';
  hasCustomImage = false;
  ACCESSORIES.forEach(a => { accessoryState[a.id] = false; });
  selectedStitchColor = '#ffffff';
  selectedPattern = 'none';
  applyPattern('none');
  document.getElementById('pattern-cost-note').style.display = 'none';
  selectedDecal = 'none';
  selectedDecalPlace = 'side';
  document.getElementById('decal-placement').value = 'side';

  document.getElementById('custom-text-input').value = '';
  updateTextPreview();
  buildMaterialGrid();
  buildLaceStyleGrid();
  buildSoleEffectsGrid();
  buildFontGrid();
  buildTextColorSwatches();
  buildLaceColorSwatches();
  buildSoleColorSwatches();
  buildAccessoryGrid();
  buildStitchColorSwatches();
  buildPatternGrid();
  buildDecalGrid();
  updateDecalPreview();
  updateLivePrice();

  const s = ENV_SETTINGS.studio;
  scene.background = new THREE.Color(s.bg);
  ambient.color.set(new THREE.Color(s.ambientColor));
  ambient.intensity     = s.ambientInt;
  directional.color.set(new THREE.Color(s.dirColor));
  directional.intensity = s.dirInt;
  fillLight.intensity   = s.fillInt;
  document.querySelectorAll('.env-btn').forEach(b => b.classList.toggle('active-env', b.dataset.env === 'studio'));
});

/* ── Animate ──────────────────────────────────────────────────── */
(function animate() { requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); })();

window.addEventListener('resize', () => {
  const w = container.clientWidth, h = container.clientHeight;
  renderer.setSize(w, h);
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
});

/* ══════════════════════════════════════════════════════════════
   BUY MODAL LOGIC
   ══════════════════════════════════════════════════════════════ */

function buildDesignPreview() {
  const wrap = document.getElementById('dp-swatches');
  if (!wrap) return;
  wrap.innerHTML = '';
  PARTS.forEach(part => {
    const item = document.createElement('div');
    item.className = 'dp-swatch-item';
    item.innerHTML = `
      <div class="dp-swatch-dot" style="background:${colorState[part.id]};color-scheme:only light"></div>
      <div class="dp-swatch-label">${part.label}</div>
    `;
    wrap.appendChild(item);
  });

  // Extra info
  const extra = document.getElementById('dp-extra');
  let extras = [];
  const mat = MATERIALS.find(m => m.id === selectedMat);
  if (mat && mat.id !== 'standard') extras.push(`${mat.label} upper`);
  if (customText) extras.push(`"${customText}" on ${customTextPlace}`);
  const ls = LACE_STYLES.find(l => l.id === selectedLaceStyle);
  if (ls && ls.id !== 'standard') extras.push(`${ls.label} laces`);
  const se = SOLE_EFFECTS.find(s => s.id === selectedSoleEffect);
  if (se && se.id !== 'standard') extras.push(`${se.label} sole`);
  const activeAcc = ACCESSORIES.filter(a => accessoryState[a.id]).map(a => a.label);
  if (activeAcc.length) extras.push(activeAcc.join(' + '));
  if (selectedPattern !== 'none') { const p = PATTERNS.find(x => x.id === selectedPattern); if (p) extras.push(`${p.label} pattern`); }
  if (selectedDecal !== 'none') { const d = DECALS.find(x => x.id === selectedDecal); if (d) extras.push(`${d.label} decal on ${selectedDecalPlace}`); }
  extra.innerHTML = extras.map(e => `<span class="dp-extra-chip">${e}</span>`).join('');
}

function updateModalPrice() {
  const total = calcTotal();
  const courier = ['courier','home'].includes(document.getElementById('modal-collection').value) ? 85 : 0;
  const grandTotal = total + courier;
  document.getElementById('modal-total').textContent = 'R' + grandTotal.toLocaleString('en-ZA', {minimumFractionDigits:2,maximumFractionDigits:2});

  // Material row
  const mat = MATERIALS.find(m => m.id === selectedMat);
  if (mat && mat.cost > 0) {
    document.getElementById('modal-material-row').style.display = '';
    document.getElementById('modal-mat-label').textContent = mat.label + ' material';
    document.getElementById('modal-mat-cost').textContent = '+R' + mat.cost.toFixed(2);
  } else { document.getElementById('modal-material-row').style.display = 'none'; }

  document.getElementById('modal-text-row').style.display = customText ? '' : 'none';

  const ls = LACE_STYLES.find(l => l.id === selectedLaceStyle);
  if (ls && ls.cost > 0) {
    document.getElementById('modal-lace-row').style.display = '';
    document.getElementById('modal-lace-label').textContent = ls.label + ' laces';
    document.getElementById('modal-lace-cost').textContent = '+R' + ls.cost.toFixed(2);
  } else { document.getElementById('modal-lace-row').style.display = 'none'; }

  const se = SOLE_EFFECTS.find(s => s.id === selectedSoleEffect);
  if (se && se.cost > 0) {
    document.getElementById('modal-sole-row').style.display = '';
    document.getElementById('modal-sole-label').textContent = se.label + ' sole';
    document.getElementById('modal-sole-cost').textContent = '+R' + se.cost.toFixed(2);
  } else { document.getElementById('modal-sole-row').style.display = 'none'; }

  let accTotal = 0;
  ACCESSORIES.forEach(a => { if (accessoryState[a.id]) accTotal += a.cost; });
  if (accTotal > 0) {
    document.getElementById('modal-acc-row').style.display = '';
    document.getElementById('modal-acc-cost').textContent = '+R' + accTotal.toFixed(2);
  } else { document.getElementById('modal-acc-row').style.display = 'none'; }

  const pat = PATTERNS.find(p => p.id === selectedPattern);
  if (pat && pat.cost > 0) {
    document.getElementById('modal-pattern-row').style.display = '';
    document.getElementById('modal-pattern-label').textContent = pat.label + ' pattern';
    document.getElementById('modal-pattern-cost').textContent = '+R' + pat.cost.toFixed(2);
  } else { document.getElementById('modal-pattern-row').style.display = 'none'; }

  if (selectedDecal !== 'none') {
    document.getElementById('modal-decal-row').style.display = '';
    document.getElementById('modal-decal-cost').textContent = '+R' + DECAL_COST.toFixed(2);
  } else { document.getElementById('modal-decal-row').style.display = 'none'; }
}

document.getElementById('open-buy-modal').addEventListener('click', () => {
  // Build price breakdown so PHP can reconstruct the exact total
  const priceBreakdown = [];
  priceBreakdown.push({ label: 'Custom shoe (1 pair)', cost: 1200 });
  const matObj = MATERIALS.find(m => m.id === selectedMat);
  if (matObj && matObj.cost > 0) priceBreakdown.push({ label: matObj.label + ' upper', cost: matObj.cost });
  if (customText) priceBreakdown.push({ label: 'Custom text', cost: 80 });
  const lsObj = LACE_STYLES.find(l => l.id === selectedLaceStyle);
  if (lsObj && lsObj.cost > 0) priceBreakdown.push({ label: lsObj.label + ' laces', cost: lsObj.cost });
  const seObj = SOLE_EFFECTS.find(s => s.id === selectedSoleEffect);
  if (seObj && seObj.cost > 0) priceBreakdown.push({ label: seObj.label + ' sole', cost: seObj.cost });
  ACCESSORIES.forEach(a => { if (accessoryState[a.id]) priceBreakdown.push({ label: a.label, cost: a.cost }); });
  const patObj = PATTERNS.find(p => p.id === selectedPattern);
  if (patObj && patObj.cost > 0) priceBreakdown.push({ label: patObj.label + ' pattern', cost: patObj.cost });
  if (selectedDecal !== 'none') {
    const dObj = DECALS.find(d => d.id === selectedDecal);
    priceBreakdown.push({ label: (dObj ? dObj.label : 'Decal') + ' / sticker', cost: DECAL_COST });
  }
  if (hasCustomImage) priceBreakdown.push({ label: 'Custom image upload', cost: 120 });

  const customData = {
    colors: { ...colorState },
    material: selectedMat,
    text: { content: customText, font: customTextFont, color: customTextColor, placement: customTextPlace },
    laces: { style: selectedLaceStyle, color: selectedLaceColor },
    sole:  { effect: selectedSoleEffect, color: selectedSoleColor },
    accessories: { ...accessoryState },
    pattern: selectedPattern,
    decal: { id: selectedDecal, placement: selectedDecalPlace },
    size: selectedSize,
    hasImage: hasCustomImage,
    price_breakdown: priceBreakdown,
    total_price: calcTotal()
  };
  document.getElementById('customization_data_field').value = JSON.stringify(customData);
  document.getElementById('shoe_size_field').value = selectedSize;
  buildDesignPreview();
  updateModalPrice();
  document.getElementById('buy-modal-overlay').classList.add('active');
  document.body.style.overflow = 'hidden';
});

document.getElementById('close-buy-modal').addEventListener('click', closeModal);
document.getElementById('buy-modal-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});

function closeModal() {
  document.getElementById('buy-modal-overlay').classList.remove('active');
  document.body.style.overflow = '';
}

document.getElementById('modal-collection').addEventListener('change', function() {
  const hasFee = ['courier','home'].includes(this.value);
  document.getElementById('courier-row').style.display  = hasFee ? '' : 'none';
  document.getElementById('address-fg').style.display   = hasFee ? '' : 'none';
  document.getElementById('delivery-address').required  = hasFee;
  updateModalPrice();
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── Init all panels ──────────────────────────────────────────── */
buildMaterialGrid();
buildFontGrid();
buildTextColorSwatches();
buildLaceStyleGrid();
buildLaceColorSwatches();
buildSoleColorSwatches();
buildSoleEffectsGrid();
buildAccessoryGrid();
buildStitchColorSwatches();
buildPatternGrid();
buildDecalGrid();
updateDecalPreview();
buildSizeGrid();
updateLivePrice();

</script>

<style>
/* ── PANEL SHELL ──
   This panel is a fixed "3D studio" dark-glass surface (like the nav/hero/
   canvas), independent of the site's light/dark toggle — its child controls
   use translucent white-on-dark styling that needs a dark backdrop. */
.customizer-panel{
  background:linear-gradient(180deg,#1E140B,#140C06);
  border:1px solid rgba(200,169,126,0.14);
  border-radius:16px;
  padding:0.35rem 0.85rem 0.85rem;
}
.customizer-panel .cp-card{background:rgba(255,255,255,0.04);border:1px solid rgba(200,169,126,0.14)}
.customizer-panel .reset-btn{color:rgba(255,255,255,0.65);border-color:rgba(200,169,126,0.2)}
.customizer-panel .reset-btn:hover{color:#D4561A;border-color:#D4561A}

/* ── ACCORDION ── */
.accordion{display:flex;flex-direction:column;gap:0;overflow-y:auto;flex:1;scrollbar-width:thin;scrollbar-color:rgba(200,169,126,0.25) transparent}
.ac-section{border-bottom:1px solid rgba(200,169,126,0.12)}
.ac-section:last-child{border-bottom:none}
.ac-header{display:flex;align-items:center;gap:10px;padding:11px 14px;cursor:pointer;user-select:none;transition:background .18s}
.ac-header:hover{background:rgba(200,169,126,0.07)}
.ac-section.open>.ac-header{background:rgba(200,169,126,0.1)}
.ac-icon{font-size:15px;line-height:1;flex-shrink:0}
.ac-title{flex:1;font-size:13px;font-weight:600;color:#fff;letter-spacing:.2px}
.ac-arrow{font-size:9px;color:rgba(200,169,126,0.6);transition:color .18s}
.ac-section.open .ac-arrow{color:var(--tan,#c8a97e)}
.ac-body{padding:12px 14px 14px;background:rgba(0,0,0,0.15)}
.ac-label{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:rgba(200,169,126,0.55);margin-bottom:8px;font-weight:500}
.ac-divider{height:1px;background:rgba(200,169,126,0.1);margin:12px 0}

/* ── PARTS GRID ── */
.parts-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-bottom:10px}
.part-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:8px 4px;border:1px solid rgba(200,169,126,0.2);border-radius:8px;background:rgba(200,169,126,0.06);color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all .2s}
.part-btn:hover{border-color:rgba(200,169,126,0.5);background:rgba(200,169,126,0.12);color:#fff}
.part-btn.active-part{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.part-swatch{width:22px;height:22px;border-radius:50%;border:2px solid rgba(255,255,255,0.2);flex-shrink:0;color-scheme:only light}
.color-picker-area{margin-top:10px;padding-top:10px;border-top:1px solid rgba(200,169,126,0.15)}
.color-picker-label{font-size:11px;color:rgba(255,255,255,0.5);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.color-swatches{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;margin-bottom:8px;color-scheme:only light}
.color-swatch{width:100%;aspect-ratio:1;border-radius:5px;border:2px solid transparent;cursor:pointer;transition:transform .15s,border-color .15s;-webkit-appearance:none;appearance:none;color-scheme:only light}
.color-swatch:hover{transform:scale(1.15)}
.color-swatch.active-swatch{border-color:#fff;transform:scale(1.1)}
.color-hex-row{display:flex;gap:6px;align-items:center}
.color-hex-input{flex:1;background:rgba(255,255,255,0.07);border:1px solid rgba(200,169,126,0.25);border-radius:6px;color:#fff;padding:5px 8px;font-size:12px;font-family:monospace}
.native-picker{width:34px;height:34px;border:none;border-radius:6px;cursor:pointer;padding:2px;background:rgba(255,255,255,0.1)}
.preset-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.preset-btn{padding:7px 4px;border:1px solid rgba(200,169,126,0.2);border-radius:8px;background:rgba(200,169,126,0.06);color:rgba(255,255,255,0.75);font-size:11px;cursor:pointer;transition:all .2s}
.preset-btn:hover{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.15);color:#fff}

/* ── MATERIAL ── */
.material-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:10px}
.material-btn{display:flex;flex-direction:column;align-items:flex-start;gap:2px;padding:10px 10px;border:1px solid rgba(200,169,126,0.2);border-radius:9px;background:rgba(200,169,126,0.05);cursor:pointer;transition:all .2s;text-align:left}
.material-btn:hover{border-color:rgba(200,169,126,0.45);background:rgba(200,169,126,0.1)}
.material-btn.active-material{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18)}
.mat-emoji{font-size:16px}
.mat-label{font-size:12px;font-weight:600;color:#fff}
.mat-desc{font-size:10px;color:rgba(255,255,255,0.45)}
.mat-cost{font-size:10px;color:var(--tan,#c8a97e);font-weight:500;margin-top:2px}
.material-price-note{font-size:11px;color:rgba(200,169,126,0.6);padding:6px 0;font-style:italic}

/* ── TEXT ── */
.cp-card label,.ac-body label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:rgba(200,169,126,0.6);margin-bottom:5px}
.cp-card input[type=text],.cp-card select,.ac-body input[type=text],.ac-body select{width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(200,169,126,0.2);border-radius:7px;color:#fff;padding:8px 10px;font-size:13px;font-family:inherit;outline:none;transition:border-color .18s;margin-bottom:10px}
.cp-card input:focus,.cp-card select:focus,.ac-body input:focus,.ac-body select:focus{border-color:var(--tan,#c8a97e)}
.cp-card select option,.ac-body select option{background:#1c120a}
.font-grid{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.font-btn{padding:6px 10px;border:1px solid rgba(200,169,126,0.2);border-radius:6px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.65);font-size:12px;cursor:pointer;transition:all .2s}
.font-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.font-btn.active-font{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.15);color:#fff}
.text-color-swatches{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.text-color-swatch{width:24px;height:24px;border-radius:5px;border:2px solid transparent;cursor:pointer;transition:transform .15s;color-scheme:only light}
.text-color-swatch:hover{transform:scale(1.15)}
.text-color-swatch.active-tc{border-color:#fff;transform:scale(1.1)}
.text-preview-box{background:rgba(255,255,255,0.08);border:1px dashed rgba(200,169,126,0.3);border-radius:8px;padding:12px;text-align:center;margin-top:8px;min-height:44px;display:flex;align-items:center;justify-content:center}
.add-cost-note{font-size:10px;color:var(--tan,#c8a97e);text-align:center;margin-top:4px}
.fg{margin-bottom:8px}

/* ── LACES ── */
.lace-style-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px}
.lace-style-btn{display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 4px;border:1px solid rgba(200,169,126,0.2);border-radius:8px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all .2s}
.lace-style-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.lace-style-btn.active-lace-style{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.lace-cost{font-size:9px;color:var(--tan,#c8a97e)}
.lace-color-swatches{display:grid;grid-template-columns:repeat(5,1fr);gap:5px}
.lace-color-swatch{width:100%;aspect-ratio:1;border-radius:5px;border:2px solid transparent;cursor:pointer;transition:transform .15s;color-scheme:only light}
.lace-color-swatch:hover{transform:scale(1.1)}
.lace-color-swatch.active-lc{border-color:#fff;transform:scale(1.1)}

/* ── SOLE ── */
.sole-color-swatches{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;margin-bottom:10px}
.sole-color-swatch{width:100%;aspect-ratio:1;border-radius:5px;border:2px solid transparent;cursor:pointer;transition:transform .15s;color-scheme:only light}
.sole-color-swatch:hover{transform:scale(1.1)}
.sole-color-swatch.active-sc{border-color:#fff;transform:scale(1.1)}
.sole-effects-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.sole-effect-btn{display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px;border:1px solid rgba(200,169,126,0.2);border-radius:8px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all .2s}
.sole-effect-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.sole-effect-btn.active-sole-effect{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.sole-cost{font-size:9px;color:var(--tan,#c8a97e)}

/* ── ACCESSORIES ── */
.accessory-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.accessory-btn{display:flex;flex-direction:column;align-items:center;gap:3px;padding:10px 6px;border:1px solid rgba(200,169,126,0.2);border-radius:9px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all .2s}
.accessory-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.accessory-btn.active-accessory{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.acc-emoji{font-size:18px}
.acc-label{font-size:11px;font-weight:500}
.acc-cost{font-size:9px;color:var(--tan,#c8a97e)}

/* ── STITCHING ── */
.stitch-color-swatches{display:grid;grid-template-columns:repeat(5,1fr);gap:5px}
.stitch-color-swatch{width:100%;aspect-ratio:1;border-radius:5px;border:2px solid transparent;cursor:pointer;transition:transform .15s;color-scheme:only light}
.stitch-color-swatch:hover{transform:scale(1.1)}
.stitch-color-swatch.active-stc{border-color:#fff;transform:scale(1.1)}

/* ── PATTERN GENERATOR ── */
.pattern-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.pattern-btn{display:flex;flex-direction:column;align-items:center;gap:2px;padding:9px 6px;border:1px solid rgba(200,169,126,0.2);border-radius:9px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all .2s}
.pattern-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.pattern-btn.active-pattern{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.pattern-emoji{font-size:18px}
.pattern-label{font-size:11px;font-weight:500}
.pattern-cost{font-size:9px;color:var(--tan,#c8a97e)}

/* ── DECALS & STICKERS ── */
.decal-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.decal-btn{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:1px solid rgba(200,169,126,0.2);border-radius:8px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.7);font-size:10px;cursor:pointer;transition:all .2s}
.decal-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.decal-btn.active-decal{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff}
.decal-emoji{font-size:18px}
.decal-label{font-size:10px}

/* ── SIZE ── */
.size-system-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:10px}
.size-sys-btn{padding:4px 10px;border:1px solid rgba(200,169,126,0.2);border-radius:20px;background:transparent;color:rgba(255,255,255,0.5);font-size:11px;cursor:pointer;transition:all .2s}
.size-sys-btn.active-sys{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.15);color:var(--tan,#c8a97e)}
.size-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;margin-bottom:8px}
.size-btn{padding:8px 4px;border:1px solid rgba(200,169,126,0.2);border-radius:6px;background:rgba(200,169,126,0.05);color:rgba(255,255,255,0.65);font-size:12px;cursor:pointer;transition:all .2s}
.size-btn:hover{border-color:rgba(200,169,126,0.45);color:#fff}
.size-btn.active-size{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:#fff;font-weight:600}
.size-note{font-size:11px;color:rgba(200,169,126,0.65);text-align:center;margin-top:4px}

/* ── ENVIRONMENT ── */
.env-btns{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.env-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:8px 4px;border:1px solid rgba(200,169,126,0.25);border-radius:10px;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.8);font-size:10px;font-weight:500;cursor:pointer;transition:all .2s;line-height:1.2;text-align:center;white-space:nowrap;overflow:hidden;font-family:inherit}
.env-btn .env-icon{font-size:18px;line-height:1;display:block}
.env-btn .env-label{display:block;font-size:10px;color:rgba(255,255,255,0.75);overflow:hidden;text-overflow:ellipsis;max-width:100%}
.env-btn:hover{border-color:rgba(200,169,126,0.6);background:rgba(200,169,126,0.12);color:#fff}
.env-btn:hover .env-label{color:#fff}
.env-btn.active-env{border-color:var(--tan,#c8a97e);background:rgba(200,169,126,0.18);color:var(--tan,#c8a97e)}
.env-btn.active-env .env-label{color:var(--tan,#c8a97e)}

/* ── LIVE PRICE ── */
.live-price-bar{background:rgba(200,169,126,0.1);border:1px solid rgba(200,169,126,0.2);border-radius:9px;padding:10px 12px;margin-bottom:10px;cursor:pointer}
.price-breakdown-toggle{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#fff}
.price-breakdown-toggle span:first-child{flex:1}
.price-breakdown-toggle span:nth-child(2){color:var(--tan,#c8a97e);font-size:15px;font-family:'Playfair Display',serif}
.toggle-arrow{font-size:10px;color:rgba(200,169,126,0.6)}
.price-breakdown{margin-top:8px;padding-top:8px;border-top:1px solid rgba(200,169,126,0.15)}
.pb-row{display:flex;justify-content:space-between;font-size:11px;color:rgba(255,255,255,0.55);padding:2px 0}
.pb-row span:last-child{color:var(--tan,#c8a97e)}

/* ── CANVAS PRICE BADGE ── */
.canvas-price-badge{position:absolute;top:12px;right:12px;background:rgba(26,18,9,0.85);border:1px solid rgba(200,169,126,0.4);border-radius:20px;padding:4px 12px;font-size:13px;font-weight:600;color:var(--tan,#c8a97e);font-family:'Playfair Display',serif;pointer-events:none;backdrop-filter:blur(4px)}

/* ── ACTIONS ── */
.actions-card{margin-top:auto}
.cta-buy-btn{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,#D4561A,#c8a97e);color:#fff;font-size:14px;font-weight:600;cursor:pointer;letter-spacing:.3px;transition:all .2s;font-family:inherit}
.cta-buy-btn:hover{transform:translateY(-1px);box-shadow:0 4px 20px rgba(212,86,26,0.4)}

/* ── MODAL ── */
.buy-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;padding:1rem}
.buy-modal-overlay.active{opacity:1;pointer-events:all}
.buy-modal{background:#1c120a;border:1px solid rgba(200,169,126,0.2);border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:1.75rem;position:relative;transform:translateY(16px);transition:transform .25s;scrollbar-width:thin;scrollbar-color:rgba(200,169,126,0.3) transparent}
.buy-modal-overlay.active .buy-modal{transform:translateY(0)}
.modal-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,0.08);border:none;color:rgba(255,255,255,0.5);width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.modal-close:hover{background:rgba(212,86,26,0.2);color:#fff}
.modal-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem}
.modal-icon{font-size:2rem;line-height:1}
.modal-title{font-family:'Playfair Display',serif;font-size:1.3rem;color:#fff;line-height:1.2}
.modal-sub{font-size:12px;color:rgba(200,169,126,0.7);margin-top:3px}
.design-preview{background:rgba(200,169,126,0.06);border:1px solid rgba(200,169,126,0.15);border-radius:10px;padding:.875rem 1rem;margin-bottom:1.25rem}
.dp-label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:rgba(200,169,126,0.6);margin-bottom:.625rem}
.dp-swatches{display:flex;flex-wrap:wrap;gap:.625rem;margin-bottom:.5rem}
.dp-swatch-item{display:flex;align-items:center;gap:5px}
.dp-swatch-dot{width:18px;height:18px;border-radius:50%;border:1.5px solid rgba(255,255,255,0.2);flex-shrink:0;color-scheme:only light}
.dp-swatch-label{font-size:10px;color:rgba(255,255,255,0.5)}
.dp-extra{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.dp-extra-chip{font-size:10px;background:rgba(200,169,126,0.15);border:1px solid rgba(200,169,126,0.3);border-radius:12px;padding:2px 8px;color:var(--tan,#c8a97e)}
.modal-section{margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(200,169,126,0.1)}
.modal-section:last-of-type{border-bottom:none;margin-bottom:0;padding-bottom:0}
.modal-section-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:rgba(200,169,126,0.7);margin-bottom:.75rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.buy-modal .fg{margin-bottom:.75rem}
.buy-modal .fg:last-child{margin-bottom:0}
.buy-modal label{display:block;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:rgba(200,169,126,0.6);margin-bottom:4px}
.buy-modal input,.buy-modal select,.buy-modal textarea{width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(200,169,126,0.2);border-radius:7px;color:#fff;padding:9px 11px;font-size:13px;font-family:inherit;outline:none;transition:border-color .18s}
.buy-modal input:focus,.buy-modal select:focus,.buy-modal textarea:focus{border-color:var(--tan,#c8a97e)}
.buy-modal textarea{resize:vertical;min-height:70px}
.buy-modal select option{background:#1c120a}
.price-summary{background:rgba(200,169,126,0.07);border:1px solid rgba(200,169,126,0.15);border-radius:10px;padding:1rem;margin:1.25rem 0}
.price-row{display:flex;justify-content:space-between;font-size:13px;color:rgba(255,255,255,0.7);padding:3px 0}
.price-total{font-size:15px;font-weight:700;color:var(--tan,#c8a97e);border-top:1px solid rgba(200,169,126,0.2);margin-top:6px;padding-top:8px}
.price-note{font-size:10px;color:rgba(200,169,126,0.45);margin-top:8px;line-height:1.5}
.modal-submit-btn{width:100%;padding:14px;border:none;border-radius:10px;background:linear-gradient(135deg,#D4561A,#c8a97e);color:#fff;font-size:15px;font-weight:600;cursor:pointer;letter-spacing:.3px;transition:all .2s;font-family:inherit}
.modal-submit-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(212,86,26,0.45)}
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
</style>