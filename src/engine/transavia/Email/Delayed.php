<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Delayed extends \TAccountChecker
{
    public $mailFiles = "transavia/it-642249643.eml, transavia/it-643236265.eml, transavia/it-644406383.eml, transavia/it-645833948.eml, transavia/it-645836653.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your booking:'                               => 'Your booking:',
            'Dear '                                       => 'Dear ',
            'We are sorry to inform you that your flight' => ['We are sorry to inform you that your flight', 'Unfortunately, the departure time of your flight'],
            //            'from' => '',
            //            'to' => '',
            //            'on' => '',
            //            'is cancelled' => '',
            //            'has been delayed' => 'has been delayed',
            'StatusNameCancelled' => 'Cancelled',
            'StatusNameDelayed'   => 'Delayed',
            //            'The new departure time is' => '',
        ],
        'fr' => [
            'Your booking:'                               => ['Numéro de réservation:', 'Votre réservation:'],
            'Dear '                                       => 'Cher(e) ',
            'We are sorry to inform you that your flight' => 'Nous sommes désolés de vous informer que votre vol',
            'from'                                        => ['au départ de', 'de'],
            'to'                                          => ['à destination de', 'à'],
            'on'                                          => ['le', 'du'],
            'is cancelled'                                => 'est annulé',
            'has been delayed'                            => ['est de nouveau retardé', 'a été retardé'],
            'StatusNameCancelled'                         => 'Annulé',
            'StatusNameDelayed'                           => 'Retardé',
            'The new departure time is'                   => 'votre nouvelle heure de départ est estimée à',
        ],
    ];

    private $detectFrom = "tip@transavia.com";
    private $detectSubject = [
        // en
        ' has been delayed',
        ' has been cancelled',
        // fr
        ' est retardé',
        ' est annulé',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]transavia\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Transavia') === false
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['transavia.com'], '@href')}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["We are sorry to inform you that your flight"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['We are sorry to inform you that your flight'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking:'))}]", null, true,
            "/{$this->opt($this->t('Your booking:'))}\s*([A-Z\d]{5,7})\s*$/u");
        $f->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
                "/{$this->opt($this->t('Dear '))}\s*([[:alpha:]][[:alpha:] \-]+?)\s*[,]\s*$/u"), false)
        ;

        $s = $f->addSegment();

        $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We are sorry to inform you that your flight'))}]");

        // We are sorry to inform you that your flight HV5310 from Bologna to Eindhoven on 20/10/2022 has been delayed due to a technical issue. The new departure time is 16:00 local time.
        // We are sorry to inform you that your flight TO4391 on 06/01/2024 from Prague to Paris (Orly) is cancelled due to (expected) weather conditions.
        $reDelay = "/{$this->opt($this->t('We are sorry to inform you that your flight'))} (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) {$this->opt($this->t('from'))} (?<from>.+) {$this->opt($this->t('to'))} (?<to>.+?) {$this->opt($this->t('on'))} (?<date>\d+\/\d+\/\d+)\b/u";
        $reCancelled = "/{$this->opt($this->t('We are sorry to inform you that your flight'))} (?:Transavia )?(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+) {$this->opt($this->t('on'))} (?<date>\d+\/\d+\/\d+) {$this->opt($this->t('from'))} (?<from>.+) {$this->opt($this->t('to'))} (?<to>.+?) (?:(?<cancelled>{$this->opt($this->t('is cancelled'))})|{$this->opt($this->t('has been delayed'))})/u";

        if (preg_match($reDelay, $text, $m) || preg_match($reCancelled, $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);

            $s->departure()
                ->noCode()
                ->name($m['from']);

            $s->arrival()
                ->noCode()
                ->name($m['to'])
                ->noDate();

            $date = strtotime(str_replace('/', '.', $m['date']));

            $time = null;

            if (preg_match("/{$this->opt($this->t('The new departure time is'))} (?<time>\d{1,2}:\d{2}(?: ?[ap]m\b)?) /u", $text, $m2)) {
                $time = $m2['time'];
            }

            if (!empty($time) && !empty($date)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            } elseif (!empty($date)) {
                $s->departure()
                    ->noDate()
                    ->day($date);
            }

            if (!empty($m['cancelled'])) {
                $s->extra()
                    ->status($this->t('StatusNameCancelled'))
                    ->cancelled();
            } else {
                $s->extra()
                    ->status($this->t('StatusNameDelayed'));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
