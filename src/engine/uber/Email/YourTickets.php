<?php

namespace AwardWallet\Engine\uber\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "uber/it-317848755.eml, uber/it-342545134.eml";

    public $detectFrom = 'travel-noreply@uber.com';

    public $detectSubject = [
        //en
        // Your tickets to London Kings Cross (KGX) on 06 May 2023
        'Your tickets to',
    ];

    public $detectBody = [
        'en' => ['Find your ticket details below.'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'Your booking number' => 'Your booking number',
            'Passengers'          => 'Passengers',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        // Travel Agency
        $confs = preg_split("/\s*,\s*/", $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking number'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*[A-Z\d\-, ]+\s*$/"));

        if (empty($confs)) {
            $email->ota()
                ->confirmation(null);
        } else {
            foreach ($confs as $conf) {
                $email->ota()
                    ->confirmation($conf);
            }
        }

        $this->parseEmail($email);

        $noTraveller = false;
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]/ancestor::ul[1]/li",
            null, "/(.+?)\s*\(/");

        if (empty(array_filter($travellers))) {
            $travellersAll = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]/ancestor::ul[1]/li");

            if (empty(array_diff($travellersAll, 'Adult'))) {
                $noTraveller = true;
            }
        }

        if ($noTraveller !== true) {
            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->travellers($travellers, true);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.uber.com/', 'email.uber.com'], '@href')}] | //*[{$this->contains(['Uber London Ltd'])}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Your booking number"], $words["Passengers"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Your booking number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Passengers'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && !preg_match('/\bUber\b/i', $headers['subject'])
        ) {
            return false;
        }

        foreach ($this->detectSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
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
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();

        $b = $email->add()->bus();

        $b->general()
            ->noConfirmation();

        $geoTip = '';

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains('Uber London Ltd')}])[last()]"))) {
            $geoTip = 'Europe';
        }
        // Segments
        $xpath = "//img[contains(@src, 'TrainBlack') or contains(@src, 'BusBlack') ]/ancestor::tr[1]";

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (!empty($this->http->FindSingleNode(".//img[contains(@src, 'BusBlack')]/@src", $root))) {
                $s = $b->addSegment();
            } else {
                $s = $t->addSegment();
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("ancestor::tr[1]/preceding::text()[normalize-space()][1]",
                $root, true, "/- (.+)/"));

            // Departure
            $info = $this->http->FindNodes("*[normalize-space()]", $root);

            if (count($info) === 2) {
                $s->departure()
                    ->name($info[1]);
                $code = $this->re("/\s*\(\s*([A-Z]{3})\s*\)\s*$/", $info[1]);

                if (!empty($code)) {
                    $s->departure()
                        ->code($code);
                }

                if (!empty($geoTip)) {
                    $s->departure()
                        ->geoTip($geoTip);
                }

                if (!empty($date) && preg_match("/^\s*\d+:\d+(\s*[ap]m)?\s*$/i", $info[0])) {
                    $s->departure()
                        ->date(strtotime($info[0], $date));
                }
            }

            // Arrival
            $info = $this->http->FindNodes("following::tr[not(.//tr)][2]/*[normalize-space()]", $root);

            if (count($info) === 2) {
                $s->arrival()
                    ->name($info[1]);
                $code = $this->re("/\s*\(\s*([A-Z]{3})\s*\)\s*$/", $info[1]);

                if (!empty($code)) {
                    $s->arrival()
                        ->code($code);
                }

                if (!empty($geoTip)) {
                    $s->arrival()
                        ->geoTip($geoTip);
                }

                if (!empty($date) && preg_match("/^\s*\d+:\d+(\s*[ap]m)?\s*$/i", $info[0])) {
                    $s->arrival()
                        ->date(strtotime($info[0], $date));
                }
            }

            // Extra
            $info = $this->http->FindNodes("following::tr[not(.//tr)][1]/*[normalize-space()]", $root);

            if (isset($t) && preg_match("/^\s*Tube\s*\|/", $info[1] ?? '')) {
                $t->removeSegment($s);

                continue;
            }

            $s->extra()
                ->duration($this->re("/^\s*(\d+h\d+)\s*$/", $info[0] ?? ''));

            if (preg_match("/\|\s*([A-Z\d]+)\s*$/", $info[1] ?? '')) {
                $s->extra()
                    ->number($this->re("/\|\s*([A-Z\d]+)\s*$/", $info[1] ?? ''));
            } elseif (preg_match("/^\s*[^|]+\s*\|\s*$/", $info[1] ?? '')) {
                $s->extra()
                    ->noNumber();
            }

            if ($s->getArrDate() < $s->getDepDate()) {
                if (preg_match('/^\s*(\d+)h(\d+)\s*$/', $s->getDuration(), $m)) {
                    $date = strtotime("+{$m[1]} hours {$m[2]} minutes", $s->getDepDate());

                    if (date('H:i:s', $date) === date('H:i:s', $s->getArrDate())) {
                        $s->arrival()
                            ->date($date);
                    }
                }
            }
        }

        if (count($t->getSegments()) === 0) {
            $email->removeItinerary($t);
        }

        if (count($b->getSegments()) === 0) {
            $email->removeItinerary($b);
        }

        return $email;
    }

    private function normalizeDate(?string $str): string
    {
//        $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            // May 08, 2023
            '/^\s*\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/u',
        ];
        $out = [
            '$2 $1 $3',
        ];

        $str = preg_replace($in, $out, $str);

//        $this->logger->debug('$str = '.print_r( $str,true));
        return strtotime($str);
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
