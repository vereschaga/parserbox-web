<?php

namespace AwardWallet\Engine\reserveam\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Tour extends \TAccountChecker
{
    public $mailFiles = "reserveam/it-883651304.eml, reserveam/it-886652385.eml";
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
        "en" => [],
    ];

    public $states = [
        'delawarestateparks.reserveamerica.com'     => 'Delaware',
        'idahostateparks.reserveamerica.com'        => 'Idaho',
        'newhampshirestateparks.reserveamerica.com' => 'New Hampshire',
        'oregonstateparks.reserveamerica.com'       => 'Oregon',
        'texasstateparks.reserveamerica.com'        => 'Texas',
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
        if ($this->http->XPath->query("//a/@href[{$this->contains('reserveamerica.com')}]")->length === 0
            && $this->http->XPath->query("//img/@src[{$this->contains('reserveamerica.com')}]")->length === 0) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your Reservation Number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Status:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket Category:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Tour Date:'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseEvent(Email $email)
    {
        $e = $email->add()->event();
        $e->type()->event();

        $patterns = [
            'date'          => '\d+\/\d+\/\d{4}', // 09/24/2024
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp](?:\.\s*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm  |  00:00
            'travellerName' => '[[:alpha:]][-.\'’`[:alpha:] ]*[[:alpha:]]',
            'guestCount'    => "(\d+)\s+{$this->opt($this->t('Ticket(s)'))}",
        ];

        // collect reservation confirmation
        $confInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Reservation Number:'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Your Reservation Number:'))})\s+(?<confNumber>\d+\-\d+)\s*$/", $confInfo, $m)) {
            $e->general()
                ->confirmation($m['confNumber'], trim($m['desc'], ':'));
        }

        $reservationDate = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Date'))}][normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('Date'))}\:\s*(\d+\/\d+\/\d{4})\s*$/"));

        if (!empty($reservationDate)) {
            $e->general()->date($reservationDate);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $e->general()->status($status);
        }

        // collect traveller name
        $travellerName = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Name:'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*({$patterns['travellerName']})\s*$/");

        if (!empty($travellerName)) {
            $e->general()->traveller($travellerName, true);
        }

        // collect park name and address
        $parkName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket Category:'))}]/preceding::text()[normalize-space()][1]");

        // state is not explicitly specified
        $urls = $this->http->FindNodes("//a/@href[{$this->contains(['.reserveamerica.com'])}]");

        foreach ($urls as $url) {
            foreach ($this->states as $href => $stateName) {
                if (stripos($url, $href) !== false) {
                    $state = $stateName;

                    break;
                }
            }
        }

        if (!empty($state)) {
            $e->place()->address($parkName . ', ' . $state);
        }

        // collect event name
        $eventName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tour Name:'))}]/following::text()[normalize-space()][1]");
        $e->place()->name($eventName);

        // collect check-in and check-out dates
        $dateCheckIn = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Tour Date:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['date']}\s*$/"));
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tour Name:'))}]/following::text()[normalize-space()][2]", null, true, "/^\s*({$patterns['time']})\s[A-Z]{3}/i");

        $e->booked()
            ->start(strtotime($timeCheckIn, $dateCheckIn))
            ->noEnd();

        // collect guests and kids count
        $guestsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('General Admission:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['guestCount']}\s*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('General Admission Adult (Age 13 and Older):'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['guestCount']}\s*$/");

        $kidsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('General Admission (ages 5 and under):'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*{$patterns['guestCount']}\s*$/");

        if (!empty($guestsCount)) {
            $e->booked()->guests($guestsCount);
        }

        if (!empty($kidsCount)) {
            $e->booked()->kids($kidsCount);
        }

        // collect payment information
        $pricePattern = "(?<currency>[^\d\s]{1,3})\s*(?<amount>[\d\.\,\']+)"; // use with 'Unicode' regex flag

        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total:'))}]/ancestor::tr[1]/td[normalize-space()][2]");

        if (preg_match("/^\s*{$pricePattern}\s*$/u", $totalText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $e->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount'], $currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEvent($email);
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
