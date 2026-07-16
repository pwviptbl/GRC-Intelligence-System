<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GRC Intelligence System</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛡️</text></svg>">
  <meta name="description" content="Sistema de Governança, Risco e Conformidade" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
    rel="stylesheet" />

  @vite(['resources/css/grc.css', 'resources/js/app.js'])
  <style>
    :root {
      --bg-base: #070d1a;
      --bg-surface: #0d1628;
      --border: #1e3258;
      --text-1: #e8f0ff;
      --text-2: #8ca0c8;
      --text-3: #4a6090;
      --cyan: #00e5ff;
      --red: #ff5370;
      --green: #00ff9f;
      --yellow: #ffd740;
    }
    body { background: var(--bg-base); color: var(--text-1); margin: 0; display: flex; font-family: 'Inter', sans-serif; }
    .sidebar {
      height: 100vh !important;
      display: flex !important;
      flex-direction: column !important;
      position: fixed !important;
      top: 0; left: 0;
      width: 220px;
      z-index: 1000;
      background: #0d1628 !important; 
      border-right: 1px solid var(--border);
    }
    .main { margin-left: 220px !important; flex: 1; min-height: 100vh; background: var(--bg-base); }
    nav { 
      flex: 1 !important; 
      overflow-y: auto !important; 
      margin-bottom: 100px !important; 
      padding: 10px 10px 40px 10px !important;
    }
    .nav-btn { display: flex; align-items: center; padding: 10px; color: var(--text-2); text-decoration: none; font-size: 14px; border-radius: 6px; }
    .nav-btn.active { background: rgba(0, 229, 255, 0.1); color: var(--cyan); }
    .nav-label { padding: 15px 10px 5px; color: var(--text-3); font-size: 11px; text-transform: uppercase; font-weight: bold; }
    .nav-folder {
      display: flex !important;
      justify-content: space-between !important;
      align-items: center !important;
      padding: 10px !important;
      cursor: pointer !important;
      color: var(--text-2) !important;
      font-size: 13px !important;
      font-weight: 500 !important;
      user-select: none !important;
    }
    .nav-folder:hover { color: var(--text-1); background: rgba(255,255,255,0.03); border-radius: 6px; }
    .nav-btn.submenu { padding-left: 32px !important; font-size: 13px !important; opacity: 0.9; }
    .sidebar-footer {
      position: absolute !important;
      bottom: 0 !important;
      width: 100% !important;
      background: #0d1628 !important;
      padding: 15px !important;
      border-top: 1px solid #1e3258 !important;
      box-sizing: border-box;
    }
    .topbar { background: var(--bg-surface); padding: 20px; border-bottom: 1px solid var(--border); }
    .mobile-menu-btn,
    .sidebar-close,
    .sidebar-backdrop { display: none; }
    .app-content { padding: 24px 28px; overflow-y: auto; height: 100%; min-width: 0; }

    @media (max-width: 900px) {
      .sidebar {
        width: min(300px, 86vw) !important;
        transform: translateX(-100%);
        transition: transform .2s ease;
        box-shadow: 16px 0 40px rgba(0,0,0,.35);
      }
      .sidebar.mobile-open { transform: translateX(0); }
      .main { width: 100%; margin-left: 0 !important; min-width: 0; }
      .mobile-menu-btn,
      .sidebar-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: rgba(255,255,255,.035);
        color: var(--text-1);
        cursor: pointer;
        font-size: 20px;
      }
      .sidebar-close { position: absolute; top: 9px; right: 10px; z-index: 2; }
      .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 900;
        border: 0;
        background: rgba(0,0,0,.62);
      }
      .topbar { gap: 12px; padding: 12px 16px; }
      .topbar-title { min-width: 0; flex: 1; }
      .topbar h2 { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .topbar p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .app-content { padding: 18px 16px; }
      .table-view { padding: 0; }
      .table-card { max-width: 100%; overflow-x: auto; }
      .stats-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
      .stat-card { min-width: 0; padding: 14px; }
      .stat-card .stat-value { font-size: 22px; }
    }
    @media (max-width: 520px) {
      .topbar { align-items: flex-start; }
      .topbar .badge { max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .topbar p { display: none; }
      .app-content { padding: 14px 12px; }
      .stats-row { grid-template-columns: 1fr 1fr; }
      .modal-actions { flex-wrap: wrap; }
      .modal-actions > button { min-height: 40px; }
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>

<body>
  <div id="app" x-data="{
    sidebarOpen: false,
    menuAtivosAberto: false,
    menuGovernancaAberto: false,
    menuRiscosAberto: false,
    view: '{{ request()->route()->getName() ?? 'dashboard' }}'
  }">

    <button
      type="button"
      class="sidebar-backdrop"
      x-show="sidebarOpen"
      x-transition.opacity
      @click="sidebarOpen = false"
      aria-label="Fechar menu"
      style="display:none"
    ></button>

    <aside class="sidebar" :class="{ 'mobile-open': sidebarOpen }">
      <button type="button" class="sidebar-close" @click="sidebarOpen = false" aria-label="Fechar menu" title="Fechar menu">×</button>
      <div class="logo">
        <h1>GRC</h1>
        <p>{{ config('app.company') }}</p>
      </div>
      <nav @click="if ($event.target.closest('a') && window.innerWidth <= 900) sidebarOpen = false">
        <a href="{{ route('profile.edit') }}" class="user-info" title="Perfil">
          <span style="font-size:18px">👤</span>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--text-1)">
                {{ explode(' ', auth()->user()->name)[0] }}
            </div>
            <div style="font-size:10px;color:var(--text-3);text-transform:capitalize">
                {{ auth()->user()->role ?? 'Acesso GRC' }}
            </div>
          </div>
        </a>

        <div class="nav-label">Principal</div>
        <a href="{{ route('dashboard') }}" class="nav-btn" :class="{ 'active': view === 'dashboard' }">
          <span class="icon" style="margin-right: 10px;">📊</span> Painel
        </a>

        <a href="{{ route('estrategia.index') }}" class="nav-btn" :class="{ 'active': view === 'estrategia' }">
          <span class="icon" style="margin-right: 10px;">🚀</span> Consultor IA
        </a>

        <a href="{{ route('relatorios.index') }}" class="nav-btn" :class="{ 'active': view === 'relatorios' }">
          <span class="icon" style="margin-right: 10px;">📊</span> Centro de Relatórios
        </a>

        <a href="{{ route('chat') }}" class="nav-btn" :class="{ 'active': view === 'chat' }">
          <span class="icon" style="margin-right: 10px;">💬</span> Chat GRC
        </a>
        <div class="nav-folder" @click="menuAtivosAberto = !menuAtivosAberto" style="margin-top: 20px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">📁</span> Ativos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuAtivosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuAtivosAberto" style="display: none;" x-transition>
          <a href="{{ route('clientes.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('clientes') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🏢</span> Clientes
          </a>
          <a href="{{ route('softwares.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('softwares') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">💾</span> Softwares
          </a>
          <a href="{{ route('instancias.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('instancias') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🔗</span> Instâncias
          </a>
        </div>

        <div class="nav-folder" @click="menuGovernancaAberto = !menuGovernancaAberto" style="margin-top: 8px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">📜</span> Governança</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuGovernancaAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuGovernancaAberto" style="display: none;" x-transition>
          <a href="{{ route('politicas.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('politicas') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">📄</span> Políticas
          </a>
          <a href="{{ route('tier_politicas.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('tier_politicas') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">📐</span> Tiers
          </a>
          <a href="{{ route('atividades.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('atividades') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🧩</span> Atividades
          </a>
          <a href="{{ route('procedimentos.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('procedimentos') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🔄</span> Procedimentos
          </a>
        </div>

        <div class="nav-folder" @click="menuRiscosAberto = !menuRiscosAberto" style="margin-top: 8px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">⚠️</span> Riscos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuRiscosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuRiscosAberto" style="display: none;" x-transition>
          <a href="{{ route('riscos.index') }}" class="nav-btn submenu" :class="{ 'active': view.includes('riscos') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">📋</span> Registro de Riscos
          </a>
        </div>

        <div class="nav-label" style="margin-top:14px">Operacional</div>
        <a href="{{ route('incidentes.index') }}" class="nav-btn" :class="{ 'active': view.includes('incidentes') }">
          <span class="icon" style="margin-right: 10px;">🚨</span> Incidentes
        </a>
        <a href="{{ route('plano_acoes.index') }}" class="nav-btn" :class="{ 'active': view.includes('plano_acoes') }">
          <span class="icon" style="margin-right: 10px;">✅</span> Plano de Ação
        </a>
        <a href="{{ route('calendario_controles.index') }}" class="nav-btn" :class="{ 'active': view === 'calendario_controles.index' }">
          <span class="icon" style="margin-right: 10px;">🗓️</span> Calendário de Controles
        </a>
        <a href="{{ route('calendario_controles.kanban') }}" class="nav-btn submenu" :class="{ 'active': view === 'calendario_controles.kanban' }">
          <span class="icon" style="margin-right: 10px;">▦</span> Execução
        </a>
        <a href="{{ route('lgpd.index') }}" class="nav-btn" :class="{ 'active': view.includes('lgpd') }">
          <span class="icon" style="margin-right: 10px;">📋</span> LGPD
        </a>
        <a href="{{ route('treinamentos.index') }}" class="nav-btn"
          :class="{ 'active': view.includes('treinamentos') }">
          <span class="icon" style="margin-right: 10px;">🎓</span> Treinamentos
        </a>

        @if(auth()->user()->isAdmin())
        <div class="nav-label" style="margin-top:14px">Configurações</div>
        <a href="{{ route('usuarios.index') }}" class="nav-btn" :class="{ 'active': view.includes('usuarios') }">
          <span class="icon" style="margin-right: 10px;">👥</span> Usuários
        </a>
        <a href="{{ route('backups.index') }}" class="nav-btn" :class="{ 'active': view.includes('backups') }">
          <span class="icon" style="margin-right: 10px;">💾</span> Backup e Restauração
        </a>
        @endif
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
        <button type="button" class="mobile-menu-btn" @click="sidebarOpen = true" aria-label="Abrir menu" title="Abrir menu">☰</button>
        <div class="topbar-title">
          <h2>@yield('title', 'Dashboard')</h2>
          <p>@yield('description', 'Visão Geral do Sistema')</p>
        </div>
        @hasSection('badge')
          <span class="badge">@yield('badge')</span>
        @endif
      </div>

      <div class="content view active app-content">
        @yield('content')
      </div>
    </main>

  </div>
</body>

</html>
