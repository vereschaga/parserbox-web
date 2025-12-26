<?php

namespace AwardWallet\Engine\ihost\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountCheckerExtended
{
    public $mailFiles = "ihost/it-856056784.eml, ihost/it-863173337.eml";

    public $detectFrom = '@i-host.gr';

    public $detectSubjects = [
        // en
        // Nolan Mykonos: Cancelled reservation for Thursday 31 August 2023 21:30
        ': Cancelled reservation for ',
        // Distinto: New reservation for Friday 18 August 2023 20:30
        ': New reservation for ',
    ];

    public $detectBody = [
        'en' => ['has been accepted.'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Your reservation at' => ['Your reservation at', 'You reservation at'],
            'has been accepted.'  => 'has been accepted.',
            'has been cancelled.' => 'has been cancelled.',
            'Booking Reference:'  => 'Booking Reference:',
            // 'Dear ' => '',
        ],
    ];

    public function ParseEvent(Email $email): void
    {
        $event = $email->add()->event();

        // Type
        $event->type()
            ->restaurant();

        // General
        $conf = $this->http->FindSingleNode("(//node()[{$this->starts($this->t('Booking Reference:'))}])[1]", null, true, '/:\s*([\w\-]{5,})\s*$/');

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]/ancestor::tr[1]", null, true, '/:\s*([\w\-]{5,})\s*$/');
        }
        $event->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking Reference:'))}])[1]/preceding::text()[{$this->starts($this->t('Dear '))}][1]", null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(.+?),$/u"))
        ;

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation at'))}]/ancestor::*[{$this->contains($this->t('has been accepted.'))}][1]"))) {
            $event->general()
                ->status('accepted');
        } elseif (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation at'))}]/ancestor::*[{$this->contains($this->t('has been cancelled.'))}][1]"))) {
            $event->general()
                ->status('cancelled')
                ->cancelled()
            ;
        }

        // Place
        // Booked
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation at'))}]/ancestor::*[{$this->contains($this->t('has been accepted.'))} or {$this->contains($this->t('has been cancelled.'))}][1]");

        if (preg_match("/{$this->opt($this->t('Your reservation at'))}\s+(?<name>\S.+?)(?:\s*\(MAP\)\s*|\s+)?for (?<guests>\d+) people, at (?<time>\d{1,2}:\d{2}) on (?<date>.+?) has been (?:accepted|cancelled)\./", $text, $m)) {
            // Your reservation at Solymar Mykonos for 4 people, at 11:30 on Friday 15 September 2023 has been accepted.
            $event->place()
                ->name($m['name']);

            $event->booked()
                ->start($this->normalizeDate($m['date'] . ', ' . $m['time']))
                ->noEnd()
                ->guests($m['guests']);
        }

        if (empty($text)) {
            // We are happy to confirm a dinner reservation for 2 people on Tuesday 26 September 2023 at 19:00 at Catch Restaurant.
            $text = $this->http->FindSingleNode("//text()[{$this->contains('dinner reservation for')}]/ancestor::*[{$this->contains(' at ')}][1]");

            if (preg_match("/a dinner reservation for (?<guests>\d+) people on (?<date>.+?) at (?<time>\d{1,2}:\d{2}) at (?<name>\S.+?)\./", $text, $m)) {
                $event->place()
                    ->name($m['name']);

                $event->booked()
                    ->start($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->noEnd()
                    ->guests($m['guests']);
            }
        }

        $phoneText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Call'))}]/ancestor::*[{$this->contains($this->t('to make changes'))}][1]");
        $phone = $this->re("/{$this->opt($this->t('Call'))}\s*([\d\W]{5,20})\s*{$this->opt($this->t('to make changes'))}/", $phoneText);

        if (empty($phone)) {
            $phone = $this->re("/Please call\s*([\d\W]{5,20})\s*if you wish to make changes/", $phoneText);
        }

        if (!empty($phone)) {
            $event->place()
                ->phone($phone);
        }
        $phone = preg_replace('/\D+/', '', $phone);

        $address = null;
        $url = $this->http->FindSingleNode("//a[{$this->eq($this->t('(MAP)'))}]/@href");

        if (!empty($url)) {
            $http1 = clone $this->http;
            $http1->GetURL($url);
            $address = $http1->FindSingleNode("//meta[@itemprop = 'name']/@content");
            $address = preg_replace('/\s+·\s+/u', ', ', $address);
        } elseif (!empty($event->getName()) && !empty($phone)) {
            $http1 = clone $this->http;
            $url = "https://www.google.com/maps/search/" . urlencode($event->getName()) . " restaurant greece";
            $http1->GetURL($url);

            $text = $http1->Response['body'];

            $text = $this->cutText('window.APP_INITIALIZATION_STATE', 'window.APP_FLAGS', $text);
            $text = mb_strstr($text, '\'\n', false);

            $pos = strpos($text, $phone);

            if (!empty($pos)) {
                $part = substr($text, $pos - 500, 2000);
                $part = str_replace('\"', '"', $part);

                $address = $this->getAddressFromGoogleMap($part);
            }
        }

        if (!empty($address)) {
            $event->place()
                ->address($address);
        }

        return;
    }

    public function getAddressFromGoogleMap($text)
    {
        $telPos = strpos($text, 'tel:');

        if (empty($telPos)) {
            return false;
        }

        $before = substr($text, 0, $telPos);

        $beforeArray = explode('[', $before);

        krsort($beforeArray);
        $beforeArray = array_values($beforeArray);

        $newBeforeArray = [];
        $endCount = 0;

        foreach ($beforeArray as $i => $v) {
            $endCount += substr_count($v, ']');

            if ($i - $endCount == 2) {
                $newBeforeArray[] = $v;

                break;
            }
            $newBeforeArray[] = $v;
        }
        krsort($newBeforeArray);

        $after = substr($text, $telPos);
        $afterArray = explode(']', $after);

        $newAfterArray = [];
        $startsCount = 0;

        foreach ($afterArray as $i => $v) {
            $startsCount += substr_count($v, '[');

            if ($i - $startsCount == 2) {
                $newAfterArray[] = $i;
            }
        }

        $json = '{' .
            '"name":[' . '[' . implode('[', $newBeforeArray)
            . implode(']', array_slice($afterArray, 0, max($newAfterArray) + 1)) . ']' . ']'
            . '}';
        $json = preg_replace("/(,null){2,}/", ',null', $json);
        $array = json_decode($json, true);

        if (is_array($array)) {
            foreach ($array['name'] as $row) {
                if (isset($row[0]) && isset($row[0])) {
                    foreach ($row[0] as $addrows) {
                        if (!empty($addrows[0]) && $addrows[0] == 2) {
                            $address = $addrows[1][0][0];

                            return $address;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"i-host.gr/")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your reservation at']) && !empty($dict['has been accepted.'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your reservation at'])}]/ancestor::*[{$this->contains($dict['has been accepted.'])}]")->length > 0) {
                return true;
            }

            if (!empty($dict['Your reservation at']) && !empty($dict['has been cancelled.'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your reservation at'])}]/ancestor::*[{$this->contains($dict['has been cancelled.'])}]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[{$this->contains('dinner reservation for')}]/ancestor::*[{$this->contains(' at ')}][1]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

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
        // $in = [
        // ];
        // $out = [
        // ];
        // $str = preg_replace($in, $out, $str);
        //

        return strtotime($str);
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $txt = stristr(stristr($text, $start), $end, true);

            return substr($txt, strlen($start));
        }

        return false;
    }
}
