<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightRescheduled extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-137971199.eml, lanpass/it-168528556.eml, lanpass/it-310524954.eml, lanpass/it-316180514.eml, lanpass/it-761533105.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [ // it-137971199.eml
            'New flight'       => ['Vuelo nuevo', 'Nuevo horario'],
            'Flight cancelled' => ['Vuelo cancelado', 'Vuelo atrasado', 'Vuelo reprogramado'],
            'Hi'               => 'Hola',
            'orden'            => ['orden', 'Numero de orden'],
        ],
        'en' => [ // it-168528556.eml
            'New flight'       => ['New schedule', 'New flight'],
            'Flight cancelled' => ['Flight cancelled', 'Flight canceled', 'Flight rescheduled', 'Previous flight'],
            'Hi'               => 'Hi',
            'orden'            => ['Order', 'Order number'],
        ],
        'pt' => [
            'New flight'          => ['Novo horário', 'Novo horário'],
            'Flight cancelled'    => ['Voo cancelado', 'Voo reprogramado'],
            'Hi'                  => 'Olá',
            'orden'               => ['Número de compra', 'ordem'],
        ],
    ];

    private $subjects = [
        'es' => ['Tu vuelo ha sido reprogramado'],
        'en' => ['Your flight has been rescheduled'],
        'pt' => ['Seu voo foi remarcado'],
    ];

    private $detectors = [
        'es' => ['reprogramado', 'atrasado'],
        'pt' => ['reprogramado', 'atrasado'],
        'en' => ['rescheduled', 'flight'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'LATAM Airlines') !== false
            || stripos($from, '@info.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".latamairlines.com/") or contains(@href,"www.latamairlines.com") or contains(@href,"latam.com")or contains(@href,"etraveligroup.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Este mensaje es enviado por LATAM Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightRescheduled' . ucfirst($this->lang));

        $this->parseFlight($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function parseFlight(Email $email): void
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd") or starts-with(translate(normalize-space(),"0123456789：.","dddddddddd::"),"dd:dd"))';

        $f = $email->add()->flight();

        $orderid = $this->http->FindSingleNode("//text()[{$this->eq($this->t('orden'))}]/following::text()[normalize-space()][1]", null, true,
            "/^\s*([A-Z\d]{5,})\s*$/u");
        $f->general()
            ->confirmation($orderid,
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('orden'))}]")
            );

        $traveller = null;
        $travellerNames = $this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]/ancestor::tr[1]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $f->general()->traveller($traveller);

        $segments = $this->http->XPath->query("//*[ count(*[normalize-space()][descendant::text()[{$xpathTime}]])=2 ][ following::*[not(.//tr) and {$this->starts($this->t('Flight cancelled'))}] ]");

        if ($segments->length === 0 && $this->http->XPath->query("//*[{$this->contains($this->t('Flight cancelled'))}]")->length == 0) {
            $segments = $this->http->XPath->query("//text()[{$xpathTime}]/ancestor::*[position()<5][ count(*[normalize-space()][descendant::text()[{$xpathTime}]])=2 ]");

            if ($segments->length !== 1) {
                $segments = null;

                return;
            }
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = implode(' ', $this->http->FindNodes("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('New flight'))}\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s|$)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $xpathDep = "*[ descendant::text()[{$xpathTime}] ][1]/*[normalize-space()]";

            $dateDepValue = $this->http->FindSingleNode($xpathDep . "[1]", $root, true, "/^.*\d.*$/");

            if (preg_match("/^(.+\d{4})\s+\d+\:\d+/", $dateDepValue, $m)) {
                $dateDepValue = $m[1];
            }

            $dateDep = strtotime($this->normalizeDate($dateDepValue));
            $timeCodeDep = implode(' ', $this->http->FindNodes($xpathDep . "[2]/descendant::text()[normalize-space()]", $root));

            if (empty($timeCodeDep)) {
                $timeCodeDep = $this->http->FindSingleNode($xpathDep . "[1]", $root, true, "/^\w+\,\s*\w+.+\d{4}\s+(.+)/");
            }

            if (preg_match("/^(?<time>.*\d.*?)\s+(?<code>[A-Z]{3})$/", $timeCodeDep, $m)) {
                $s->departure()
                    ->date((!empty($dateDep)) ? strtotime($m[1], $dateDep) : null)
                    ->code($m[2])
                    ->name($this->http->FindSingleNode($xpathDep . "[3]", $root));
            } elseif (preg_match("/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[ap]m\b)?)\s+(?<name>.{3,})$/i", $timeCodeDep, $m)) {
                $s->departure()
                    ->date((!empty($dateDep)) ? strtotime($m['time'], $dateDep) : null)
                    ->noCode()
                    ->name($m['name']);
            }

            $xpathArr = "*[ descendant::text()[{$xpathTime}] ][2]/*[normalize-space()]";

            $dateArrValue = $this->http->FindSingleNode($xpathArr . "[1]", $root, true, "/^.*\d.*$/");

            if (preg_match("/^(.+\d{4})\s+\d+\:\d+/", $dateArrValue, $m)) {
                $dateArrValue = $m[1];
            }
            $dateArr = strtotime($this->normalizeDate($dateArrValue));
            $timeCodeArr = implode(' ', $this->http->FindNodes($xpathArr . "[2]/descendant::text()[normalize-space()]", $root));

            if (empty($timeCodeArr)) {
                $timeCodeArr = $this->http->FindSingleNode($xpathArr . "[1]", $root, true, "/^\w+\,\s*\w+.+\d{4}\s+(.+)/");
            }

            if (preg_match("/^(?<time>.*\d.*?)\s+(?<code>[A-Z]{3})$/", $timeCodeArr, $m)) {
                $s->arrival()
                    ->date((!empty($dateArr)) ? strtotime($m[1], $dateArr) : null)
                    ->code($m[2])
                    ->name($this->http->FindSingleNode($xpathArr . "[3]", $root));
            } elseif (preg_match("/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[ap]m\b)?)\s+(?<name>.{3,})$/i", $timeCodeArr, $m)) {
                $s->arrival()
                    ->date((!empty($dateArr)) ? strtotime($m['time'], $dateArr) : null)
                    ->noCode()
                    ->name($m['name']);
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                $this->logger->debug($phrase);

                if (!is_string($phrase)) {
                    continue;
                }
                $this->logger->debug("//*[{$this->contains($phrase)}]");

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['New flight']) || empty($phrases['Flight cancelled'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['New flight'])}]")->length > 0
                || $this->http->XPath->query("//*[{$this->contains($phrases['Flight cancelled'])}]")->length > 0
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
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        if (preg_match('/^[-[:alpha:]]+[,\s]+([[:alpha:]]+)\s*(\d{1,2})[,\s]+(\d{4})$/u', $text, $m)) {
            // Thursday, July 07, 2022
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // 18 de febrero de 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } else {
                foreach (['es', 'pt'] as $lang) {
                    if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                        $month = $monthNew;

                        break;
                    }
                }
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
