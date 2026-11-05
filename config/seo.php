<?php

return [
    // Only the live site may be indexed (docs/SEO.md §0). Staging and local stay noindex.
    // Set SEO_INDEXABLE=true in the production ENV_FILE only, at cutover.
    'indexable' => (bool) env('SEO_INDEXABLE', false),
];
