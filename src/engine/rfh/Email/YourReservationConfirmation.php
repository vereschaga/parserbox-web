<?php

namespace AwardWallet\Engine\rfh\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "rfh/it-16210920.eml, rfh/it-17320395.eml, rfh/it-27423468.eml, rfh/it-63465936.eml, rfh/it-642504480.eml, rfh/it-686218144.eml, rfh/it-787029657.eml";

    private $detectFrom = "roccofortehotels.com";
    private $detectSubject = [
        'Rocco Forte Hotels - Your reservation confirmation',
        'Rocco Forte Hotels - Reservierungsbestätigung',
    ];
    private $detectCompany = 'roccofortehotels.com';
    private $detectBody = [
        'en' => ['Planning your stay', 'P L A N N I N G   Y O U R   S T A Y', 'PLANNING YOUR STAY', 'We are sorry you need to cancel your reservation at'],
        'de' => ['Planen Sie ihren Aufenthalt'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'StartHotelName' => [
                'I very much look forward to welcoming you to',
            ],
            'EndHotelName' => [
                'and hope that your stay will be a memorable one',
            ],
            'EndCancellation' => [
                'Notes from the Hotel Management',
                'For the best online rates, book at',
                'Planning your stay',
                'P L A N N I N G   Y O U R   S T A Y',
                'PLANNING YOUR STAY',
            ],
            "AVERAGE DAILY RATE:"               => ["Average Daily Rate:", "AVERAGE DAILY RATE:", "AVERAGE DAILY RATE"],
            "Modification/cancellation policy:" => [
                "Modification/cancellation policy:",
                "MODIFICATION/CANCELLATION POLICY:",
                "MODIFICATION / CANCELLATION POLICY",
            ],
            "ROOM CATEGORY:"    => ["ROOM CATEGORY:", "ROOM CATEGORY :", "PRIVATE SUITE :", "ROOM CATEGORY"],
            "CONFIRMATION REF:" => ["CONFIRMATION REF:", "CONFIRMATION REF"],
            "NAME:"             => ["NAME:", "NAME"],
            "ARRIVAL:"          => ["ARRIVAL:", "ARRIVAL"],
            "DEPARTURE:"        => ["DEPARTURE:", "DEPARTURE"],
            "NUMBER OF ROOMS:"  => ["NUMBER OF ROOMS:", "NUMBER OF ROOMS"],
            "CHILDREN:"         => ["CHILDREN:", "CHILDREN"],
            "ADULTS:"           => ["ADULTS:", "ADULTS"],
            "TOTAL COST:"       => ["TOTAL COST:", "TOTAL COST"],
            'ADDRESS'           => ['ADDRESS', 'Address'],
            'VIEW MAP'          => ['VIEW MAP', 'View map'],
            'EMAIL US'          => ['EMAIL US', 'Email us'],
        ],
        'de' => [
            'Email us'          => 'Schreiben Sie uns eine E-Mail',
            'CONFIRMATION REF:' => 'Reservierungsnummer:',
            'NAME:'             => 'Name:',
            'ARRIVAL:'          => 'Ankunft:',
            'DEPARTURE:'        => 'Abreise:',
            'NUMBER OF ROOMS:'  => 'Zimmeranzahl:',
            'ADULTS:'           => 'Erwachsene:',
            'CHILDREN:'         => 'Kinder:',
            'TOTAL COST:'       => 'Preis total:',
            'EndCancellation'   => [
                'Für die besten Onlinepreise buchen Sie unter',
                'Planen Sie ihren Aufenthalt',
            ],
            'AVERAGE DAILY RATE:'               => ['Durchschnittspreis pro tag:'],
            'Modification/cancellation policy:' => [
                "Umbuchungs- / Stornierungsbedingungen:",
            ],
            'ROOM CATEGORY:' => ['Zimmerkategorie:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if (!$this->assignLang($this->http->Response['body'])) {
            $this->logger->debug('can\'t determinate a language by body');
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            if (count($pdfs) > 0) {
                foreach ($pdfs as $i => $pdf) {
                    $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                    $text = preg_replace("#\s{10,}#", "\n", $text);

                    if ($this->assignLang($text)) {
                        $type = 'Pdf';
                        $this->parseEmail($email, $text);
                    } else {
                        $this->logger->debug('can\'t determinate a language by attach - ' . $i);
                    }
                }
            }

            return false;
        } else {
            $text = text($this->http->Response['body']);
            $this->parseEmail($email, $text);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->unitDetectBody($this->http->Response['body'])) {
            return true;
        }
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (count($pdf) > 0) {
            $html = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdf)), \PDF::MODE_COMPLEX);
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($html));

            return $this->unitDetectBody($html);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])
            || (stripos($headers['from'], $this->detectFrom) === false && stripos($headers['subject'], 'Rocco Forte Hotels') === false)
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // html | pdf
        $cnt = $types * count(self::$dict);

        return $cnt;
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

    private function parseEmail(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        // General
        $confirmation = str_replace(" ", "", $this->nextText($this->t("CONFIRMATION REF:"), $text, "#^\s*([A-Z\d\s]{5,})\s*$#"));

        if (empty($confirmation)) {
            $confirmation = $this->re('/CANCELLATION REF:\s*([A-Z\d]{6,})/', $text);
        }

        $traveller = $this->re("/NAME\:\s*(\D+)\s+ARRIVAL\:/", $text);

        if (empty($traveller)) {
            $traveller = $this->nextText($this->t("NAME:"), $text);
        }

        $h->general()
            ->confirmation($confirmation)
            ->traveller(str_replace("\n", " ", $traveller), true)
            ->cancellation(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t("Modification/cancellation policy:"))}\s+(.+?)\s+{$this->opt($this->t('EndCancellation'))}#s",
                    $text)), true, true);

        // Hotel
        $hotelInfo = $this->re("#([^\n]+\s+[^\n]+\s+[^\n]+\s+{$this->opt($this->t('Email us'))})#", $text);

        if (preg_match("#(?<name>[^\n]+)\s+[^\n]+\s+(?<addr>.+)[\W\s]+" . $this->opt($this->t("Tel:")) . "\s*(?<tel>[\d+ \-().]+)\s+" . $this->opt($this->t("Email us")) . "#",
            $hotelInfo, $m) && strpos($m['name'], '>') === false && !preg_match("#(?:From|Sent|To|Subject):#", $m['name'])) {
            $h->hotel()
                ->name(trim($m['name'], ' ·.'))
                ->address(trim($m['addr']))
                ->phone(trim($m['tel']));
        } else {
            $hotelName = '';
            $address = trim($this->http->FindSingleNode("//text()[normalize-space(.)='Email us' or normalize-space()='EMAIL US']/ancestor::tr[1]", null, true, "#(.+?)[\s·]*Tel:#"));

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ADDRESS'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('ADDRESS'))}\s*(.+)\s*{$this->opt($this->t('VIEW MAP'))}/");
            }

            $phone = trim($this->http->FindSingleNode("//text()[normalize-space(.)='Email us']/ancestor::tr[1]", null, true, "#Tel:\s*(.+)\s*Email us#"));

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('EMAIL US'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('EMAIL US'))}\s*(?:\x{feff})?\s*([+]*[\d\s\(\)]+)$/u");
            }

            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'We are pleased to confirm your reservation at')]", null, true, "#We are pleased to confirm your reservation at\s+([^,]+)#");

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('I very much look forward to welcoming you to'))}]", null, true, "/{$this->opt($this->t('StartHotelName'))}(.+){$this->opt($this->t('EndHotelName'))}/");
            }

            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t("ARRIVAL:"), $text)))
            ->checkOut($this->normalizeDate($this->nextText($this->t("DEPARTURE:"), $text)))
            ->kids($this->nextText($this->t("CHILDREN:"), $text, '/\s*(\d+)\s*/'), false, true);

        $rooms = $this->nextText($this->t("NUMBER OF ROOMS:"), $text);

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        $guests = $this->nextText($this->t("ADULTS:"), $text, "/^\s*(\d+)/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='YOUR CANCELLATION']")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('cancelled');

            return $email;
        }

        $rm = $h->addRoom();
        $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ROOM CATEGORY:'))}]/ancestor::td[position() < 3][{$this->starts($this->t('ROOM CATEGORY:'))}][preceding-sibling::*][1]",
            null, true, "/{$this->opt($this->t('ROOM CATEGORY:'))}\s*(.*)/");

        if (empty($type)) {
            $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ROOM CATEGORY:'))}]/ancestor::td[1][{$this->starts($this->t('ROOM CATEGORY:'))}]",
                null, true, "/{$this->opt($this->t('ROOM CATEGORY:'))}\s*(.*)/");
        }

        if (empty($type) && empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('ROOM CATEGORY:'))}])[1]"))) {
            $type = $this->nextText($this->t("ROOM CATEGORY:"), $text);
        }

        if (!empty($type)) {
            $rm->setType($type);
        }
        $rateText = $this->nextText($this->t("AVERAGE DAILY RATE:"), $text, "#[\d\/]{6,}.+?[\d\/]{6,}\s+(.+)#");
        $rates = array_values(array_unique(array_filter(array_map('trim',
            preg_split("#\w+\s+[\d/]{6,}.+?[\d/]{6,}\s+#u", $rateText)))));

        if (count($rates) == 1) {
            $rm->setRate($rates[0]);
        } elseif (count($rates) > 1) {
            $ratesAmount = array_map([$this, 'amount'], $rates);
            sort($ratesAmount);
            $currency = $this->currency($rates[0]);
            $rm->setRate(array_shift($ratesAmount) . ' - ' . array_pop($ratesAmount) . ' ' . $currency);
        }

        if (empty($rm->getType()) && empty($rm->getRate())) {
            $h->removeRoom($rm);
        }

        $total = $this->nextText($this->t("TOTAL COST:"), $text);
        $total = preg_replace('/\s+,\s+/', ',', $total);

        if (!empty($total)) {
            $h->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $timeText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hotel check-in starts at')]");

        if (preg_match("/Hotel check-in starts at\s*([\d\:]+\s*a?p?m)\,\s*check-out is at\s*([\d\:]+\s*a?p?m)\./", $timeText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Reservations must be cancelled no later than (?<hour>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?),? local hotel time, (?<prior>\d+ hours) prior to date of arrival#i",
            $cancellationText, $m)
        || preg_match("#A cancellation is free of charge if completed by (?<hour>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?) local time (?<prior>\d+ days) prior to arrival.#i",
                $cancellationText, $m)
            || preg_match("#Cancellation is free of charge if completed by (?<hour>\d+A?P?M?) local time (?<prior>\d+ days) prior to arrival#i",
                $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        } elseif (preg_match("#Reservations must be cancelled no later than (?<days1>\d+) days? prior to date of arrival by (?<hour1>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?), local hotel time. On all the other months reservations must be cancelled no later than (?<days2>\d+) days? prior to date of arrival by (?<hour2>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?), local hotel time#i",
            $cancellationText, $m)
        || preg_match("#Cancellation is free of charge if completed by (?<hour1>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?) local time (?<days1>\d+) days? prior to arrival#i",
                $cancellationText, $m)) {//set the earliest date to for sure
            if ((int) $m['days1'] > (int) $m['days2']) {
                $h->booked()->deadlineRelative($m['days1'] . ' days', $m['hour1']);
            } else {
                $h->booked()->deadlineRelative($m['days2'] . ' days', $m['hour2']);
            }
        } elseif (preg_match("#Reservations must be cancelled no later than (?<days>\d+) days? prior to date of arrival by (?<hour>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?), local hotel time.#i", $cancellationText, $m)
        || preg_match("#The reservation must be cancelled by (?<hour>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?) local time (?<days>\d+) days prior to arrival to avoid further penalty charges#i", $cancellationText, $m)
        || preg_match("#Reservations must be cancelled no later than (?<hour>\d+ *[ap]m|\d+:\d+(?: *[ap]m)?) local hotel time\, (?<days>\d+) days prior to date of arrival#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['days'] . ' days', $m['hour']);
        } elseif (preg_match("#Reservierungen müssen bis spätestens (?<hour>\d+:\d+) Uhr Ortszeit einen Tag vor Anreise storniert werden. #i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 days', $m['hour']);
        }
    }

    private function unitDetectBody($text)
    {
        if (stripos($text, $this->detectCompany) === false) {
            return false;
        }

        return $this->assignLang($text);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            $dBody = (array) $detectBody;

            foreach ($dBody as $r) {
                if (stripos($body, $r) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $date = str_replace(' ', '', $date);
        $in = [
            //16/11/2016
            '#^\s*(\d{1,2})\s*\/(\d{1,2})\/(\d{4})\s*$#',
        ];
        $out = [
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $date);
        //		$str = $this->dateStringToEnglish($str);
        return strtotime($str);
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

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'    => ['EUR'],
            'EURO' => 'EUR',
            '$'    => 'USD',
            '£'    => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1)
    {
        $nextText = $this->re("#{$this->opt($field)}\s+(.+)#", $root);

        if (isset($regexp)) {
            if (preg_match($regexp, $nextText, $m)) {
                return $m[$n];
            } else {
                return null;
            }
        }

        return $nextText;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
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
}
