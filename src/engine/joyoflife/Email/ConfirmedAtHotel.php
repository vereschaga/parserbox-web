<?php

namespace AwardWallet\Engine\joyoflife\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmedAtHotel extends \TAccountChecker
{
    public $mailFiles = "joyoflife/it-17799695.eml";

    private $reFrom = "jdvhotels.com";
    private $reBody = [
        'en' => 'JOIE DE VIVRE HOUSE RULES',
    ];
    private $reSubject = [
        'You\'re Confirmed at',
    ];
    private $lang = '';
    private $emailSubject;
    private static $dict = [
        'en' => [
            'Number of Kids'       => ['Number of Kids', 'Kids'],
            'Accommodation'        => ['Accommodation', 'Accommodation Request'],
            'Average Nightly Rate' => ['Average Nightly Rate', 'Nightly Rate'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->emailSubject = $parser->getSubject();
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Confirmation #')) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guest Name')) . "]/following::text()[normalize-space()][1]"));

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Check In')) . "]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Check Out')) . "]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Number of Adults')) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"))
            ->kids($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Number of Kids')) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"));
        $h->general()
            ->cancellation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Cancellation Policy:')) . "]/following::text()[normalize-space()][1]"));

        if (!empty($h->getCheckInDate()) && $time = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check-in time is after')) . "][1]", null, true, "#Check-in time is after\s*(.+?)and#")) {
            $h->booked()
                ->checkIn(strtotime($this->normalizeTime($time), $h->getCheckInDate()));
        }

        if (!empty($h->getCheckOutDate()) && $time = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('check-out time is')) . "][1]", null, true, "#check-out time is\s*(.+?)\.#")) {
            $h->booked()
                ->checkOut(strtotime($this->normalizeTime($time), $h->getCheckOutDate()));
        }

        // Room
        $rm = $h->addRoom();
        $rm
            ->setType($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Accommodation')) . "]/following::text()[normalize-space()][1]"))
            ->setRateType($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Average Nightly Rate')) . "]/following::text()[normalize-space()][1]"));

        // Hotel
        if (!empty($this->emailSubject) && preg_match("#Confirmed at\s+(.+)\s*\(Ref#", $this->emailSubject, $m)) {
            $h->hotel()
                ->name(trim($m[1]))
                ->address($this->http->FindSingleNode("(//text()[" . $this->eq(trim($m[1])) . "])[last()]/following::text()[normalize-space()][1]"));
        }

        //Price
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total Taxes')) . "]/following::text()[normalize-space()][1]"));

        if (!empty($sum['Total'])) {
            $h->price()
                ->tax($sum['Total']);
        }

        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total Charges with Tax')) . "]/following::text()[normalize-space()][1]"));

        if (!empty($sum['Total'])) {
            $h->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Reservation has a (\d+ hour) cancellation policy \(non-refundable/non-cancellable reservations may not cancel\).#i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match("#All guaranteed reservations need to be cancelled by at least (\d+ *[ap]m|\d+:\d+(?: *[ap]m)?) (\d+ days?) prior to arrival.#i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[2], $m[1]);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*[^\d\s]+,\s*([^\d\s\.\,]+)\s+(\d{1,2}),\s*(\d{4})\s*(?:\(.*)?$#', //Sunday, July 8, 2018
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function normalizeTime($date)
    {
        $in = [
            '#^\s*(\d+)\s*([ap]m)\b$#i', //3 pm
        ];
        $out = [
            '$1:00 $2',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
