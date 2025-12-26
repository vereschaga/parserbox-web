<?php

namespace AwardWallet\Engine\panpacific\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationCancellation extends \TAccountCheckerExtended
{
    public $mailFiles = "panpacific/it-221424799.eml";

    public $detectSubject = [
        'Your Reservation Cancellation Confirmation',
    ];

    public $detectBody = [
        "en" => ['Your booking has been cancelled'],
    ];

    public $lang = "";
    public static $dictionary = [
        "en" => [
        ],
    ];

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Dear ")) . "])[1]",
                null, true, "/" . $this->opt($this->t("Dear ")) . "\s*(?:(?:Mr|Mrs|Dr|Miss) )?(.+?)\s*,\s*$/"), false)
            ->cancellationNumber($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\s*([A-Z\d]{5,})\s*$/"))
            ->status('Cancelled')
            ->cancelled()
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space() = 'Your Stay With Us']/following::text()[normalize-space()][1]"))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space() = 'Your Stay With Us']/following::text()[normalize-space()][2]",
                null, true, "/(.+) - .+/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space() = 'Your Stay With Us']/following::text()[normalize-space()][2]",
                null, true, "/.+ - (.+)/")))
        ;

        $h->booked()
            ->rooms($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Your Stay With Us'))}]/following::text()[{$this->contains($this->t("Room(s)"))}])[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t("Room(s)"))}/ui"))
            ->guests($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Your Stay With Us'))}]/following::text()[{$this->contains($this->t("Adult(s)"))}])[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t("Adult(s)"))}/ui"))
            ->kids($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Your Stay With Us'))}]/following::text()[{$this->contains($this->t("Children"))}])[1]",
                null, true, "/(\d+)\s+{$this->opt($this->t("Children"))}/ui"), true, true)
        ;

        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Room(s)'))}]/following::text()[normalize-space()][1]"))
        ;
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
        if ($this->http->XPath->query("//a[contains(@href, '.panpacific.com')] | //node()[contains(., 'Pacific Hotels and Resorts. All rights reserved')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
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
