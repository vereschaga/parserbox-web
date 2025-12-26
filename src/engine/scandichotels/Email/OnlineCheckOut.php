<?php

namespace AwardWallet\Engine\scandichotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class OnlineCheckOut extends \TAccountChecker
{
    public $mailFiles = "scandichotels/it-39756045.eml, scandichotels/it-39973671.eml, scandichotels/it-40231414.eml";

    public $reFrom = ["@property.booking.com", "Scandic Svolvær", "@scandichotels.com"]; // via booking
    public $reBody = [
        'en' => ['Check out on your mobile or on your computer'],
        'da' => ['Check ud på din mobiltelefon eller din computer'],
        'no' => ['Sjekk ut med mobiltelefonen eller PC\'en'],
    ];
    public $reSubject = [
        'Online check-out room',
        'Online Check ud værelse',
        'Online check-out rom',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reservation number:' => 'Reservation number:',
            'Arrival date:'       => 'Arrival date:',
            'regSubject'          => ['Online check-out room (?<room>\d+) PPAAXX \– (?<hotel>.+)$'],
        ],
        'da' => [
            'Reservation number:' => 'Reservationsnummer:',
            'Arrival date:'       => 'Ankomstdato:',
            'Departure date:'     => 'Afrejsedato:',
            'regSubject'          => ['Online Check ud værelse (?<room>\d+) PPAAXX \– (?<hotel>.+)$'],
        ],
        'no' => [
            'Reservation number:' => 'Bookingnummer:',
            'Arrival date:'       => 'Ankomstdato:',
            'Departure date:'     => 'Avreisedato:',
            'regSubject'          => ['Online check-out rom (?<room>\d+) PPAAXX \– (?<hotel>.+)$'],
        ],
    ];
    private $keywordProv = 'Scandic';
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

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
        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number:'))}]/preceding::text()[normalize-space()!=''][1]");
        $regExps = (array) $this->t('regSubject');

        foreach ($regExps as $regExp) {
            $reg = str_replace("PPAAXX", $pax, $regExp);

            if (preg_match("#{$reg}#i", $this->subject, $m)) {
                $hotelName = $m['hotel'];
                $roomNumber = $m['room'];

                break;
            }
        }

        if (!isset($hotelName)) {
            $imgAlts = array_filter(array_unique(array_map("trim",
                $this->http->FindNodes("//img[contains(@src,'SiteScandic')]/@alt"))));

            if (count($imgAlts) === 1) {
                $hotelName = array_shift($imgAlts);
            }
        }

        if (!isset($hotelName)) {
            $this->logger->debug('can\'t find hotel name');

            return false;
        }

        $r = $email->add()->hotel();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number:'))}]/following::text()[normalize-space()!=''][1]",
                null, false, "#(.+?)\s*(?:\/\s*\d\s*)?$#"))
            ->traveller($pax, true);
        $r->hotel()
            ->name($hotelName)
            ->noAddress();

        if (isset($roomNumber)) {
            $room = $r->addRoom();
            $room->setDescription('#' . $roomNumber);
        }

        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival date:'))}]/following::text()[normalize-space()!=''][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date:'))}]/following::text()[normalize-space()!=''][1]")));

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wednesday, June 19, 2019
            '#^(\w+),\s+(\w+)\s+(\d+),\s+\d{4}$#u',
            //11. mars 2019
            '#^(\d+)\.\s+(\w+)\s+\d{4}$#u',
        ];
        $out = [
            '$3 $2 $4',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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
            if (isset($words['Reservation number:'], $words['Arrival date:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reservation number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrival date:'])}]")->length > 0
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
}
