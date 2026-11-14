<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/** One search box for name, email, phone, GSTIN, city, product or task. Results respect each role. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $me = $request->user();
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return view('search', ['q' => $q, 'leads' => collect(), 'products' => collect(), 'tasks' => collect(), 'users' => collect()]);
        }
        $t = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $phone = Lead::normalizePhone($q);
        $leads = Lead::visibleTo($me)->with('owner:id,name')->where(function ($w) use ($q, $t, $phone) {
            $w->where('firm_name', 'like', $t)->orWhere('contact_name', 'like', $t)->orWhere('city', 'like', $t)->orWhere('gstin', 'like', $t);
            if (str_contains($q, '@')) {
                $w->orWhere('email_hash', Lead::hashEmail($q));
            } elseif ($phone && strlen($phone) >= 10) {
                $w->orWhere('phone_hash', Lead::hashPhone($q));
            }
        })->latest('updated_at')->limit(30)->get();

        return view('search', [
            'q' => $q,
            'leads' => $leads,
            'products' => Product::search($q)->where('active', true)->orderBy('name')->limit(15)->get(),
            'tasks' => Task::visibleTo($me)->where('title', 'like', $t)->with('assignee:id,name')->latest()->limit(15)->get(),
            'users' => $me->canManage() ? User::visibleTo($me)->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t))->limit(10)->get() : collect(),
        ]);
    }
}
