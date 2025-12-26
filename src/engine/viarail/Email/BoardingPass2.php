<?php

namespace AwardWallet\Engine\viarail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "viarail/it-603170699.eml, viarail/it-604979868.eml, viarail/it-653987830.eml";
    public $subjects = [
        'VIA Rail boarding pass |',
        'Booking confirmation | ',
        'Your trip has changed - Booking #',
        'Modification de réservation | ', // fr
    ];

    public $lang = '';
    public $subjConf = '';

    public $detectLang = [
        "en" => ['Boarding Pass'],
        "fr" => ["Carte d'embarquement", "Voir le statut du train"],
    ];

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Check Train Status' => ['Check Train Status', 'Check train status'],
        ],
        "fr" => [
            'Boarding Pass'      => "Carte d'embarquement",
            'Booking #'          => 'N° de réservation',
            'Check Train Status' => 'Voir le statut du train',
            'pass modification'  => 'Modification de réservation',
            'Grand total'        => 'Grand total',
            'Subtotal'           => 'Sous-total',
            'VIA Préférence'     => ['N° VIA Préférence :', 'VIA Préférence'],
            'Train:'             => 'Train : ',
            'Seat:'              => 'Siège : ',
            'Car:'               => 'Voiture :',
            'Departure:'         => 'Départ :',
            'Arrival:'           => 'Arrivée :',
            'Class:'             => 'Classe :',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@viarail.ca') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            $this->assignLangPdf($text);

            if ($this->containsText($text, $this->t('Boarding Pass'))
                && $this->containsText($text, $this->t('Booking #'))
                && ($this->containsText($text, $this->t('Check Train Status')) || $this->containsText($text, 'Check Train Status'))
            ) {
                return true;
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'VIA Rail Canada')]")->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Booking #'))}]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(),'Boarding passes')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(),'Train')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]viarail\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subjConf = $this->re("/-(?:\s+Booking ref\.\s*:\s+|\s+)(?<conf>[A-Z\d]{6})\b/", $parser->getSubject());

        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf . "\n";
            }
        }

        if ($textPdfFull) {
            $this->ParseTrainPDF($email, $textPdfFull);
        } else {
            $this->ParseTrainHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTrainHTML(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->train();

        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Booking date:')]/following::text()[starts-with(normalize-space(), 'Booking #')]/following::text()[normalize-space()][1]");

        $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking date:')]/preceding::text()[starts-with(normalize-space(), 'Booking #')][1]", null, true, "/{$this->opt($this->t('Booking #'))}\s*([A-Z\d]{5,})/");

        if (empty($conf) && !empty($this->subjConf)) {
            $conf = $this->subjConf;
        }
        $t->general()
            ->confirmation($conf)
            ->travellers(array_filter(array_unique($travellers)))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking date:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking date:'))}\s*(.+)/")));

        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'VIA Préférence #:')]", null, "/{$this->opt($this->t('VIA Préférence #:'))}\s*(\d+)/")));

        if (count($accounts) > 0) {
            $t->setAccountNumbers($accounts, false);
        }

        $trainNumberArray = [];
        $xpath = "//text()[{$this->eq($this->t('Check Train Status'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $trainInfo = implode("\n", $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Train')][1]/ancestor::tr[1][contains(normalize-space(), 'Car')][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^Train(?:\s*#)?\s*(?<trainNumber>[A-Z\d]+)\s*Car\s*(?<car>\d+)\s*Seat\s*(?<seat>\d{1,2}[A-Z])$/su", $trainInfo, $m)) {
                if (in_array($m['trainNumber'], $trainNumberArray) === false) {
                    $s = $t->addSegment();
                    $trainNumberArray[] = $m['trainNumber'];
                }

                $s->setNumber($m['trainNumber']);
                $s->setCarNumber($m['car']);
                $s->addSeat($m['seat']);
            }

            $dateDeparture = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^(.+\s\d{4})$/");
            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.+)\n+(?<depCode>[A-Z\n]+)\n+Departure\:\s*(?<depTime>[\d\:]+)/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($dateDeparture . ', ' . $m['depTime']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrName>.+)\n+(?<arrCode>[A-Z\n]+)\n+Arrival\:\s*(?<arrTime>[\d\:]+)/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($dateDeparture . ', ' . $m['arrTime']));
            }

            $cabin = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Train')][1]/ancestor::tr[1][contains(normalize-space(), 'Car')][1]/following::tr[1][contains(normalize-space(), 'Class:')]",
                $root, true, "/{$this->opt($this->t('Class:'))}\s*(.+)/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }

    public function ParseTrainPDF(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->train();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('pass modification'))}]")->length > 0) {
            $t->setStatus('modification');
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Grand total'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^(?<currency>\D{1,2})(?<total>[\d\.\,]+)$/", $price, $m)
        || preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,2})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $t->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $currency));

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Subtotal'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/\D*([\d\.\,]+)/");

            if (!empty($cost)) {
                $t->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $feesRoot = $this->http->XPath->query("//text()[{$this->eq($this->t('Subtotal'))}]/ancestor::tr[2]/following-sibling::tr/descendant::tr");

            foreach ($feesRoot as $feeRoot) {
                $name = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $summ = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/^(?:\D{1,3})?([\d\.\,]+)/");

                if (!empty($name) && !empty($summ)) {
                    $t->price()
                        ->fee($name, PriceHelper::parse($summ, $currency));
                }
            }
        }

        if (preg_match_all("/^([[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]])(?: {3,}.*)?\n(?:Youth|Adult|Child|Senior|Dog|Infant)/mu", $text, $travellerMatches)) {
            $t->general()->travellers(array_unique($travellerMatches[1]), true);
        }

        if (preg_match_all("/{$this->opt($this->t('Booking #'))}\s*([A-Z\d]{6})\b/u", $text, $pnrMatches)) {
            $confs = array_unique($pnrMatches[1]);

            foreach ($confs as $conf) {
                $t->general()
                    ->confirmation($conf);
            }
        } elseif (!empty($this->subjConf)) {
            $t->general()
                ->confirmation($this->subjConf);
        }

        if (preg_match_all("/{$this->opt($this->t('VIA Préférence'))}[#\s]*(\d+)\s*-/u", $text, $accountMatches)) {
            $t->setAccountNumbers(array_unique($accountMatches[1]), false);
        }

        $segments = splitter("/({$this->opt($this->t('Boarding Pass'))})/", $text);

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            $number = $this->re("/{$this->opt($this->t('Train:'))}\s*([A-Z\d]+)/", $segment);

            foreach ($email->getItineraries() as $itinerary) {
                /** @var \AwardWallet\Schema\Parser\Common\Train $itinerary */
                foreach ($itinerary->getSegments() as $seg) {
                    if ($seg->getNumber() === $number) {
                        $t->removeSegment($s);
                        $s = $seg;
                    }
                }
            }

            if (!empty($number)) {
                $s->setNumber($number);
            }

            $carNumber = $this->re("/{$this->opt($this->t('Car:'))}\s*(\d+)(?: {3,}|\n)/", $segment);

            if (!empty($carNumber)) {
                $s->setCarNumber($carNumber);
            }

            if (!empty($s->getNumber()) && preg_match_all("/{$s->getNumber()}\s+.*{$this->opt($this->t('Seat:'))}\s*([A-Z\d]{1,4})(?:[ ]{3}|\n)/", $segment, $seatMatches)) {
                if (count($seatMatches[1]) === 1) {
                    $seat = array_shift($seatMatches[1]);

                    if (preg_match("/\n\n(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\b[ ]{2,}(?:.*\n){10,}.*Siège\s*\:\s*$seat/u", $segment, $m)) {
                        $s->extra()
                            ->seat($seat, false, false, $m['traveller']);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                } else {
                    $s->extra()->seats(array_unique($seatMatches[1]));
                }
            }

            $cabin = $this->re("/{$this->opt($this->t('Class:'))}\s*(.+?)(?: {3,}|\n)/", $segment);

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }

            $segment = str_replace('–', '-', $segment);
            $segText = $this->re("/(?:Youth|Adult|Child|Senior|Adulte|Jeune|Dog|Infant|Aîné)(?:\s+{$this->opt($this->t('VIA Préférence'))}?\s*[#]*\d*[\s\-]+\w+)?\n+(.+)\n+\s*{$this->opt($this->t('Train:'))}/su", $segment);
            $segTable = $this->splitCols($segText);

            if (count($segTable) > 0) {
                if (preg_match("/^(?<depName>.+)\n+(?<depCode>[A-Z\s]+)\n(?<depDate>.+\d{4})\n{$this->opt($this->t('Departure:'))}\s*(?<depTime>[\d\:]+)\n+/", $segTable[0], $m)) {
                    $s->departure()
                        ->name($m['depName'] . ', Canada')
                        ->code(str_replace(["\n", " "], "", $m['depCode']))
                        ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
                }

                if (preg_match("/^(?<arrName>.+)\n+(?<arrCode>[A-Z\n]+)\n+{$this->opt($this->t('Arrival:'))}\s*(?<arrTime>[\d\:]+)\n+$/", $segTable[1], $m)
                    || preg_match("/^(?<arrName>.+)\n+(?<arrCode>[A-Z\n]+)\n+(?<arrDate>.+\d{4})\n+{$this->opt($this->t('Arrival:'))}\s*(?<arrTime>[\d\:]+)\n*/", $segTable[1], $m)) {
                    $s->arrival()
                        ->name($m['arrName'] . ', Canada')
                        ->code(str_replace(["\n", " "], "", $m['arrCode']));

                    if (isset($m['arrDate']) && !empty($m['arrDate'])) {
                        $s->arrival()
                            ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
                    } else {
                        $s->arrival()
                            ->date(strtotime($m['arrTime'], $s->getDepDate()));
                    }
                }
            }
        }
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function assignLangPdf(string $text): bool
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate($str)
    {
        $in = [
            // mer. 15 nov. 2023, 09:11
            "#^\w+\.\s*(\d+)\s*(\w+)\.?\s*(\d{4})\,\s*([\d\:]+)$#su",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
