<?php

namespace App\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

class ContentSanitizer
{
    /**
     * Whitelist tag HTML yang diizinkan untuk artikel blog.
     *
     * @var list<string>
     */
    protected static array $allowedTags = [
        'div',
        'span',
        'p',
        'br',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'strike',
        'del',
        'blockquote',
        'ul',
        'ol',
        'li',
        'hr',
        'code',
        'pre',
        'table',
        'thead',
        'tbody',
        'tr',
        'th',
        'td',
        'a',
        'img',
    ];

    /**
     * Sanitasi konten HTML dari Rich Text Editor (Tiptap).
     */
    public static function clean(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        // 1. Bersihkan komentar kondisional dan tag script/iframe/style berbahaya sebelum parsing
        $cleaned = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $trimmed) ?? '';
        $cleaned = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $cleaned) ?? '';
        $cleaned = preg_replace('#<iframe\b[^>]*>(.*?)</iframe>#is', '', $cleaned) ?? '';
        $cleaned = preg_replace('#<object\b[^>]*>(.*?)</object>#is', '', $cleaned) ?? '';
        $cleaned = preg_replace('#<embed\b[^>]*>(.*?)</embed>#is', '', $cleaned) ?? '';

        // 2. Parse menggunakan DOMDocument
        $dom = new DOMDocument();
        $libxmlState = libxml_use_internal_errors(true);
        @$dom->loadHTML('<div>' . $cleaned . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof DOMElement) {
            return strip_tags($cleaned);
        }

        // Sanitasi semua child di dalam root div
        $children = [];
        foreach ($root->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            self::sanitizeNode($child);
        }

        // Ambil isi root div kembali
        $output = '';
        foreach ($root->childNodes as $child) {
            $saved = $dom->saveHTML($child);
            if ($saved !== false) {
                $output .= $saved;
            }
        }

        return trim($output);
    }

    protected static function sanitizeNode(DOMNode $node): void
    {
        // Hanya elemen HTML yang diproses atributnya
        if (!$node instanceof DOMElement) {
            return;
        }

        $element = $node;
        $tagName = strtolower($element->nodeName);

        // Jika tag tidak ada dalam whitelist, unwrap anak-anaknya dan hapus tag ini
        if (!in_array($tagName, self::$allowedTags, true)) {
            $parent = $element->parentNode;
            if ($parent !== null) {
                while ($element->hasChildNodes()) {
                    $child = $element->firstChild;
                    if ($child !== null) {
                        $element->removeChild($child);
                        $parent->insertBefore($child, $element);
                    }
                }
                $parent->removeChild($element);
            }
            return;
        }

        // Sanitasi atribut
        if ($element->hasAttributes() && $element->attributes !== null) {
            $attributesToRemove = [];
            foreach ($element->attributes as $attr) {
                if (!$attr instanceof DOMAttr) {
                    continue;
                }

                $attrName = strtolower($attr->nodeName);
                $attrValue = trim($attr->nodeValue ?? '');

                // Blokir event handler (on*)
                if (str_starts_with($attrName, 'on')) {
                    $attributesToRemove[] = $attrName;
                    continue;
                }

                // Tag <a> hanya boleh href, target, rel
                if ($tagName === 'a') {
                    if ($attrName === 'href') {
                        if (preg_match('#^(javascript|vbscript|data):#i', $attrValue)) {
                            $attributesToRemove[] = $attrName;
                        }
                    } elseif ($attrName === 'target') {
                        if ($attrValue === '_blank') {
                            $element->setAttribute('rel', 'noopener noreferrer');
                        }
                    } elseif ($attrName !== 'rel') {
                        $attributesToRemove[] = $attrName;
                    }
                    continue;
                }

                // Tag <img> hanya boleh src, alt, title, width, height
                if ($tagName === 'img') {
                    if ($attrName === 'src') {
                        if (preg_match('#^(javascript|vbscript):#i', $attrValue)) {
                            $attributesToRemove[] = $attrName;
                        }
                    } elseif (!in_array($attrName, ['alt', 'title', 'width', 'height'], true)) {
                        $attributesToRemove[] = $attrName;
                    }
                    continue;
                }

                // Tag lainnya izinkan hanya class standar jika ada
                if (!in_array($attrName, ['class'], true)) {
                    $attributesToRemove[] = $attrName;
                }
            }

            foreach ($attributesToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }
        }

        // Sanitasi anak-anak secara rekursif
        $children = [];
        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::sanitizeNode($child);
        }
    }
}
