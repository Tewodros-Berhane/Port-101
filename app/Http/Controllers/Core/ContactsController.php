<?php

namespace App\Http\Controllers\Core;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ContactStoreRequest;
use App\Http\Requests\Core\ContactUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->with('partner')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/contacts/index', [
            'contacts' => $contacts->through(function (Contact $contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'partner' => $contact->partner?->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'title' => $contact->title,
                    'is_primary' => $contact->is_primary,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Contact::class);

        return Inertia::render('core/contacts/create', [
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(ContactStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $user = $request->user();

        $contact = Contact::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.contacts.edit', $contact)
            ->with('success', 'Contact created.');
    }

    public function edit(Contact $contact): Response
    {
        $this->authorize('update', $contact);

        $attachments = Attachment::query()
            ->where('attachable_type', $contact::class)
            ->where('attachable_id', $contact->id)
            ->latest('created_at')
            ->get()
            ->map(function (Attachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) $attachment->size,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                    'download_url' => route('core.attachments.download', $attachment),
                ];
            });

        return Inertia::render('core/contacts/edit', [
            'contact' => [
                'id' => $contact->id,
                'partner_id' => $contact->partner_id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'title' => $contact->title,
                'is_primary' => $contact->is_primary,
            ],
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name', 'code']),
            'attachments' => $attachments,
        ]);
    }

    public function update(ContactUpdateRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $user = $request->user();

        $contact->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.contacts.edit', $contact)
            ->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()
            ->route('core.contacts.index')
            ->with('success', 'Contact removed.');
    }
}
