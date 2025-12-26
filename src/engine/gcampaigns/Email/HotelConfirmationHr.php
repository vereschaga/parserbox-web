<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelConfirmationHr extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-254117682.eml, gcampaigns/it-256943771.eml, gcampaigns/it-307738447.eml, gcampaigns/it-887175420.eml";

    public $reFrom = ["pkghlrss.com", 'info@cvent.com', '@bjcvip.com'];
    public $reBody = [
        'en' => ['Upcoming Event'],
    ];
    public $reSubject = [
        'Hotel Reservation Confirmation',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Date booked'             => ['Date booked', 'Date Reserved:'],
            'Confirmation'            => ['Acknowledgment number', 'Hotel confirmation number', 'Marriott Confirmation Number:', 'Acknowledgment Number:'],
            'Check-In'                => ['Check-In', 'Check-in'],
            'Check-Out'               => ['Check-Out', 'Checkout'],
            'Room Type'               => ['Room Type', 'Room type'],
            'All reservation changes' => ['All reservation changes', 'Visit our'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($code = $this->detectProv())) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectProv()
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Marriott Bonvoy'))}]")->length > 0) {
            return 'marriott';
        }

        return null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.passkey.com')] | //img[contains(@src,'groupmax-content')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]/following::hr/following::tr[not(.//tr)][normalize-space()][1][{$this->contains($this->t('Date booked'))}]")->length > 0
                 || $this->http->XPath->query("//*[{$this->contains($reBody)}]/following::tr/following::tr[not(.//tr)][normalize-space()][1][{$this->contains($this->t('Date booked'))}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['marriott', 'gcampaigns'];
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if ($fromProv && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        foreach ($this->t('Confirmation') as $title) {
            $conf = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($title)}]]/descendant::text()[normalize-space()][2]",
                null, true, "/^\s*#\s*([A-Z\d]{5,})\s*$/");

            if (!empty($conf)) {
                $h->general()
                    ->confirmation($conf, trim($title, ':'));
            }
        }

        $cancellation = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}]]",
            null, true, "/^\s*Cancellation Policy\s*(.+)/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}]]/following-sibling::td[1]");
        }

        $h->general()
            ->date(strtotime($this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Date booked'))}]]/descendant::text()[normalize-space()][2]")))
            ->cancellation($cancellation);

        $travellers = $this->http->FindNodes("//td[not(.//td)][{$this->eq(['Share-withs'])}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//td[not(.//td)][{$this->starts(['Check-Out'])}][preceding-sibling::td[normalize-space()][1][descendant::text()[normalize-space()][3][{$this->eq('Guest Information')}]]]/descendant::text()[normalize-space()][3]");
        }

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//td[not(.//td)][{$this->eq(['Guest information'])}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()][1]");
        }

        if (!empty($travellers)) {
            $h->general()
                ->travellers($travellers);
        }

        $xpathStart = "//text()[({$this->eq($this->t('Date booked'))})]/ancestor::tr[1]/preceding::td[normalize-space()][1]";
        $this->logger->debug($xpathStart);
        $xpaths = [
            "[count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()]",
            "[count(.//span[not(.//span)][normalize-space()]) = 2]/descendant::span[not(.//span)][normalize-space()]",
            "[count(.//text()[normalize-space()]) > 2 and descendant::text()[normalize-space()][3][{$this->starts($this->t('All reservation changes'))}]]/descendant::text()[normalize-space()]",
        ];

        foreach ($xpaths as $xp) {
            if ($this->http->XPath->query($xpathStart . $xp)->length > 0) {
                $h->hotel()
                    ->name($this->http->FindSingleNode($xpathStart . $xp . "[1]"))
                    ->address($this->http->FindSingleNode($xpathStart . $xp . "[2]", null, true, "/^\s*(.+?)\s*(?:\||$)/"))
                    ->phone($this->http->FindSingleNode($xpathStart . $xp . "[2]", null, true, "/^\s*.+?\s*\|\s*(.+)/"), true, true)
                ;
            }
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-In'))}]]/descendant::text()[normalize-space()][2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-Out'))}]]/descendant::text()[normalize-space()][2]")))
            ->guests($this->http->FindSingleNode("//td[not(.//td)][{$this->eq(['Guests per room'])}]/following-sibling::td[normalize-space()][1]"));

        $account = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Rewards Number')]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s+(\d{5,})$/");

        if (!empty($account)) {
            if (count($travellers) === 1) {
                $h->addAccountNumber($account, false, array_shift($travellers));
            } else {
                $h->addAccountNumber($account, false);
            }
        }
        $currency = $this->http->FindSingleNode("//td[not(.//td)][{$this->starts('Grand Total')}]",
            null, true, "/\(([A-Z]{3})\)$/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate (')]",
            null, true, "/\(\s*([A-Z]{3})/");
        }
        $h->price()
            ->total(PriceHelper::parse($this->http->FindSingleNode("//td[not(.//td)][{$this->starts('Grand Total')}]/following-sibling::td[normalize-space()][1]"), $currency))
            ->currency($currency);

        $rateNodes = $this->http->XPath->query("//text()[normalize-space()='Rate Summary']/ancestor::tr[1]/descendant::tbody/descendant::tr[not(contains(normalize-space(), 'Total'))]");
        $rate = [];

        foreach ($rateNodes as $rateRoot) {
            $dateRate = $this->http->FindSingleNode("./descendant::td[1]", $rateRoot);
            $summRate = $this->http->FindSingleNode("./descendant::td[4]", $rateRoot, true, "/^([\d\.\,\']+)$/");

            if (!empty($dateRate) && !empty($summRate)) {
                $rate[] = $dateRate . ' - ' . $summRate . ' / night';
            }
        }

        $roomType = $this->nextField($this->t('Room Type'));

        if (!empty($roomType) || count($rate) > 0) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (count($rate) > 0) {
                $room->setRates($rate);
            }
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function nextField($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][normalize-space()!=':'][1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            //12-Nov-2019
            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Any cancellation or amendment must be received no later than \w+, (?<date>.+? \d{4})\. Failing to do/i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->dateStringToEnglish($m['date'])));
        } elseif (preg_match("/Any modification or cancellation of bookings must be received no later than (.+? \d+) prior to/i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->dateStringToEnglish($m[1])));
        } elseif (preg_match("/To avoid a one night\'s room and tax penalty, reservations must be cancelled by\s*(?<time>[\d\:]+\s*a?p?m)\s+local time\s+(?<hours>\d+\shours?)\s+prior to arrival/i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['hours'], $m['time']);
        } elseif (preg_match("/Reservations must be cancelled by (?<hours>\d+\shours?) prior to date of arrival to avoid being charged one night's room & tax./i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['hours']);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
