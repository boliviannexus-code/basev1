<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth()->user()?->can('companies.update'), 403);
        $company = CompanyContext::activeCompany(auth()->user());
        abort_unless($company, 404);

        return view('website-page.edit', compact('company'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('companies.update'), 403);
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 404);

        $data = $request->validate([
            'public_page_title' => ['nullable', 'string', 'max:160'],
            'public_page_summary' => ['nullable', 'string', 'max:500'],
            'public_page_body' => ['nullable', 'string', 'max:3000'],
            'public_contact_text' => ['nullable', 'string', 'max:1000'],
            'public_whatsapp' => ['nullable', 'string', 'max:40'],
            'public_facebook_url' => ['nullable', 'string', 'max:255'],
            'public_instagram_url' => ['nullable', 'string', 'max:255'],
            'public_tiktok_url' => ['nullable', 'string', 'max:255'],
            'public_youtube_url' => ['nullable', 'string', 'max:255'],
            'public_banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'public_image_one' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'public_image_two' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'public_page_is_enabled' => ['sometimes', 'boolean'],
        ]);
        $data['public_page_is_enabled'] = $request->boolean('public_page_is_enabled');

        foreach ([
            'public_banner' => 'public_banner_path',
            'public_image_one' => 'public_image_one_path',
            'public_image_two' => 'public_image_two_path',
        ] as $input => $column) {
            if ($request->hasFile($input)) {
                if ($company->{$column}) {
                    Storage::disk('public')->delete($company->{$column});
                }

                $data[$column] = $request->file($input)->store('companies/public-pages', 'public');
            }
        }

        unset($data['public_banner'], $data['public_image_one'], $data['public_image_two']);

        $company->update($data);

        return redirect()->route('website-page.edit')->with('success', 'Pagina web actualizada correctamente.');
    }
}
