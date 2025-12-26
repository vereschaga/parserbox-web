<?php

namespace AwardWallet\Engine\panpacific\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "panpacific/it-221066132.eml";

    public $detectSubject = [
        'Reservation Confirmation For',
    ];

    public $detectBody = [
        "en" => ['Your Stay With Us'],
    ];

    public $lang = "";
    public static $dictionary = [
        "en" => [
            'Check-In' => 'Check-In',
            'Check-Out' => 'Check-Out',
            'Cancellation Charges' => ['Cancellation Charges', 'Cancel Policy'],
        ],
    ];

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest Details")) . "]/following::text()[" . $this->eq($this->t("Name")) . "][1]/following::text()[normalize-space()][1]"), true)
            ->cancellation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Charges")) . "]/ancestor::*[" . $this->eq($this->t("Cancellation Charges")) . "]/following::*[normalize-space()][1]"))
        ;

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Find Out More']/ancestor::*[preceding::text()[normalize-space()][1][normalize-space() = 'Your Stay With Us']][1]//text()[normalize-space()]"));
        if (preg_match("/^\s*(?<name>.+)\s*\n(?<address>[\s\S]+?)\s*\n\s*Telephone:\s*(?<phone>[\+\- \d\(\)]{5,}?)\s*\n/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone'])
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//*[*[normalize-space()][1][{$this->starts($this->t('Check-In'))}] and *[normalize-space()][2][{$this->starts($this->t('Check-Out'))}]]/*[normalize-space()][1]",
                null, true, "/{$this->opt($this->t("Check-In"))}\s*(.*)/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//*[*[normalize-space()][1][{$this->starts($this->t('Check-In'))}] and *[normalize-space()][2][{$this->starts($this->t('Check-Out'))}]]/*[normalize-space()][2]",
                null, true, "/{$this->opt($this->t("Check-Out"))}\s*(.*)/")))
        ;

        $rooms = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check-In'))}]/following::text()[{$this->contains($this->t("Room(s)"))}])[1]",
            null, true, "/(\d+)\s+{$this->opt($this->t("Room(s)"))}/ui");
        $h->booked()
            ->rooms($rooms)
            ->guests($rooms * $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check-In'))}]/following::text()[{$this->contains($this->t("Adult(s)/room"))}])[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t("Adult(s)/room"))}/ui"))
            ->kids($rooms * $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check-In'))}]/following::text()[{$this->contains($this->t("Children/room"))}])[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t("Children/room"))}/ui"))
        ;

        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Room(s)'))}]/following::text()[normalize-space()][1]"))
        ;

        // Program
        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DISCOVERY Membership Number'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\d{5,}\s*$/");
        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
        // Price
        $cost = $this->http->FindSingleNode("//tr[./td[1][{$this->starts($this->t('Total Room Rate'))}]]/td[2]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $taxes = $this->http->FindSingleNode("(//tr[./td[1][{$this->eq($this->t('Tax and Service Charges'))}]])[1]/td[2]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $taxes, $m)) {
            $h->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $total = $this->http->FindSingleNode("//tr[./td[1][{$this->eq($this->t('Grand Total'))}]]/td[2]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@panpacific.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.panpacific.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($this->detectBody[$lang]) && $this->http->XPath->query("//node()[{$this->contains($this->detectBody[$lang])}]")->length > 0
                && isset($dict['Check-In']) && isset($dict['Check-Out']) && $this->http->XPath->query("//*[*[normalize-space()][1][{$this->starts($dict['Check-In'])}] and *[normalize-space()][2][{$this->starts($dict['Check-Out'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;
                break;
            }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 22 Nov 2022 After 16:00
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s+[\D]*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+(?:\d{4}|%Y%)#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }
}
