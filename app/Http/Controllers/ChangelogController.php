<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Changelog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "Novedades del sitio" welcome modal the dashboard shows while there are
 * unseen changes, plus the page that lists every past entry.
 */
class ChangelogController extends Controller
{
    public function __construct(private Changelog $changelog) {}

    public function index(): View
    {
        return view('changelog.index', [
            'entries' => $this->changelog->all(),
        ]);
    }

    /**
     * The welcome modal is acknowledged: the account remembers the latest entry
     * and it won't be shown again until something newer is released. The "see
     * all" button sends next=all to land on the full list.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->changelog->markSeen($user);

        if ($request->input('next') === 'all') {
            return redirect()->route('changelog.index');
        }

        return redirect()->back();
    }
}
