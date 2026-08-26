import { useContext, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { CustomizationContext, SIZE_SYSTEMS, MATERIALS, LACE_STYLES, SOLE_OPTIONS, ENVIRONMENTS, TEXT_FONTS, PRICING, calcPrice } from '../context/CustomizationContex.jsx';
import CustomColorPicker from './CustomColorPicker.jsx';

// ── Shared style tokens ───────────────────────────────────
const glass   = { background: 'rgba(245,199,238,0.13)', backdropFilter: 'blur(10px)', WebkitBackdropFilter: 'blur(10px)', borderRadius: 10, border: '1px solid rgba(255,255,255,0.12)' };
const heading = { fontFamily: "'Noto Sans Mono', monospace", color: '#efdeed', margin: '0 0 10px', fontSize: 13, textTransform: 'uppercase', letterSpacing: 1 };
const pill    = (active) => ({ padding: '5px 12px', borderRadius: 20, border: '1px solid rgba(255,255,255,0.25)', background: active ? 'rgba(255,255,255,0.22)' : 'rgba(255,255,255,0.07)', color: '#efdeed', cursor: 'pointer', fontSize: 12, fontFamily: "'Work Sans',sans-serif", transition: 'background .15s' });
const cardBtn = (active) => ({ ...pill(active), display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 2, padding: '8px 12px', borderRadius: 8, width: '100%', textAlign: 'left' });
const input   = { width: '100%', boxSizing: 'border-box', padding: '7px 10px', borderRadius: 8, border: '1px solid rgba(255,255,255,0.2)', background: 'rgba(0,0,0,0.35)', color: '#efdeed', fontSize: 13, fontFamily: "'Work Sans',sans-serif", outline: 'none' };
const row     = { display: 'flex', flexWrap: 'wrap', gap: 6 };
const label   = { fontSize: 11, color: 'rgba(239,222,237,0.55)', fontFamily: "'Work Sans',sans-serif" };
const priceTag = (extra) => ({ fontSize: extra ? 11 : 10, color: extra ? '#f5a623' : 'rgba(239,222,237,0.4)', marginLeft: 4 });

const TABS = [
  { key: 'colors',   icon: '🎨', label: 'Colours' },
  { key: 'size',     icon: '📏', label: 'Size' },
  { key: 'material', icon: '🧵', label: 'Material' },
  { key: 'laces',    icon: '👟', label: 'Laces' },
  { key: 'sole',     icon: '⬛', label: 'Sole' },
  { key: 'text',     icon: '✍️', label: 'Text' },
  { key: 'image',    icon: '🖼', label: 'Logo' },
  { key: 'env',      icon: '🌍', label: 'Scene' },
];

const PLACEMENTS = ['patch','band','mesh','caps'];

export default function CustomizationInterface() {
  const {
    isOpenModal, setIsOpenModal,
    customization, setCustomization,
    activeTab, setActiveTab,
    environment, setEnvironment,
  } = useContext(CustomizationContext);

  const fileRef = useRef();
  const price   = calcPrice(customization);

  const set = (key, val) => setCustomization((p) => ({ ...p, [key]: val }));
  const setTextProp  = (k, v) => setCustomization((p) => ({ ...p, customText:  { ...p.customText,  [k]: v } }));
  const setImageProp = (k, v) => setCustomization((p) => ({ ...p, customImage: { ...p.customImage, [k]: v } }));

  const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => setImageProp('dataUrl', ev.target.result);
    reader.readAsDataURL(file);
    setImageProp('fileName', file.name);
  };

  const heightSideBar  = window.innerHeight > 909 ? 'auto' : '80vh';
  const overflowY      = window.innerHeight > 909 ? 'hidden' : 'scroll';

  return (
    <div style={{ position: 'absolute', top: 0, right: '1%', padding: 12, width: 310 }}>

      {/* ── Header ── */}
      <div style={{ ...glass, padding: '12px 16px', marginBottom: 12, background: 'rgba(255,0,229,0.13)', border: '1px solid rgba(255,255,255,0.18)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span style={{ color: '#efdeed', fontSize: 13, fontFamily: "'Work Sans',sans-serif" }}>Shoe Configurator</span>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <span style={{ fontSize: 13, fontWeight: 600, color: '#f5a623', fontFamily: "'Noto Sans Mono',monospace" }}>R{price.toLocaleString()}</span>
            {isOpenModal && (
              <button onClick={() => setIsOpenModal(false)} style={{ background: 'none', border: '1px solid rgba(255,255,255,0.2)', color: '#efdeed', cursor: 'pointer', borderRadius: 6, padding: '2px 8px', fontSize: 12 }}>▲</button>
            )}
          </div>
        </div>
        <div style={{ fontFamily: "'Noto Sans Mono',monospace", fontSize: 16, color: '#efdeed', marginTop: 6 }}>
          {customization.layerName ? `Editing: ${customization.layerName}` : 'Click a layer to start editing'}
        </div>
      </div>

      <AnimatePresence>
        {isOpenModal && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: heightSideBar, overflowY }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.3, ease: [0.04, 0.62, 0.23, 0.98] }}
          >

            {/* ── Tab bar ── */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginBottom: 10 }}>
              {TABS.map(t => (
                <button key={t.key} onClick={() => setActiveTab(t.key)} style={{ ...pill(activeTab === t.key), padding: '4px 9px', fontSize: 11 }}>
                  {t.icon} {t.label}
                </button>
              ))}
            </div>

            {/* ══════════════════════════════════════════
                TAB: COLOURS
            ══════════════════════════════════════════ */}
            {activeTab === 'colors' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Layer colour</h3>
                {customization.layerName
                  ? <CustomColorPicker />
                  : <p style={{ ...label, textAlign: 'center', padding: '20px 0' }}>Click any part of the shoe on the left to select a layer</p>
                }
                <div style={{ marginTop: 12 }}>
                  <h3 style={{ ...heading, marginBottom: 6 }}>All layers</h3>
                  <div style={row}>
                    {Object.entries(customization.layerColor).map(([layer, color]) => (
                      <button key={layer} onClick={() => { setCustomization(p => ({ ...p, layerName: layer })); setIsOpenModal(true); }}
                        style={{ ...pill(customization.layerName === layer), display: 'flex', alignItems: 'center', gap: 5 }}>
                        <span style={{ width: 12, height: 12, borderRadius: '50%', background: color, border: '1px solid rgba(255,255,255,0.3)', display: 'inline-block' }} />
                        {layer}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: SIZE
            ══════════════════════════════════════════ */}
            {activeTab === 'size' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Size system</h3>
                <div style={{ ...row, marginBottom: 12 }}>
                  {Object.entries(SIZE_SYSTEMS).map(([key, sys]) => (
                    <button key={key} onClick={() => { set('sizeSystem', key); set('shoeSize', sys.sizes[0]); }} style={pill(customization.sizeSystem === key)}>
                      {sys.label}
                    </button>
                  ))}
                </div>
                <h3 style={heading}>Shoe size</h3>
                <div style={row}>
                  {SIZE_SYSTEMS[customization.sizeSystem].sizes.map(s => (
                    <button key={s} onClick={() => set('shoeSize', s)} style={pill(customization.shoeSize === s)}>
                      {s}
                    </button>
                  ))}
                </div>
                <div style={{ marginTop: 14, padding: '10px 12px', background: 'rgba(255,255,255,0.07)', borderRadius: 8 }}>
                  <span style={label}>Selected: </span>
                  <span style={{ color: '#efdeed', fontSize: 14, fontWeight: 600 }}>{customization.shoeSize}</span>
                  <span style={{ ...label, marginLeft: 8 }}>({SIZE_SYSTEMS[customization.sizeSystem].label})</span>
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: MATERIAL
            ══════════════════════════════════════════ */}
            {activeTab === 'material' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Upper material</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {MATERIALS.map(m => (
                    <button key={m.key} onClick={() => set('material', m.key)} style={cardBtn(customization.material === m.key)}>
                      <span style={{ color: '#efdeed', fontSize: 13 }}>
                        {m.label}
                        {PRICING.materials[m.key] > 0 && <span style={priceTag(true)}>+R{PRICING.materials[m.key]}</span>}
                        {PRICING.materials[m.key] === 0 && <span style={priceTag(false)}>included</span>}
                      </span>
                      <span style={label}>{m.desc}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: LACES
            ══════════════════════════════════════════ */}
            {activeTab === 'laces' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Lace colour</h3>
                <div style={{ marginBottom: 14 }}>
                  <button onClick={() => setCustomization(p => ({ ...p, layerName: 'laces' }))}
                    style={{ ...pill(customization.layerName === 'laces'), marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span style={{ width: 14, height: 14, borderRadius: '50%', background: customization.layerColor.laces, border: '1px solid rgba(255,255,255,0.3)', display: 'inline-block' }} />
                    Edit lace colour
                  </button>
                  {customization.layerName === 'laces' && <CustomColorPicker />}
                </div>
                <h3 style={heading}>Lace style</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {LACE_STYLES.map(l => (
                    <button key={l.key} onClick={() => set('laceStyle', l.key)} style={cardBtn(customization.laceStyle === l.key)}>
                      <span style={{ color: '#efdeed', fontSize: 13 }}>
                        {l.label}
                        {PRICING.laceStyles[l.key] > 0 && <span style={priceTag(true)}>+R{PRICING.laceStyles[l.key]}</span>}
                        {PRICING.laceStyles[l.key] === 0 && <span style={priceTag(false)}>included</span>}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: SOLE
            ══════════════════════════════════════════ */}
            {activeTab === 'sole' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Sole colour</h3>
                <div style={{ marginBottom: 14 }}>
                  <button onClick={() => setCustomization(p => ({ ...p, layerName: 'sole' }))}
                    style={{ ...pill(customization.layerName === 'sole'), marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span style={{ width: 14, height: 14, borderRadius: '50%', background: customization.layerColor.sole, border: '1px solid rgba(255,255,255,0.3)', display: 'inline-block' }} />
                    Edit sole colour
                  </button>
                  {customization.layerName === 'sole' && <CustomColorPicker />}
                </div>
                <h3 style={heading}>Sole type</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {SOLE_OPTIONS.map(s => (
                    <button key={s.key} onClick={() => set('soleOption', s.key)} style={cardBtn(customization.soleOption === s.key)}>
                      <span style={{ color: '#efdeed', fontSize: 13 }}>
                        {s.label}
                        {PRICING.sole[s.key] > 0 && <span style={priceTag(true)}>+R{PRICING.sole[s.key]}</span>}
                        {PRICING.sole[s.key] === 0 && <span style={priceTag(false)}>included</span>}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: CUSTOM TEXT
            ══════════════════════════════════════════ */}
            {activeTab === 'text' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Custom text <span style={priceTag(true)}>+R{PRICING.customText} if used</span></h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <div>
                    <div style={label}>Name, initials or message</div>
                    <input style={{ ...input, marginTop: 4 }} maxLength={30} placeholder="e.g. Sipho / S.D. / Just Do It"
                      value={customization.customText.text}
                      onChange={e => setTextProp('text', e.target.value)} />
                  </div>
                  <div>
                    <div style={label}>Font</div>
                    <div style={{ ...row, marginTop: 4 }}>
                      {TEXT_FONTS.map(f => (
                        <button key={f.key} onClick={() => setTextProp('font', f.key)} style={{ ...pill(customization.customText.font === f.key), fontFamily: f.css }}>
                          {f.label}
                        </button>
                      ))}
                    </div>
                  </div>
                  <div>
                    <div style={label}>Text colour</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 4 }}>
                      <input type="color" value={customization.customText.color}
                        onChange={e => setTextProp('color', e.target.value)}
                        style={{ width: 36, height: 36, border: 'none', borderRadius: 6, cursor: 'pointer', background: 'none' }} />
                      <span style={{ ...label, fontSize: 12 }}>{customization.customText.color}</span>
                    </div>
                  </div>
                  <div>
                    <div style={label}>Placement</div>
                    <div style={{ ...row, marginTop: 4 }}>
                      {PLACEMENTS.map(p => (
                        <button key={p} onClick={() => setTextProp('placement', p)} style={pill(customization.customText.placement === p)}>{p}</button>
                      ))}
                    </div>
                  </div>
                  {customization.customText.text && (
                    <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(0,0,0,0.35)', textAlign: 'center', fontSize: 18, color: customization.customText.color, fontFamily: TEXT_FONTS.find(f => f.key === customization.customText.font)?.css }}>
                      {customization.customText.text}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: CUSTOM IMAGE / LOGO
            ══════════════════════════════════════════ */}
            {activeTab === 'image' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Custom logo / image <span style={priceTag(true)}>+R{PRICING.customImage} if used</span></h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <button onClick={() => fileRef.current.click()}
                    style={{ ...pill(false), textAlign: 'center', padding: '10px', borderStyle: 'dashed' }}>
                    {customization.customImage.dataUrl ? `✓ ${customization.customImage.fileName}` : '+ Upload logo or image'}
                  </button>
                  <input ref={fileRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleImageUpload} />

                  {customization.customImage.dataUrl && (
                    <>
                      <div style={{ borderRadius: 8, overflow: 'hidden', background: 'rgba(0,0,0,0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 8 }}>
                        <img src={customization.customImage.dataUrl} alt="logo preview" style={{ maxWidth: '100%', maxHeight: 100, objectFit: 'contain', borderRadius: 6 }} />
                      </div>
                      <div>
                        <div style={label}>Placement</div>
                        <div style={{ ...row, marginTop: 4 }}>
                          {PLACEMENTS.map(p => (
                            <button key={p} onClick={() => setImageProp('placement', p)} style={pill(customization.customImage.placement === p)}>{p}</button>
                          ))}
                        </div>
                      </div>
                      <button onClick={() => setCustomization(p => ({ ...p, customImage: { dataUrl: null, placement: 'patch', fileName: '' } }))}
                        style={{ ...pill(false), color: '#ff8080' }}>
                        Remove image
                      </button>
                    </>
                  )}
                </div>
              </div>
            )}

            {/* ══════════════════════════════════════════
                TAB: ENVIRONMENT / SCENE
            ══════════════════════════════════════════ */}
            {activeTab === 'env' && (
              <div style={{ ...glass, padding: 16 }}>
                <h3 style={heading}>Scene environment</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {ENVIRONMENTS.map(env => (
                    <button key={env.key} onClick={() => setEnvironment(env)} style={{ ...cardBtn(environment.key === env.key) }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ width: 18, height: 18, borderRadius: 4, background: env.bg || '#2a2a3a', border: '1px solid rgba(255,255,255,0.2)', display: 'inline-block', flexShrink: 0 }} />
                        <span style={{ color: '#efdeed', fontSize: 13 }}>{env.label}</span>
                      </div>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* ── Price summary ── */}
            <div style={{ ...glass, padding: '12px 16px', marginTop: 10, background: 'rgba(0,0,0,0.3)' }}>
              <h3 style={{ ...heading, marginBottom: 8 }}>Price breakdown</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                {[
                  ['Base shoe',    `R${PRICING.basePrice.toLocaleString()}`],
                  ...(PRICING.materials[customization.material] > 0  ? [[`${MATERIALS.find(m=>m.key===customization.material)?.label} material`, `+R${PRICING.materials[customization.material]}`]] : []),
                  ...(PRICING.laceStyles[customization.laceStyle] > 0 ? [[`${LACE_STYLES.find(l=>l.key===customization.laceStyle)?.label} laces`,    `+R${PRICING.laceStyles[customization.laceStyle]}`]] : []),
                  ...(PRICING.sole[customization.soleOption] > 0      ? [[`${SOLE_OPTIONS.find(s=>s.key===customization.soleOption)?.label} sole`,      `+R${PRICING.sole[customization.soleOption]}`]] : []),
                  ...(customization.customText?.text?.trim()           ? [['Custom text',   `+R${PRICING.customText}`]] : []),
                  ...(customization.customImage?.dataUrl               ? [['Custom logo',   `+R${PRICING.customImage}`]] : []),
                ].map(([desc, amt]) => (
                  <div key={desc} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: 'rgba(239,222,237,0.75)' }}>
                    <span>{desc}</span><span>{amt}</span>
                  </div>
                ))}
                <div style={{ borderTop: '1px solid rgba(255,255,255,0.15)', marginTop: 6, paddingTop: 6, display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: '#efdeed', fontWeight: 600, fontSize: 14 }}>Total</span>
                  <span style={{ color: '#f5a623', fontWeight: 700, fontSize: 16, fontFamily: "'Noto Sans Mono',monospace" }}>R{price.toLocaleString()}</span>
                </div>
              </div>
            </div>

          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}