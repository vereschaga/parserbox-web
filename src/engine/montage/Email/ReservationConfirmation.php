<?php

namespace AwardWallet\Engine\montage\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "montage/it-92368339.eml";
    public $subjects = [
        '/Montage\D+\|\s*Reservation Confirmation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Check In:'  => ['Check In:', 'Check In'],
            'Check Out:' => ['Check Out:', 'Check Out'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'montage\.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Montage')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation Details')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We truly look forward to'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/montage\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Confirmation')]/following::td[1]"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest Name')]/following::td[1]"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy:')]/following::text()[string-length()>1][1]"));

        $address = $phone = null;

        $hotelContacts = $this->htmlToText($this->http->FindHTMLByXpath("//tr[count(descendant::img)>1 and descendant::img[contains(@alt,'Facebook') or contains(@src,'Images/fb.jpg')] and normalize-space()='']/preceding-sibling::tr[normalize-space()][1]"));

        if (empty($hotelContacts)) {
            $hotelContacts = $this->htmlToText($this->http->FindHTMLByXpath("//img[contains(@alt,'Facebook') or contains(@src,'Images/fb.jpg')]/ancestor::table[1]/preceding::td[1]"));
        }

        if (preg_match("/^\s*(?<address>.{3,}?)[ ]*\n+(?<phone>\s*\[*[+(\d][-. \d)(\]]{5,}[\d)])\s*$/", $hotelContacts, $m)) {
            $address = $m['address'];
            $phone = str_replace(['[', ']'], '', $m['phone']);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We truly look forward to welcoming you to'))}]", null, true, "/{$this->opt($this->t('We truly look forward to welcoming you to'))}\s*(\D+)\./")
           ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]", null, true, "/{$this->opt($this->t('Thank you for choosing'))}\s*(\D+){$this->opt($this->t('for your upcoming stay'))}\./");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
        ;

        $dateCheckIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date')]/following::text()[normalize-space()][1]");
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In:'))}]/following::text()[string-length()>1][1]");

        $dateCheckOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure')]/ancestor::td[1]/following::td[1]");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out:'))}]/following::text()[string-length()>1][1]");

        $h->booked()
            ->checkIn(strtotime($dateCheckIn . ', ' . $timeCheckIn))
            ->checkOut(strtotime($dateCheckOut . ', ' . $timeCheckOut));

        $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Type')]/ancestor::td[1]/following::td[1]");
        $rateType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate Type')]/ancestor::td[1]/following::td[1]");
        $nightlyRate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Nightly Rate')]/ancestor::td[1]/following::td[1]");
        $nightlyRates = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Nightly Rate')]/ancestor::td[1]/following::td[1]//tr[not(.//tr)]/*[2]");

        if (!empty($roomType) || !empty($rateType) || !empty($nightlyRate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }

            if (!empty($nightlyRates) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                $nights = date_diff(
                    date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                    date_create('@' . strtotime('00:00', $h->getCheckInDate()))
                )->format('%a');

                if ($nights == count($nightlyRates)) {
                    $room->setRates($nightlyRates);
                }
            }

            if (empty($room->getRates()) && !empty($nightlyRate) && strlen($nightlyRate) <= 400) {
                $room->setRate($nightlyRate);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Reservations (?i)must be cancell?ed(?: or changed)? by (?<time>(?:noon|[\d\:]+\s*a?p?m)) local property time (?<prior>\d{1,3}) day\(s\) before arrival to avoid/u', $cancellationText, $m)) {
            if ($m['time'] === 'noon') {
                $m['time'] = '12:00';
            }
            $h->booked()->deadlineRelative($m['prior'] . ' days', $m['time']);
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
