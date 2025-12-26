<?php

namespace AwardWallet\Engine\rocketmiles\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "rocketmiles/it-634792756.eml, rocketmiles/it-634797498.eml";
    public $lang = '';
    public static $dictionary = [
        'en' => [
            "CancelledText" => ["We’ve canceled your booking", "Your hotel reservation has been canceled.", 'we canceled your upcoming hotel booking at'],
            // "Reference code" => "",
            "Primary guest" => "Primary guest",
            // "Hotel" => "",
            // "Room type" => "",
            "Number of guests" => ["Number of guests", "Number of adults"],
            // "Number of children" => "",
            // "Check-in:" => "",
            // "Check-out:" => "",
            "Hotel address"      => "Hotel address",
            "Total miles earned" => ["Total miles earned", "Total AAdvantage® miles earned", 'Total Velocity Points earned'],
            // "Cancellation policy" => "",
            // "Total paid" => "",
            "Total redeemed" => ["Total redeemed", "Velocity Points used"],
        ],
    ];

    private static $providers = [
        'aeromexico'    => '@clubpremier.com',
        'rapidrewards'  => '@southwesthotels.com',
        'hotels'        => '@hotelstorm.com',
        'alaskaair'     => '@hotels.alaskaair.com',
        'kayak'         => 'hotels@opentable.kayak.com',
        'aa'            => '@mail.aadvantagehotels.com',
        'lanpass'       => 'latampass@rocketmiles.com',
        'golair'        => ['smiles@rocketmiles.com', '@smiles.com.br', 'www.smiles.com.br'],
        'aviancataca'   => ['LifeMileshotels@rocketmiles.com'],
        'skywards'      => ['emiratesskywardshotels@rocketmiles.com'],
        'velocity'      => ['Virgin Australia has partnered with', 'Total Velocity Points earned'],
        'rocketmiles'   => ['@rocketmiles.com', 'Rocket Travel'], // always last
    ];

    private $subject = [
        // en
        'Your hotel reservation is confirmed.',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            return false;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $provider = $this->getProviderCode($parser->getCleanFrom());

        if (empty($provider)) {
            $provider = $this->getProviderCode();
        }
        $email->setProviderCode($provider);

        $this->parseEmail($email);

        return $email;
    }

    public function getProviderCode($from = null)
    {
        if (!empty($from)) {
            foreach (self::$providers as $code => $provider) {
                if ($this->containsText($from, $provider) !== false) {
                    return $code;
                }
            }
        } else {
            foreach (self::$providers as $code => $provider) {
                if ($this->http->XPath->query("//node()[{$this->contains($provider)}]")->length > 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->getProviderCode())) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($this->getProviderCode($headers["from"]))) {
            return false;
        }

        foreach ($this->subject as $subject) {
            if ($this->containsText($headers["subject"], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'rocketmiles.com') !== false;
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
        return array_keys(self::$providers);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        // Travel agency
        $conf = $this->nextTd($this->t("Reference code"), "/^\s*[A-Z\d]{5,}\s*$/");

        $email->ota()
            ->confirmation($conf);

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->nextTd($this->t("Primary guest")), true)
        ;

        if ($this->http->XPath->query("//*[{$this->contains($this->t('CancelledText'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("Cancellation policy")) . "])[1]", null, true, "/" . $this->opt($this->t("Cancellation policy")) . "\W*(.+)/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Cancellation policy")) . "])[1]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[" . $this->eq($this->t("Cancellation policy")) . "])][last()]");
        }

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t("Hotel")))
            ->address($this->nextTd($this->t("Hotel address")))
            ->phone($this->nextTd($this->t("Phone number")), true, true)
        ;

        // Price
        $total = $this->nextTd($this->t("Total paid"), "#^(?:.+ \+ )?(.*\d+.*)#");

        if (!empty($total)) {
            $h->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }
        $totalSpent = $this->nextTd($this->t("Total redeemed"), "#^\s*(.*?\d+.*?)\s*(?:\(.*\))?\s*$#");

        if (!empty($totalSpent)) {
            $h->price()
                ->spentAwards($totalSpent);
        }

        $miles = $this->nextTd($this->t("Total miles earned"));

        if (!empty($miles)) {
            $email->ota()
                ->earnedAwards($miles);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate(
                $this->http->FindSingleNode("//td[{$this->starts($this->t('Check-in:'))}]/following-sibling::*[normalize-space()][1]")
                . ', ' . $this->normalizeTime($this->http->FindSingleNode("//td[{$this->starts($this->t('Check-in:'))}]", null, true, "/^\s*{$this->opt($this->t('Check-in:'))}\s*(.+)/"))
            ))
            ->checkOut($this->normalizeDate(
                $this->http->FindSingleNode("//td[{$this->starts($this->t('Check-out:'))}]/following-sibling::*[normalize-space()][1]")
                . ', ' . $this->normalizeTime($this->http->FindSingleNode("//td[{$this->starts($this->t('Check-out:'))}]", null, true, "/^\s*{$this->opt($this->t('Check-out:'))}\s*(.+)/"))
            ))
            ->guests($this->nextTd($this->t("Number of guests")), true, true)
            ->kids($this->nextTd($this->t("Number of children")), true, true)
        ;

        // Room
        $r = $h->addRoom();
        $r->setType($this->nextTd($this->t("Room type")));

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (preg_match("/^\s*This booking will be 100% refundable if cancelled by (?<time>\d+:\d+(?: *[AP]M)?) local time on (?<date>\w+ \w+)\./", $cancellationText, $m) // en
        ) {
            $dateDeadline = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false);

            if ($dateDeadline) {
                $h->booked()->deadline(strtotime($m['time'], $dateDeadline));
            }
        }

        // nonRefundable
        if (
            preg_match("/This booking is nonrefundable\./i", $cancellationText) // en
        ) {
            $h->booked()->nonRefundable();
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Hotel address']) && !empty($dict['Primary guest'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Hotel address'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($dict['Primary guest'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Wed, 14 Feb, 2024, 11:00 AM
            '/^\s*[[:alpha:]]+\s*,\s*(\d+)\s+([[:alpha:]]+)\s*,\s*(\d{4})\s*[,\s]\s*(\d+:\d+(?:\s*[ap]m)?)\s*$/iu',
            // Friday, July 25, 2014
            '/^\s*[[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d+)\s*,\s*(\d{4})\s*[,\s]\s*(\d+:\d+(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date 2 = '.print_r( $date,true));
        $date = $this->dateStringToEnglish($date);

        return strtotime($date);
    }

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1];
        } // 21:51 PM    ->    21:51
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25

        return $s;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextTd($field, $regexp = null)
    {
        $text = $this->http->FindSingleNode("descendant::td[" . $this->eq($field) . "]/following-sibling::*[1]", null, true, $regexp);

        return $text;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $node = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ',"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
}
