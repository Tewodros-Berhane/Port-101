<?php

use App\Http\Controllers\Approvals\ApprovalsDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('approvals', [ApprovalsDashboardController::class, 'index'])
    ->name('modules.approvals');

Route::post('approvals/{approvalRequest}/approve', [ApprovalsDashboardController::class, 'approve'])
    ->name('approvals.approve');

Route::post('approvals/{approvalRequest}/reject', [ApprovalsDashboardController::class, 'reject'])
    ->name('approvals.reject');
