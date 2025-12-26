<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "wizz/it-162903994.eml, wizz/it-163826376.eml, wizz/it-164074831.eml, wizz/it-249827734.eml, wizz/it-426597005-cs.eml";

    public $lang = '';

    public $detectSubject = [
        // en
        'Check-in contact',
        'Check-in starts now!',
        // it
        'Il check-in inizia ora!',
        // es
        '¡La facturación comienza ahora!',
        // pl
        'Rozpoczęcie odprawy',
        // bg
        'Чекирането започва сега!',
        // fr
        'L\'enregistrement commence dès maintenant !',
        // uk
        'Реєстрація розпочинається зараз!',
        // ro
        'Check-inul se deschide acum!',
        // de
        'Check-in ab sofort möglich!',
        // lt
        'Registracija pradedama dabar!',
        // ru
        'Регистрация начинается прямо сейчас!',
        // ar
        'تسجيل الوصول يبدأ الآن!',
        // he
        'הצ\'ק-אין מתחיל עכשיו!',
        // pt
        'O check-in vai começar agora!',
        // sv
        'Incheckning börjar nu!',
        // hu
        'Az utasfelvétel elkezdődött!',
        // cs
        'Odbavení (check-in) začíná nyní!',
    ];

    public static $dictionary = [
        'en' => [
            'detect'        => ['we will send you notification to inform you', 'Your Wizz Air flight is coming up soon'],
            'Departs from:' => 'Departs from:',
            //            'Terminal' => '',
        ],
        'it' => [
            'detect'        => ['Il tuo volo Wizz Air è imminente'],
            'Departs from:' => 'Partenza da:',
            //            'Terminal' => '',
        ],
        'es' => [
            'detect'        => ['Se acerca la fecha de su vuelo con Wizz Air'],
            'Departs from:' => 'Sale de:',
            //            'Terminal' => '',
        ],
        'pl' => [
            'detect'        => ['Zbliża się termin Twojego lotu WIZZ Air'],
            'Departs from:' => 'Wylot z:',
            //            'Terminal' => '',
        ],
        'bg' => [
            'detect'        => ['Полетът Ви с Wizz Air предстои скоро'],
            'Departs from:' => 'Тръгва от:',
            //            'Terminal' => '',
        ],
        'fr' => [
            'detect'        => ['Votre vol Wizz Air est pour bientôt'],
            'Departs from:' => 'Part de :',
            //            'Terminal' => '',
        ],
        'uk' => [
            'detect'        => ['Наближається Ваш рейс Wizz Air.'],
            'Departs from:' => 'Аеропорт відправлення:',
            'Terminal'      => 'Термінал',
        ],
        'ro' => [
            'detect'        => ['Zborul dumneavoastră Wizz Air se apropie.'],
            'Departs from:' => 'Pleacă din:',
            'Terminal'      => 'Terminal',
        ],
        'de' => [
            'detect'        => ['Ihr Flug mit Wizz Air steht kurz bevor.'],
            'Departs from:' => 'Abflug von:',
            //            'Terminal' => '',
        ],
        'lt' => [
            'detect'        => ['artėja jūsų „Wizz Air“ skrydis.'],
            'Departs from:' => 'Išvyksta iš:',
            //            'Terminal' => '',
        ],
        'ru' => [
            'detect'        => ['Ваш рейс Wizz Air скоро отправляется.'],
            'Departs from:' => 'Аэропорт отправления:',
            'Terminal'      => 'Терминал',
        ],
        'ar' => [
            'detect'        => ['ستبدأ رحلة طيران Wizz Air الخاصة بك قريبًا جدًا.'],
            'Departs from:' => 'وصول إلى:',
            //            'Terminal' => '',
        ],
        'he' => [
            'detect'        => ['הטיסה שלך עם Wizz Air עומדת להמריא בקרוב.'],
            'Departs from:' => 'מקום המראה:',
            //            'Terminal' => '',
        ],
        'pt' => [
            'detect'        => ['O seu voo com a Wizz Air irá realizar-se em breve.'],
            'Departs from:' => 'Parte de:',
            //            'Terminal' => '',
        ],
        'sv' => [
            'detect'        => ['Det är snart dags för ditt Wizz Air-flyg.'],
            'Departs from:' => 'Avgår från:',
            //            'Terminal' => '',
        ],
        'hu' => [
            'detect'        => ['Közeleg Wizz Air-járatod indulásának időpontja.'],
            'Departs from:' => 'Indulási hely:',
            //            'Terminal' => '',
        ],
        'cs' => [
            'detect'        => ['Váš let se společností Wizz Air se blíží.'],
            'Departs from:' => 'Odlet:',
            //            'Terminal' => '',
        ],
    ];

    public $emailSubject;

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@wizzair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".wizzair.com/") or contains(@href,"www.wizzair.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Wizz Air Hungary Ltd.")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->emailSubject = $parser->getSubject();

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        if (preg_match("/{$this->opt($this->detectSubject)}[>\s]*(?-i)([A-Z\d]{5,7})\s*$/iu",
            $this->emailSubject, $m)
        ) {
            $f->general()
                ->confirmation($m[1]);
        } elseif (!preg_match("/\b([A-Z\d]{5,7})\b/", $this->emailSubject)) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation(null);
        }

        $xpath = "//tr[ *[1][{$this->eq($this->t('Departs from:'))}] and count(*[normalize-space()]) = 2]/following-sibling::tr[normalize-space()][1]";
        $segments = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $routes = $this->http->FindNodes("*[normalize-space()]", $root);

            if (count($routes) == 2) {
                if (preg_match("/(.+?)\s*\(([A-Z]{3})\)\s*$/", $routes[0], $m)) {
                    if (preg_match("/(.+) - ((?:[\w\-]+\s+)?(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\s*$/iu", $m[1], $mat)) {
                        $s->departure()
                            ->terminal(trim(preg_replace("/\s*(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})\s*/u",
                                '', $mat[2])));
                        $m[1] = $mat[1];
                    }
                    $s->departure()
                        ->code($m[2])
                        ->name($m[1]);

                    $s->airline()
                        ->noNumber()
                        ->noName();
                } elseif (preg_match("/^\s*\(([A-Z]{3})\)\s*$/", $routes[0], $m)) {
                    $s->departure()
                        ->code($m[1]);

                    $s->airline()
                        ->noNumber()
                        ->noName();
                }

                if (preg_match("/(.+?)\s*\(([A-Z]{3})\)\s*$/", $routes[1], $m)) {
                    if (preg_match("/(.+) - ((?:[\w\-]+\s+)?(?:terminal|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\s*$/ui", $m[1], $mat)) {
                        $s->arrival()
                            ->terminal(trim(preg_replace("/\s*(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})\s*/u",
                                '', $mat[2])));
                        $m[1] = $mat[1];
                    }
                    $s->arrival()
                        ->code($m[2])
                        ->name($m[1]);
                } elseif (preg_match("/^\s*\(([A-Z]{3})\)\s*$/", $routes[1], $m)) {
                    $s->arrival()
                        ->code($m[1]);
                }
            }
            $dates = $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[normalize-space()]", $root);

            if (count($dates) == 2) {
                $s->departure()->date($this->normalizeDate($dates[0]));
                $s->arrival()->date($this->normalizeDate($dates[1]));
            }
        }
    }

    private function normalizeDate(?string $text)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 27/05/2022 14:25
            "/^\s*(\d{1,2})\/(\d{2})\/(\d{4})[T\s]+({$this->patterns['time']})\s*$/",
            // 2023. 07. 12. 14:20
            "/^\s*(\d{4})\s*[.]+\s*(\d{1,2})\s*[.]+\s*(\d{1,2})\s*[.]+\s*({$this->patterns['time']})\s*$/",
        ];
        $out = [
            '$1.$2.$3, $4',
            '$1-$2-$3T$4',
        ];

        $text = preg_replace($in, $out, $text);
//        $this->logger->debug('$text = '.print_r( $text,true));

        return strtotime($text);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['detect']) || empty($dict['Departs from:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($dict['detect'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Departs from:'])}]")->length > 0
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
}
