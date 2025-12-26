<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class InvoiceNoHtml extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-10071117.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['INVOICE NUMBER:'],
    ];
    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Icelandair Americas') !== false
            || stripos($from, '@icelandair.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return strpos($headers['subject'], 'Invoice') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->antiDetect()) {
            return false;
        }

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }
        $this->http->SetEmailBody($textBody);

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Icelandair North America") or contains(.,"@icelandair.")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->antiDetect()) {
            return false;
        }

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }
        $this->http->SetEmailBody($textBody);

        if ($this->assignLang() === false) {
            return false;
        }

        $it = $this->parseEmail($textBody);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'InvoiceNoHtml_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail($text)
    {
        $it = [];
        $it['Kind'] = 'T';

        // Date: 18.01.2016 Time: 14:51
        if (preg_match('/^[> ]*Date:\s*(\d{1,2}\.\d{1,2}\.\d{4})\s*Time:\s*(\d{1,2}:\d{2})$/mi', $text, $matches)) {
            $receiptDate = strtotime($matches[1] . ' ' . $matches[2]);
        }

        if (preg_match('/BOOKING\s*REFERENCE\s*NUMBER:\s*([A-Z\d]{5,})/', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        if (preg_match_all('/^[> ]*([A-Z][A-Z\/\' -]*[A-Z])[ ]*\((?:ADT|CHD)\)/m', $text, $passengerMatches)) {
            $it['Passengers'] = array_unique($passengerMatches[1]);
        }

        $it['TripSegments'] = [];

        $patternSegments = '/'
            . '(?<day>\d{1,2})(?<month>[A-Z]{3,})\s*(?<depTime>\d{1,2}:\d{2})' // 25MAY 15:55
            . '\s*Flight\s*(?<airline>[A-Z\d]{2})(?<flightNumber>\d+)\s*From:\s*(?<depCode>[A-Z]{3})\s*To:\s*(?<arrCode>[A-Z]{3})' // Flight FI696 From: YVR To: KEF
            . '/';
        preg_match_all($patternSegments, $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $matches) {
            $seg = [];

            if ($receiptDate) {
                $dateDep = EmailDateHelper::parseDateRelative($matches['day'] . ' ' . $matches['month'], $receiptDate);
                $seg['DepDate'] = strtotime($matches['depTime'], $dateDep);
                $seg['ArrDate'] = MISSING_DATE;
            }

            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];

            $seg['DepCode'] = $matches['depCode'];
            $seg['ArrCode'] = $matches['arrCode'];

            $it['TripSegments'][] = $seg;
        }

        if (preg_match('/^[> ]*SALE:\s*(\d[,.\d ]*)([A-Z]{3,})$/m', $text, $matches)) {
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
            $it['Currency'] = $matches[2];
        }

        return $it;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function antiDetect()
    { // linked with parser Invoice
        return $this->http->XPath->query('//td[contains(@class,"invoicedatareceipt") or contains(@style,"#E8E8E8")]')->length > 0;
    }
}
