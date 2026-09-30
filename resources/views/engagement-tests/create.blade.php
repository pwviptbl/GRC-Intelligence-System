@extends('layouts.grc')
@section('title', 'Novo Teste')
@section('content')
<div style="max-width:800px;margin:0 auto;padding:0 16px">
  <div style="margin-bottom:20px">
    <a href="{{ route('engagements.show', $engagement) }}" style="color:var(--text-3);font-size:13px;text-decoration:none">← {{ $engagement->nome }}</a>
    <h1 style="font-size:20px;font-weight:700;color:var(--text-1);margin:8px 0 0">📋 Novo Teste</h1>
  </div>
  <form method="POST" action="{{ route('engagement-tests.store') }}">
    @csrf
    @include('engagement-tests._form')
    <div style="display:flex;gap:12px;margin-top:24px">
      <button type="submit" style="padding:11px 28px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:14px">Criar Teste</button>
      <a href="{{ route('engagements.show', $engagement) }}" class="btn-cancel" style="padding:11px 20px;border-radius:6px;text-decoration:none;font-size:14px">Cancelar</a>
    </div>
  </form>
</div>
@endsection
