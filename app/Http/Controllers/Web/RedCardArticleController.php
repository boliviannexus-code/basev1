<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RedCard\StoreRedCardArticleRequest;
use App\Http\Requests\RedCard\UpdateRedCardArticleRequest;
use App\Models\Company;
use App\Models\RedCardArticle;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RedCardArticleController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('red-card-articles.view'), 403);

        $articles = RedCardArticle::query()
            ->with('company')
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderBy('number')
            ->paginate(15);

        return view('red-card-articles.index', compact('articles'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->can('red-card-articles.create'), 403);

        return view('red-card-articles.create', [
            'article' => new RedCardArticle(),
            'companies' => $this->companiesForSelect(),
        ]);
    }

    public function store(StoreRedCardArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['company_id'] = CompanyContext::id($request->user()) ?? $request->integer('company_id');

        RedCardArticle::query()->create($data);

        return redirect()->route('red-card-articles.index')->with('success', 'Articulo registrado correctamente.');
    }

    public function edit(RedCardArticle $redCardArticle): View
    {
        $this->ensureArticleVisible($redCardArticle, 'red-card-articles.update');

        return view('red-card-articles.edit', ['article' => $redCardArticle]);
    }

    public function update(UpdateRedCardArticleRequest $request, RedCardArticle $redCardArticle): RedirectResponse
    {
        $this->ensureArticleVisible($redCardArticle, 'red-card-articles.update');
        $redCardArticle->update($request->validated());

        return redirect()->route('red-card-articles.index')->with('success', 'Articulo actualizado correctamente.');
    }

    public function destroy(RedCardArticle $redCardArticle): RedirectResponse
    {
        $this->ensureArticleVisible($redCardArticle, 'red-card-articles.delete');
        $redCardArticle->delete();

        return redirect()->route('red-card-articles.index')->with('success', 'Articulo eliminado correctamente.');
    }

    private function ensureArticleVisible(RedCardArticle $article, string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
        abort_unless(CompanyContext::belongsToUser($article->company_id, auth()->user()), 403);
    }

    private function companiesForSelect()
    {
        return Company::query()
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->whereKey($companyId))
            ->orderBy('name')
            ->get();
    }
}
