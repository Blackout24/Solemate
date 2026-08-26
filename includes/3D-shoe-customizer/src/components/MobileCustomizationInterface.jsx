import { useContext, useRef, useEffect } from 'react';
import { CustomizationContext, SIZE_SYSTEMS, MATERIALS, LACE_STYLES, SOLE_OPTIONS, ENVIRONMENTS, TEXT_FONTS, PRICING, calcPrice } from '../context/CustomizationContex.jsx';
import { Carousel } from 'react-responsive-carousel';
import 'react-responsive-carousel/lib/styles/carousel.min.css';
import CustomColorPicker from './CustomColorPicker.jsx';

const glass = { background: 'rgba(245,199,238,0.15)', backdropFilter: 'blur(10px)', WebkitBackdropFilter: 'blur(10px)' };
const h3s   = { fontFamily: "'Noto Sans Mono',monospace", color: '#efdeed', fontSize: 12, textTransform: 'uppercase', letterSpacing: 1, margin: '0 0 8px' };
const pill  = (a) => ({ padding: '4px 10px', borderRadius: 20, border: '1px solid rgba(255,255,255,0.25)', background: a ? 'rgba(255,255,255,0.22)' : 'rgba(255,255,255,0.07)', color: '#efdeed', cursor: 'pointer', fontSize: 11, fontFamily: "'Work Sans',sans-serif" });
const inp   = { width: '100%', boxSizing: 'border-box', padding: '6px 9px', borderRadius: 7, border: '1px solid rgba(255,255,255,0.2)', background: 'rgba(0,0,0,0.35)', color: '#efdeed', fontSize: 12, fontFamily: "'Work Sans',sans-serif", outline: 'none', marginTop: 4 };
const rows  = { display: 'flex', flexWrap: 'wrap', gap: 5 };

const PLACEMENTS = ['patch','band','mesh','caps'];

export default function MobileCustomizationInterface() {
  const {
    isOpenModal, setIsOpenModal,
    customization, setCustomization,
    environment, setEnvironment,
  } = useContext(CustomizationContext);

  const fileRef = useRef();
  const panelRef = useRef();
  const price = calcPrice(customization);

  useEffect(() => {
    const handler = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) setIsOpenModal(false);
    };
    document.body.addEventListener('mousedown', handler);
    return () => document.body.removeEventListener('mousedown', handler);
  });

  const set = (key, val) => setCustomization(p => ({ ...p, [key]: val }));
  const setTextProp  = (k, v) => setCustomization(p => ({ ...p, customText:  { ...p.customText,  [k]: v } }));
  const setImageProp = (k, v) => setCustomization(p => ({ ...p, customImage: { ...p.customImage, [k]: v } }));

  const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => setImageProp('dataUrl', ev.target.result);
    reader.readAsDataURL(file);
    setImageProp('fileName', file.name);
  };

  return (
    <>
      {/* Header bar */}
      <div style={{ position: 'absolute', top: 0, right: '1%', width: 250, ...glass, borderRadius: 10, border: '1px solid rgba(255,255,255,0.18)', padding: '8px 12px', marginTop: 8 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span style={{ color: '#efdeed', fontSize: 12, fontFamily: "'Work Sans',sans-serif" }}>Shoe Configurator</span>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#f5a623', fontFamily: "'Noto Sans Mono',monospace" }}>R{price.toLocaleString()}</span>
        </div>
        <div style={{ fontFamily: "'Noto Sans Mono',monospace", fontSize: 13, color: '#efdeed', marginTop: 4 }}>
          {customization.layerName ? `Editing: ${customization.layerName}` : 'Tap a layer to edit'}
        </div>
      </div>

      {/* Slide-up panel */}
      <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, margin: 'auto', padding: 12, width: 310 }}>
        {isOpenModal && (
          <div ref={panelRef} onClick={() => setIsOpenModal(true)}>
            <Carousel className="glass" swipeable showThumbs={false} showStatus={false} showIndicators={true}>

              {/* Slide 1 – Colours */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>🎨 Layer colour</h3>
                {customization.layerName ? <CustomColorPicker /> : <p style={{ fontSize: 11, color: 'rgba(239,222,237,0.5)' }}>Tap a part of the shoe first</p>}
                <div style={{ ...rows, marginTop: 10 }}>
                  {Object.entries(customization.layerColor).map(([layer, color]) => (
                    <button key={layer} onClick={() => setCustomization(p => ({ ...p, layerName: layer }))} style={{ ...pill(customization.layerName === layer), display: 'flex', alignItems: 'center', gap: 4 }}>
                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: color, display: 'inline-block', border: '1px solid rgba(255,255,255,0.3)' }} />{layer}
                    </button>
                  ))}
                </div>
              </div>

              {/* Slide 2 – Size */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>📏 Size</h3>
                <div style={{ ...rows, marginBottom: 8 }}>
                  {Object.entries(SIZE_SYSTEMS).map(([key, sys]) => (
                    <button key={key} onClick={() => { set('sizeSystem', key); set('shoeSize', sys.sizes[0]); }} style={pill(customization.sizeSystem === key)}>{sys.label}</button>
                  ))}
                </div>
                <div style={rows}>
                  {SIZE_SYSTEMS[customization.sizeSystem].sizes.map(s => (
                    <button key={s} onClick={() => set('shoeSize', s)} style={pill(customization.shoeSize === s)}>{s}</button>
                  ))}
                </div>
              </div>

              {/* Slide 3 – Material */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>🧵 Material</h3>
                {MATERIALS.map(m => (
                  <button key={m.key} onClick={() => set('material', m.key)}
                    style={{ ...pill(customization.material === m.key), display: 'block', width: '100%', textAlign: 'left', marginBottom: 5, padding: '7px 10px' }}>
                    {m.label} {m.key !== 'leather' ? <span style={{ fontSize: 10, color: '#f5a623' }}>+R{PRICING.materials[m.key]}</span> : <span style={{ fontSize: 10, opacity: 0.5 }}>base</span>}
                  </button>
                ))}
              </div>

              {/* Slide 4 – Laces */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>👟 Laces</h3>
                <button onClick={() => setCustomization(p => ({ ...p, layerName: 'laces' }))}
                  style={{ ...pill(customization.layerName === 'laces'), marginBottom: 8, display: 'flex', alignItems: 'center', gap: 5 }}>
                  <span style={{ width: 12, height: 12, borderRadius: '50%', background: customization.layerColor.laces, display: 'inline-block' }} /> Edit colour
                </button>
                {customization.layerName === 'laces' && <CustomColorPicker />}
                <div style={{ marginTop: 8 }}>
                  {LACE_STYLES.map(l => (
                    <button key={l.key} onClick={() => set('laceStyle', l.key)}
                      style={{ ...pill(customization.laceStyle === l.key), display: 'block', width: '100%', textAlign: 'left', marginBottom: 5, padding: '7px 10px' }}>
                      {l.label} {PRICING.laceStyles[l.key] > 0 ? <span style={{ fontSize: 10, color: '#f5a623' }}>+R{PRICING.laceStyles[l.key]}</span> : ''}
                    </button>
                  ))}
                </div>
              </div>

              {/* Slide 5 – Sole */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>⬛ Sole</h3>
                <button onClick={() => setCustomization(p => ({ ...p, layerName: 'sole' }))}
                  style={{ ...pill(customization.layerName === 'sole'), marginBottom: 8, display: 'flex', alignItems: 'center', gap: 5 }}>
                  <span style={{ width: 12, height: 12, borderRadius: '50%', background: customization.layerColor.sole, display: 'inline-block' }} /> Edit colour
                </button>
                {customization.layerName === 'sole' && <CustomColorPicker />}
                <div style={{ marginTop: 8 }}>
                  {SOLE_OPTIONS.map(s => (
                    <button key={s.key} onClick={() => set('soleOption', s.key)}
                      style={{ ...pill(customization.soleOption === s.key), display: 'block', width: '100%', textAlign: 'left', marginBottom: 5, padding: '7px 10px' }}>
                      {s.label} {PRICING.sole[s.key] > 0 ? <span style={{ fontSize: 10, color: '#f5a623' }}>+R{PRICING.sole[s.key]}</span> : ''}
                    </button>
                  ))}
                </div>
              </div>

              {/* Slide 6 – Text */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>✍️ Custom text <span style={{ color: '#f5a623', fontSize: 10 }}>+R{PRICING.customText}</span></h3>
                <input style={inp} maxLength={30} placeholder="Name, initials or message"
                  value={customization.customText.text}
                  onChange={e => setTextProp('text', e.target.value)} />
                <div style={{ ...rows, marginTop: 8 }}>
                  {TEXT_FONTS.map(f => (
                    <button key={f.key} onClick={() => setTextProp('font', f.key)} style={{ ...pill(customization.customText.font === f.key), fontFamily: f.css }}>{f.label}</button>
                  ))}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                  <span style={{ fontSize: 11, color: 'rgba(239,222,237,0.55)' }}>Colour:</span>
                  <input type="color" value={customization.customText.color} onChange={e => setTextProp('color', e.target.value)}
                    style={{ width: 32, height: 32, border: 'none', borderRadius: 6, cursor: 'pointer', background: 'none' }} />
                </div>
                {customization.customText.text && (
                  <div style={{ marginTop: 8, padding: '8px 12px', background: 'rgba(0,0,0,0.35)', borderRadius: 8, textAlign: 'center', fontSize: 16, color: customization.customText.color, fontFamily: TEXT_FONTS.find(f => f.key === customization.customText.font)?.css }}>
                    {customization.customText.text}
                  </div>
                )}
              </div>

              {/* Slide 7 – Logo */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>🖼 Logo / Image <span style={{ color: '#f5a623', fontSize: 10 }}>+R{PRICING.customImage}</span></h3>
                <button onClick={() => fileRef.current.click()} style={{ ...pill(false), width: '100%', textAlign: 'center', padding: '9px', borderStyle: 'dashed' }}>
                  {customization.customImage.dataUrl ? `✓ ${customization.customImage.fileName}` : '+ Upload image'}
                </button>
                <input ref={fileRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleImageUpload} />
                {customization.customImage.dataUrl && (
                  <>
                    <img src={customization.customImage.dataUrl} alt="preview" style={{ maxWidth: '100%', maxHeight: 80, borderRadius: 6, marginTop: 8, objectFit: 'contain' }} />
                    <div style={{ ...rows, marginTop: 8 }}>
                      {PLACEMENTS.map(p => <button key={p} onClick={() => setImageProp('placement', p)} style={pill(customization.customImage.placement === p)}>{p}</button>)}
                    </div>
                  </>
                )}
              </div>

              {/* Slide 8 – Scene */}
              <div style={{ padding: 16, textAlign: 'left' }}>
                <h3 style={h3s}>🌍 Scene</h3>
                <div style={rows}>
                  {ENVIRONMENTS.map(env => (
                    <button key={env.key} onClick={() => setEnvironment(env)} style={{ ...pill(environment.key === env.key), display: 'flex', alignItems: 'center', gap: 5 }}>
                      <span style={{ width: 10, height: 10, borderRadius: 3, background: env.bg || '#2a2a3a', display: 'inline-block' }} />
                      {env.label}
                    </button>
                  ))}
                </div>
              </div>

            </Carousel>
          </div>
        )}
      </div>
    </>
  );
}