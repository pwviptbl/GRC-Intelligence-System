@extends('layouts.grc')
@section('title', 'Novo Achado')
@section('content')
<div style="max-width:900px;margin:0 auto;padding:0 16px">
  <div style="margin-bottom:20px">
    <a href="{{ route('engagement-tests.show', $test) }}" style="color:var(--text-3);font-size:13px;text-decoration:none">← {{ $test->titulo }}</a>
    <h1 style="font-size:20px;font-weight:700;color:var(--text-1);margin:8px 0 0">🎯 Novo Achado</h1>
  </div>
  <form method="POST" action="{{ route('findings.store') }}">
    @csrf
    @include('findings._form')
    <div style="display:flex;gap:12px;margin-top:24px">
      <button type="submit" style="padding:11px 28px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:14px">Criar Achado</button>
      <a href="{{ route('engagement-tests.show', $test) }}" class="btn-cancel" style="padding:11px 20px;border-radius:6px;text-decoration:none;font-size:14px">Cancelar</a>
    </div>
  </form>
</div>
@endsection
