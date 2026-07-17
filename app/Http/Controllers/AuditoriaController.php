<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $events = AuditEvent::query()
            ->with('user:id,name,email')
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('auditoria.index', compact('events'));
    }
}
