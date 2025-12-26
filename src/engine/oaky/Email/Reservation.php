<?php

namespace AwardWallet\Engine\oaky\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "oaky/it-250526970.eml, oaky/it-256674429.eml, oaky/it-45122609.eml, oaky/it-45251768.eml, oaky/it-45379861.eml";

    public $lang = '';

    public static $dictionary = [
        'no' => [
            'confNumber'     => ['Bookingnummer:'],
            'Dear'           => 'Hei',
            'hotelNameStart' => 'Vi forbereder ditt opphold ved',
            //            'hotelNameEnd' => '',
            'Arrival date:'   => 'Ankomstdato:',
            'Departure date:' => 'Avreosedato:',
            'contacts'        => 'bookingen din er det bare å kontakte',
        ],
        'en' => [
            'confNumber'             => ['Reservation number:', 'Your reservation number is', 'with the reservation number -'],
            'Dear'                   => ['Dear', 'Hello', 'Hi'],
            'hotelNameFromSubjectRe' => [
                'Upgrade your reservation at (?<name>.+)$',
                '(?:Your upcoming stay at|Your reservation at) (?<name>.+) (?:\d{1,2} [[:alpha:]]+ 20\d{2}|\d{2}\/\d{2}\/\d{4})\s*$', // Your upcoming stay at Radisson Blu Hotel, Prague 10 Jun 2022
                '(?:Your upcoming stay at|Your reservation at) (?<name>.+)\s*$', // Your upcoming stay at Radisson Blu Hotel, Prague 10 Jun 2022
            ],
            'hotelNameStart' => [
                'We are looking forward to welcoming you to',
                'We are delighted to be welcoming you at the',
                'We are currently preparing your stay at',
                'We’re looking forward to warmly welcoming you to the',
                'We’re looking forward to welcoming you to the',
                'We’re getting all geared up for your visit to the',
                'It’s almost time for your visit to the iconic',
            ],
            'hotelNameEnd' => ['for your', 'from'],
            'contacts'     => [
                'please do not hesitate to contact', 'please don’t hesitate to contact', 'please contact us directly',
                'can contact us by simply', 'or call us at', 'or call us on',
            ],
        ],
        'fr' => [
            'confNumber'             => ['Votre numéro de réservation est le suivant'],
            'hotelNameFromSubjectRe' => [
                'Upgrade your reservation at (?<name>.+)$',
                'Votre réservation à: (?<name>.+) (?:\d{1,2} [[:alpha:]]+ 20\d{2})\s*$', //  Votre réservation à: Radisson Blu Royal Hotel, Bergen 4 juin 2022
            ],
            //            'hotelNameStart' => [
            //            ],
            //            'hotelNameEnd' => ['for your', 'from'],
            //            'contacts'     => [
            //                'please do not hesitate to contact', 'please don’t hesitate to contact', 'please contact us directly',
            //                'can contact us by simply', 'or call us at', 'or call us on',
            //            ],
            "Dear" => 'Cher/Chère',
            "from" => 'séjour du',
            "to"   => 'au',
        ],
    ];

    private $emailSubject;
    private $subjects = [
        // en
        'Your reservation at',
        'Your upcoming stay at',
        'Upgrade your reservation at',

        // no
        'Oppholdet ditt på',

        // fr
        'Votre réservation à:',
    ];

    private $detectors = [
        'no' => ['Tilpass oppholdet ditt'],
        'en' => ['Personalize your stay', 'Personalise your stay', 'Enhance your stay', 'Click here to get started!'],
        'fr' => ['Personnalisez votre séjour!'],
    ];

    private $detectProvider = [
        'shangrila'    => ['Shangri-La'],
        'carlson'      => ['Radisson'],
        'goldpassport' => ['Hyatt'],
        'marriott'     => ['Residence Inn', 'Moxy', 'The Westin'],
        'hardrock'     => ['HARD ROCK HOTEL'],
        'nordic'       => ['Nordic'],
        'triprewards'  => ['Wyndham'],
        'goldcrown'    => ['Best Western'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info@oakyapp.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"getoaky.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $email->obtainTravelAgency();

        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,})\s*(?:[,.;!?]|$)/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        } else {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/ancestor::*[2]");
            if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,})\b\s*(?:[,.;!? ]|$)/", $confirmation, $m)) {
                $h->general()->confirmation($m[2], rtrim($m[1], ': '));
            }
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:[,;!?]|$)/u");
        $h->general()->traveller($guestName);

        // Hotel
        foreach ((array) $this->t('hotelNameFromSubjectRe') as $re) {
            if (preg_match("/" . $re . "/", $this->emailSubject, $m) && !empty($m['name']) && strlen(preg_replace("/\D+/", '', $m['name'])) < 5) {
                $hotelName_temp = trim($m['name']);

                break;
            }
        }

        if (!empty($hotelName_temp) && ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1
        || substr_count(strtolower($this->http->Response['body']), strtolower($hotelName_temp)) > 1)) {
            $h->hotel()
                ->name($hotelName_temp)
                ->noAddress();
        } else {
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('hotelNameStart'))}]",
                null, true,
                "/{$this->opt($this->t('hotelNameStart'))}\s+(.{3,}?)\s*(?:[.;!?]|{$this->opt($this->t('hotelNameEnd'))})/");

            if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $h->hotel()
                    ->name($hotelName_temp)
                    ->noAddress();
            }
        }
        $hotelName = $h->getHotelName();

        if (!empty($hotelName)) {
            $str = implode("\n", $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/following::text()[normalize-space()][position()<3]"));

            if (preg_match("/^(?:Tel|T) *: *(?<phone>[+(\d][-. \d)(]{5,}[\d)])\s*$/m", $str, $m)) {
                $phone = $m['phone'];
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('contacts'))}]", null, true,
                "/[+(\d][-. \d)(]{5,}[\d)]/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('contacts'))}]/following::text()[normalize-space()!=''][position()<3][{$this->contains($this->t('or by phone'))}]",
                null, true, "/[+(\d][-. \d)(]{5,}[\d)]/");
        }

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
        }

        if (!empty($hotelName)) {
            foreach ($this->detectProvider as $prov => $values) {
                if (preg_match("/(^| |\b)" . $this->opt($values) . "(\b| |$)/", $hotelName)) {
                    $h->setProviderCode($prov);

                    break;
                }
            }
        }
        // Booked
        $re = "/({$this->opt($this->t('from'))}[:\s]+(?<d1>.+?\b20.+?)\s+{$this->opt($this->t('to'))}[:\s]+(?<d2>.+?\b20.+?)\s*(?:[,.;!?]|$))/";
        $dates = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('from'))} and {$this->contains($this->t('to'))} and contains(., '20')])[1]",
            null, true, $re);
        if (empty($dates)) {
            $dates = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('from'))}]/ancestor::*[position() < 4][{$this->contains($this->t('to'))} and contains(., '20')])[1]",
                null, true, $re);
        }
        if (preg_match($re, $dates, $m)) {
            // from 19/09/2019 to 20/09/2019
            $in = strtotime($this->normalizeDate($m['d1']));
            $out = strtotime($this->normalizeDate($m['d2']));

            if ($in && $out) {
                $h->booked()
                    ->checkIn($in)
                    ->checkOut($out);
            }
        }

        if (empty($h->getCheckInDate())) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival date:'))}]", null, true, "/{$this->opt($this->t('Arrival date:'))}\s*(.{6,})/")
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival date:'))}]/following::text()[normalize-space()][1]");
            $h->booked()->checkIn2($this->normalizeDate($checkIn));
        }

        if (empty($h->getCheckOutDate())) {
            $checkOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure date:'))}]", null, true, "/{$this->opt($this->t('Departure date:'))}\s*(.{6,})/")
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date:'))}]/following::text()[normalize-space()][1]");
            $h->booked()->checkOut2($this->normalizeDate($checkOut));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function normalizeDate($text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
//        $this->logger->debug('$text = '.print_r( $text,true));

        $in = [
            // 27/09/2019
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        $text = preg_replace($in, $out, $text);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $text, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $text = str_replace($m[1], $en, $text);
            }
        }

        return $text;
    }
}
