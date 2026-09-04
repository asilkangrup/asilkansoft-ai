<?php

namespace App\Http\Controllers;

use App\Services\WaiLeadDemoService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WaiLeadDemoController extends Controller
{
    public function show(string $token, WaiLeadDemoService $service): View
    {
        $lead = $service->get($token);

        if (! is_array($lead)) {
            throw new NotFoundHttpException('Demo bağlantısının süresi dolmuş veya bağlantı geçersiz.');
        }

        return view('wai-lead-demo', [
            'token' => $token,
            'lead' => $lead,
        ]);
    }
}
