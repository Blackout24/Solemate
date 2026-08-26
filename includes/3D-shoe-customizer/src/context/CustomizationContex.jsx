import { useState, createContext } from 'react';

export const CustomizationContext = createContext({});

// ── Pricing table ────────────────────────────────────────
export const PRICING = {
  basePrice: 1200,
  materials: { leather: 0, suede: 150, canvas: 50, mesh: 80, patent: 200 },
  laceStyles: { flat: 0, round: 30, waxed: 50, reflective: 80, rope: 60 },
  sole: { standard: 0, transparent: 120, glowDark: 200 },
  customText: 100,
  customImage: 180,
};

// ── Shoe size definitions ────────────────────────────────
export const SIZE_SYSTEMS = {
  mens: {
    label: "Men's",
    sizes: ['UK 6','UK 6.5','UK 7','UK 7.5','UK 8','UK 8.5','UK 9','UK 9.5',
            'UK 10','UK 10.5','UK 11','UK 11.5','UK 12','UK 13','UK 14'],
  },
  womens: {
    label: "Women's",
    sizes: ['UK 2','UK 2.5','UK 3','UK 3.5','UK 4','UK 4.5','UK 5','UK 5.5',
            'UK 6','UK 6.5','UK 7','UK 7.5','UK 8','UK 8.5','UK 9'],
  },
  kids: {
    label: "Kids",
    sizes: ['UK 2K','UK 3K','UK 4K','UK 5K','UK 6K','UK 7K','UK 8K','UK 9K',
            'UK 10K','UK 11K','UK 12K','UK 13K','UK 1','UK 2','UK 3'],
  },
};

// ── Material definitions ─────────────────────────────────
export const MATERIALS = [
  { key: 'leather',  label: 'Leather',        desc: 'Classic & durable' },
  { key: 'suede',    label: 'Suede',           desc: 'Soft premium feel' },
  { key: 'canvas',   label: 'Canvas',          desc: 'Lightweight casual' },
  { key: 'mesh',     label: 'Mesh',            desc: 'Breathable sport' },
  { key: 'patent',   label: 'Patent Leather',  desc: 'High gloss luxury' },
];

// ── Lace style definitions ───────────────────────────────
export const LACE_STYLES = [
  { key: 'flat',        label: 'Flat' },
  { key: 'round',       label: 'Round' },
  { key: 'waxed',       label: 'Waxed' },
  { key: 'reflective',  label: 'Reflective' },
  { key: 'rope',        label: 'Rope' },
];

// ── Sole options ─────────────────────────────────────────
export const SOLE_OPTIONS = [
  { key: 'standard',    label: 'Standard' },
  { key: 'transparent', label: 'Transparent' },
  { key: 'glowDark',    label: 'Glow-in-Dark' },
];

// ── Environment definitions ──────────────────────────────
export const ENVIRONMENTS = [
  { key: 'syntwave-2',    label: 'Synthwave',        file: './img/syntwave-2.hdr' },
  { key: 'synth1',        label: 'Studio',           file: './img/synth1.hdr' },
  { key: 'xoor',          label: 'Golden Hour',      file: './img/xoor.hdr' },
  { key: 'beach',         label: 'Beach',            file: './img/syntwave-2.hdr',  bg: '#87CEEB' },
  { key: 'snow',          label: 'Snow Mountain',    file: './img/synth1.hdr',      bg: '#E8F4FD' },
  { key: 'basketball',    label: 'Basketball Court', file: './img/xoor.hdr',        bg: '#D4570A' },
  { key: 'soccer',        label: 'Soccer Field',     file: './img/syntwave-2.hdr',  bg: '#2D5A27' },
  { key: 'city',          label: 'City Street',      file: './img/xoor.hdr',        bg: '#3A3A4A' },
  { key: 'graffiti',      label: 'Graffiti Alley',   file: './img/syntwave-2.hdr',  bg: '#1A1A2E' },
  { key: 'luxury',        label: 'Luxury Showroom',  file: './img/xoor.hdr',        bg: '#F5E6CC' },
  { key: 'space',         label: 'Space',            file: './img/synth1.hdr',      bg: '#050510' },
  { key: 'rainy',         label: 'Rainy Day',        file: './img/syntwave-2.hdr',  bg: '#4A5568' },
  { key: 'racing',        label: 'Racing Track',     file: './img/xoor.hdr',        bg: '#2D2D2D' },
];

// ── Font options for custom text ─────────────────────────
export const TEXT_FONTS = [
  { key: 'sans',    label: 'Clean Sans',    css: 'Work Sans, sans-serif' },
  { key: 'serif',   label: 'Classic Serif', css: 'Georgia, serif' },
  { key: 'mono',    label: 'Monospace',     css: 'Noto Sans Mono, monospace' },
  { key: 'script',  label: 'Script',        css: 'Brush Script MT, cursive' },
  { key: 'bold',    label: 'Bold Block',    css: 'Impact, sans-serif' },
];

export function calcPrice(customization) {
  let total = PRICING.basePrice;
  total += PRICING.materials[customization.material] ?? 0;
  total += PRICING.laceStyles[customization.laceStyle] ?? 0;
  total += PRICING.sole[customization.soleOption] ?? 0;
  if (customization.customText?.text?.trim()) total += PRICING.customText;
  if (customization.customImage?.dataUrl)     total += PRICING.customImage;
  return total;
}

export default function CustomizationContextProvider({ children }) {
  const [isOpenModal, setIsOpenModal]     = useState(false);
  const [activeTab, setActiveTab]         = useState('colors');
  const [environment, setEnvironment]     = useState(ENVIRONMENTS[0]);

  const [customization, setCustomization] = useState({
    // ── existing ──
    layerName: '',
    layerColor: {
      laces: '#ffffff', mesh: '#ffffff', caps: '#ffffff', inner: '#ffffff',
      sole:  '#ffffff', stripes: '#ffffff', band: '#ffffff', patch: '#ffffff',
    },
    layerSize: {
      laces:   { x:1,y:1,z:1 }, mesh:    { x:1,y:1,z:1 },
      caps:    { x:1,y:1,z:1 }, inner:   { x:1,y:1,z:1 },
      sole:    { x:1,y:1,z:1 }, stripes: { x:1,y:1,z:1 },
      band:    { x:1,y:1,z:1 }, patch:   { x:1,y:1,z:1 },
    },
    // ── new ──
    sizeSystem:  'mens',
    shoeSize:    'UK 9',
    material:    'leather',
    laceStyle:   'flat',
    soleOption:  'standard',
    customText: {
      text:   '',
      font:   'sans',
      color:  '#ffffff',
      placement: 'patch',
    },
    customImage: {
      dataUrl:   null,
      placement: 'patch',
      fileName:  '',
    },
  });

  const onOpenModal  = () => setIsOpenModal(true);
  const onCloseModal = () => setIsOpenModal(false);

  return (
    <CustomizationContext.Provider
      value={{
        isOpenModal, onOpenModal, onCloseModal, setIsOpenModal,
        customization, setCustomization,
        activeTab, setActiveTab,
        environment, setEnvironment,
      }}
    >
      {children}
    </CustomizationContext.Provider>
  );
}