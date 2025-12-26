<?php

namespace AwardWallet\Engine\groome\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelItineraryFor extends \TAccountChecker
{
    public $mailFiles = "groome/it-361644351.eml, groome/it-366705979.eml, groome/it-855951265.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'reservationDetails' => ['Reservation Details', 'Reservation Details Return'],
            'Flight Time' => ['Arrival Time:', 'ArrivalTime:', 'DepartureTime:', 'Departure Time'],
            'Fare:'       => ['Fare:', 'Total Fare:'],
        ],
    ];

    private $detectFrom = "donotreply@groometrans.com";
    private $detectSubject = [
        // en
        'Travel Itinerary for',
    ];

    private $detectBody = [
        'en' => [
            'This email contains your reservation confirmation',
        ],
    ];

    // used in parser groome/TravelItineraryForAirport
    public static $badAirportCodes = [
        'NAS', // Nashville Airport (NAS)
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[.@]groometrans\.com\s*$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains(['Groome Transportation, Inc'])}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email): void
    {
        $countIt = count($this->http->FindNodes("//text()[{$this->eq($this->t('reservationDetails'))}]"));

        if ($countIt < 1) {
            $this->logger->debug('No Reservations');

            return;
        }

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'nameCode' => '/^\s*.*Airport.*\(\s*(?-i)([A-Z]{3})\s*\)/i', // Orlando International Airport (MCO)
        ];

        for ($i = 1; $i <= $countIt; $i++) {
            $cond = '';

            if ($countIt > 1) {
                $cond = "[count(preceding::text()[{$this->eq($this->t('reservationDetails'))}]) = {$i}"
                    . " and count(following::text()[{$this->eq($this->t('reservationDetails'))}]) = " . ($countIt - $i) . "]";
            }
            $t = $email->add()->transfer();

            // General
            $t->general()
                ->confirmation($this->nextTd($this->t('Confirmation #:'), $cond))
                ->traveller($this->nextTd($this->t('Name:'), $cond, "/^{$patterns['travellerName']}$/u"));

            // Segments
            $s = $t->addSegment();

            $s->departure()->date($this->normalizeDate($this->nextTd($this->t('Pickup Date:'), $cond)));

            $infoPickup = implode(' ', $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pickup Information:'))}] ]{$cond}/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

            $locationDep = $this->re("/^[*\s]*(.+?)\s*(?:{$this->opt($this->t('Flight Time'))}|$)/", $infoPickup);

            if (preg_match($patterns['nameCode'], $locationDep, $m)) {
                if (!in_array($m[1], self::$badAirportCodes)) {
                    $s->departure()->code($m[1]);
                }

                $s->departure()->name($locationDep);
            } elseif (preg_match("/\d{5}/", $locationDep) || preg_match("/^.{3,},\s*[A-Z]{2}$/", $locationDep)) {
                $s->departure()->address($locationDep);
            }  else {
                $s->departure()->name($locationDep);
            }

            $s->arrival()->noDate();

            $infoDropoff = implode(' ', $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Dropoff Information:'))}] ]{$cond}/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

            $locationArr = $this->re("/^[*\s]*(.+?)\s*(?:{$this->opt($this->t('Flight Time'))}|$)/", $infoDropoff);

            if (preg_match($patterns['nameCode'], $locationArr, $m)) {
                if (!in_array($m[1], self::$badAirportCodes)) {
                    $s->arrival()->code($m[1]);
                }

                $s->arrival()->name($locationArr);
            } elseif (preg_match("/\d{5}/", $locationArr) || preg_match("/^.{3,},\s*[A-Z]{2}$/", $locationArr)) {
                $s->arrival()->address($locationArr);
            } else {
                $s->arrival()->name($locationArr);
            }

            $s->extra()
                ->type($this->nextTd($this->t('Vehicle Type:'), $cond))
                ->adults($this->nextTd($this->t('Passengers:'), $cond, "/^([0-9]+).*?$/su"));

            // Price
            $fare = $this->nextTd($this->t('Fare:'), $cond);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $fare, $matches)) {
                // $ 168.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        $totalFare = $this->nextTd($this->t('Total Fare:'));

        if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalFare, $matches)) {
            // 158.00  |  $ 100.18
            $currency = empty($matches['currency']) ? null : $matches['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $amount = PriceHelper::parse($matches['amount'], $currencyCode);
            $email->price()->currency($currency, false, true)->total($amount);
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextTd($field, $cond = '', $regexp = null)
    {
        return $this->http->FindSingleNode("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]"
            . $cond, null, true, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // Sunday, April 16, 2023 at 10:30 AM
            '/^\s*[[:alpha:]\-]+\s*[,\s]\s*([[:alpha:]]+)\s+(\d+)\s*,\s*(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
