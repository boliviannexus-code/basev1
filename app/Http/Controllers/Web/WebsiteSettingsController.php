<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteSettingsRequest;
use App\Services\WebsiteContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteSettingsController extends Controller
{
    public function __construct(
        private readonly WebsiteContentService $website,
    ) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()?->can('website.manage'), 403);

        return view('website-settings.edit', [
            'settings' => $this->website->settings(),
            'tours' => $this->website->toursForSelect(),
        ]);
    }

    public function update(UpdateWebsiteSettingsRequest $request): RedirectResponse
    {
        $this->website->update($request->validated());

        return redirect()->route('website-settings.edit')->with('success', 'Contenido de la pagina web actualizado correctamente.');
    }
}
