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
  <script>
    (function() {
      const userPref = @json(auth()->user()?->theme_preference);
      const savedTheme = localStorage.getItem('grc_theme') || userPref || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
  <style>
    :root, html[data-theme="dark"] {
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
    html[data-theme="light"] {
      --bg-base: #f4f6fa;
      --bg-surface: #ffffff;
      --border: #e2e8f0;
      --text-1: #0f172a;
      --text-2: #334155;
      --text-3: #64748b;
      --cyan: #0284c7;
      --red: #ef4444;
      --green: #10b981;
      --yellow: #d97706;
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
      background: var(--bg-surface) !important; 
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
      background: var(--bg-surface) !important;
      padding: 15px !important;
      border-top: 1px solid var(--border) !important;
      box-sizing: border-box;
    }
    .topbar { background: var(--bg-surface); padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 16px; position: relative; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .btn-topbar-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-surface); color: var(--text-1); cursor: pointer; transition: border-color .15s, background .15s; }
    .btn-topbar-bell:hover { border-color: var(--border-glow); background: var(--bg-hover); }
    .btn-topbar-bell.has-danger { border-color: rgba(255,83,112,.4); background: rgba(255,83,112,.08); }
    .btn-topbar-bell.has-warning { border-color: rgba(255,215,64,.4); background: rgba(255,215,64,.08); }
    .bell-badge { position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; font: 700 10px/18px var(--mono); color: #fff; text-align: center; box-shadow: 0 0 6px rgba(0,0,0,.4); }
    .alerts-dropdown { position: absolute; top: calc(100% + 8px); right: 0; width: min(380px, calc(100vw - 32px)); max-height: 480px; border: 1px solid var(--border-glow); border-radius: 10px; background: var(--bg-surface); box-shadow: 0 10px 30px rgba(0,0,0,.6); z-index: 1000; display: flex; flex-direction: column; overflow: hidden; }
    .alerts-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--border); background: rgba(255,255,255,.02); }
    .alerts-body { max-height: 380px; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 6px; }
    .alert-item { padding: 10px 12px; border-radius: 7px; border: 1px solid var(--border); background: rgba(255,255,255,.02); transition: background .15s; text-align: left; }
    .alert-item:hover { background: var(--bg-hover); }
    .alert-item.severity-danger { border-color: rgba(255,83,112,.3); background: rgba(255,83,112,.03); }
    .alert-item.severity-warning { border-color: rgba(255,215,64,.3); background: rgba(255,215,64,.03); }
    .alert-item.severity-info { border-color: rgba(6,182,212,.3); background: rgba(6,182,212,.03); }
    .alert-badge { font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; }
    .badge-danger { background: rgba(255,83,112,.15); color: var(--red); }
    .badge-warning { background: rgba(255,215,64,.15); color: var(--yellow); }
    .badge-info { background: rgba(6,182,212,.15); color: var(--cyan); }
    .text-danger { color: var(--red); }
    .text-warning { color: var(--yellow); }
    .text-info { color: var(--cyan); }
    .alert-action-link { display: inline-block; font-size: 11px; font-weight: 600; color: var(--cyan); text-decoration: none; }
    .alert-action-link:hover { text-decoration: underline; }
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
</head>

<body>
  <div id="app" x-data="{
    sidebarOpen: false,
    menuAtivosAberto: false,
    menuGovernancaAberto: false,
    menuRiscosAberto: false,
    menuSegurancaAberto: false,
    view: '{{ request()->route()?->getName() ?? 'dashboard' }}',
    currentTheme: document.documentElement.getAttribute('data-theme') || 'dark',
    toggleTheme() {
      this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', this.currentTheme);
      localStorage.setItem('grc_theme', this.currentTheme);
      fetch('{{ route('profile.theme') }}', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ theme: this.currentTheme })
      }).catch(() => {});
    }
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

        @if(in_array(auth()->user()->role, ['admin', 'governanca']))
        <a href="{{ route('estrategia.index') }}" class="nav-btn" :class="{ 'active': view.includes('estrategia') }">
          <span class="icon" style="margin-right: 10px;">🚀</span> Assistente GRC
        </a>
        @endif

        <a href="{{ route('relatorios.index') }}" class="nav-btn" :class="{ 'active': view.includes('relatorios') }">
          <span class="icon" style="margin-right: 10px;">📊</span> Centro de Relatórios
        </a>
        <div class="nav-folder" @click="menuAtivosAberto = !menuAtivosAberto" style="margin-top: 20px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">📁</span> Ativos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuAtivosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuAtivosAberto" style="display: none;" x-transition>
          <a href="{{ route('clientes.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('clientes') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🏢</span> Organizações
          </a>
          <a href="{{ route('softwares.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('softwares') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">💾</span> Sistemas
          </a>
          <a href="{{ route('instancias.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('instancias') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🔗</span> Ambientes
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
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">📐</span> Níveis de Criticidade
          </a>
          <a href="{{ route('atividades.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('atividades') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🧩</span> Controles
          </a>
          <a href="{{ route('atividades.module_coverage') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('module_coverage') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🗺️</span> Mapeamento de Controles
          </a>
          <a href="{{ route('procedimentos.index') }}" class="nav-btn submenu"
            :class="{ 'active': view.includes('procedimentos') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🔄</span> Procedimentos
          </a>
        </div>

        <div class="nav-folder" @click="menuSegurancaAberto = !menuSegurancaAberto" style="margin-top: 8px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">🔐</span> Segurança</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuSegurancaAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuSegurancaAberto" style="display: none;" x-transition>
          <a href="{{ route('engagements.index') }}" class="nav-btn submenu" :class="{ 'active': view.includes('engagement') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🔐</span> Engajamentos
          </a>
          <a href="{{ route('findings.index') }}" class="nav-btn submenu" :class="{ 'active': view === 'findings.index' }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">🎯</span> Achados
          </a>
        </div>

        <div class="nav-folder" @click="menuRiscosAberto = !menuRiscosAberto" style="margin-top: 4px;">
          <span style="display: flex; align-items: center; gap: 6px;"><span class="icon"
              style="font-size: 14px; margin-right: 10px;">⚠️</span> Riscos</span>
          <span style="font-size: 10px; color: var(--text-3);" x-text="menuRiscosAberto ? '▼' : '►'"></span>
        </div>
        <div class="nav-submenu-group" x-show="menuRiscosAberto" style="display: none;" x-transition>
          <a href="{{ route('riscos.index') }}" class="nav-btn submenu" :class="{ 'active': view.includes('riscos') }">
            <span class="icon" style="opacity: 0.8; margin-right: 10px;">📋</span> Registro de Riscos / Vulns
          </a>
        </div>

        <div class="nav-label" style="margin-top:14px">Operacional</div>
        <a href="{{ route('incidentes.index') }}" class="nav-btn" :class="{ 'active': view.includes('incidentes') }">
          <span class="icon" style="margin-right: 10px;">🚨</span> Incidentes
        </a>
        @if(in_array(auth()->user()->role, ['admin', 'governanca']))
        <a href="{{ route('calendario_controles.index') }}" class="nav-btn" :class="{ 'active': view === 'calendario_controles.index' }">
          <span class="icon" style="margin-right: 10px;">🗓️</span> Plano de Controles
        </a>
        <a href="{{ route('planejamento_semanal.index') }}" class="nav-btn submenu" :class="{ 'active': view === 'planejamento_semanal.index' }">
          <span class="icon" style="margin-right: 10px;">⌁</span> Planejamento Semanal
        </a>
        @endif
        @if(auth()->user()->role !== 'auditor')
        <a href="{{ route('calendario_controles.kanban') }}" class="nav-btn submenu" :class="{ 'active': view === 'calendario_controles.kanban' }">
          <span class="icon" style="margin-right: 10px;">▦</span> Execuções
        </a>
        @endif
        <a href="{{ route('lgpd.index') }}" class="nav-btn" :class="{ 'active': view.includes('lgpd') }">
          <span class="icon" style="margin-right: 10px;">📋</span> Conformidade (LGPD)
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
        <a href="{{ route('auditoria.index') }}" class="nav-btn" :class="{ 'active': view.includes('auditoria') }">
          <span class="icon" style="margin-right: 10px;">📜</span> Auditoria
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
        <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
          <button type="button" class="mobile-menu-btn" @click="sidebarOpen = true" aria-label="Abrir menu" title="Abrir menu">☰</button>
          <div class="topbar-title">
            <h2>@yield('title', 'Dashboard')</h2>
            <p>@yield('description', 'Visão Geral do Sistema')</p>
          </div>
        </div>

        <div class="topbar-right">
          @hasSection('badge')
            <span class="badge">@yield('badge')</span>
          @endif

          <!-- Botão Alternador de Tema (Modo Claro / Modo Escuro) -->
          <button type="button" 
                  x-on:click="toggleTheme()" 
                  class="btn-topbar-bell" 
                  :title="currentTheme === 'dark' ? 'Alternar para Modo Claro' : 'Alternar para Modo Escuro'" 
                  aria-label="Alternar Tema">
              <span x-show="currentTheme === 'dark'" style="font-size:16px">☀️</span>
              <span x-show="currentTheme === 'light'" style="font-size:16px;display:none">🌙</span>
          </button>

          <!-- Central de Notificações e Alertas In-App -->
          <div class="topbar-alerts" x-data="{
              open: false,
              loading: false,
              data: { badge_count: 0, danger_count: 0, warning_count: 0, total_items: 0, alerts: [] },
              async loadAlerts() {
                  try {
                      this.loading = true;
                      const res = await fetch('{{ route('alertas.summary') }}');
                      this.data = await res.json();
                  } catch(e) {
                      console.error('Erro ao buscar alertas:', e);
                  } finally {
                      this.loading = false;
                  }
              },
              init() {
                  this.loadAlerts();
              }
          }" x-on:click.outside="open = false" style="position:relative">
              <button type="button" x-on:click="open = !open" class="btn-topbar-bell" :class="{ 'has-danger': data.danger_count > 0, 'has-warning': data.warning_count > 0 }" aria-label="Alertas e Notificações" title="Alertas de Segurança e SLA">
                  <span style="font-size:17px">🔔</span>
                  <template x-if="data.badge_count > 0">
                      <span class="bell-badge" :style="data.danger_count > 0 ? 'background:var(--red)' : 'background:var(--yellow)'" x-text="data.badge_count"></span>
                  </template>
              </button>

              <!-- Dropdown de Notificações -->
              <div x-show="open" x-transition class="alerts-dropdown" style="display:none">
                  <div class="alerts-header">
                      <div>
                          <strong style="color:var(--text-1);font-size:13px;display:flex;align-items:center;gap:6px">
                              <span>🔔 Alertas e Notificações</span>
                          </strong>
                          <div style="font-size:11px;color:var(--text-3);margin-top:2px" x-text="data.badge_count > 0 ? data.total_items + ' pendência(s) em ' + data.badge_count + ' alerta(s)' : 'Tudo em conformidade'"></div>
                      </div>
                      <button type="button" x-on:click.stop="loadAlerts()" style="background:transparent;border:0;color:var(--cyan);font-size:12px;cursor:pointer;padding:4px" title="Atualizar">
                          <span :style="loading ? 'display:inline-block;animation:spin 1s linear infinite' : ''">🔄</span>
                      </button>
                  </div>
                  
                  <div class="alerts-body">
                      <template x-if="data.alerts.length === 0">
                          <div style="padding:28px 16px;text-align:center;color:var(--text-3);font-size:12px">
                              <div style="font-size:26px;margin-bottom:8px">🛡️</div>
                              <strong style="color:var(--text-1);display:block;margin-bottom:4px">Tudo sob controle!</strong>
                              Nenhum alerta crítico ou SLA vencido no momento.
                          </div>
                      </template>

                      <template x-for="alert in data.alerts" :key="alert.id">
                          <div class="alert-item" :class="'severity-' + alert.severity">
                              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                  <div style="display:flex;align-items:center;gap:6px">
                                      <span x-text="alert.icon"></span>
                                      <span class="alert-badge" :class="'badge-' + alert.severity" x-text="alert.category"></span>
                                  </div>
                                  <span class="alert-counter" :class="'text-' + alert.severity" style="font-weight:700;font-size:11px" x-text="alert.count"></span>
                              </div>
                              <div style="margin-top:5px;font-weight:600;font-size:12px;color:var(--text-1)" x-text="alert.title"></div>
                              <div style="font-size:11px;color:var(--text-2);margin-top:3px;line-height:1.4" x-text="alert.description"></div>
                              <div style="margin-top:6px;text-align:right">
                                  <a :href="alert.action_url" class="alert-action-link" x-text="alert.action_label + ' ➔'"></a>
                              </div>
                          </div>
                      </template>
                  </div>
              </div>
          </div>
        </div>
      </div>

      <div class="content view active app-content">
        @yield('content')
      </div>
    </main>

  </div>
</body>

</html>
