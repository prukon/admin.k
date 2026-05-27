<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\BlogVk\BlogVkPublicationCoordinator;

class BlogPostObserver
{
    public function __construct(
        private readonly BlogVkPublicationCoordinator $coordinator,
    ) {
    }

    public function saved(BlogPost $post): void
    {
        $post->loadMissing(['category', 'socialPublications']);
        $this->coordinator->syncForPost($post);
    }
}
