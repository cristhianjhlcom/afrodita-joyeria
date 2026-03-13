<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function show(string $policy): View
    {
        $policies = [
            'terminos-y-condiciones' => ['file' => 'terms.md', 'title' => __('Terminos y condiciones')],
            'privacidad' => ['file' => 'privacy.md', 'title' => __('Politica de privacidad')],
            'devoluciones' => ['file' => 'returns.md', 'title' => __('Politica de devoluciones')],
            'envios' => ['file' => 'shipping.md', 'title' => __('Politica de envios')],
        ];

        if (! array_key_exists($policy, $policies)) {
            abort(404);
        }

        $path = resource_path('policies/'.$policies[$policy]['file']);

        if (! File::exists($path)) {
            abort(404);
        }

        $markdown = File::get($path);

        return view('pages.storefront.policy', [
            'title' => $policies[$policy]['title'],
            'content' => Str::markdown($markdown),
        ]);
    }
}
