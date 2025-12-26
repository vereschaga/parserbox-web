<?php

namespace AwardWallet\Engine\reserveam\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "reserveam/it-850485054.eml, reserveam/it-857189177.eml, reserveam/it-873251810.eml";
    public $lang = 'en';

    public $detectSubjects = [
        'en' => [
            'Confirmation Letter Email',
        ],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        "en" => [
            'traveller'    => ['Primary Occupant:', 'PRIMARY OCCUPANTS:'],
            'Arrival Date' => ['Arrival Date:', 'DATE:'],
            'Site'         => ['Site:', 'SITE:'],
            'TOTAL'        => ['TOTAL', 'TOTAL:'],
        ],
    ];

    public $states = [
        'idahostateparks.reserveamerica.com' => 'Idaho',
        'texasstateparks.reserveamerica.com' => 'Texas',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reserveamerica\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'reserveamerica.com') === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // detect Provider
        if ($this->http->XPath->query("//a/@href[{$this->contains('reserveamerica.com')}]")->length === 0) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('*IMPORTANT BILLING INFORMATION:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('RESERVATION DETAILS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('NOTES'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->hotel()->house();

        $patterns = [
            'date'          => '\D+\s+(\D+\s+\d+\s+\d{4})', // Fri Dec 1 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp](?:\.\s*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm  |  00:00
            'travellerName' => '[[:alpha:]][-.\'’`[:alpha:] ]*[[:alpha:]]',
        ];

        // collect reservation confirmation
        $confInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation #'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]");

        if (preg_match("/\s*(?<desc>{$this->opt($this->t('Reservation #'))})\s+(?<confNumber>\d+\-\d+)(?:\s+\((?<status>.+?)\))?\s*$/", $confInfo, $m)) {
            $h->general()
                ->confirmation($m['confNumber'], $m['desc']);

            if (!empty($m['status'])) {
                $h->general()
                    ->status($m['status']);

                if (stripos($m['status'], 'cancelled') !== false) {
                    $h->general()->cancelled();
                }
            }
        }

        // collect traveller name
        $travellerName = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('traveller'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*({$patterns['travellerName']})\s*$/");

        if (!empty($travellerName)) {
            $h->general()
                ->traveller($travellerName, true);
        }

        // collect hotel name and address
        $hotelName = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Campground:'))}])[last()]/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('DIRECTIONS'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*{$this->opt($this->t('can be accessed'))}/");
        $state = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('State:'))}])[last()]/following::text()[normalize-space()][1]");

        // if state is not explicitly specified
        $urls = $this->http->FindNodes("//a/@href[{$this->contains(['.reserveamerica.com'])}]");

        foreach ($urls as $url) {
            foreach ($this->states as $href => $stateName) {
                if (stripos($url, $href) !== false) {
                    $state = $stateName;

                    break;
                }
            }
        }

        $h->hotel()->name($hotelName);

        if (!empty($state)) {
            $h->hotel()->address($hotelName . ', ' . $state);
        }

        // collect check-in and check-out dates
        $dateCheckIn = strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Arrival Date'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['date']}\s*$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Departure Date:'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['date']}\s*$/"));
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-In Time:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*({$patterns['time']}).*$/i")
            ?? '00:00';
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-Out Time:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*({$patterns['time']}).*$/i")
            ?? '00:00';

        $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        $checkOut = strtotime($timeCheckOut, $dateCheckOut);

        if (!empty($checkOut) && $h->getCheckInDate() !== $checkOut) {
            $h->booked()->checkOut($checkOut);
        } else {
            $h->booked()->noCheckOut();
        }

        // collect guests count
        $guestsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('# of Occupants:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Occupants'))}]", null, true, "/^\s*{$this->opt($this->t('Number of Occupants'))}\s*\((\d+)\)\s*/");

        if (!empty($guestsCount)) {
            $h->booked()->guests($guestsCount);
        }

        // collect room type
        $site = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Site'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+)\s*$/");
        $siteType = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Site Type:'))}])[last()]/following::text()[normalize-space()][1]");

        if (!empty($site) && !empty($siteType)) {
            $r = $h->addRoom();
            $r->setType($site . ', ' . $siteType);
        } elseif (!empty($site)) {
            $r = $h->addRoom();
            $r->setType($site);
        }

        // collect payment information
        $pricePattern = "(?<currency>[^\d\s]{1,3})\s*(?<amount>[\d\.\,\']+)"; // use with 'Unicode' regex flag

        $currency = $this->normalizeCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL'))}]/ancestor::tr[1]/td[normalize-space()][2]", null, true, "/^\s*([^\d\s]{1,3})\s*$/u"));
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL'))}]/ancestor::tr[1]/td[normalize-space()][3]", null, true, "/^\s*([\d\.\,\']+)\s*$/");

        if (!empty($currency) && $total !== null) {
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($total, $currency));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('USE FEE:'))}]/ancestor::tr[1]/td[normalize-space()][3]", null, true, "/^\s*([\d\.\,\']+)\s*$/");

        if (!empty($currency) && $cost !== null) {
            $h->price()->cost(PriceHelper::parse($cost, $currency));
        }

        $discount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('DISCOUNT'))}]/ancestor::tr[1]/td[normalize-space()][3]", null, true, "/^\s*\(([\d\.\,\']+)\)\s*$/");

        if (!empty($currency) && $discount !== null) {
            $h->price()->discount(PriceHelper::parse($discount, $currency));
        }

        $fees = $this->http->FindNodes("//text()[{$this->eq($this->t('TOTAL'))}]/ancestor::tr[1]/preceding-sibling::tr[(preceding::text()[{$this->eq($this->t('USE FEE:'))}]) and not({$this->contains($this->t('DISCOUNT'))})]");

        foreach ($fees as $fee) {
            if (preg_match("/^\s*(?<feeName>.+?)\:\s*{$pricePattern}\s*$/u", $fee, $m)) {
                $h->price()->fee($m['feeName'], PriceHelper::parse($m['amount'], $currency));
            }
        }

        // collect phone
        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Park Office: Call'))}]", null, true, "/^\s*{$this->opt($this->t('Park Office: Call'))}\s*([+\-()\d\s]+?)\s*{$this->opt($this->t('for information'))}/");

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
        }

        // collect cancellation policy
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellations received prior'))}]");

        if (!empty($cancellationPolicy)) {
            $h->setCancellation($cancellationPolicy);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);
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

    private function re($re, $str, $c = 1)
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'          => 'EUR',
            'US dollars' => 'USD',
            '£'          => 'GBP',
            '₹'          => 'INR',
            'CA$'        => 'CAD',
            '$'          => '$',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3}\D)(?:$|\s)#", $s)) {
            return $code;
        }

        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }
}
