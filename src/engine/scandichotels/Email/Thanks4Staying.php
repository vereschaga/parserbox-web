<?php

namespace AwardWallet\Engine\scandichotels\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Thanks4Staying extends \TAccountChecker
{
    public $mailFiles = "scandichotels/it-124520788.eml, scandichotels/it-40187683.eml, scandichotels/it-40191605.eml, scandichotels/it-40247107.eml";

    public $reFrom = ["@scandichotels.com"];
    public $reBody = [
        'en' => ['Thank you for being our guest at', 'You\'ll soon be our guest at', 'We are looking forward to welcoming you at'],
        'da' => ['Tak, for dit besøg på'],
        'no' => ['Du skal snart være vår gjest på'],
    ];
    public $reSubject = [
        //en
        'Welcome to',
        'Thanks for staying at',
        //no
        'Velkommen til',
        //da
        'Tak, for dit besøg på',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'ARRIVAL'      => ['ARRIVAL', 'Arrival'],
            'DEPARTURE'    => ['DEPARTURE', 'Departure'],
            'YOUR PROFILE' => ['YOUR PROFILE', 'Your Profile'],
        ],
        'da' => [
            'ARRIVAL'         => ['ANKOMST', 'Ankomst'],
            'DEPARTURE'       => ['AFREJSE', 'Afrejse'],
            'YOUR PROFILE'    => ['DIN PROFIL', 'Din profil'],
            'Points to spend' => ['Point du kan bruge', 'Point du kan bruge pr.'],
            'Hello '          => 'Hej ',
            //            'Check in:'=>'',
            //            'Check out:'=>'',
            //            'Adults'=>'',
            //            'Children'=>'',
            //            'Directions'=>'',
        ],
        'no' => [
            'ARRIVAL'         => ['ANKOMST', 'Ankomst'],
            'DEPARTURE'       => ['AVREISE', 'Avreise'],
            'YOUR PROFILE'    => ['PROFILEN DIN', 'Profilen din'],
            'Points to spend' => 'Disponible poeng',
            'Hello '          => 'Hei ',
            'Check in:'       => 'Innsjekk:',
            'Check out:'      => 'Utsjekk:',
            'Adults'          => 'Voksne',
            'Children'        => 'Barn',
            'Directions'      => 'Veibeskrivelse',
            'Your Profile'    => 'Profilen din',
        ],
    ];
    private $keywordProv = 'Scandic';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

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
        if ($this->http->XPath->query("//img[contains(@src,'Scandic') or contains(@src,'scandic')] | //a[contains(@href,'.scandichotels.com/')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
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
        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR PROFILE'))}]/following::text()[normalize-space()!=''][1]/ancestor::td[1]");

        if (empty($pax)) {
            $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]", null, false,
                "#{$this->opt($this->t('Hello '))}\s*(.+?),\s*$#");
        } else {
            $acc = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Points to spend'))}])[last()]/ancestor::tr[1]/following::text()[normalize-space()!=''][1]");

            if (!empty($acc) && stripos($acc, 'Expiring points') !== false) {
                $acc = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Points to spend'))}])[last()]/ancestor::tr[1]/following::text()[normalize-space()!=''][1]/ancestor::tr[1]/following::tr[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/");
            }
        }

        $r = $email->add()->hotel();
        $r->general()
            ->traveller($pax, !empty(strpos($pax, ' ')));

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Reservation number:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reservation number:'))}\s*(\d+)/");

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation);
        } else {
            $r->general()
                ->noConfirmation();
        }

        if (isset($acc)) {
            $r->program()->account($acc, false);
        }

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('ARRIVAL'))}]/preceding::text()[normalize-space()!=''][1]"));

        if (($root = $this->http->XPath->query("//text()[{$this->starts($this->t('Directions'))}]"))->length === 1) {
            $root = $root->item(0);
            $r->hotel()
                ->address($this->http->FindSingleNode("./ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[normalize-space()!=''][3]",
                    $root))
                ->phone($this->http->FindSingleNode("./ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[normalize-space()!=''][2]",
                    $root));
        } else {
            $r->hotel()->noAddress();
        }

        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('ARRIVAL'))}]/ancestor::tr[1]",
                null, false, "#{$this->opt($this->t('ARRIVAL'))}\s*(.+)#")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::tr[1]",
                null, false, "#{$this->opt($this->t('DEPARTURE'))}\s*(.+)#")));

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check in:'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#\b(\d+:\d+.*)$#");

        if (!empty($time)) {
            $r->booked()->checkIn(strtotime($time, $r->getCheckInDate()));
        }

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out:'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#\b(\d+:\d+.*)$#");

        if (!empty($time)) {
            $r->booked()->checkOut(strtotime($time, $r->getCheckOutDate()));
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Adults'))}]/preceding::text()[normalize-space()!=''][1]",
            null, false, "#^\d+$#");

        if (is_numeric($guests)) {
            $r->booked()->guests($guests);
        }
        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Children'))}]/preceding::text()[normalize-space()!=''][1]",
            null, false, "#^\d+$#");

        $room = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure'))}]/following::text()[{$this->contains($this->t('Rooms'))}]/ancestor::td[1]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('Rooms'))}/");

        if ($room !== null) {
            $r->booked()
                ->rooms($room);
        }

        if (is_numeric($kids)) {
            $r->booked()->kids($kids);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('YOUR PROFILE'))}]")->length > 0) {
            $st = $email->add()->statement();

            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR PROFILE'))}]/following::text()[{$this->starts($this->t('Points to spend'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

            if (!empty($name)) {
                $st->addProperty('Name', trim($name));
            }

            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR PROFILE'))}]/following::text()[{$this->starts($this->t('Points to spend'))}][1]/ancestor::tr[1]", null, true, "/\:\s*([\d\s]+)$/");

            if (!empty($balance)) {
                $st->setBalance(str_replace(' ', '', $balance));
                $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR PROFILE'))}]/following::text()[{$this->starts($this->t('Points to spend'))}][1]/ancestor::tr[1]", null, true, "/\s(\d+\.\d+\.\d{4})\s*\:*/");
                $st->setBalanceDate(strtotime($dateOfBalance));
            }

            $expiring = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expiring points'))}]/ancestor::tr[1]", null, true, "/\:\s*(\d+)$/");

            if (!empty($expiring)) {
                $st->addProperty('PointsToExpire', $expiring);
            }

            if (!empty($acc)) {
                $st->setNumber($acc);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Sat March 30
            '#^(\w+)\s+(\w+)\s+(\d{1,2})$#u',
        ];
        $out = [
            '$3 $2 ' . $year,
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['ARRIVAL'], $words['DEPARTURE'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['ARRIVAL'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['DEPARTURE'])}]")->length > 0
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
