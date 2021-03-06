<?php
declare(strict_types=1);

namespace Src\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Src\Config\AppConfig;

class Helpers
{
    /**
     * Source: @link https://www.php.net/manual/ru/function.memory-get-usage.php#96280
     *
     * @param int $size
     * @return string
     */
    public static function formatBytes(int $size): string
    {
        static $unit = ['b','KB','MB','GB','TB','PB'];

        return @round($size/(1024 ** ($i = floor(log($size, 1024)))),2).Str::SPACE.$unit[$i];
    }

    /**
     * @param string $link
     * @return string
     */
    public static function getHost(string $link): string
    {
        $urlParts = parse_url($link);

        if (empty($urlParts['host'])) {
            return Str::EMPTY;
        }

        $host = $urlParts['host'];

        if (!empty($urlParts['scheme'])) {
            $host = $urlParts['scheme'] . '://' . $host;
        }

        return $host;
    }

    /**
     * @param string $curlError
     * @return array
     */
    public static function getScheduleFilesLinks(string &$curlError = Str::EMPTY): array
    {
        $config = AppConfig::getInstance();

        $pageWithFiles = $config->pageWithScheduleFiles;
        $html = self::httpGet($pageWithFiles, $curlError);

        $links = [];

        if ($curlError || empty($html)) {
            return $links;
        }

        $doc = new DOMDocument;

        @$doc->loadHTML($html);

        $xpath = new DOMXPath($doc);

        $aTags = $xpath->query('//body//a');
        $host = self::getHost($pageWithFiles);

        /** @var DOMElement[] $aTags */
        foreach ($aTags as $a) {
            $linkUri = Security::sanitizeString($a->getAttribute('href'));

            if (!Str::endsWith($linkUri, $config->allowedExtensions)) {
                continue;
            }

            $linkUri = "$host/$linkUri";

            $linkUri = Security::sanitizeScheduleLink($linkUri);
            if (!Security::isScheduleLinkValid($linkUri)) {
                continue;
            }

            $linkText = Security::sanitizeString($a->textContent);

            $links[] = [
                'uri' => $linkUri,
                'text' => $linkText,
            ];
        }

        return $links;
    }

    /**
     * @param string $location Ex.: '/terms-and-conditions'
     */
    public static function goToLocation(string $location): void
    {
        header('Location: '.$location);
        die(0);
    }

    /**
     * @param string $link
     * @param int $timeout In seconds
     * @param string $curlError
     * @return ?string
     */
    public static function httpGet(string $link, string &$curlError = Str::EMPTY, int $timeout = 5): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = @curl_exec($ch);

        if ($data === false) {
            $curlError = curl_error($ch);
        }

        curl_close($ch);

        if (empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Convert integer to Roman number.
     *
     * Source: @link https://stackoverflow.com/a/26298774
     *
     * @param int $num Ex.: 4
     * @return string Ex.: IV
     */
    public static function intToRoman(int $num): string
    {
        $res = Str::EMPTY;

        static $romanNumberMap = [
            'M'  => 1000,
            'CM' => 900,
            'D'  => 500,
            'CD' => 400,
            'C'  => 100,
            'XC' => 90,
            'L'  => 50,
            'XL' => 40,
            'X'  => 10,
            'IX' => 9,
            'V'  => 5,
            'IV' => 4,
            'I'  => 1
        ];

        foreach ($romanNumberMap as $roman => $number){
            //divide to get  matches
            $matches = (int)($num / $number);

            //assign the roman char * $matches
            $res .= \str_repeat($roman, $matches); // multibyte support is not necessary here

            //substract from the number
            $num %= $number;
        }

        return $res;
    }

    /**
     * @return bool
     */
    public static function isCli(): bool
    {
        return AppConfig::getInstance()->forceConsoleMode || IS_CONSOLE;
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isExternalLink(string $str): bool
    {
        return !empty(self::getHost($str));
    }

    /**
     * @param string $string
     * @param bool $caseInsensitive
     * @return bool
     */
    public static function isRomanNumber(string $string, bool $caseInsensitive = true): bool
    {
        if ($string === Str::EMPTY) {
            return false;
        }

        $regex = '/^M*(C[MD]|D?C{0,3})(X[CL]|L?X{0,3})(I[XV]|V?I{0,3})$/i';

        if (!$caseInsensitive) {
            $regex = \rtrim($regex, 'i'); // multibyte support is not necessary here
        }

        return preg_match($regex, $string) > 0;
    }

    /**
     * Get part before GET-params in URI.
     * Example: return "/page" from "/page?p1=v1&p2=v2"
     *
     * @param string $uri
     * @return string
     */
    public static function uriWithoutGetPart(string $uri): string
    {
        return strtok($uri, '?');
    }
}
