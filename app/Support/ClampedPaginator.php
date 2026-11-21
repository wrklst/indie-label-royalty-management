<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The paginator of every list. Asked for a page beyond the last one, which
 * is what happens after deleting the last row of the last page, the visit
 * is sent on to the last page instead of showing an empty one. Each list's
 * own page parameter (`costs_page`, `files_page`) is kept apart.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends LengthAwarePaginator<TKey, TValue>
 */
class ClampedPaginator extends LengthAwarePaginator
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        parent::__construct($items, $total, $perPage, $currentPage, $options);

        $request = request();

        if ($total > 0 && $this->currentPage() > $this->lastPage() && $request->isMethod('get')) {
            throw new HttpResponseException(redirect(rtrim($request->fullUrlWithQuery([
                $this->getPageName() => $this->lastPage() > 1 ? $this->lastPage() : null,
            ]), '?')));
        }
    }
}
