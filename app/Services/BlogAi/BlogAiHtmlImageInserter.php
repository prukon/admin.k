<?php

namespace App\Services\BlogAi;

class BlogAiHtmlImageInserter
{
    public function insertInlineImage(string $html, int $inlineIndex, int $totalInline, string $imgUrl, string $alt): string
    {
        $totalInline = max(1, $totalInline);
        $inlineIndex = max(0, min($totalInline - 1, $inlineIndex));

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . '<div id="root">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($doc);
        $root = $xpath->query('//div[@id="root"]')->item(0);
        if (!$root) {
            return $html;
        }

        $anchor = $this->pickAnchorNode($xpath, $inlineIndex, $totalInline);

        $figure = $doc->createElement('figure');
        $figure->setAttribute('class', 'my-3');

        $img = $doc->createElement('img');
        $img->setAttribute('src', $imgUrl);
        $img->setAttribute('alt', $alt);
        $img->setAttribute('class', 'img-fluid rounded');
        $figure->appendChild($img);

        if ($anchor && $anchor->parentNode) {
            if ($anchor->nextSibling) {
                $anchor->parentNode->insertBefore($figure, $anchor->nextSibling);
            } else {
                $anchor->parentNode->appendChild($figure);
            }
        } else {
            $root->appendChild($figure);
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out !== '' ? $out : $html;
    }

    private function pickAnchorNode(\DOMXPath $xpath, int $inlineIndex, int $totalInline): ?\DOMNode
    {
        // Prefer headings: insert between sections
        $h2 = $xpath->query('//div[@id="root"]//h2');
        if ($h2 && $h2->length > 0) {
            $h2Count = $h2->length;
            // UX: первую картинку ставим после первого H2, вторую — после второго и т.д.
            // Если H2 меньше, чем картинок — остальные тоже ставим после последнего доступного H2.
            $pos = max(0, min($h2Count - 1, $inlineIndex));
            return $h2->item($pos);
        }

        // Fallback: big blocks among direct children
        $blocks = $xpath->query('//div[@id="root"]/*[self::p or self::ul or self::ol or self::table or self::blockquote or self::pre]');
        if ($blocks && $blocks->length > 0) {
            $count = $blocks->length;
            $pos = (int) floor((($inlineIndex + 1) * $count) / ($totalInline + 1));
            $pos = max(0, min($count - 1, $pos));
            return $blocks->item($pos);
        }

        // Last resort: any child element
        $any = $xpath->query('//div[@id="root"]/*');
        if ($any && $any->length > 0) {
            $count = $any->length;
            $pos = (int) floor((($inlineIndex + 1) * $count) / ($totalInline + 1));
            $pos = max(0, min($count - 1, $pos));
            return $any->item($pos);
        }

        return null;
    }
}

