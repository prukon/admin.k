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

        $this->line('Worker должен слушать очереди: default,blog_vk,platform_outbound_mail');

        return self::SUCCESS;
    }
}
