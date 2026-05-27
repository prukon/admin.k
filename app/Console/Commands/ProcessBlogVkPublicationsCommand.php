<?php

namespace App\Console\Commands;

use App\Services\BlogVk\BlogVkPublicationCoordinator;
use Illuminate\Console\Command;

class ProcessBlogVkPublicationsCommand extends Command
{
    protected $signature = 'blog:process-vk-publications';

    protected $description = 'Публикует в VK статьи блога, ожидающие дату или обложку';

    public function handle(BlogVkPublicationCoordinator $coordinator): int
    {
        $processed = $coordinator->processDuePublications();

        if ($processed > 0) {
            $this->info("Обработано статей: {$processed}");
        }

        return self::SUCCESS;
    }
}
