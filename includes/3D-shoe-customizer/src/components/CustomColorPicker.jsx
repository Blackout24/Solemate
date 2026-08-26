import { useContext, useState, useEffect } from 'react';
import { HexColorPicker } from 'react-colorful';
import styled from '@emotion/styled';
import Circle from '@uiw/react-color-circle';
import { CustomizationContext } from '../context/CustomizationContex.jsx';

const StyledColorPicker = styled(HexColorPicker)`
  &.react-colorful { width: 100%; height: 180px; }
  & .react-colorful__hue { height: 28px; }
`;

export default function CustomColorPicker() {
  const { customization, setCustomization } = useContext(CustomizationContext);
  const currentColor = customization.layerColor[customization.layerName] || '#ffffff';
  const [circleColor, setCircleColor] = useState(currentColor);
  const [pickerColor, setPickerColor] = useState(currentColor);

  useEffect(() => {
    const c = customization.layerColor[customization.layerName] || '#ffffff';
    setCircleColor(c);
    setPickerColor(c);
  }, [customization.layerName]);

  const applyColor = (hex) => {
    setPickerColor(hex);
    setCircleColor(hex);
    setCustomization((prev) => ({
      ...prev,
      layerColor: { ...prev.layerColor, [customization.layerName]: hex },
    }));
  };

  return (
    <>
      <div style={{ background: 'rgba(0,0,0,0.25)', borderRadius: 8, padding: 8, marginBottom: 8 }}>
        <Circle
          colors={['#ffffff','#000000','#1A0E3E','#1F1A70','#DB488B','#FF83F6','#3ED0EB','#f5a623','#7ed321','#d0021b']}
          color={circleColor}
          onChange={(c) => applyColor(c.hex)}
        />
      </div>
      <StyledColorPicker color={pickerColor} onChange={applyColor} />
    </>
  );
}