<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Amount in words for money receipts — South-Asian grouping
 * (thousand / lakh / crore), in Bangla or English.
 *   Money::words(3333.50, 'bn') => "তিন হাজার তিনশত তেত্রিশ টাকা পঞ্চাশ পয়সা মাত্র"
 *   Money::words(3333.50, 'en') => "Three thousand three hundred thirty-three taka and fifty paisa only"
 */
final class Money
{
    private const BN = ['শূন্য', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়', 'দশ',
        'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোলো', 'সতেরো', 'আঠারো', 'উনিশ', 'বিশ',
        'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'উনত্রিশ', 'ত্রিশ',
        'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'উনচল্লিশ', 'চল্লিশ',
        'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'উনপঞ্চাশ', 'পঞ্চাশ',
        'একান্ন', 'বায়ান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'উনষাট', 'ষাট',
        'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'উনসত্তর', 'সত্তর',
        'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'উনআশি', 'আশি',
        'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'আটাশি', 'উননব্বই', 'নব্বই',
        'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই'];

    private const EN_ONES = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    private const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    public static function words(float|int|string $amount, ?string $locale = null): string
    {
        $locale ??= function_exists('current_locale') ? current_locale() : 'en';
        $amount = round((float) $amount, 2);
        $taka = (int) floor($amount);
        $paisa = (int) round(($amount - $taka) * 100);

        if ($locale === 'bn') {
            $text = self::group($taka, 'bn') . ' টাকা';
            if ($paisa > 0) {
                $text .= ' ' . self::BN[$paisa] . ' পয়সা';
            }
            return $text . ' মাত্র';
        }
        $text = ucfirst(self::group($taka, 'en')) . ' taka';
        if ($paisa > 0) {
            $text .= ' and ' . self::below100($paisa) . ' paisa';
        }
        return $text . ' only';
    }

    /** Split into crore / lakh / thousand / hundred / rest. */
    private static function group(int $n, string $locale): string
    {
        if ($n === 0) {
            return $locale === 'bn' ? self::BN[0] : 'zero';
        }
        $parts = [];
        $crore = intdiv($n, 10_000_000);
        $n %= 10_000_000;
        $lakh = intdiv($n, 100_000);
        $n %= 100_000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        $hundred = intdiv($n, 100);
        $rest = $n % 100;

        $unit = $locale === 'bn'
            ? ['crore' => 'কোটি', 'lakh' => 'লক্ষ', 'thousand' => 'হাজার']
            : ['crore' => 'crore', 'lakh' => 'lakh', 'thousand' => 'thousand'];
        if ($crore > 0) {
            // Crore can exceed 99 (e.g. 150 crore) — recurse.
            $parts[] = self::group($crore, $locale) . ' ' . $unit['crore'];
        }
        foreach (['lakh' => $lakh, 'thousand' => $thousand] as $name => $value) {
            if ($value > 0) {
                $parts[] = ($locale === 'bn' ? self::BN[$value] : self::below100($value)) . ' ' . $unit[$name];
            }
        }
        if ($hundred > 0) {
            $parts[] = $locale === 'bn' ? self::BN[$hundred] . 'শত' : self::EN_ONES[$hundred] . ' hundred';
        }
        if ($rest > 0) {
            $parts[] = $locale === 'bn' ? self::BN[$rest] : self::below100($rest);
        }
        return implode(' ', $parts);
    }

    private static function below100(int $n): string
    {
        if ($n < 20) {
            return self::EN_ONES[$n];
        }
        return self::EN_TENS[intdiv($n, 10)] . ($n % 10 > 0 ? '-' . self::EN_ONES[$n % 10] : '');
    }
}
