<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ContactRequestStatusUpdateRequest;
use App\Models\ContactRequest;
use App\Notifications\DemoScheduledConfirmationNotification;
use App\Support\Http\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ContactRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')) ?: null,
            'request_type' => trim((string) $request->input('request_type', '')) ?: null,
            'status' => trim((string) $request->input('status', '')) ?: null,
        ];

        $contactRequests = ContactRequest::query()
            ->when($filters['search'], function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('role_title', 'like', "%{$search}%");
                });
            })
            ->when($filters['request_type'], function ($query, string $requestType) {
                $query->where('request_type', $requestType);
            })
            ->when($filters['status'], function ($query, string $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('platform/contact-requests/index', [
            'contactRequests' => $contactRequests->through(function (ContactRequest $contactRequest) {
                return [
                    'id' => $contactRequest->id,
                    'request_type' => $contactRequest->request_type,
                    'full_name' => $contactRequest->full_name,
                    'work_email' => $contactRequest->work_email,
                    'company_name' => $contactRequest->company_name,
                    'role_title' => $contactRequest->role_title,
                    'team_size' => $contactRequest->team_size,
                    'preferred_demo_date' => $contactRequest->preferred_demo_date?->toDateString(),
                    'scheduled_demo_date' => $contactRequest->scheduled_demo_date?->toDateString(),
                    'modules_interest' => $contactRequest->modules_interest ?? [],
                    'message' => $contactRequest->message,
                    'phone' => $contactRequest->phone,
                    'country' => $contactRequest->country,
                    'source_page' => $contactRequest->source_page,
                    'status' => $contactRequest->status,
                    'assigned_to' => $contactRequest->assignedTo?->name,
                    'created_at' => $contactRequest->created_at?->toIso8601String(),
                    'updated_at' => $contactRequest->updated_at?->toIso8601String(),
                ];
            }),
            'filters' => $filters,
            'requestTypeOptions' => array_map(
                fn (string $value) => [
                    'value' => $value,
                    'label' => $value === ContactRequest::REQUEST_TYPE_DEMO ? 'Book demo' : 'Contact sales',
                ],
                ContactRequest::REQUEST_TYPES,
            ),
            'statusOptions' => array_map(
                fn (string $value) => [
                    'value' => $value,
                    'label' => str($value)->replace('_', ' ')->title()->toString(),
                ],
                ContactRequest::STATUS_OPTIONS,
            ),
        ]);
    }

    public function update(
        ContactRequestStatusUpdateRequest $request,
        ContactRequest $contactRequest
    ): RedirectResponse {
        $validated = $request->validated();
        $previousScheduledDemoDate = $contactRequest->scheduled_demo_date?->toDateString();

        $contactRequest->status = $validated['status'];

        if ($contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO) {
            if (array_key_exists('scheduled_demo_date', $validated)) {
                $contactRequest->scheduled_demo_date = $validated['scheduled_demo_date'];
            }

            if (array_key_exists('demo_date_change_reason', $validated)) {
                $contactRequest->demo_date_change_reason = $validated['demo_date_change_reason'];
            }
        }

        $contactRequest->save();

        $scheduledDemoDateChanged = $contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO
            && $previousScheduledDemoDate !== $contactRequest->scheduled_demo_date?->toDateString();
        $emailSent = false;

        if (
            $contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO
            && $scheduledDemoDateChanged
            && $contactRequest->scheduled_demo_date
        ) {
            Notification::route('mail', $contactRequest->work_email)
                ->notify(new DemoScheduledConfirmationNotification(
                    $contactRequest,
                    $previousScheduledDemoDate,
                    $validated['demo_date_change_reason'] ?? null,
                ));

            $emailSent = true;
        }

        return back(303)->with(
            'success',
            Feedback::flash(
                $request,
                $emailSent
                    ? 'Request updated. Demo email sent to the requester.'
                    : 'Request status updated.',
            ),
        );
    }
}
