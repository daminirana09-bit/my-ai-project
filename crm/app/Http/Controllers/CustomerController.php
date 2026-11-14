<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Sale;
use Illuminate\Http\Request;

/** Customers = won leads, with their orders and how long since the last one. */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $q = Lead::visibleTo($me)->where('status', 'won')->with('owner:id,name')
            ->when($request->filled('q'), function ($w) use ($request) {
                $t = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->q).'%';
                $w->where(fn ($x) => $x->where('firm_name', 'like', $t)->orWhere('city', 'like', $t)->orWhere('contact_name', 'like', $t));
            });
        $customers = $q->orderBy('firm_name')->paginate(30)->withQueryString();

        $stats = Sale::whereIn('lead_id', $customers->pluck('id'))->where('status', 'confirmed')
            ->selectRaw('lead_id, COUNT(*) as orders, SUM(amount) as total, MAX(sale_date) as last_order')
            ->groupBy('lead_id')->get()->keyBy('lead_id');

        return view('customers.index', ['customers' => $customers, 'stats' => $stats]);
    }
}
