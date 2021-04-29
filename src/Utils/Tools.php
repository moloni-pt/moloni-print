<?php

namespace MoloniPrint\Utils;


class Tools
{
    public static $exchangeRate = 1;
    public static $currency = '€';
    public static $symbolRight = 1;

    public static function dateFormat($dateInput, $format = 'd-m-Y H:i', $timezone = false)
    {
        try {
            $date = new \DateTime($dateInput);

            if ($timezone) {
                $date->setTimezone(new \DateTimeZone($timezone));
            }

            $dateFormatted = $date->format($format);
        } catch (\Exception $exception) {
            $dateFormatted = $dateInput;
        }

        return $dateFormatted;
    }

    /**
     * Parse a string into multiple strings with a maximum of $availableLineLength chars
     * The string will be broken in white spaces and dashes if possible
     * @param $text string
     * @param $availableLineLength int
     * @return string
     */
    public static function wrapText($text, $availableLineLength)
    {
        $textLength = mb_strlen($text);
        $offset = 0;
        $wrappedLine = '';

        if ((int)$textLength > (int)$availableLineLength) {
            $whileCounter = 0;
            while (($textLength - $offset) > $availableLineLength) {
                $whileCounter++;

                if ($whileCounter > 35) {
                    break;
                }

                if ($text[$offset] == ' ') {
                    $offset++;
                    continue;
                }

                $negativeOffset = $textLength - $availableLineLength - $offset;
                $spaceIndex = @mb_strrpos($text, ' ', -($negativeOffset));
                $hyphenIndex = @mb_strrpos($text, '-', -($negativeOffset) - 1);
                $lfIndex = @mb_strrpos($text, "\n", -($negativeOffset));

                $hyphenIndex++;

                $spaceToWrapAt = max($spaceIndex, $hyphenIndex);

                if (($lfIndex >= 0) && ($lfIndex > $offset) && ($lfIndex < $spaceToWrapAt)) {
                    $spaceToWrapAt = $lfIndex;
                }

                if ($spaceToWrapAt >= $offset) {
                    if ($spaceToWrapAt == 0) {
                        $wrappedLine .= (mb_substr($text, $offset, $availableLineLength) . "\n");
                        $offset += $availableLineLength;
                    } else {
                        $wrappedLine .= (mb_substr($text, $offset, ($spaceToWrapAt - $offset)) . "\n");
                        $offset = $spaceToWrapAt + ($spaceToWrapAt == $hyphenIndex ? 0 : 1);
                    }
                } else {
                    $wrappedLine .= (mb_substr($text, $offset, $availableLineLength) . "\n");
                    $offset += $availableLineLength;
                }
            }

            $wrappedLine .= mb_substr($text, $offset);
            return $wrappedLine;

        } else {
            return $text;
        }
    }

    /**
     * Formats a float value into a price string (can also be used to format percentage values)
     * @param float $price
     * @param string|false $currencySymbol
     * @param int $decimals
     * @param string $decimalSeparator
     * @param string $thousandsSeparator
     * @param bool $currencyRight The currency symbol will be put on the right or left of the value
     * @param bool|float $exchangeRate
     * @return string
     */
    public static function priceFormat($price, $currencySymbol = false, $decimals = 2, $decimalSeparator = ',', $thousandsSeparator = '.', $currencyRight = true, $exchangeRate = false)
    {

        if (!$currencySymbol) {
            $currencySymbol = self::$currency;
        }

        $exchangeRate = $exchangeRate ? $exchangeRate : self::$exchangeRate;
        $currencySymbolRight = ($currencyRight && self::$symbolRight);

        $price = preg_replace("/[^\d\-.,]/", '', $price);
        $priceWithoutCommas = preg_replace("/[^\d\-]/", ".", $price);
        $priceSplit = explode('.', $priceWithoutCommas);
        $priceLength = count($priceSplit);

        if ($priceLength > 1) {
            $priceEnd = $priceLength - 1;
            $priceStart = array_slice($priceSplit, 0, $priceEnd);
            $price = implode('', $priceStart) . '.' . $priceSplit[$priceEnd];
        }

        if (!is_numeric($price)) {
            return '';
        }


        if ($currencySymbol === '%') {
            $exchangeRate = 1;
            $currencySymbolRight = 1;
        }

        if ($exchangeRate !== (float)1) {
            $price = $price * $exchangeRate;
        }

        $formatted = number_format($price, $decimals, $decimalSeparator, $thousandsSeparator);
        if ($currencySymbolRight) {
            $formatted = $formatted . $currencySymbol;
        } else {
            $formatted = $currencySymbol . $formatted;
        }

        return $formatted;
    }

    /**
     * Multi byte version of the str_pad php function
     * @param $str
     * @param $pad_len
     * @param string $pad_str
     * @param int $dir
     * @param null $encoding
     * @return string
     */
    public static function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = NULL)
    {
        $encoding = $encoding === NULL ? mb_internal_encoding() : $encoding;
        $padBefore = $dir === STR_PAD_BOTH || $dir === STR_PAD_LEFT;
        $padAfter = $dir === STR_PAD_BOTH || $dir === STR_PAD_RIGHT;
        $pad_len -= mb_strlen($str, $encoding);
        $targetLen = $padBefore && $padAfter ? $pad_len / 2 : $pad_len;
        $strToRepeatLen = mb_strlen($pad_str, $encoding);
        $repeatTimes = ceil($targetLen / $strToRepeatLen);
        $repeatedString = str_repeat($pad_str, max(0, $repeatTimes)); // safe if used with valid utf-8 strings
        $before = $padBefore ? mb_substr($repeatedString, 0, floor($targetLen), $encoding) : '';
        $after = $padAfter ? mb_substr($repeatedString, 0, ceil($targetLen), $encoding) : '';
        return $before . $str . $after;
    }

}