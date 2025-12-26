<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "klook/it-437121633.eml, klook/it-647683246.eml, klook/it-763477080.eml";
    public $subjects = [
        'Your booking confirmation for Car Rentals - ',
    ];

    public $lang = 'en';
    public $langDate = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'otaConfNumber'     => ['Booking reference ID'],
            'confNumber'        => ['Confirmation No', 'Confirmation no.'],
            'Drop-off info'     => ['Drop-off info', 'Drop Off Information'],
            'Pick Up Location'  => ['Pick Up Location', 'Pick-up location'],
            'Drop Off Location' => ['Drop Off Location', 'Drop-off location'],
            'Office Hours'      => ['Office Hours', 'Opening hours'],
            'Telephone Number'  => ['Telephone Number', 'Phone number'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@klook.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".klook.com/") or contains(@href,"click.klook.com")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Klook Travel")] | //*[contains(normalize-space(),"Thanks for booking with Klook") or contains(normalize-space(),"Website: www.klook.com")]')->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Car rentals'))}]/following::text()[{$this->contains($this->t('Pick Up Store Information'))}]")->length > 0
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text): bool
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, "Klook") === false) {
            return false;
        }

        if ($this->containsText($text, 'Package Information') === true
            && ($this->containsText($text, 'Pick Up Information') === true
                || $this->containsText($text, 'Car Rentals') === true)
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, string $text): void
    {
        // examples: it-437121633.eml
        $this->logger->debug(__FUNCTION__ . '()');

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('confNumber'))}.*\n+([A-Z\d\.]{3,})\s*/i", $text))
            ->traveller(str_replace(['MRS', 'MS', 'MR', 'MISS'], '', $this->re("/Drive\s*r\'\s*s Name.*\n{1,2}[ ]{0,20}({$this->patterns['travellerName']})[ ]{10}/iu", $text)));

        $r->car()
            ->model($this->re("/Car Rentals\s*-\s*(.+?)\s*\(/i", $text));

        $qPickUp = "/" . $this->addSpacesWord('Pick Up Information') . "\n+(?:.+\n+)?" .
            $this->addSpacesWord('Date & time') . "\n+(?<date>.{4,}?{$this->patterns['time']}).*\n+" .
            $this->addSpacesWord($this->opt($this->t('Pick Up Location'))) . "\n+(?<location>.+)\n+" .
            $this->addSpacesWord('Address') . "(?<address>(?:\n+.+){1,3})\n+" .
            $this->addSpacesWord($this->opt($this->t('Office Hours'))) . "\n+(?<hours>.+(?:\n.+){0,2})\n+" .
            $this->addSpacesWord($this->opt($this->t('Telephone Number'))) . "\n+(?<phone>{$this->patterns['phone']})/i";

        if (preg_match($qPickUp, $text, $m)) {
            $r->pickup()
                ->location($this->buildLocation($m['location'], $m['address'], true))
                ->date($this->normalizeDate($m['date']))
                ->openingHours(preg_replace('/\s+/', ' ', trim($m['hours'])))
                ->phone($m['phone']);
        }

        $qDropOff = "/" . $this->addSpacesWord($this->opt($this->t('Drop-off info'))) . "\n+(?:.+\n+)?" .
            $this->addSpacesWord('Date & time') . "\n+(?<date>.{4,}?{$this->patterns['time']}).*\n+" .
            $this->addSpacesWord($this->opt($this->t('Drop Off Location'))) . "\n+(?<location>.+)\n+" .
            $this->addSpacesWord('Address') . "(?<address>(?:\n+.+){1,3})\n+" .
            $this->addSpacesWord($this->opt($this->t('Office Hours'))) . "\n+(?<hours>.+(?:\n.+){0,2})\n+" .
            $this->addSpacesWord($this->opt($this->t('Telephone Number'))) . "\n+(?<phone>{$this->patterns['phone']})/i";

        if (preg_match($qDropOff, $text, $m)) {
            $r->dropoff()
                ->location($this->buildLocation($m['location'], $m['address'], true))
                ->date($this->normalizeDate($m['date']))
                ->openingHours(preg_replace('/\s+/', ' ', trim($m['hours'])))
                ->phone($m['phone']);
        }
    }

    public function ParseRentalHTML(Email $email): void
    {
        // examples: it-647683246.eml
        $this->logger->debug(__FUNCTION__ . '()');

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match("/^({$this->opt($this->t('otaConfNumber'))})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('otaConfNumber'))}]"), $m)) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
        }

        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^[-A-Z\d]{4,40}$/");
        $confirmationTitle = $this->http->FindSingleNode("//*[count(node()[normalize-space() and not(self::comment())])=2]/node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');

        if (!$confirmation
            && preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]"), $m)
        ) {
            $confirmation = $m[2];
            $confirmationTitle = $m[1];
        }

        if (!$confirmation && $otaConfirmation
            && $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length === 0
        ) {
            $r->general()->noConfirmation();
        } else {
            $r->general()->confirmation($confirmation, $confirmationTitle);
        }

        if ($otaConfirmation !== $confirmation) {
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $dateBooking = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked:')]", null, true, "/{$this->opt($this->t('Booked:'))}\s*(.+)/");

        if (!empty($dateBooking)) {
            $r->general()
                ->date(strtotime($dateBooking));
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Driver info'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Name:'))}\s*({$this->patterns['travellerName']})$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Driver info'))}]/following::*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Name:'))}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^{$this->patterns['travellerName']}$/u");
        $r->general()->traveller($traveller, true);

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Original Total:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Original Total:'))}\s*(\D{1,3}\s*[\d\.\,]+)/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $company = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Supplier:'))}]", null, true, "/{$this->opt($this->t('Supplier:'))}\s*(.+)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Supplier:'))}] ]/node()[normalize-space() and not(self::comment())][2]");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $carInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Car rentals'))} and not(following::text()[{$this->contains($this->t('otaConfNumber'))}])]", null, true, "/{$this->opt($this->t('Car rentals'))}[\s\-]*(.+)/");

        if (preg_match("/^(?<model>.+)\s*\((?<type>.+)\)/", $carInfo, $m)) {
            $r->car()
                ->model($m['model'])
                ->type($m['type'])
                ->image($this->http->FindSingleNode("//text()[{$this->starts($this->t('Car rentals'))} and not(following::text()[{$this->contains($this->t('otaConfNumber'))}])]/preceding::*[1]/@src"));
        }

        /* Pick Up */

        $xpathPickUpFilter = "preceding::text()[{$this->eq($this->t('Pick Up Store Information'), "translate(.,':','')")}] and following::text()[{$this->eq($this->t('Drop Off Store Information'), "translate(.,':','')")}]";

        $locationPickUp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick Up Location'))}]", null, true, "/{$this->opt($this->t('Pick Up Location'))}[:\s]+(\S.*)$/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Pick Up Location'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]");
        $addressPickUp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address'))}][{$xpathPickUpFilter}]", null, true, "/{$this->opt($this->t('Address'))}[:\s]+(\S.+)$/i")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Address'), "translate(.,':','')")}] ][{$xpathPickUpFilter}]/node()[normalize-space() and not(self::comment())][2]");

        $r->pickup()->location($this->buildLocation($locationPickUp ?? '', $addressPickUp ?? ''));

        $datePickUpVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick Up Date'))}]", null, true, "/{$this->opt($this->t('Pick Up Date'))}[:\s]+(.*{$this->patterns['time']}.*?)(?:\s*\(|$)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Pick Up Date'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^(.*{$this->patterns['time']}.*?)(?:\s*\(|$)/")
        ;
        $r->pickup()->date(strtotime($this->normalizeStrDate($datePickUpVal)));

        $hoursPickUp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Office Hours'))}][{$xpathPickUpFilter}]", null, true, "/{$this->opt($this->t('Office Hours'))}[:\s]+(\S.+)$/i")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Office Hours'), "translate(.,':','')")}] ][{$xpathPickUpFilter}]/node()[normalize-space() and not(self::comment())][2]");
        $r->pickup()->openingHours($hoursPickUp, false, true);

        $phonePickUp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone Number'))}][{$xpathPickUpFilter}]", null, true, "/^{$this->opt($this->t('Telephone Number'))}[:\s]+({$this->patterns['phone']})(?:(?:(?:\s*[，,]\s*)+{$this->patterns['phone']})+|$)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Telephone Number'), "translate(.,':','')")}] ][{$xpathPickUpFilter}]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^({$this->patterns['phone']})(?:(?:(?:\s*[，,]\s*)+{$this->patterns['phone']})+|$)/");
        $r->pickup()->phone($phonePickUp);

        /* Drop Off */

        $xpathDropOffFilter = "preceding::text()[{$this->eq($this->t('Drop Off Store Information'), "translate(.,':','')")}]";

        $locationDropOff = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Drop Off Location'))}]", null, true, "/{$this->opt($this->t('Drop Off Location'))}[:\s]+(\S.*)$/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Drop Off Location'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]");
        $addressDropOff = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address'))}][{$xpathDropOffFilter}]", null, true, "/{$this->opt($this->t('Address'))}[:\s]+(\S.+)$/i")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Address'), "translate(.,':','')")}] ][{$xpathDropOffFilter}]/node()[normalize-space() and not(self::comment())][2]");

        $r->dropoff()->location($this->buildLocation($locationDropOff ?? '', $addressDropOff ?? ''));

        $dateDropOffVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Drop Off Date'))}]", null, true, "/{$this->opt($this->t('Drop Off Date'))}[:\s]+(.*{$this->patterns['time']}.*?)(?:\s*\(|$)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Drop Off Date'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^(.*{$this->patterns['time']}.*?)(?:\s*\(|$)/")
        ;
        $r->dropoff()->date(strtotime($this->normalizeStrDate($dateDropOffVal)));

        $hoursDropOff = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Office Hours'))}][{$xpathDropOffFilter}]", null, true, "/{$this->opt($this->t('Office Hours'))}[:\s]+(\S.+)$/i")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Office Hours'), "translate(.,':','')")}] ][{$xpathDropOffFilter}]/node()[normalize-space() and not(self::comment())][2]");
        $r->dropoff()->openingHours($hoursDropOff, false, true);

        $phoneDropOff = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone Number'))}][{$xpathDropOffFilter}]", null, true, "/^{$this->opt($this->t('Telephone Number'))}[:\s]+({$this->patterns['phone']})(?:(?:(?:\s*[，,]\s*)+{$this->patterns['phone']})+|$)/")
            ?? $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Telephone Number'), "translate(.,':','')")}] ][{$xpathDropOffFilter}]/node()[normalize-space() and not(self::comment())][2]", null, true, "/^({$this->patterns['phone']})(?:(?:(?:\s*[，,]\s*)+{$this->patterns['phone']})+|$)/");
        $r->dropoff()->phone($phoneDropOff);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->detectPdf($text)) {
                $this->langDate = preg_match("/" . $this->addSpacesWord('um Kontakt mit uns aufzunehmen') . "/", $text) ? 'de' : 'en';
                $this->ParseRentalPDF($email, $text);
            }
        }

        if (count($email->getItineraries()) === 0) {
            $this->ParseRentalHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function normalizeStrDate($string): string
    {
        $depDate = preg_replace([
            '/((?:\D|\b)\d{1,2})fin(\d{2}(?:\D|\b))/i',
            '/(\d)un(\d{1,2}(?:\D|\b))/i',
        ], [
            '$1:$2',
            '$1 $2',
        ], $string);

        return $depDate;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function addSpacesWord($text): string
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d+)\s*(\w+)\.\s*(\d{4})\s*([\d\:]+\s*A?P?M?)$#u", //7 Okt. 2023 10:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->langDate)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function buildLocation(string $location, string $address = '', bool $isPdf = false): string
    {
        $result = '';

        $location = preg_replace("/^(.*?)[-\s]+translated by AI.*$/is", '$1', $location);
        $address = preg_replace([
            '/\s+/',
            "/[.;!\s]*\(*\s*Please contact.*/is",
        ], [
            ' ',
            '',
        ], trim($address));

        $result = implode(', ', array_filter([$location, $address]));

        if ($isPdf) {
            $result = preg_replace([
                "/^(.*[^.;!\s])[.;!\s]*The store provides.*$/is",
                "/^(.*\S)\s+\(.*$/s",
            ], '$1', $result);
        }

        return $result;
    }
}
