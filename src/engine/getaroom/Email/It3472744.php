<?php

namespace AwardWallet\Engine\getaroom\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3472744 extends \TAccountCheckerExtended
{
    public $mailFiles = "getaroom/it-3472744.eml"; // +1 bcdtravel(html)[de]

    public $lang = '';

    public static $dictionary = [
        'de' => [
            'Details of your stay' => ['Details Ihres Aufenthalts'],
            //            'Status' => '',
            'Confirmation number' => ['Bestätigungsnummer'],
            'Check in'            => 'Anreise',
            'Check out'           => 'Abreise',
            'Total'               => 'Finaler Preis',
            'Hotel Info'          => 'Hotel',
            'Room %'              => 'Zimmer %',
            'Room'                => 'Zimmer',
            'Guest'               => 'Gast',
            'Adults'              => 'Erw.',
            'Children'            => 'Kinder',
            'Cancellation policy' => 'Stornobedingungen',
        ],
        'en' => [
            'Details of your stay' => ['Details of your stay'],
            'Confirmation number'  => ['Confirmation number'],
        ],
    ];

    private $subjects = [
        'de' => ['Hotel Reservations. Bestätigungsnummer'],
        'en' => ['Hotel Reservations. Confirmation number'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@getaroom.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, 'getaroom.com') !== false && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        $body = $parser->getHTMLBody();
//        if ( empty($body) ) {
//            $body = $parser->getPlainBody();
//            $this->http->SetEmailBody($body);
//        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->status($this->getField("Status"))
            ->confirmation($this->getField("Confirmation number"))
            ->traveller($this->getField("Guest"));

        $h->booked()
            ->checkIn2($this->getField("Check in"))
            ->checkOut2($this->getField("Check out"));

        $h->price()
            ->total(cost($this->getField("Total")))
            ->currency(currency($this->getField("Total")));

        $hotelName = $this->http->FindSingleNode("//td[{$this->eq($this->t('Hotel Info'))}]/following-sibling::td[normalize-space()][1]/*[1]");
        $address = $this->http->FindSingleNode("//td[{$this->eq($this->t('Hotel Info'))}]/following-sibling::td[normalize-space()][1]/*[2]");
        $h->hotel()
            ->name($hotelName)
            ->address(preg_replace('/(\s*,\s*)+/', '$1', $address));

        $h->booked()
            ->guests($this->getField("Adults"))
            ->kids($this->getField("Children"));

        $room = $h->addRoom();
        $xpathRoom = "//tr[{$this->eq($this->t('Room %'), 'translate(normalize-space(),"0123456789","%%%%%%%%%%")')}]";
        $roomType = $this->http->FindSingleNode($xpathRoom . "/following::td[{$this->eq($this->t('Room'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/[!.]\s*([^!.]+)$/', $roomType, $m)) {
            // it-3472744.eml
            $roomType = $m[1];
        }
        $room->setType($roomType);

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::tr[normalize-space()][1]");
        $h->general()->cancellation($cancellation);

        if ($cancellation) {
            if (preg_match("/Stornierungen vor (?<dateTime>.{6,}?) \([\w \/]+\) sind vollständig rückzahlbar\./iu", $cancellation, $m)) {
                $h->booked()->deadline2($m['dateTime']);
            } elseif (preg_match("/Cancellations before (?<dateTime>.{6,}?) \([\w \/]+\) are fully refundable\./iu", $cancellation, $m)) {
                $h->booked()->deadline2($m['dateTime']);
            } else {
                $h->booked()->parseNonRefundable('This reservation is non-refundable.');
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Details of your stay']) || empty($phrases['Confirmation number'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Details of your stay'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation number'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function getField(string $str): ?string
    {
        $rule = $this->eq($this->t($str));

        return $this->http->FindSingleNode('//td[' . $rule . ']/following-sibling::td[normalize-space()][1]');
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
