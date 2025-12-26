<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-1641562.eml, rentalcars/it-1641563.eml, rentalcars/it-1641583.eml, rentalcars/it-3.eml, rentalcars/it-36345367.eml, rentalcars/it-87899936.eml, rentalcars/it.eml";

    public $reSubject = [
        "en" => "Itinerary for",
    ];

    public $reBody2 = [
        "en"  => "Drop-Off Location:",
        "en2" => "Your car rental reservation",
    ];

    public $lang = '';
    public static $dictionary = [
        "en" => [
            "Partner Reference:"  => ["Partner Reference:", "Rental Partner Confirmation Number:"],
            "Itinerary Number is" => ["Itinerary Number is", "Booking Number is"],
        ],
    ];

    private $providerCode = '';

    public function parseHtml(Email $email)
    {
        $r = $email->add()->rental();

        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts("Driver:") . "]", null, true, "#:\s+(.+)#");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/");
        }
        $r->general()
            ->traveller($traveller);

        $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Partner Reference:"))}]", null, true, "#:\s*([A-Z\d-]{5,})$#");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Itinerary Number is"))}]", null, true, "/{$this->opt($this->t("Itinerary Number is"))}\s*([A-Z\d-]{5,})$/");

        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Itinerary Number is"))}]/following::text()[normalize-space()!=''][1]", null, true, '/^[A-Z\d-]{5,}$/');
        }

        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your car rental reservation booking number'))}]", null, true, "/{$this->opt($this->t('Your car rental reservation booking number'))}\s*(\d+)/");
        }
        $r->general()
            ->confirmation($confirmation);

        if ($this->http->XPath->query('//text()[contains(normalize-space(), "has been canceled")]')->length > 0) {
            $r->general()
                ->cancelled();
        }

        $pickUpLocation = $this->http->FindSingleNode("//text()[" . $this->starts("Pick-Up Location:") . "]", null, true, "#:\s+(.+)#");

        if (!empty($pickUpLocation)) {
            $r->pickup()
                ->location($pickUpLocation);
        }

        $pickUpDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("Pick-up:") . "]", null, true, "#:\s+(.+)#")));

        if (!empty($pickUpDate)) {
            $r->pickup()
                ->date($pickUpDate);
        }

        $dropOffLocation = $this->http->FindSingleNode("//text()[" . $this->starts("Drop-Off Location:") . "]", null, true, "#:\s+(.+)#");

        if (!empty($dropOffLocation)) {
            $r->dropoff()
                ->location($dropOffLocation);
        }

        $dropOffDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("Drop-Off:") . "]", null, true, "#:\s+(.+)#")));

        if (!empty($dropOffDate)) {
            $r->dropoff()
                ->date($dropOffDate);
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->starts("* Contact ")}]", null, true, "#:\s*([+(\d][-. \d)(]{5,}[\d)])$#");

        if ($phone === null) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts("* Contact ")}]/following::text()[normalize-space()][1]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        }

        if ($phone !== null) {
            $r->pickup()
                ->phone($phone);
        }

        $company = $this->http->FindSingleNode("//text()[" . $this->starts("Rental Partner:") . "]", null, true, "#:\s+(.+)#");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $carType = $this->http->FindSingleNode("//text()[" . $this->starts("Car Type:") . "]", null, true, "#:\s+(.+)#");

        if (!empty($carType)) {
            $r->car()
                ->type($carType);
        }

        // Currency
        // TotalCharge
        $total = $this->http->FindSingleNode("//text()[{$this->starts("Amount Due at Pick-up:")}]", null, true, "#:\s*(\b.+)#");

        if ($total !== null && !empty($this->amount($total))) {
            $r->price()
                ->currency($this->currency($total))
                ->total($this->amount($total));
            // TotalTaxAmount
            $taxes = $this->http->FindSingleNode("//text()[{$this->starts("Taxes and Fees:")}]", null, true, "#:\s*(\b.+)#");

            if ($taxes !== null) {
                $r->price()
                    ->tax($this->amount($taxes));
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@](?:rentalcars\.com|booking\.com|united\.com)/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], ' rentalcars.com ') === false
            && stripos($headers['subject'], ' Booking.com ') === false
            && stripos($headers['subject'], ' United.com ') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        foreach ($this->reBody2 as $lang=>$re) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseHtml($email);

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

    public static function getEmailProviders()
    {
        return ['rentalcars', 'mileageplus', 'booking'];
    }

    private function assignProvider($headers): bool
    {
        if ($this->detectEmailFromProvider($headers['from']) === true || stripos($headers['subject'], ' rentalcars.com ') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for booking your trip with RentalCars.com") or contains(normalize-space(),"rentalcars.com Reservations Team") or contains(.,"@rentalcars.com")]')->length > 0
        ) {
            $this->providerCode = 'rentalcars';

            return true;
        }

        if (preg_match('/[.@]united\.com/i', $headers['from']) > 0 || stripos($headers['subject'], ' United.com ') !== false
            || $this->http->XPath->query('//node()[contains(.,"United.com") or contains(.,".united.com") or contains(.,"@reservation-cars.united.com")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"/united_airlines.") and @width="230" and @height="39"]')->length > 0
        ) {
            $this->providerCode = 'mileageplus';

            return true;
        }

        if (preg_match('/[.@]booking\.com/i', $headers['from']) > 0 || stripos($headers['subject'], ' Booking.com ') !== false
            || $this->http->XPath->query('//node()[contains(.,"booking.com") or contains(.,"Booking.com")]')->length > 0
        ) {
            $this->providerCode = 'booking';

            return true;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+@\s+(\d+:\d+\s+[AP]M)$#", //Wednesday, April 23, 2014 @ 9:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
