<?php

namespace App\Services\MedicalRecords;

class ClinicalHtml
{
    public function clean(string $html): string
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            $wrapper = $body?->firstElementChild;

            return $wrapper ? $this->children($wrapper) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function children(\DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $result .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'form', 'input', 'img'], true)) {
                continue;
            }
            $content = $this->children($child);
            if (! in_array($tag, ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'hr'], true)) {
                $result .= $content;

                continue;
            }
            $attributes = '';
            if ($tag === 'a' && preg_match('~^https?://~i', trim($child->getAttribute('href')))) {
                $attributes .= ' href="'.htmlspecialchars(trim($child->getAttribute('href')), ENT_QUOTES, 'UTF-8').'" rel="noopener noreferrer"';
            }
            if (in_array($child->getAttribute('dir'), ['rtl', 'ltr'], true)) {
                $attributes .= ' dir="'.$child->getAttribute('dir').'"';
            }
            if (preg_match('/^text-align:\s*(left|right|center|justify);?$/', trim($child->getAttribute('style')), $matches)) {
                $attributes .= ' style="text-align:'.$matches[1].'"';
            }
            $result .= '<'.$tag.$attributes.'>'.(in_array($tag, ['br', 'hr'], true) ? '' : $content.'</'.$tag.'>');
        }

        return $result;
    }
}
