<?php

namespace App\Http\Controllers\PublicSite;

use App\Core\Notifications\NotificationGovernanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactRequestsController extends Controller
{
    public function bookDemo(Request $request): Response
    {
        return $this->renderForm($request, ContactRequest::REQUEST_TYPE_DEMO);
    }

    public function contactSales(Request $request): Response
    {
        return $this->renderForm($request, ContactRequest::REQUEST_TYPE_SALES);
    }

    public function store(
        StoreContactRequestRequest $request,
        NotificationGovernanceService $notificationGovernance
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['website']);

        $redirectRoute = $data['request_type'] === ContactRequest::REQUEST_TYPE_DEMO
            ? 'public.book-demo'
            : 'public.contact-sales';

        $recentDuplicate = ContactRequest::query()
            ->where('request_type', $data['request_type'])
            ->whereRaw('LOWER(work_email) = ?', [Str::lower($data['work_email'])])
            ->whereRaw('LOWER(company_name) = ?', [Str::lower($data['company_name'])])
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($recentDuplicate) {
            return redirect()
                ->route($redirectRoute)
                ->with('warning', 'A similar request was already submitted recently. The team will follow up using the existing request.');
        }

        $contactRequest = ContactRequest::create([
            ...$data,
            'status' => ContactRequest::STATUS_NEW,
        ]);

        $superAdmins = User::query()
            ->where('is_super_admin', true)
            ->get(['id', 'name', 'email']);

        if ($superAdmins->isNotEmpty()) {
            $notificationGovernance->notify(
                recipients: $superAdmins,
                notification: new ContactRequestSubmittedNotification($contactRequest),
                severity: 'medium',
                context: [
                    'event' => 'New public contact request',
                    'source' => 'public.contact_requests',
                    'details' => sprintf(
                        '%s request from %s (%s).',
                        $contactRequest->request_type,
                        $contactRequest->company_name,
                        $contactRequest->work_email,
                    ),
                ],
            );
        }

        return redirect()
            ->route($redirectRoute)
            ->with(
                'success',
                $contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO
                    ? 'Demo request received. The team will review it and follow up.'
                    : 'Sales request received. The team will review it and follow up.',
            );
    }

    private function renderForm(Request $request, string $requestType): Response
    {
        $isDemo = $requestType === ContactRequest::REQUEST_TYPE_DEMO;

        return Inertia::render('public/contact-request', [
            'requestType' => $requestType,
            'sourcePage' => $isDemo ? '/book-demo' : '/contact-sales',
            'hero' => [
                'eyebrow' => $isDemo ? 'Book demo' : 'Contact sales',
                'title' => $isDemo
                    ? 'Show the right Port-101 workflows in a guided demo.'
                    : 'Start a serious sales conversation around your operating model.',
                'description' => $isDemo
                    ? 'Share a little context so the demo can focus on the workflows, controls, and modules that matter to your team.'
                    : 'Share your company context and the team can respond with the right commercial, rollout, and control discussion.',
                'highlights' => $isDemo
                    ? [
                        'Focus the walkthrough on your operational priorities.',
                        'Propose a date the team can aim to confirm.',
                        'Use the same product surface shown on the public site.',
                        'Keep the request tied to real module and workflow needs.',
                    ]
                    : [
                        'Describe the business areas you want to bring into one ERP system.',
                        'Give the team enough context to respond without a back-and-forth just to qualify the request.',
                        'Keep the path grounded in rollout fit, control needs, and module coverage.',
                    ],
            ],
            'teamSizeOptions' => ContactRequest::TEAM_SIZE_OPTIONS,
            'moduleOptions' => ContactRequest::MODULE_OPTIONS,
        ]);
    }
}
