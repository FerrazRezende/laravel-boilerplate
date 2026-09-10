<?php

declare(strict_types=1);

namespace Modules\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Modules\Profile\Http\Requests\UpdateLocaleRequest;

class LocaleController extends Controller
{
    /**
     * Update the user's locale preference.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        // Set locale immediately for current request
        App::setLocale($request->validated('locale'));

        return redirect()
            ->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Language updated successfully'),
            ]);
    }
}
