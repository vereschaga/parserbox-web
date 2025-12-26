<?php

namespace AwardWallet\Engine\onetravel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Changes extends \TAccountChecker
{
    public $mailFiles = "onetravel/it-1905167.eml, onetravel/it-5744258.eml, onetravel/it-3.eml";
    private $providerCode = '';
    private $lang = '';
    private $reFrom = ['onetravel.com', 'cheapoair.com'];
    private static $reProvider = [
        'cheapoair' => ['CheapOair.com', 'CheapOair.ca'],
        'onetravel' => ['OneTravel.com'],
    ];
    private $reSubject = [
        'Confirmation of Itinerary Changes and Fees for Booking Number',
    ];
    private $reBody = [
        'en' => [
            ['Total cost to change this itinerary including taxes and fees:', 'DEPARTURE:'],
            ['This is in reference to booking #', 'DEPARTURE:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Name:' => ['Name:', 'Name :'],
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->detectEmailByBody($parser);

        if (empty($this->providerCode)) {
            return $email;
        }
        $this->date = strtotime($parser->getDate());
        $text = $this->htmlToText($this->findCutSection($parser->getHTMLBody(), null,
            ['Please contact our Customer Service', 'Additionally, if you do not contact us']));

        $this->parseFlight($email, $text);
        $email->setProviderCode($this->providerCode);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, ['onetravel.com']) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->arrikey($headers['from'], $this->reFrom) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$reProvider as $code => $values) {
            if ($this->http->XPath->query("//text()[{$this->contains($values)}]")->length > 0) {
                $this->providerCode = $code;

                break;
            }
        }

        if (empty($this->providerCode)) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$reProvider);
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($input, $searchFinish, true);
            } else {
                $inputResult = $input;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();
        $f->ota()->confirmation($this->re('/(?:Booking Number|reference to booking #)\s*(\d+)/', $text));
        $f->general()->noConfirmation();

        $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Name:'))}]", null,
            "/{$this->opt($this->t('Name:'))}\s*([[:alpha:]\s.\-]{3,})/");

        if (empty($travellers)) {
            $travellers = explode(', ', $this->re('/(?:Name:|Dear)\s+(.+?)(?:,|\n)/', $text));
        }
        $f->general()->travellers($travellers);

        // taxes and fees: $470.00 USD
        // taxes and fees: C$248
        $total = $this->re('/taxes and fees:\D*([\d.,]+)\s*([A-Z]{3})/', $text, true);

        if (preg_match('/taxes and fees:\D*(?<total>[\d.,]+)\s*(?<currency>[A-Z]{3})/', $text, $m)
            || preg_match('/taxes and fees:\s+(?<currency>\D+)\s*(?<total>[\d.,]+)/', $text, $m)) {
            $f->price()->total(preg_replace('/[^\d.]+/', '', $m['total']));
            $f->price()->currency($this->currency($m['currency']));
        }

        foreach ($this->splitter('/(FLIGHT:)/', $text) as $text) {
            $s = $f->addSegment();

            if (preg_match('/FLIGHT:(.+?)\s+(\d+)/s', $text, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
            }

            if (preg_match('/FLIGHT:.+?\s+\d+\s*\(\s*OPERATED BY\s+([\w\s]+)\s*\)/s', $text, $m)) {
                $s->airline()->operator($m[1]);
            }
            // DEPARTURE: Bridgetown 08FEB /655P
            if (preg_match('#DEPARTURE:\s*(.+?)\s+(\d+\w+)\s*/\s*(\d+)\s*([AP])#s', $text, $m)) {
                $s->departure()->name($m[1]);
                $s->departure()->noCode();
                $time = substr_replace($m[3], ':', (strlen($m[3]) === 3 ? 1 : 2), 0);
                $s->departure()->date2("{$m[2]}, {$time}{$m[4]}M", $this->date);
            }

            if (preg_match('#ARRIVAL:\s*(.+?)\s+(\d+\w+)\s*/\s*(\d+)\s*([AP])#s', $text, $m)) {
                $s->arrival()->name($m[1]);
                $s->arrival()->noCode();
                $time = substr_replace($m[3], ':', (strlen($m[3]) === 3 ? 1 : 2), 0);
                $s->arrival()->date2("{$m[2]}, {$time}{$m[4]}M", $this->date);
            }

            if (preg_match('#Air[lL]ine reservation ID:\s*([A-Z\d]{5,6})#', $text, $m)) {
                $s->setConfirmation($m[1]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    protected function re($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return false;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeDate($str)
    {
        $in = [
            // 08FEB /655P
            '#^(\w+)\s*/(\d{3,})[AP]$#',
        ];
        $out = [
            "$1, $2M",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s*([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->logger->error($str);

        return strtotime($str, false);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            'US$' => 'USD',
            '£'   => 'GBP',
            'C$'  => 'CAD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
