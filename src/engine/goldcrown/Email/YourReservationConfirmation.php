<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-95541891.eml, goldcrown/it-96299867.eml";
    public $subjects = [
        '/Best Western \D+\: Your Reservation Confirmation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Confirmation'        => ['Confirmation Number', 'Confirmation'],
            'HOTEL'               => ['HOTEL', 'ACCOMMODATIONS'],
            'DIRECTIONS'          => ['DIRECTIONS', 'CONTACT'],
            'CONTACT INFORMATION' => ['CONTACT INFORMATION', 'CONTACT Information'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reneson.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Best Western')]")->length > 0
            && $this->http->XPath->query("//tr[{$this->contains($this->t('HOTEL'))} and {$this->contains($this->t('DIRECTIONS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CONTACT INFORMATION'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reneson\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation'))}\s*([\dA-Z]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Guest Name'))}\s*(.+)/"));

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $hotelInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('HOTEL'))}]/preceding::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr[normalize-space()][1]");
        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('HOTEL'))}]/preceding::text()[normalize-space()][1]/ancestor::table[1]/descendant::img/@alt");
        if (empty($hotelInfo)) {
            $hotelInfo = $this->http->FindSingleNode("//*[ *[1][not(normalize-space()) and .//img[contains(@src, 'logo')] ]     and *[normalize-space()][1]//text()[starts-with(normalize-space(), 'p.')]]/*[normalize-space()][1]/descendant::tr[normalize-space()][1]");
            $hotelName = $this->http->FindSingleNode("//*[ *[1][not(normalize-space()) and .//img[contains(@src, 'logo')] ]     and *[normalize-space()][1]//text()[starts-with(normalize-space(), 'p.')]]/*[normalize-space()][1]/descendant::img/@alt");
        }
        if (preg_match("/\s*(?<address>.+)\s+p\.\s+(?<phone>.+)\s+.+f\.\s+(?<fax>.+)\s*/su", $hotelInfo, $m)
            || preg_match("/\s*(?<address>.+)\s*p\.\s+(?<phone>.+)[•]/su", $hotelInfo, $m)
        ) {
            $h->hotel()
                ->name($hotelName)
                ->address($m['address'])
                ->phone($m['phone']);

            if (isset($m['fax'])) {
                $h->hotel()
                    ->fax($m['fax']);
            }
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Arrival Date']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Arrival Date'))}\s*(.+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Departure Date']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Departure Date'))}\s*(.+)/")));

        $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-In Time']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-In Time'))}\s*(.+)/");

        if ($h->getCheckInDate() && !empty($inTime)) {
            $h->booked()
                ->checkIn(strtotime($inTime, $h->getCheckInDate()));
        }

        $outTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-Out Time']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-Out Time'))}\s*(.+)/");
        $outTime = preg_replace("/\s*(pm)?\s*\(Noon\)/i", " PM", $outTime);

        if ($h->getCheckOutDate() && !empty($outTime)) {
            $h->booked()
                ->checkOut(strtotime($outTime, $h->getCheckOutDate()));
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]/descendant::td[2]");
        $rate = implode('; ', $this->http->FindNodes("//text()[normalize-space()='Nightly Rate']/ancestor::tr[1]/descendant::td[2]/descendant::tr"));

        if (!empty($roomType) || !empty($rate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rate)) {
                $room->setRate($rate);
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match('/Cancellations must be received by (?<time>[\d\:]+\s*A?P?M), (?<priorH>\d+) hours prior to date/', $cancellationText, $m)
            || preg_match('/^\s*(?<priorH>\d+) hrs notice is required to cancel without charge\./', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['priorH'] . ' hours', $m['time'] ?? '0:00');
        }
    }
}
