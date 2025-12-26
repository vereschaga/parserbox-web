<?php

namespace AwardWallet\Engine\vueling\Email;

use AwardWallet\Schema\Parser\Email\Email;

class GetYourBPNow extends \TAccountChecker
{
    public $mailFiles = "vueling/it-10447227.eml, vueling/it-10461815.eml, vueling/it-373039545.eml, vueling/it-380657208.eml, vueling/it-380657233.eml, vueling/it-382305423.eml, vueling/it-382405238.eml, vueling/it-385590181.eml, vueling/it-387485457.eml, vueling/it-630941692.eml, vueling/it-632135712-nl.eml";

    public $reFrom = ["vueling.com"];
    public $reBody = [
        'en' => [
            'add a checked bag now',
            'check in now and get your boarding pass free of charge',
            'don\'t forget that you must carry your boarding ',
            'need to have your ID document to hand to complete the process',
        ],
        'es' => [
            'ten a mano tu documento de identificación durante el proceso',
        ],
        'fr' => [
            'gardez votre document d’identité à portée de main pendant le processus.',
        ],
        'de' => [
            'Halten Sie während des Vorgangs Ihr Ausweisdokument bereit.',
        ],
        'it' => [
            'tieni a portata di mano il documento di identità durante il processo.',
        ],
        'pt' => [
            'faça o check-in online e obtenha o seu lugar',
        ],
        'nl' => [
            'houd je identificatiepapieren tijdens het proces bij de hand.',
            'voeg nu je ingecheckte koffer toe',
        ],
    ];
    public $reSubject = [
        'Get your boarding pass now',
        'Es hora de hacer el check-in',
        'È il momento di fare il check-in',
        'Save money by adding your checked bag now | Booking', // en
        'Het is tijd om in te checken | Reservering', // nl
        'Bespaar met je ingecheckte koffer | Reservering', // nl
    ];
    public $lang = '';
    public $date = 0;
    public static $dict = [
        'en' => [
            'Booking code:' => ['Booking code:', 'Booking code'],
            'Check in'      => ['Check in', 'Add bag'],
        ],
        'es' => [ // it-382405238.eml
            'Booking code:'                      => ['Código de reserva:', 'Código de reserva:'],
            'Check in'                           => 'Hacer el check-in',
            'check in online and get your seat.' => 'haz el check-in online y obtén tu asiento.',
        ],
        'fr' => [ // it-382305423.eml
            'Booking code:'                      => ['Code de réservation :', 'Code de réservation:', 'Code de réservation'],
            'Check in'                           => 'Réaliser l’enregistrement',
            'check in online and get your seat.' => 'faites votre check-in en ligne et connaissez votre numéro de siège.',
        ],
        'de' => [ // it-380657208.eml
            'Booking code:'                      => ['Buchungscode:', 'Buchungscode'],
            'Check in'                           => 'Einchecken',
            'check in online and get your seat.' => 'checken Sie online ein und sichern Sie sich ihren Sitzplatz.',
        ],
        'it' => [ // it-373039545.eml
            'Booking code:'                      => ['Codice di prenotazione:', 'Codice di prenotazione'],
            'Check in'                           => 'Fai il check-in',
            'check in online and get your seat.' => 'fai il check-in online e ottieni il tuo posto.',
        ],
        'pt' => [ // it-387485457.eml
            'Booking code:'                      => ['Código da reserva:', 'Código da reserva'],
            'Check in'                           => 'Fazer o check-in',
            'check in online and get your seat.' => 'faça o check-in online e obtenha o seu lugar.',
        ],
        'nl' => [
            'Booking code:'                      => ['Reserveringsnummer:', 'Reserveringsnummer'],
            'Check in'                           => ['Inchecken', 'Koffer toevoegen'],
            'check in online and get your seat.' => 'check online in en krijg je stoel toegewezen.',
        ],
    ];
    private $keywordProv = 'Vueling';

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
        'time'        => '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
    ];

    private $patterns = [
        'date' => '[[:alpha:]]+[,\s]+\d{1,2}\s*[[:alpha:]]+\s*\d{4}\b', // Samedi, 13 Mai 2023
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (count($pdfs) > 0) {
            $this->logger->debug('check attachment');

            return $email;
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Vueling' or contains(@src,'.vueling.com')] | //a[contains(@href,'.vueling.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
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

    private function parseEmail(Email $email): void
    {
        $xpath = "//text()[{$this->eq($this->t('Check in'))}]/ancestor::tr[preceding-sibling::tr[normalize-space()][{$this->contains($this->t('Booking code:'))}]][1]/preceding-sibling::tr[last()]";
        $this->logger->debug($xpath);
        $node = $this->http->XPath->query($xpath);

        if ($node->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Check in'))}]/following::text()[{$this->contains($this->t('Booking code:'))}][1]/ancestor::tr[1]";
            $this->logger->debug($xpath);
            $node = $this->http->XPath->query($xpath);
        }

        if ($node->length !== 1) {
            $this->logger->debug('check format');

            return;
        }
        $root = $node->item(0);
        $r = $email->add()->flight();
        $r->general()->confirmation($this->http->FindSingleNode(".", $root, false,
            "#{$this->opt($this->t('Booking code:'))}\s*(.+)#"));

        $traveller = str_replace([','], '', $this->http->FindSingleNode("//text()[{$this->contains($this->t('check in online and get your seat.'))}]/ancestor::tr[1]", null, true, "/^(.+)\s*{$this->opt($this->t('check in online and get your seat.'))}/"));

        if (!empty(trim($traveller))) {
            $r->general()
                ->traveller($traveller);
        }

        $this->date = $this->normalizeDate($this->http->FindSingleNode('.', $root, true, "/{$this->patterns['date']}/u"));

        if (preg_match("/\s{$this->patterns['time']}.*{$this->patterns['time']}/", $this->http->FindSingleNode("following::table[1]", $root))) {
            $this->parseSegment2($email, $r, $root);
        } else {
            $this->parseSegment($email, $r, $root);
        }
    }

    private function parseSegment(Email $email, \AwardWallet\Schema\Parser\Common\Flight $r, \DOMNode $root): void
    {
        // examples: it-10447227.eml, it-10461815.eml
        $this->logger->debug(__FUNCTION__);
        $s = $r->addSegment();

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Check in'))}]/ancestor::a[contains(@href,'.vueling.')]")->length == 1) {
            $s->airline()->name('VY'); // vueling IATA
        } else {
            $s->airline()->noName();
        }
        $s->airline()->noNumber();

        $s->departure()
            ->noDate()
            ->day(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                $root)))
            ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                $root))
            ->code($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                $root));
        $s->arrival()
            ->noDate()
            ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][4]",
                $root))
            ->code($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][5]",
                $root));
    }

    private function parseSegment2(Email $email, \AwardWallet\Schema\Parser\Common\Flight $r, \DOMNode $root): void
    {
        // examples: it-373039545.eml, it-380657208.eml, it-380657233.eml, it-382305423.eml, it-382405238.eml, it-385590181.eml, it-387485457.eml, it-630941692.eml, it-632135712-nl.eml
        $this->logger->debug(__FUNCTION__);
        $segments = $this->http->XPath->query("./following::table[1]/descendant::img[contains(@src, 'Plane')]", $root);
        $this->logger->debug('Found segments: ' . $segments->length);

        if ($segments->length > 1) {
            foreach ($segments as $key => $node) {
                $s = $r->addSegment();

                $airlineInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::img[1]/ancestor::tr[1][contains(normalize-space(), '|')]/descendant::text()[normalize-space()][not(contains(normalize-space(), '|'))][$key+1]", $node);

                if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{2,4})$/", $airlineInfo, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }

                $s->departure()
                    ->code($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $node, true, "/^([A-Z]{3})$/"));

                $s->arrival()
                    ->code($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $node, true, "/^([A-Z]{3})$/"));

                if ($key > 0) {
                    $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(), ':')][$key+2]", $node, true, "/^([\d\:]+)/");
                    $arrTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(), ':')][$key+3]", $node, true, "/^([\d\:]+)/");

                    $s->departure()->date(strtotime($depTime, $this->date));
                    $s->arrival()->date(strtotime($arrTime, $this->date));
                } else {
                    $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(), ':')][1]", $node, true, "/^([\d\:]+)/");
                    $arrTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(), ':')][2]", $node, true, "/^([\d\:]+)/");

                    $s->departure()->date(strtotime($depTime, $this->date));
                    $s->arrival()->date(strtotime($arrTime, $this->date));
                }

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()
                        ->date(strtotime('+1 day', $s->getArrDate()));
                }
            }
        } else {
            $s = $r->addSegment();

            $xpathCodes = "following::table[1]/descendant::tr[count(*[normalize-space()])=2 and count(*[{$this->xpath['airportCode']}])=2]";
            $xpathNames = $xpathCodes . "/preceding::tr[not(.//tr) and normalize-space() and count(*)=2][1]";
            $xpathTerminals = $xpathCodes . "/following::tr[not(.//tr) and normalize-space()][1]";
            $xpathTimes = $xpathCodes . "/following::tr[not(.//tr) and normalize-space()][position()<3][count(*[{$this->xpath['time']}])=2]";

            $nameDep = $this->http->FindSingleNode($xpathNames . "/*[1]", $root);
            $nameArr = $this->http->FindSingleNode($xpathNames . "/*[2]", $root);

            $codeDep = $this->http->FindSingleNode($xpathCodes . "/*[{$this->xpath['airportCode']}][1]", $root);
            $codeArr = $this->http->FindSingleNode($xpathCodes . "/*[{$this->xpath['airportCode']}][2]", $root);

            $timeDep = $this->http->FindSingleNode($xpathTimes . "/*[{$this->xpath['time']}][1]", $root, true, "/^{$this->patterns['time']}/");
            $timeArr = $this->http->FindSingleNode($xpathTimes . "/*[{$this->xpath['time']}][2]", $root, true, "/^{$this->patterns['time']}/");

            $terminalDep = $this->http->FindSingleNode($xpathTerminals . "/*[normalize-space() and normalize-space(@align)='left']", $root, true, $pattern = "/^(?:Terminal[-\s]+)?([-A-z\d\s]+)$/i")
            ?? $this->http->FindSingleNode($xpathTerminals . "[count(*[normalize-space()])=2]/*[normalize-space()][1]", $root, true, $pattern);
            $terminalArr = $this->http->FindSingleNode($xpathTerminals . "/*[normalize-space() and normalize-space(@align)='right']", $root, true, $pattern)
            ?? $this->http->FindSingleNode($xpathTerminals . "[count(*[normalize-space()])=2]/*[normalize-space()][2]", $root, true, $pattern);

            $s->departure()->name($nameDep)->code($codeDep)->date(strtotime($timeDep, $this->date))->terminal($terminalDep, false, true);
            $s->arrival()->name($nameArr)->code($codeArr)->date(strtotime($timeArr, $this->date))->terminal($terminalArr, false, true);

            $xpathTable = "following::table[1]/descendant-or-self::*[ tr[normalize-space()][2] ][1]";

            $flight = $this->http->FindSingleNode($xpathTable . "/tr[normalize-space()][last()]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\D|$)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking code:'], $words['Check in'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking code:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check in'])}]")->length > 0
                ) {
                    $this->lang = $lang;

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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            '/^[[:alpha:]]+[,\s]+(\d{1,2}\s*[[:alpha:]]+\s*\d{4})$/u', // Samedi, 13 Mai 2023
        ];

        $out = [
            "$1",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
