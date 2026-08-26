import { Canvas } from '@react-three/fiber';
import { Environment, OrbitControls, ContactShadows } from '@react-three/drei';
import { Suspense, useContext } from 'react';
import ModelShoe from './models/ModelShoe.jsx';
import { CustomizationContext, ENVIRONMENTS } from '../../context/CustomizationContex.jsx';

function SceneBg({ envKey }) {
  const env = ENVIRONMENTS.find(e => e.key === envKey) || ENVIRONMENTS[0];
  return (
    <Environment
      files={env.file}
      background
      blur={0.4}
      environmentIntensity={0.3}
    />
  );
}

function Scene() {
  const { environment } = useContext(CustomizationContext);

  return (
    <Canvas
      shadows
      camera={{ position: [0, 0, 2.5], fov: 40 }}
      gl={{ preserveDrawingBuffer: true }}
      style={{ background: environment.bg || 'transparent' }}
    >
      <ambientLight intensity={0.5} />
      <directionalLight castShadow intensity={1.5} position={[5, 5, 5]} shadow-mapSize={[1024, 1024]} />
      <directionalLight intensity={0.8} position={[-5, 3, -5]} />
      <Suspense fallback={null}>
        <ModelShoe />
        <ContactShadows position={[0, -0.9, 0]} opacity={0.5} scale={10} blur={1.5} far={0.8} />
        <SceneBg envKey={environment.key} />
      </Suspense>
      <OrbitControls
        enablePan={false}
        enableZoom={true}
        enableRotate={true}
        minPolarAngle={Math.PI / 6}
        maxPolarAngle={Math.PI / 1.8}
        minDistance={1.5}
        maxDistance={5}
      />
    </Canvas>
  );
}

export default Scene;