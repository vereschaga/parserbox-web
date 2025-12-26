<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2022 extends \TAccountChecker
{
    public $mailFiles = "marriott/it-186162523.eml";
    public $subjects = [
        'Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email1.marriott-vacations.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Marriott')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'STAY PREFERENCES')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'GUEST PROFILE')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'usage details')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email1\.marriott\-vacations\.com$/', $from) > 0;
    }

    public function ParseHotels(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation #:'))}\s*(\d+)/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Transaction date:'))}\s*(.+)/")))
        ;

        $travellers = [];
        $travellersValues = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Additional Guests:' or normalize-space()='Name (Primary):'] ]/*[normalize-space()][2]");

        foreach ($travellersValues as $tVal) {
            foreach (preg_split('/(\s*,\s*)+/', $tVal) as $name) {
                if (preg_match("/^{$patterns['travellerName']}$/u", $name)) {
                    $travellers[] = $name;
                } else {
                    $travellers = [];

                    break 2;
                }
            }
        }
        $h->general()->travellers($travellers, true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'PHONE:')]/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[contains(normalize-space(), 'PHONE:')]/preceding::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[contains(normalize-space(), 'PHONE:')]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['phone']}/"))
        ;

        $checkInVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Check-In:'] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $checkOutVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Check-Out:'] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match($pattern = "/^(?<date>.{6,}?)\s+at\s+(?<time>{$patterns['time']})$/", $checkInVal, $m)) {
            $h->booked()->checkIn(strtotime($m['time'], strtotime($m['date'])));
        }

        if (preg_match($pattern, $checkOutVal, $m)) {
            $h->booked()->checkOut(strtotime($m['time'], strtotime($m['date'])));
        }

        $h->booked()->guests($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='# Of Guests:'] ]/*[normalize-space()][2]", null, true, '/^(\d{1,3})\b/'));

        $roomType = $roomDescription = null;
        $roomTypeVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Room Type:'] ]/*[normalize-space()][2]");

        if (preg_match("/^(?<type>.*?)\s*\(\s*(?<desc>[^)(]*?)\s*\)$/", $roomTypeVal, $m)) {
            // 1 Bedroom Premium Villa, 1 Bathroom (Master portion of a Two-Bedroom Lockoff Villa)
            $roomType = $m['type'];
            $roomDescription = $m['desc'];
        } else {
            $roomType = $roomTypeVal;
        }

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();
            $room->setType($roomType, false, true);
            $room->setDescription($roomDescription, false, true);
        }

        $pointSpent = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'POINTS APPLIED:')]/following::text()[normalize-space()][1]");

        if (!empty($pointSpent)) {
            $h->price()
                ->spentAwards($pointSpent);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotels($email);

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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
