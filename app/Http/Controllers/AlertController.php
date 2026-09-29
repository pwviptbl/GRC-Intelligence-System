<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function __construct(protected AlertService $alertService)
    {
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->alertService->getAlertSummary());
    }
}
