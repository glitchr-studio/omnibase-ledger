<?php

namespace Base\Ledger\Model;

/** How bank labels, names and references are compared: upper-case, without accents nor punctuation. */
final class Text
{
    private const ACCENTS = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ø' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Í' => 'I', 'Ì' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U', 'Ÿ' => 'Y', 'Ñ' => 'N', 'Œ' => 'OE', 'Æ' => 'AE'];

    /** "Loyer d'Octobre - Dupont" -> "LOYER D OCTOBRE DUPONT" */
    public static function fold(?string $text): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(strtr((string) $text, self::ACCENTS))));
    }

    /** "AVIS-2026-10/U3" -> "AVIS202610U3": references survive the bank's own spacing and punctuation. */
    public static function squash(?string $text): string
    {
        return str_replace(' ', '', self::fold($text));
    }
}
