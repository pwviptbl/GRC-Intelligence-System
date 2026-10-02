<svg {{ $attributes->merge(['class' => 'bastion-icon']) }} viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bastionGradPrimary" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#00f5ff"/>
      <stop offset="60%" stop-color="#0ea5e9"/>
      <stop offset="100%" stop-color="#3b82f6"/>
    </linearGradient>
    <linearGradient id="bastionGradCircuit" x1="0%" y1="100%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#00f5ff"/>
      <stop offset="100%" stop-color="#10b981"/>
    </linearGradient>
    <filter id="bastionGlow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="1.5" result="glow"/>
      <feComposite in="SourceGraphic" in2="glow" operator="over"/>
    </filter>
  </defs>

  <!-- Contorno da Fortaleza / Bastião Geométrico -->
  <path d="M24 3L32 10V14L38 12V22L40 23V34L24 45L8 34V23L10 22V12L16 14V10L24 3Z" 
        stroke="url(#bastionGradPrimary)" 
        stroke-width="2.2" 
        stroke-linecap="round" 
        stroke-linejoin="round"
        fill="rgba(14, 165, 233, 0.08)"
        filter="url(#bastionGlow)"/>

  <!-- Linhas Internas de Faceta 3D -->
  <path d="M24 3V16M32 10L24 16M16 10L24 16" 
        stroke="url(#bastionGradPrimary)" 
        stroke-width="1.4" 
        stroke-linecap="round" 
        opacity="0.8"/>

  <!-- Circuito Cibernético Central (Tronco & Ramificações) -->
  <path d="M24 40V24M24 32L17 26V20M24 32L31 26V20M24 24L19 18M24 24L29 18" 
        stroke="url(#bastionGradCircuit)" 
        stroke-width="1.8" 
        stroke-linecap="round" 
        stroke-linejoin="round"/>

  <!-- Nós de Conexão Cibernética (Cyber Nodes) -->
  <circle cx="24" cy="24" r="2.2" fill="#00f5ff"/>
  <circle cx="17" cy="20" r="1.8" fill="#10b981"/>
  <circle cx="31" cy="20" r="1.8" fill="#10b981"/>
  <circle cx="19" cy="18" r="1.6" fill="#00f5ff"/>
  <circle cx="29" cy="18" r="1.6" fill="#00f5ff"/>
  <circle cx="24" cy="40" r="1.8" fill="#3b82f6"/>
</svg>
