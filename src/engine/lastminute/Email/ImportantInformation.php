<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-777347983.eml, lastminute/it-783910210.eml, lastminute/it-785022906.eml, lastminute/it-793531679.eml";
    public static $detectProvider = [
        'bravofly' => [
            'from'       => 'bravofly.',
            'logoImgSrc' => ['bravofly', 'logo-BF', 'BRAVOFLY'],
            'name'       => ['bravofly', 'Bravofly'],
        ],
        'rumbo' => [
            'from'       => 'rumbo.',
            'logoImgSrc' => ['rumbo', 'RUMBO'],
            'name'       => ['rumbo'],
        ],
        'volagratis' => [
            'from'       => 'volagratis.',
            'logoImgSrc' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
            'name'       => ['volagratis'],
        ],
        'lastminute' => [
            'from'       => '@lastminute.com',
            'logoImgSrc' => ['lastminute', 'LASTMINUTE'],
            'name'       => ['lastminute'],
        ],
    ];

    public $detectSubject = [
        // en
        'Important information: schedule change to your flight - Booking ID',
        // es
        'Información importante: cambio de horario en tu vuelo - ID Booking',
        // de
        'Wichtige Auskunft: Änderung Ihrer Flugzeiten – Booking ID',
        // fr
        "Information importante: changement d'horaire de votre vol",
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Dear '           => 'Dear ',
            'Booking ID:'     => 'Booking ID:',
            "What's changed?" => "What's changed?",
            'newRoute'        => ['new outbound', 'NEW OUTBOUND', 'new return', 'NEW RETURN'],
            'Terminal'        => 'Terminal',
        ],
        'es' => [
            'Dear '           => 'Hola ',
            'Booking ID:'     => 'ID Booking',
            "What's changed?" => "¿Qué ha cambiado?",
            'newRoute'        => ['NUEVA IDA', 'nueva ida', 'NUEVA VUELTA', 'nueva vuelta'],
            // 'Terminal' => 'Terminal',
        ],
        'de' => [
            'Dear '           => 'Hallo ',
            'Booking ID:'     => 'Booking ID:',
            "What's changed?" => "Was wurde geändert?",
            'newRoute'        => ['NEUER RÜCKFLUG', 'neuer rückflug', 'NEUER HINFLUG', 'neuer hinflug'],
            'Terminal'        => 'Terminal',
        ],
        'fr' => [
            'Dear '           => 'Bonjour ',
            'Booking ID:'     => 'ID Booking:',
            "What's changed?" => ["un changement mineur dans votre vol."],
            'newRoute'        => ['NOUVEL ALLER', 'nouvel aller', 'NOUVEAU RETOUR', 'nouveau retour'],
            'Terminal'        => 'Terminal',
        ],
    ];

    private $codeProvider = null;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["What's changed?"]) && !empty($dict['newRoute'])
                && $this->http->XPath->query("//text()[{$this->eq($dict["What's changed?"])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['newRoute'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (empty($this->codeProvider)) {
            foreach (self::$detectProvider as $prov => $detects) {
                if ((!empty($detects['from']) && strpos($parser->getCleanFrom(), $detects['from']) !== false)) {
                    $this->codeProvider = $prov;

                    break;
                }

                if (!empty($detects['name']) && (
                    stripos($parser->getSubject(), $detects['name']) !== false
                    || $this->http->XPath->query("//a/@href[{$this->contains($detects['name'])}]")->length > 0
                )) {
                    $this->codeProvider = $prov;

                    break;
                }

                if (!empty($detects['logoImgSrc']) && $this->http->XPath->query("//img/@src[contains(@src, 'logo')][{$this->contains($detects['logoImgSrc'])}]")->length > 0) {
                    $this->codeProvider = $prov;

                    break;
                }
            }
        }

        if (!empty($this->codeProvider)) {
            $email->setProviderCode($this->codeProvider);
        }

        // Travel Agency
        $email->obtainTravelAgency();

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking ID:")) . "]/following::text()[normalize-space(.)][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        $email->ota()->confirmation($tripNumber);

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $prov => $detects) {
            if ((!empty($detects['from']) && strpos($from, $detects['from']) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['from']) && strpos($headers["from"], $detects['from']) !== false) {
                $head = true;
                $this->codeProvider = $prov;

                break;
            }

            if (!empty($detects['name'])) {
                foreach ($detects['name'] as $dName) {
                    if (stripos($headers["subject"], $dName) !== false) {
                        $head = true;
                        $this->codeProvider = $prov;

                        break 2;
                    }
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $head = false;

        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['name'])
                && $this->http->XPath->query("//a/@href[{$this->contains($detects['name'])}] | //text()[{$this->contains($detects['name'])}]")->length > 0
            ) {
                $head = true;
                $this->codeProvider = $this->codeProvider ?? $prov;

                break;
            }

            if (!empty($detects['logoImgSrc']) && $this->http->XPath->query("//img/@src[contains(@src, 'logo')][{$this->contains($detects['logoImgSrc'])}]")->length > 0) {
                $head = true;
                $this->codeProvider = $this->codeProvider ?? $prov;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict["What's changed?"]) && !empty($dict['newRoute'])
                && $this->http->XPath->query("//text()[{$this->eq($dict["What's changed?"])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['newRoute'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
                "/{$this->preg_implode($this->t('Dear '))}\s*(\D+)[,:]\s*$/"), false)
        ;

        // Segments
        $xpath = "//tr[*[2][{$this->starts($this->t('newRoute'))}]]/following-sibling::tr[normalize-space()][1]/*[2]//img[contains(@src, '/point.png')]/ancestor::tr[2]";
        // $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        $segments = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $date = $this->normalizeDate($this->http->FindSingleNode("preceding-sibling::*[normalize-space()][not(.//img)][1]", $nodes->item($i)));
            $rowsI = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr)][normalize-space()]", $nodes->item($i)));

            if ($i === ($nodes->length - 1)) {
                $segments[] = ['date' => $date, 'dep' => $rowsI, 'arr' => ''];

                break;
            }
            $rowsInext = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr)][normalize-space()]", $nodes->item($i + 1)));

            if (preg_match("/\n.+ - (?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?\d{1,4}\s*(?:\n|$)/", $rowsI)
                && !preg_match("/\n.+ - (?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?\d{1,4}\s*(?:\n|$)/", $rowsInext)
            ) {
                $segments[] = ['date' => $date, 'dep' => $rowsI, 'arr' => $rowsInext];
                $i++;

                continue;
            }
            $segments[] = ['date' => $date, 'dep' => $rowsI, 'arr' => ''];
        }

        // $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            // Airline
            if (preg_match('/\n.+ - (?<an>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,4})\s*(?:\n|$)/', $sText['dep'], $m)) {
                $s->airline()
                    ->name($m['an'])
                    ->number($m['fn']);
            }

            // Departure
            if (preg_match("/^\s*.+\n\s*(?<name>.+) - (?<code>[A-Z]{3})(?:\n *{$this->preg_implode($this->t('Terminal'))} *(?<terminal>.*))?\s*(?:\n|$)/", $sText['dep'], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            if (!empty($sText['date']) && preg_match("/^\s*(?<time>\d{1,2}:\d{2}) *(?<overnight>[\-+] ?\d)?\s*\n/", $sText['dep'], $m)) {
                $date = strtotime($m['time'], $sText['date']);

                if (!empty($date) && !empty($m['overnight'])) {
                    $date = strtotime($m['overnight'] . ' day', $date);
                }
                $s->departure()
                    ->date($date);
            }

            // Arrival
            if (preg_match("/^\s*.+\n\s*(?<name>.+) - (?<code>[A-Z]{3})(?:\n *{$this->preg_implode($this->t('Terminal'))} *(?<terminal>.*))?\s*(?:\n|$)/", $sText['arr'], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            if (!empty($sText['date']) && preg_match("/^\s*(?<time>\d{1,2}:\d{2}) *(?<overnight>[\-+] ?\d)?\s*\n/", $sText['arr'], $m)) {
                $date = strtotime($m['time'], $sText['date']);

                if (!empty($date) && !empty($m['overnight'])) {
                    $date = strtotime($m['overnight'] . ' day', $date);
                }
                $s->arrival()
                    ->date($date);
            }
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        $in = [
            // Montag 4 November 2024
            // '/^\s*[[:alpha:]\-]+\s*[, ]\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui',
        ];
        $out = [
            // '$1 $2 $3',
            // '$2 $3 ' . $year . ', $1',
        ];
        $date = preg_replace($in, $out, $str);

        if (preg_match("#\b\d{1,2}\s+([[:alpha:]]+)\s+\d{4}\b#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/\b\d{4}\b/", $str)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
