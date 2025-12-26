<?php

namespace AwardWallet\Engine\signature\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBookingDetails extends \TAccountChecker
{
    public $mailFiles = "signature/it-108684226.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Guest Details' => 'Guest Details',
        ],
    ];

    private $detectFrom = "@signaturetravelnetwork.com";
    private $detectSubject = [
        // en
        'Agent Copy - Hotel Booking Details for',
        'Hotel Booking Details -',
    ];

    private $detectBody = [
        'en' => [
            'Options/Details',
            'SIGNATURE TRAVEL NETWORK RATE',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['signaturetravelnetwork.com'], '@href')}]")->length === 0) {
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
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Confirmation Number:")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([\dA-Z]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest Details")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([[:alpha:] \-]+)\s*$/"), true)
        ;

        $cancellation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Policy:")) . "]/following::text()[normalize-space()][1]");
        $cancellation = preg_replace("/CANCELLATION POLICIES[\s\*]+/", "", $cancellation);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Name")) . "]/following::text()[normalize-space()][1]"))
            ->address($this->re('/' . $this->opt("Hotel Address") . '[\s,]*(.+)$/', implode(', ',
                $this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel Address")) . "]/ancestor::td[1]//text()[normalize-space()]"))))
            ->phone($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Phone")) . "]/following::text()[normalize-space()][1]",
                null, true, "/(?:^|:)\s*([\d\+\- \(\)]{5,})\s*(?:\-\s*Main\s*number)?$/"), true, true)
            ->fax($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Fax")) . "]/following::text()[normalize-space()][1]",
                null, true, "/(?:^|:)\s*([\d\+\- \(\)]{5,})\s*(?:\-\s*Sales office)?$/"), true, true)

        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-In Date:")) . "]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-Out Date:")) . "]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Number of Rooms:")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d+)\s*$/"))
        ;
        // Rooms
        // no examples with 2 or more rooms
        $types = $this->http->FindNodes("//text()[" . $this->eq($this->t("Room Description:")) . "]/following::text()[normalize-space()][1]");
        $ratesNodes = $this->http->XPath->query("//text()[" . $this->starts($this->t("Per night starting")) . "]/ancestor::td[1]");

        if (count($types) == $ratesNodes->length) {
            foreach ($ratesNodes as $i => $root) {
                $rates = array_filter($this->http->FindNodes(".//text()[normalize-space()]", $root, "/^\s*\d{1,2}\/\d{1,2}\/\d{2} - (\d+.+)$/"));
                $h->addRoom()
                    ->setDescription($types[$i])
                    ->setRates($rates)
                ;
            }
        } elseif (!empty($types)) {
            foreach ($types as $type) {
                $h->addRoom()
                    ->setDescription($type);
            }
        }

        // Price
        $price = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Amount Before Tax:")) . "])[1]",
            null, true, '/' . $this->opt($this->t("Amount Before Tax:")) . '\s*(.+)/');

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $price, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($m['curr'])
            ;
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Guest Details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Guest Details'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', $rules) . ')';
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        //$this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        //$this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
