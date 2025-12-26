<?php

namespace AwardWallet\Engine\sncf\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JourneyHtml2014 extends \TAccountChecker
{
    public $mailFiles = "sncf/it-1587596.eml, sncf/it-1705715.eml, sncf/it-1744049.eml, sncf/it-1826639.eml, sncf/it-2037580.eml, sncf/it-2037582.eml, sncf/it-2053154.eml, sncf/it-3.eml, sncf/it-5.eml, sncf/it-68959455.eml, sncf/it-69109286.eml";

    public static $dict = [
        'en' => [ // it-1744049.eml, it-5.eml
            'passengers' => ['Mister', 'Mademoiselle', 'Mrs.', 'Mr.'],
            'segments'   => ['Outward', 'Return'],
            'seats'      => '/Coach\s+(?<car>\d+) - Seat\s+(?<seat>\d+)/',
            //            'Confirmation de votre annulation' => '',
        ],
        'fr' => [ // it-1587596.eml, it-1705715.eml, it-1826639.eml, it-2037580.eml, it-2037582.eml, it-2053154.eml, it-3.eml
            'Reference'                                  => ['Référence', 'Référence de dossier :'],
            'passengers'                                 => ['Monsieur', 'Madame', 'Mademoiselle'],
            'You carried out an order on our website on' => 'Vous avez effectué une commande sur notre site le',
            'at'                                         => 'à',
            'segments'                                   => ['Aller', 'Retour'],
            'seats'                                      => '/Voiture\s+(?<car>\d+) - Place\s+(?<seat>\d+)/',
            'TOTAL'                                      => 'TOTAL',
            'Confirmation de votre annulation'           => 'Confirmation de votre annulation',
        ],
        'de' => [
            'Reference'  => 'Buchungsreferenz',
            'passengers' => ['Herr', 'Frau'],
            // 'You carried out an order on our website on' => '',
            // 'at' => '',
            'segments' => ['Hinfahrt', 'Rückfahrt'],
            'seats'    => '/Wagen\s+(?<car>\d+) - Platz\s+(?<seat>\d+)/',
            'TOTAL'    => 'GESAMTBETRAG',
            //            'Confirmation de votre annulation' => '',
        ],
        'nl' => [
            'Reference'  => 'Dossiernummer',
            'passengers' => ['Meneer'],
            // 'You carried out an order on our website on' => '',
            // 'at' => '',
            'segments' => ['Heenreis'],
            'seats'    => '/Rijtuig\s+(?<car>\d+) - Plaats\s+(?<seat>\d+)/',
            'TOTAL'    => 'TOTAAL',
            //            'Confirmation de votre annulation' => '',
        ],
    ];

    private $date;

    private $lang = '';
    private $subjects = [
        'en' => ['Journey confirmation '],
        'fr' => ['Confirmation pour vos voyages ', 'Confirmation pour votre voyage ', 'Confirmation de votre annulation'],
        'de' => ['Bestätigung Ihrer Verbindung '],
        'nl' => ['Bevestiging van uw reis '],
    ];
    private $langDetectors = [
        'en' => ['You carried out an order on our website'],
        'fr' => ['effectué une commande sur notre site', 'Confirmation de votre annulation'],
        'de' => ['Vielen Dank für Ihre Bestellung'],
        'nl' => ['U hebt een reservering gemaakt'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@voyages-sncf.') === false
            && stripos($headers['from'], '@de.oui.sncf') === false
            && stripos($headers['from'], '@en.oui.sncf.cdn-vsct.fr') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'sncf') !== false && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.](?:voyages-sncf|(?:de|en)\.oui\.sncf)\./", $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $t = $email->add()->train();

        // General
        $recordLocatorsAll = array_unique($this->http->FindNodes('//text()[' . $this->contains($this->t('Reference')) . ']/following::*[normalize-space(.)][1]', null, "/^\s*[\w-| ]+\s*$/"));
        $rls = [];

        foreach ($recordLocatorsAll as $rl) {
            $rls += array_map('trim', explode("|", $rl));
        }

        foreach (array_unique($rls) as $rl) {
            $t->general()
                ->confirmation($rl);
        }

        $travellers = $this->arrayUniquei(
            $this->http->FindNodes("//text()[{$this->starts($this->t('passengers'))}]/following-sibling::span[1]"));

        if (empty($travellers)) {
            $travellers = $this->arrayUniquei(
                $this->http->FindNodes("//text()[{$this->contains($this->t('passengers'))}]", null,
                    "#{$this->opt($this->t('passengers'))}\s+(.+)#"));
        }

        if (empty($travellers)) {
            $travellers = $this->arrayUniquei(
                $this->http->FindNodes("//text()[{$this->starts($this->t('passengers'))}]/following::span[1]"));
        }

        if (empty($travellers) && !empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('passengers'))}]", null,
                    "#^\s*\w+ {$this->opt($this->t('passengers'))}\s*$#"))) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[{$this->contains($this->t('passengers'))}]/following::text()[normalize-space()][1]", null,
                    "#^\s*([A-Z\-]{4,})\s*$#")]);
        }
        $t->general()
            ->travellers($travellers);

        if ($this->http->XPath->query("//*[" . $this->contains($this->t("Confirmation de votre annulation")) . "]")->length > 0) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
            $this->parseCancelledSegment($t);

            return;
        }
        // Price
        $total = $this->http->FindSingleNode("//td[{$this->contains($this->t('TOTAL'), 'text()')}]/following-sibling::td[1] | //span[{$this->contains($this->t('TOTAL'), 'text()')}]/following::span[1]");

        if (!empty($total)) {
            $t->price()
                ->total((float) str_replace(',', '.', preg_replace('/[^\d.,]+/', '', $total)))
                ->currency(preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $total))
            ;
        }

        $dateText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You carried out an order on our website on'))}]");

        if (preg_match("/{$this->opt($this->t('You carried out an order on our website on'))}\s+(?<date>.{6,}?)\s+{$this->opt($this->t('at'))}\s+(?<h>\d{1,2})[Hh](?<m>\d{1,2})\b/", $dateText, $m)) {
            $this->date = strtotime($this->normalizeDate($m['date']) . ' ' . $m['h'] . ':' . $m['m']);
        }

        $xpath = "//table[({$this->contains($this->t('segments'))}) and not(.//table) and contains(translate(.,'0123456789', '**********'), '**h**')]";
        $groupSegments = $this->http->XPath->query($xpath);

        foreach ($groupSegments as $gRoot) {
            $segments = $this->http->XPath->query(".//tr[position() mod 2 = 1]", $gRoot);
//            $this->logger->debug('$segments xpath = ' . print_r($xpath."//tr[position() mod 2 = 1]", true));

            $passengerGroupInfos = $this->http->XPath->query('./following-sibling::table[1]/tbody/tr[normalize-space()]', $gRoot);
            $passengerInfos = [];
            $passengerInfosColumns = [];
            $passSeg = [];

            foreach ($passengerGroupInfos as $pgRow) {
                $tds = $this->http->FindNodes("td[normalize-space()]", $pgRow);

                if (count($tds) >= 3 & preg_match('/^\s*\d+ ?\w{1,3}? \w+/u', $tds[0])) {
                    if (!empty($passSeg)) {
                        $passengerInfos[] = $passSeg;
                        $passengerInfosColumns[] = count($passSeg);
                    }
                    $passSeg = [$pgRow->nodeValue];

                    continue;
                }
                $passSeg[] = $pgRow->nodeValue;
            }

            if (!empty($passSeg)) {
                $passengerInfos[] = $passSeg;
                $passengerInfosColumns[] = count($passSeg);
            }

            if (count(array_unique($passengerInfosColumns)) !== 1 || empty($passengerInfosColumns) || $segments->length !== $passengerInfosColumns[0]) {
                $passengerInfos = [];
            }

            foreach ($segments as $i => $element) {
                foreach ($this->http->XPath->query('td[1]', $element) as $elementChild) {
                    if ($this->findStri($elementChild->nodeValue, $this->t('segments'))) {
                        $elementChild->parentNode->removeChild($elementChild);
                    }
                }

                $date = null;
                $dateValue = $this->http->FindSingleNode('ancestor::table[1]/preceding-sibling::*[string-length(normalize-space(.))>1][1]', $element);

                if (preg_match("/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>\d{1,2}\s+[[:alpha:]]+|[[:alpha:]]+\s+\d{1,2})$/u", $dateValue, $m)) {
                    // Sunday 26 October    |    Sunday, October 26
                    $parsedDate = $this->normalizeDate($m['date']);
                    $parsedYear = empty($this->date) ? null : date('Y', $this->date);
                    $weekDateNumber = WeekTranslate::number1($m['wday']);

                    if ($parsedDate && $weekDateNumber) {
                        $date = EmailDateHelper::parseDateUsingWeekDay($parsedDate . ' ' . $parsedYear, $weekDateNumber);
                    }
                }

                $s = $t->addSegment();

                $depName = $this->http->FindSingleNode('td[2]', $element);

                $s->departure()
                    ->name($depName)
                    ->date((!empty($date)) ? strtotime(str_ireplace('h', ':', $this->http->FindSingleNode('td[1]', $element)), $date) : null)
                    ->geoTip(', Europe');

                $arrName = $this->http->FindSingleNode('following-sibling::tr[1]/td[2]', $element);

                $s->arrival()
                    ->name($arrName)
                    ->date((!empty($date)) ? strtotime(str_ireplace('h', ':', $this->http->FindSingleNode('following-sibling::tr[1]/td[1]', $element)), $date) : null)
                    ->geoTip(', Europe');

                // Extra
                $type = join(' ', $this->http->FindNodes('td[position()=3 or position()=4]', $element));

                if (preg_match("/(.+)\s+(\d+)\s*$/", $type, $m)) {
                    $s->extra()
                        ->service($m[1])
                        ->number($m[2])
                    ;
                }

                $s->extra()
                    ->cabin($this->http->FindSingleNode('(.//td[normalize-space(.)])[last()]', $element));

                if (!empty($passengerInfos)) {
                    if (preg_match_all($this->t('seats'), implode("\n", array_column($passengerInfos, $i)), $m)
                        && isset($m['seat']) && isset($m['car'])) {
                        $s->extra()->seats($m['seat']);
                        $s->extra()->car($m['car'][0]);
                    }
                }
            }
        }
    }

    private function parseCancelledSegment(Train $t)
    {
        $xpath = "//text()[{$this->eq($this->t('segments'))}]/ancestor::tr[1][count(.//text()[contains(translate(.,'0123456789', '**********'), '**h**')])=2 and count(td[normalize-space()]) = 3]";
        // нет примеров, когда более 1 поезда в одну дату
//        $this->logger->debug('Cancelled segments xpath = ' . print_r($xpath, true));
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $element) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode('preceding-sibling::*[string-length(normalize-space(.))>1][1]', $element)));

            $s = $t->addSegment();

            $info = implode(" ", $this->http->FindNodes('td[2]//text()[normalize-space()]', $element));

            if (preg_match("/(\d{2}h\d{2})\s+(\S.+)\s+(\d{2}h\d{2})\s+(\S.+)/", $info, $m)) {
                $s->departure()
                    ->name($m[2])->geoTip(', Europe')
                    ->date((!empty($date)) ? strtotime(str_replace('h', ':', $m[1]), $date) : null);
                $s->arrival()
                    ->name($m[4])->geoTip(', Europe')
                    ->date((!empty($date)) ? strtotime(str_replace('h', ':', $m[3]), $date) : null);
            }

            // Extra
            $info = implode(" ", $this->http->FindNodes('td[3]//text()[normalize-space()][1]', $element));

            if (preg_match("/^(\D+)\s+(\d+)\s+(.+)$/", $info, $m)) {
                $s->extra()
                    ->service($m[1])
                    ->number($m[2])
                    ->cabin($m[3])
                ;
            }
        }
    }

    private function contains($array, $str1 = 'normalize-space(.)', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = "contains({$str1}, '{$str2}')";
        }

        return join(" {$operator} ", $arr);
    }

    private function starts($array, $str1 = 'normalize-space(.)', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = "starts-with({$str1}, '{$str2}')";
        }

        return join(" {$operator} ", $arr);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function arrayUniquei($array)
    {
        return array_values(array_filter(array_intersect_key(
                $array, array_unique(array_map("strToLower", $array))))
        );
    }

    private function findStri($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            // 23/12/2019
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]{2,}[,.\s]+)?(\d{1,2})\s+([[:alpha:]]{3,})$/u', $text, $m)) {
            // Lundi 13 Avril    |    13 Avril
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^(?:[-[:alpha:]]{2,}[,.\s]+)?([[:alpha:]]{3,})\s+(\d{1,2})$/u', $text, $m)) {
            // Lundi, Avril 13
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
