<?php

namespace App\Http\Controllers\Core;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttachmentsController extends Controller
{
    /**
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const ATTACHABLE_TYPES = [
        'partner' => Partner::class,
        'contact' => Contact::class,
        'address' => Address::class,
        'product' => Product::class,
        'tax' => Tax::class,
        'currency' => Currency::class,
        'uom' => Uom::class,
        'price_list' => PriceList::class,
    ];

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Attachment::class);

        $data = $request->validate([
            'attachable_type' => [
                'required',
                'string',
                Rule::in(array_keys(self::ATTACHABLE_TYPES)),
            ],
            'attachable_id' => ['required', 'uuid'],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('core.attachments.max_size_kb', 10240),
            ],
        ]);

        $modelClass = self::ATTACHABLE_TYPES[$data['attachable_type']];
        $attachable = $modelClass::query()->findOrFail($data['attachable_id']);
        $companyId = $request->user()?->current_company_id;

        if (
            isset($attachable->company_id)
            && (string) $attachable->company_id !== (string) $companyId
        ) {
            abort(403, 'Attachment target is outside the active company.');
        }

        $file = $request->file('file');
        $disk = (string) config('core.attachments.disk', 'local');
        $path = $file->store(
            'attachments/'.$companyId.'/'.strtolower(class_basename($modelClass)),
            $disk
        );

        Attachment::create([
            'company_id' => $companyId,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'file_name' => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->extension(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(Attachment $attachment)
    {
        $this->authorize('view', $attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
