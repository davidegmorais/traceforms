<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $this->account()->auditLogs()->with('user')->latest('created_at');
        if ($u = $request->query('user')) {
            $q->where('user_id', $u);
        }
        if ($a = $request->query('action')) {
            $q->where('action', 'like', $a.'%');
        }

        return view('audit.index', [
            'logs' => $q->paginate(40)->withQueryString(),
            'users' => $this->account()->users()->orderBy('name')->get(['users.id', 'name']),
        ]);
    }
}
