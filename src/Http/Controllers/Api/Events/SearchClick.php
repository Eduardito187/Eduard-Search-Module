<?php

namespace Eduard\Search\Http\Controllers\Api\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SearchClick extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'uuid'=> ['required','string','size:36'],
            'query'=> ['required','string'],
            'product_id' => ['required','integer','min:1'],
            'rank'=> ['nullable','integer','min:1'],
        ]);

        DB::insert(
            "INSERT INTO search_clicks (request_uuid, product_id, rank, clicked_at, raw_query_norm, referer, client_ip, ua)
             VALUES (?, ?, ?, NOW(), ?, ?, INET6_ATON(?), ?)",
            [
                $data['uuid'],
                $data['product_id'],
                $data['rank'] ?? null,
                $data['query'] ?? '',
                substr((string) $request->headers->get('referer'), 0, 255),
                $request->ip(),
                substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->noContent();
    }
}
