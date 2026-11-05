<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Seo\Seo;

/** Public verification page for a printed/shared certificate. Shows no private data. */
class CertificateController extends Controller
{
    public function __invoke(string $code)
    {
        $certificate = Certificate::with(['author', 'post'])->where('code', strtoupper($code))->firstOrFail();
        $seo = new Seo(title: 'সনদ যাচাই · '.$certificate->code, noindex: true);

        return view('certificate', compact('certificate', 'seo'));
    }
}
