<?php

namespace AwardWallet\Engine\tmobtrav\Email;

use AwardWallet\Schema\Parser\Email\Email;

class UpcomingStay extends \TAccountChecker
{
    public $mailFiles = "tmobtrav/it-394289764.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'  => ['Check-in:'],
            'checkOut' => ['Check-out:'],
        ],
    ];

    private $subjects = [
        'en' => ['Information for your upcoming stay at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tmobiletravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".tmobiletravel.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"T-Mobile TRAVEL is powered by") or contains(.,"concierge@tmobiletravel.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('UpcomingStay' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('Your reservation is viewable here'))}]")->length > 0) {
            $h->general()->noConfirmation();
        }

        $primaryGuest = $this->http->FindSingleNode("//text()[{$this->contains($this->t('present the name of the primary guest'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($primaryGuest, true);

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^.{6,}$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('checkOut'))}] ]/*[normalize-space()][2]", null, true, "/^.{6,}$/"));

        $timeCheckIn = $this->normalizeTime($this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('checkIn'))}]", null, true, "/{$this->opt($this->t('checkIn'))}[:\s]*({$patterns['time']})$/"));
        $timeCheckOut = $this->normalizeTime($this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('checkOut'))}]", null, true, "/{$this->opt($this->t('checkOut'))}[:\s]*({$patterns['time']})$/"));

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Type'))}] ]/*[normalize-space()][2]");
        $room->setType($roomType);

        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Ready to check-in to'))}]", null, true, "/{$this->opt($this->t('Ready to check-in to'))}\s+(.{3,70}?)[ ]*[.?!]/");
        $address = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address'))}] ]/*[normalize-space()][2]");
        $h->hotel()->name($hotelName)->address($address);

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

    /*
        Used in parser tmobtrav/YourReservation
    */
    public static function normalizeTime(?string $s): ?string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1]; // 21:51 PM    ->    21:51
        }

        return $s;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
