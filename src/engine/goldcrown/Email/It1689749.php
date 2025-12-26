<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1689749 extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-162806159.eml, goldcrown/it-3076812.eml, goldcrown/it-37794129.eml, goldcrown/it-6292901.eml, goldcrown/it-6392337.eml";
    public $subjects = [
        'Best Western Plus',
        'BEST WESTERN PLUS',
        'Reservierungsbestätigung - Best Western Plus',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Cancellation Policy', 'Cancellation Notice:', 'Confirmation Number'],
        'de' => ['Rateninformation:'],
    ];

    public $detects = [
        'en' => [
            'We wanted to remind you that your upcoming visit to BEST WESTERN',
            'Thank you for choosing Best Western',
            'Thank you for booking your stay at the Best Western',
            'Thank you for your reservation at Best Western',
            'We wanted to remind you that your upcoming visit to',
            'If you no longer wish to receive email communications from Best Western',
        ],

        'de' => [
            'wir freuen uns, dass Sie sich für uns entschieden haben',
        ],
    ];

    public $reSubject;
    public $text;

    public static $dictionary = [
        "en" => [
            'Confirmation Number' => ['Confirmation number', 'Confirmation', 'Confirmation Number:', 'Confirmation Number'],
            'Cancellation Policy' => ['Cancellation Policy', 'Cancellation Notice:'],
            'Arrival Date'        => ['Arrival Date', 'Arrival Date:'],
            'Departure Date'      => ['Departure Date', 'Departure Date:'],
            'Room Type'           => ['Room Type', 'Accommodation:'],
            'Mister'              => ['Mister', 'Dear'],
            'Check In Time:'      => ['Check In Time:', 'Check-In Time'],
            'Check Out Time:'     => ['Check Out Time:', 'Check-Out Time'],
            'Telephone:'          => ['Telephone:', 'p.', 'P:'],
            'Adults:'             => ['Adults:', 'Adults'],
        ],

        "de" => [
            'Confirmation Number' => ['Reservierungsbestätigung'],
            'Cancellation Policy' => ['Rateninformation:'],
            'Arrival Date'        => ['Anreise:'],
            'Departure Date'      => ['Abreise:'],
            'Number of Guests:'   => ['Personenanzahl:'],
            'Room Total:'         => ['Gesamtpreis:'],
            'Room Type'           => ['Zimmerkategorie:'],
            'Guest Name'          => ['Gastname:'],
            //'Mister'              => [''],
            //'Check In Time:'      => [''],
            //'Check Out Time:'     => [''],
            'InOutTime'           => ['An- & Abreise:'],
            'Telephone:'          => ['Tel.'],
            'Adults:'             => ['Erwachsene(r)'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bestwestern.') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Best Western') or contains(normalize-space(), 'BEST WESTERN')]")->length > 0) {
            foreach ($this->detects as $lang => $detect) {
                if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]bestwestern\./', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]", null, true, "/{$this->opt($this->t('Confirmation Number'))}\s*\(([A-Z\d]+)\)/");
        }
        $h->general()
            ->confirmation($confirmation);

        $guestName = $this->http->FindNodes("//text()[{$this->contains($this->t('Guest Name'))}]/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (!$guestName) {
            $guestName = $this->http->FindNodes("//text()[{$this->starts($this->t('Mister'))}]", null, "#{$this->opt($this->t('Mister'))}\s+(.+?)(?:,|$)#");
        }

        $h->general()
            ->travellers($guestName, true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]"));

        $hotelName = '';

        if (preg_match("/Best Western Plus\s*(\D+)\:/iu", $this->reSubject, $m)
            || preg_match("/Best Western Plus\s*(\D+)\s+\-/iu", $this->reSubject, $m)
            || preg_match("/Best Western Plus\s*(\D+)/iu", $this->reSubject, $m)
            || preg_match("/Best Western\s*(\D+)\s*\:/", $this->reSubject, $m)) {
            $hotelName = $m[1];
        }

        if (empty($hotelName)) {
            if (preg_match("/Thank\s+you\s+for\s+choosing\s+BEST\s+WESTERN\s*(?:PLUS){0,1}\s+(.*?)\s+for\s+your\s+upcoming\s+visit/ims", $this->text, $m)
            || preg_match("/visit\s+to\s+BEST\s+WESTERN\s+(?:PLUS)?\s+(.*?)\s+is\s+just\s/ims", $this->text, $m)
            || preg_match("/Thank\s+you\s+for\s+booking\s+your\s+stay\s+at\s+the\s+Best\s+Western\s*(?:Plus)?\s+(.*?)\./", $this->text, $m)
            || preg_match("/Thank\s+you\s+for\s+your\s+reservation\s+at\s+Best\s+Western(?:\s*Plus)?\s+(.*?)\./i", $this->text, $m)
            || preg_match("/Assistant\s+de\s+Direction\s+(.+)/", $this->text, $m)) {
                $hotelName = $m[1];
            }
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your existing reservation by calling'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('your existing reservation by calling'))}\s*([\d\-]+)/");

        if (!empty($phone)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[{$this->contains($phone)}]/ancestor::tr[1]", null, true, "/^\s*(.+)\s*{$phone}/");
        }

        if (empty($address) && empty($phone)) {
            $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]/ancestor::tr[1]");

            if (preg_match("/{$hotelName}(.+){$this->opt($this->t('Telephone:'))}\s*([\d\.]+)/", $addressText, $m)) {
                $address = $m[1];
                $phone = $m[2];
            }
        }

        if (empty($address) && empty($phone)) {
            $addressText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View in browser'))}]/following::text()[{$this->contains($this->t('Telephone:'))}][1]/ancestor::tr[1]");

            if (empty($addressText)) {
                $addressText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/preceding::text()[{$this->contains($this->t('Telephone:'))}][1]/ancestor::tr[1]");
            }

            if (empty($addressText)) {
                $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]/ancestor::p[1]");
            }

            if (preg_match("/^(.+){$this->opt($this->t('Telephone:'))}\s*([\d\.\-\(\)\s\+]+)/u", $addressText, $m)) {
                $address = $m[1];
                $phone = $m[2];
            }
        }

        if (!empty($address) && !empty($phone)) {
            $h->hotel()
                ->address($address)
                ->phone($phone);
        }

        $h->hotel()
            ->name(preg_replace('/\s*\n\s*/u', ' ', $hotelName));

        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In Time:'))}]/ancestor::tr[1]/descendant::td[2]");
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out Time:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($inTime) && empty($outTime)) {
            $timeText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('InOutTime'))}]/following::text()[string-length()>2][1]");

            if (preg_match("/^.*\s(?<inTime>[\d\:]+).*\s(?<outTime>[\d\:]+)/u", $timeText, $m)) {
                $inTime = $m['inTime'];
                $outTime = $m['outTime'];
            }
        }

        $inDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date'))}]/ancestor::tr[1]/descendant::td[2]");
        $outDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");

        $h->booked()
            ->checkIn($this->normalizeDate($inTime ? $inDate . ', ' . $inTime : $inDate))
            ->checkOut($this->normalizeDate($outTime ? $outDate . ', ' . $outTime : $outDate));

        $guestsText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests:'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[{$this->contains($this->t('Adults:'))}]");

        if (preg_match("/{$this->opt($this->t('Adults:'))}\s*(?<adults>\d+)\,\s*{$this->opt($this->t('Children:'))}\s*(?<kids>\d+)/", $guestsText, $m)
        || preg_match("/^(?<adults>\d+)\s*{$this->opt($this->t('Adults:'))}/u", $guestsText, $m)) {
            $h->booked()
                ->guests($m['adults']);

            if (isset($m['kids'])) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Total:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.]+)\s/", $totalText, $m)
        || preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s/", $totalText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1]/descendant::td[2]");
        $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Nightly Rate'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType) || !empty($roomRate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomRate)) {
                $room->setRate($roomRate);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->reSubject = $parser->getSubject();
        $this->text = str_replace(['>', '='], '', $parser->getBodyStr());

        $this->ParseHotel($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[\w\-]+\,\s*(\d+)\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Reservations must be cancelled by (?<hour>[\d\:]+\s*a?p?m) (?<prior>\d+) hours prior to the day of arrival./", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'] . ' hours', $m['hour']);
        } elseif (preg_match("/Reservations? must be cancelled (?<prior>\d+) hours prior to/u", $cancellationText, $m)
                || preg_match("/Should you need to cancel your reservation, please do so (?<prior>\d+) hours prior to arrival date/u", $cancellationText, $m)
                || preg_match("/Diese Reservierung ist bis (?<prior>\d+) Stunden vor Anreise kostenfrei stornierbar./u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'] . ' hours');
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
