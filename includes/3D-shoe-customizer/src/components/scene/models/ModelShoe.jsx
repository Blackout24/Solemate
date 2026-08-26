import { useGLTF, Bounds } from '@react-three/drei';
import { useRef, useContext } from 'react';
import { CustomizationContext } from '../../../context/CustomizationContex.jsx';

function ModelShoe({ ...props }) {
  const { nodes, materials } = useGLTF('/model.glb', true);
  const group = useRef();
  const { setIsOpenModal, customization, setCustomization } = useContext(CustomizationContext);

  const handleClick = (e) => {
    e.stopPropagation();
    setCustomization((prev) => ({ ...prev, layerName: e.object.material.name }));
    setIsOpenModal(true);
  };

  return (
    <Bounds fit clip observe margin={1.1}>
      <group {...props} ref={group} dispose={null} scale={2.2} rotation={[0, Math.PI / 5, 0]} position={[0, -1, 0]}>
        <mesh castShadow receiveShadow geometry={nodes.shoe.geometry}   material={materials.laces}   material-color={customization.layerColor.laces}   onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_1.geometry} material={materials.mesh}    material-color={customization.layerColor.mesh}    onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_2.geometry} material={materials.caps}    material-color={customization.layerColor.caps}    onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_3.geometry} material={materials.inner}   material-color={customization.layerColor.inner}   onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_4.geometry} material={materials.sole}    material-color={customization.layerColor.sole}    onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_5.geometry} material={materials.stripes} material-color={customization.layerColor.stripes} onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_6.geometry} material={materials.band}    material-color={customization.layerColor.band}    onClick={handleClick} />
        <mesh castShadow receiveShadow geometry={nodes.shoe_7.geometry} material={materials.patch}   material-color={customization.layerColor.patch}   onClick={handleClick} />
      </group>
    </Bounds>
  );
}

useGLTF.preload('/model.glb', true);
export default ModelShoe;