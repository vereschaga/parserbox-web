<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class KeepingYouUpdate extends \TAccountChecker
{
    public $mailFiles = "tcase/it-35465523.eml, tcase/it-51158286.eml, tcase/it-51159786.eml, tcase/it-51182770.eml";

    public $reFrom = "info@tripcase.com";
    public $reBody = [
        'en'  => ['TripCase will notify you', 'Estimated Arrival Time'],
        'en2' => ['has landed in', 'Baggage Claim'], // type 2
        'en3' => ['Change of plans', 'will be arriving'], // type 3
        'pt' => ['pousou em ', 'Retirada da Bagagem'], // type 2
        'es' => ['Cambio de planes', 'arribará'], // type 3
        'es2' => ['ha aterrizado', 'Banda de equipaje'], // type 2
        'pt2' => ['Mudança de Planos', 'chegará no'], // type 3
    ];
    public $reSubject = [
        // en
        'is keeping you updated on an upcoming flight',
        '\'s flight landed',
        '\'s flight update: baggage claim changed',
        // pt
        ' pousou!', // O voo de Oliveira Assis Junior pousou!
        ': mudança no horário de chegada',
        // es
        ': cambio de reclamo de equipaje',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            // for junk type
            'junkSubjects' => [
                '\'s flight landed',
                '\'s flight update: arrival time changed',
                '\'s flight update: baggage claim changed',
            ],
            'Original Time' => ['Original Time', 'Original Baggage'],
            'New Time'      => ['New Time', 'New Baggage'],
        ],
        'pt' => [
            // for junk type
            'junkSubjects' => [
                ' pousou!',
                ': mudança no horário de chegada',
                ': mudança de portão',
                ': mudança na retirada da bagagem',
                ': diversas mudanças',
            ],
//            'Estimated Arrival Time' => '',
//            'Where's' => '',

            // type 2
            'Flight Number' => ['Número do Voo'],
            'has landed in' => 'pousou em',
            'Baggage Claim' => 'Retirada da Bagagem',
            'has landed at' => 'pousou em',
            'arriving at' => 'a(às)',

            // type 3
            'Original Time' => ['Horário Original', 'Portão Original', 'Esteira de Bagagem Original'],
            'New Time'      => ['Novo Horário', 'Novo Portão', 'Nova Esteira de', 'Nova Esteira de Bagagem'],
            'Flight' => 'O voo',
            'will be arriving' => 'chegará no',
            'at Gate' => 'Portão',
        ],
        'es' => [
            // for junk type
            'junkSubjects' => [
                ' ha aterrizado',
                ': cambio de reclamo de equipaje',
            ],
//            'Estimated Arrival Time' => '',
//            'Where's' => '',

            // type 2
            'Flight Number' => ['Número de vuelo'],
            'has landed in' => 'ha aterrizado en',
            'Baggage Claim' => 'Banda de equipaje',
            'has landed at' => 'ha aterrizado en',
            'arriving at' => 'donde arribará a las',

            // type 3
            'Original Time' => ['Horário Original', 'Banda de equipaje original'],
            'New Time'      => ['Novo Horário', 'Nueva banda de equipaje'],
            'Flight' => 'El vuelo',
            'will be arriving' => 'arribará',
            'at Gate' => 'a la puerta',
        ],
    ];
    private $date;
    private $dateEmail;
    private $seemsJunk;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->date = strtotime($parser->getDate());

        if (preg_match("/[\w ]+\w{$this->opt($this->t('junkSubjects'))}$/ui",
            $parser->getSubject())) {
            $this->seemsJunk = true;

            if (stripos($parser->getCleanFrom(), "info@tripcase.com") !== false) {
                //for junk formats
                $this->dateEmail = $this->date;
            }
        }

        $html = str_ireplace(['&zwnj;', '&8204;', '‌'], '', $this->http->Response["body"]); // Zero-width non-joiner
        $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $html); // Zero-width
        $this->http->SetEmailBody($html);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email, $parser->getSubject());

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'tripcase.com')] | //img[@alt='TripCase']")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, $subject)
    {
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $xpath = "//text()[{$this->eq($this->t('Flight Number'))}]/ancestor::tr[1][{$this->contains($this->t('Estimated Arrival Time'))}]/following-sibling::tr[string-length(normalize-space(.))>3]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('Segments was found by xpath: ' . $xpath);
            $f->general()->traveller($this->http->FindSingleNode("//text()[({$this->starts($this->t("Where's"))}) and contains(.,'?')]",
                null, false, "#{$this->opt($this->t("Where's"))}\s+(.+)\?#"));
            $this->parseType_1($nodes, $f);

            return;
        }

        $xpath = "//text()[{$this->eq($this->t('Flight Number'))}]/ancestor::tr[1][{$this->contains($this->t('Baggage Claim'))}]/following-sibling::tr[string-length(normalize-space(.))>3]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('Segments was found by xpath: ' . $xpath);
            $traveller = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("has landed in"))}])[1]/ancestor::*[1]",
                null, false, "#^([[:alpha:]][[:alpha:] \-]+) {$this->opt($this->t("has landed in"))}#u");
            if (empty($traveller) && $this->lang == 'pt' && preg_match("/O voo de ([[:alpha:]][[:alpha:] \-]+) pousou!/u", $subject, $m)) {
                $traveller = $m[1];
            }
            if (empty($traveller) && $this->lang == 'es' && preg_match("/El vuelo de ([[:alpha:]][[:alpha:] \-]+) ha aterriza/u", $subject, $m)) {
                $traveller = $m[1];
            }
            $f->general()->traveller($traveller);
            $this->parseType_2($nodes, $f);

            return;
        }

        $xpath = "//text()[{$this->eq($this->t('Original Time'))}]/ancestor::tr[1][{$this->contains($this->t('New Time'))}][count(./following-sibling::tr[string-length(normalize-space(.))>3])=0]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug('Type 3. Segments was found by xpath: ' . $xpath);

            if ($this->parseType_3_isJunk($nodes, $f)) {
                $email->removeItinerary($f);
                $email->setIsJunk(true);
            }

            return;
        }
    }

    private function parseType_1(\DOMNodeList $nodes, Flight $f)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $s->departure()->noCode()->noDate();
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s*\/.+#", $node, $m)) {
                $s->arrival()
                    ->code($m[1]);
            }
            $node = $this->http->FindSingleNode("./td[3]", $root);
            $this->logger->warning($node);
            $s->arrival()
                ->date($this->normalizeDate($node));
        }
    }

    private function parseType_2(\DOMNodeList $nodes, Flight $f)
    {
        if ($nodes->length > 1) {
            $this->logger->debug('other format');

            return;
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $txt = preg_replace("/(.+)/", $m[2] . ' $1', $this->t('has landed at'));
                $node = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Flight Number'))}]/preceding::*[{$this->contains($txt)}])[last()]");

                if (preg_match("/{$this->opt($this->t('has landed at'))}\s*([A-Z]{3})\b.+?{$this->opt($this->t('arriving at'))}\s*(\d+:\d+(?:\s*[ap]m)?)/iu",
                    $node, $m)) {
                    $s->arrival()->code($m[1]);

                    if ($this->dateEmail) {
                        $s->arrival()->date(strtotime($m[2], $this->dateEmail));
                    } else {
                        $s->arrival()->noDate();
                    }
                }
            }
            $s->departure()->noCode()->noDate();
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("/^(\w+)\s*\/\s*\w+$/", $node, $m)) {
                $s->arrival()->terminal($m[1]);
            }
        }
    }

    private function parseType_3_isJunk(\DOMNodeList $nodes, Flight $f): bool
    {
        if ($nodes->length > 1) {
            $this->logger->debug('other format');

            return false;
        }

        foreach ($nodes as $root) {
            $startTxt = array_merge((array) $this->t('Original Time'), (array) $this->t('New Time'));
            $tds = array_filter($this->http->FindNodes("./td[normalize-space()!='' and not(.//img)]", $root,
                "/^{$this->opt($startTxt)}\s*(.+)\s*$/i"));
            $condition1 = (count($tds) === 2);
            $condition2 = $this->http->XPath->query("//text()[({$this->starts($this->t('Flight'))}) and ({$this->ends($this->t('will be arriving'))})]/following::text()[normalize-space()!=''][1][{$this->starts($this->t('at Gate'))}]")->length > 0;

            if ($condition1 && $condition2 && $this->seemsJunk) {
                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //‌Sun, ‌June ‌17 ‌9:38 ‌PM
            '#^([\w\-]+),\s+(\w+)\s+(\d+)\s+(\d+:\d+(\s*[ap]m)?)$#ui',
        ];
        $out = [
            '$3 $2 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $this->logger->warning($str);
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
    }
}
