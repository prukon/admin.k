<?php

declare(strict_types=1);

namespace Tests\Unit\Services\InAppNotifications;

use App\Services\InAppNotifications\InAppNotificationBodyHtml;
use PHPUnit\Framework\TestCase;

final class InAppNotificationBodyHtmlTest extends TestCase
{
    public function test_preview_keeps_paragraph_breaks_as_newlines(): void
    {
        $html = '<p>Первая строка</p><p>Вторая строка</p>';

        $this->assertSame("Первая строка\nВторая строка", InAppNotificationBodyHtml::preview($html, 60));
    }

    public function test_preview_keeps_br_and_collapses_nl2br_double_break(): void
    {
        $fromBr = InAppNotificationBodyHtml::preview("Строка 1<br>Строка 2", 60);
        $fromNl2br = InAppNotificationBodyHtml::preview("Строка 1<br>\nСтрока 2", 60);

        $this->assertSame("Строка 1\nСтрока 2", $fromBr);
        $this->assertSame("Строка 1\nСтрока 2", $fromNl2br);
    }

    public function test_preview_keeps_list_item_breaks(): void
    {
        $html = '<ul><li>Один</li><li>Два</li></ul>';

        $this->assertSame("Один\nДва", InAppNotificationBodyHtml::preview($html, 60));
    }

    public function test_preview_collapses_horizontal_whitespace_but_not_newlines(): void
    {
        $html = "<p>А   Б</p>\n<p>В</p>";

        $this->assertSame("А Б\nВ", InAppNotificationBodyHtml::preview($html, 60));
    }

    public function test_preview_still_limits_length(): void
    {
        $body = str_repeat('А', 90);
        $preview = InAppNotificationBodyHtml::preview($body, 60);

        $this->assertLessThanOrEqual(63, mb_strlen($preview));
        $this->assertStringEndsWith('...', $preview);
    }

    public function test_preview_keeps_br_inside_paragraph_as_in_attach_event(): void
    {
        $html = '<p>Родитель: Иванов Иван<br>Ребёнок: Комарова Ярослав.<br>Добавлена группа «НовГруппа» (объект «Тестовый объект»).</p>';
        $display = InAppNotificationBodyHtml::toDisplayHtml($html);
        $preview = InAppNotificationBodyHtml::preview($display, 200);

        $this->assertSame(
            "Родитель: Иванов Иван\nРебёнок: Комарова Ярослав.\nДобавлена группа «НовГруппа» (объект «Тестовый объект»).",
            $preview
        );
    }

    public function test_preview_strips_markup_without_embedding_tags(): void
    {
        $html = '<p>Кабинет <a href="/cabinet">сюда</a></p><p>Дальше</p>';

        $this->assertSame("Кабинет сюда\nДальше", InAppNotificationBodyHtml::preview($html, 60));
    }
}
