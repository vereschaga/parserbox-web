<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-45943338.eml";

    public $reFrom = ["pkghlrss.com"];
    public $reBody = [
        'en' => ['Check-in Location', 'We are delighted to confirm your reservation and look forward to welcoming the'],
    ];
    public $reSubject = [
        'Your Reservation at',
        'Your Hotel Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation Code' => 'Confirmation Code',
            'Room Type'         => 'Room Type',
            'Your Reservations Team'         => ['Your Reservations Team', 'Your Group Operations Team'],
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

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.passkey.com')] | //img[contains(@src,'.passkey.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

        $h->general()
            ->confirmation($this->nextField($this->t('Confirmation Code')), $this->t($this->t('Confirmation Code')))
            ->traveller($this->nextField($this->t('Guest Name')), true);

        $hotelName = $this->http->FindSingleNode("//span[({$this->starts($this->t('Sincerely'))}) and ({$this->contains($this->t('Your Reservations Team'))})]",
            null, false, "#{$this->opt($this->t('Your Reservations Team'))}\s?+(.+)#");
        $h->hotel()
            ->name($hotelName)
            ->noAddress();

        $h->booked()
            ->checkIn($this->normalizeDate($this->nextField($this->t('Arrival Date'))))
            ->checkOut($this->normalizeDate($this->nextField($this->t('Departure Date'))))
            ->guests($this->nextField($this->t('Number of Guests')));

        if ($h->getCheckInDate()) {
            if ($time = strtotime($this->nextField($this->t('Check-in Time')), $h->getCheckInDate())) {
                $h->booked()->checkIn($time);
            }
        }

        if ($h->getCheckOutDate()) {
            if ($time = strtotime($this->nextField($this->t('Check-out Time')), $h->getCheckOutDate())) {
                $h->booked()->checkOut($time);
            }
        }

        $sum = str_replace(":", '',
            $this->http->FindSingleNode("//text()[{$this->starts('Total Price')}]/ancestor::tr[1]"));
        $sum = $this->getTotalCurrency($sum);
        $h->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        $room = $h->addRoom();
        $room->setType($this->nextField($this->t('Room Type')));

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]");

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Any modification or cancellation of bookings'))}]/ancestor::span[1]");
        }
        $h->general()->cancellation($cancellation);

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
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->dateStringToEnglish($m['date'])));
        } elseif (preg_match("/Any modification or cancellation of bookings must be received no later than (.+? \d+) prior to/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->dateStringToEnglish($m[1])));
        }
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Confirmation Code'], $words['Room Type'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation Code'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Room Type'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function getTotalCurrency($node)
    {
        $node = str_replace(["S$"], ["SGD"], $node);
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
