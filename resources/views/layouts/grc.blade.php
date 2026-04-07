<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GRC Intelligence System</title>
  <meta name="description" content="Sistema de Governança, Risco e Conformidade" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
    rel="stylesheet" />

  @vite(['resources/css/grc.css', 'resources/js/app.js'])
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>

<body>
  <div id="app" x-data="{
    menuAtivosAberto: false,
    menuGovernancaAberto: false,
    menuRiscosAberto: false,
    view: '{{ request()->route()->getName() ?? 'dashboard' }}'
  }">

    <aside class="sidebar">
      <div class="logo">
        <div class="logo-icon">🛡️</div>
        <h1>GRC Intel</h1>
        <p>{{ config('app.company') }}</p>
      </div>
      <nav>
        <a href="{{ route('profile.edit') }}" class="user-info" title="Perfil">
          <span style="font-size:18px">👤</span>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-1)">{{ auth()->user()->nome ?? 'Usuário' }}
            </div>
            <div style="font-size:10px;color:var(--text-3)">{{ auth()->user()->username ?? 'admin' }}</div>
          </div>
        </a>

        <div class="nav-label">Principal</div>
        <a href="{{ route('dashboard') }}" class="nav-btn" :class="{ 'active': view === 'dashboard' }">
          <span class="icon">🏠</span> Dashboard
        </a>
        <a href="{{ route('chat') }}" class="nav-btn" :class="{ 'active': view === 'chat' }">
          <span class="icon">🤖</span> Chat IA
        </a>

        <div class="nav-folder" @click="menuAtivosAberto = !menuAtivosAberto" style="margin-top: 20px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px;">📁</span> Ativos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuAtivosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuAtivosAberto" style="display: none;" x-transition>
          <a href="{{ route('clientes.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('clientes') }">
            <span class="icon" style="opacity: 0.8;">🏢</span> Clientes
          </a>
          <a href="{{ route('softwares.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('softwares') }">
            <span class="icon" style="opacity: 0.8;">💾</span> Softwares
          </a>
          <a href="{{ route('instancias.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('instancias') }">
            <span class="icon" style="opacity: 0.8;">🔗</span> Instâncias
          </a>
        </div>

        <div class="nav-folder" @click="menuGovernancaAberto = !menuGovernancaAberto" style="margin-top: 8px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px;">📜</span> Governança</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuGovernancaAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuGovernancaAberto" style="display: none;" x-transition>
          <a href="{{ route('politicas.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('politicas') }">
            <span class="icon" style="opacity: 0.8;">📄</span> Políticas
          </a>
          <a href="{{ route('procedimentos.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('procedimentos') }">
            <span class="icon" style="opacity: 0.8;">🔄</span> Procedimentos
          </a>
        </div>

        <div class="nav-folder" @click="menuRiscosAberto = !menuRiscosAberto" style="margin-top: 8px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px;">⚠️</span> Riscos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuRiscosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuRiscosAberto" style="display: none;" x-transition>
          <a href="{{ route('riscos.index') }}" class="nav-btn submenu" :class="{ 'active': view.includes('riscos') }">
            <span class="icon" style="opacity: 0.8;">📋</span> Registro de Riscos
          </a>
        </div>

        <div class="nav-label" style="margin-top:14px">Operacional</div>
        <a href="{{ route('incidentes.index') }}" class="nav-btn" :class="{ 'active': view.includes('incidentes') }">
          <span class="icon">🚨</span> Incidentes
        </a>
        <a href="{{ route('plano_acoes.index') }}" class="nav-btn" :class="{ 'active': view.includes('plano_acoes') }">
          <span class="icon">✅</span> Plano de Ação
        </a>
        <a href="{{ route('lgpd.index') }}" class="nav-btn" :class="{ 'active': view.includes('lgpd') }">
          <span class="icon">📋</span> LGPD
        </a>
        <a href="{{ route('treinamentos.index') }}" class="nav-btn"
          :class="{ 'active': view.includes('treinamentos') }">
          <span class="icon">🎓</span> Treinamentos
        </a>
      </nav>

      <div class="sidebar-footer">
        <div><span class="status-dot"></span>API Conectada</div>
        <div style="margin-top:6px;color:var(--text-3)">Gemini 2.5 Flash Lite</div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit"
            style="margin-top:10px;background:rgba(255,83,112,.1);border:1px solid rgba(255,83,112,.2);color:var(--red);border-radius:6px;padding:6px 12px;font-size:11px;cursor:pointer;width:100%">
            🚪 Sair
          </button>
        </form>
      </div>
    </aside>

    <main class="main">
      <div class="topbar">
        <div>
          <h2>@yield('title', 'Dashboard')</h2>
          <p>@yield('description', 'Visão Geral do Sistema')</p>
        </div>
        @hasSection('badge')
          <span class="badge">@yield('badge')</span>
        @endif
      </div>

      <div class="content view active" style="padding: 24px 28px; overflow-y: auto; height: 100%;">
        @yield('content')
      </div>
    </main>

  </div>
</body>

</html>