<?php

namespace AwardWallet\Engine\trenitalia\Email;

use AwardWallet\Schema\Parser\Common\Itinerary;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketPDF extends \TAccountChecker
{
    public $mailFiles = "trenitalia/it-106426729.eml, trenitalia/it-114888737.eml, trenitalia/it-173467622.eml, trenitalia/it-184660361.eml, trenitalia/it-31328493.eml, trenitalia/it-321396121.eml, trenitalia/it-95911436.eml, trenitalia/it-96480036.eml";

    public $reSubject = [
        "fr" => "Votre Billet Trenitalia",
        // "it" => "",
        "de" => "Ihre Tickets für die Fahrt von",
        "en" => "Your Trenitalia Ticket",
    ];
    public $reBody = 'Trenitalia';
    public $reBody2 = [
        "fr" => "Gare de départ",
        "it" => "Stazione di Partenza",
        "de" => "Abfahrtsbahnhof",
        "en" => "Departure station",
    ];

    public static $dictionary = [
        "fr" => [ // it-187572709-fr.eml
            'separator'             => '/(\n[ ]*VOYAGE\s+de\s+.+?\s+a\s+)/',
            'Receipt n.'            => 'Reçu n°',
            'PNR'                   => 'PNR',
            'Ticket Code'           => 'Code du billet',
            'Stazione di partenza'  => ['Gare de départ', 'Gare de départ/From'],
            'Stazione di arrivo'    => ["Gare d'arrivée", "Gare d'arrivée/To"],
            'Treno'                 => 'Train',
            // 'Autobus' => '',
            'Importo totale'       => 'Montant total payé',
            'Importo pagato totale'=> 'Montant total payé',
            'DETTAGLIO PASSEGGERI' => 'DETAILS DU PASSAGER',
            'Altri dati'           => ['Acheteur:', "\n\n\n\n\n"],
            'Carrozza'             => 'Voiture',
            'Servizio'             => 'Service',
            'Posti'                => ['Sièges', 'Places'],
            'Ore'                  => 'Heure',
            'Offerta - Servizio'   => ['Offre -', 'Offre - Service'],
            'Nome Passeggero'      => 'Nom du Passager',
        ],
        "it" => [ // ?
            'separator'             => '/(VIAGGIO\s+da\s+.+?\s+a\s+|DETTAGLIO VIAGGIO|\s+PNR[ ]*:+[ ]*[-A-Z\d]+\s+DETTAGLIO VIAGGIO)/u',
            'Receipt n.'            => 'Ricevuta n.',
            'PNR'                   => 'PNR',
            'Ticket Code'           => 'Codice Biglietto',
            'Stazione di partenza'  => ['Stazione di partenza', 'Stazione di Partenza/From'],
            'Stazione di arrivo'    => ['Stazione di arrivo', 'Stazione di Arrivo/To'],
            //			'Treno' => 'Treno',
            //			'Autobus' => '',
            'Importo totale' => ['Importo totale', 'Total amount'],
            //          'Importo pagato totale' => '',
            //          'DETTAGLIO PASSEGGERI' => '',
            'Altri dati' => ['Altri dati', 'Acquirente', "\n\n\n\n\n"],
            //			'Carrozza' => 'Carrozza',
            //			'Servizio' => 'Servizio',
            'Posti' => ['Posti', 'Seats'],
            //			'Ore' => '',
            //			'Offerta - Servizio' => 'Offerta - Servizio',
            'Nome Passeggero' => ['Nome Passeggero', 'name (Adulto)', 'name (Adult)'],
        ],
        "de" => [ // it-114888737.eml
            'separator'            => '/(?:(\n[ ]*REISE\s+von\s+.+?\s+Nach\s+)|(REISEVERBINDUNG IM DETAIL\/ITINERARY DETAILS))/',
            // 'Receipt n.'            => '',
            'PNR'                  => 'PNR',
            // 'Ticket Code'          => '',
            'Stazione di partenza' => ['Abfahrtsbahnhof', 'Abfahrtsbahnhof/From'],
            'Stazione di arrivo'   => ['Ankunftsbahnhof', 'Ankunftsbahnhof/To'],
            'Treno'                => 'Zug',
            // 'Autobus' => '',
            'Importo totale' => ['Total amount*:', 'Betrag*:'],
            // 'Importo pagato totale' => '',
            'DETTAGLIO PASSEGGERI' => 'PASSAGIEREDATEN',
            'Altri dati'           => ['Käufer:', "\n\n\n\n\n"],
            'Carrozza'             => 'Wagen',
            'Servizio'             => 'Serviceleistung',
            'Posti'                => 'Sitzplätze',
            'Ore'                  => 'Uhrzeit',
            'Offerta - Servizio'   => ['Angebot -', 'Angebot - Serviceleistung'],
            'Nome Passeggero'      => ['Passagiername', 'Passagiername/Passengername (Erwachsener)', 'Passagiername/Passenger name (Erwachsener)'],
        ],
        "en" => [ // it-31328493.eml, it-95911436.eml, it-96480036.eml, it-106426729.eml
            'separator'             => '/(\n\s*TRAVEL\s+from\s+.+?\s+To\s+)/',
            'Receipt n.'            => 'Receipt n.',
            'PNR'                   => 'PNR',
            'Ticket Code'           => 'Ticket Code',
            'Stazione di partenza'  => 'Departure station',
            'Stazione di arrivo'    => 'Arrival station',
            'Treno'                 => 'Train',
            'Autobus'               => 'Bus',
            'Importo totale'        => ['Total amount'],
            'Importo pagato totale' => 'Total Amount Paid',
            'DETTAGLIO PASSEGGERI'  => 'PASSENGERS DETAILS',
            'Altri dati'            => ['Buyer:', "TRANSPORT CONDITIONS", "\n\n\n\n\n"],
            'Carrozza'              => 'Coaches',
            'Servizio'              => 'Service',
            'Posti'                 => 'Seats',
            'Ore'                   => 'Hours',
            'Offerta - Servizio'    => 'Offer - Service',
            'Nome Passeggero'       => 'Passenger Name',
        ],
    ];

    public $lang = "it";

    protected $result = [];
    private $fileIdsAll = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $html = null;

                if (($html = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    foreach ($this->reBody2 as $lang => $re) {
                        if (strpos($html, $re) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                    $this->parseEmail($email, $html);
                } else {
                    return $email;
                }
            }
        } else {
            return $email;
        }
        $email->setType('TicketPDF' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trenitalia\.(?:com|it)\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Trenitalia') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers['subject']) && strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

                foreach ($this->reBody2 as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
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

    protected function parseEmail(Email $email, string $plainText): void
    {
        $this->parseSegments($email, $plainText, $this->t('separator'));
    }

    protected function parsePassengers(Itinerary $train, $plainText)
    {
        $passengerRows = $this->splitter("/(.+{$this->opt($this->t('Offerta - Servizio'))}.*)/", $plainText);

        foreach ($passengerRows as $passengerRow) {
            $tablePos = [0];

            if (preg_match("/^(.+?[ ]{2}){$this->opt($this->t('Offerta - Servizio'))}/m", $passengerRow, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($passengerRow, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong passengers table!');

                return null;
            }

            if (preg_match("/" . str_replace(" ", '(?: | *\n *)', $this->opt($this->t('Nome Passeggero'))) . ".*\n+[ ]*([[:alpha:]][-.'[:alpha:]\s]{5,25}[[:alpha:]])\s*\n/u", $table[0], $m)) {
                $m[1] = preg_replace('/\s+/', ' ', $m[1]);

                if (!in_array($m[1], array_column($train->getTravellers(), 0))) {
                    $train->addTraveller($m[1], true);
                }
            }

            $accText = $this->splitCols($table[1]);

            if (preg_match('/CartaFreccia\s+(\d+)/', implode("\n", $accText), $m)
                && !in_array($m[1], array_column($train->getTravellers(), 0))
            ) {
                $train->addAccountNumber($m[1], false);
            }
        }
    }

    protected function parseTotalCharge(Itinerary $it, $plainText): void
    {
        if (preg_match("/^[*\s]*(?:Total amount[*\s]*)?:?\s*(.+)/iu", $plainText, $m)) {
            $tot = $this->getTotalCurrency(str_replace("$", "USD", str_replace("€", "EUR", $m[1])));

            if ($tot['Total'] !== '') {
                $it->price()
                    ->total($it->getPrice() && $it->getPrice()->getTotal() ? $it->getPrice()->getTotal() + $tot['Total'] : $tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    protected function parseSegments(Email $email, $plainText, $segmentsSplitter): void
    {
        $segments = $this->splitter($segmentsSplitter, $plainText);

        $fileIds = $receiptNums = [];

        if (preg_match_all("/(?:^[ ]*|[ ]{2})(PNR|{$this->opt($this->t('Ticket Code'))})\s*:\s*([-A-Z\d]{5,})$/m", $plainText, $m)) {
            foreach ($m[0] as $i => $v) {
                $fileIds[$m[2][$i]] = $m[1][$i];
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Receipt n.'))} +(\d{5,}) +/", $plainText, $m)) {
            $receiptNums = array_unique($m[1]);
        }

        foreach ($segments as $value) {
            unset($t);
            $value = trim($value);

            if (!empty($value) && preg_match("/" . $this->opt($this->t('Offerta - Servizio')) . "/", $value)) {
                $type = 'train';

                if (preg_match("/[ ]{2}{$this->opt($this->t('Autobus'))}(?:\s*\/\s*[^:]+)?:.+/", $value)
                    && !preg_match("/[ ]{2}{$this->opt($this->t('Treno'))}(?:\s*\/\s*[^:]+)?:.+/", $value)
                ) {
                    // it-96480036.eml
                    $type = 'bus';
                }

                foreach ($email->getItineraries() as $it) {
                    if ($type === $it->getType() && !empty(array_intersect_key($this->fileIdsAll[$it->getId()], array_merge($fileIds, $receiptNums)))) {
                        $t = $it;

                        break;
                    }
                }

                if (!isset($t)) {
                    if ($type === 'bus') {
                        $t = $email->add()->bus();
                    } else {
                        $t = $email->add()->train();
                    }
                }
                $this->fileIdsAll[$t->getId()] = array_merge($fileIds, $receiptNums);

                foreach ($fileIds as $num => $name) {
                    if (!empty($num) && !in_array($num, array_column($t->getConfirmationNumbers(), 0))) {
                        $t->general()->confirmation($num, $name);
                    }
                }

                $s = $t->addSegment();

                foreach ($this->t('Importo totale') as $phrase) {
                    $price = $this->findCutSection($value, $phrase, $this->t('DETTAGLIO PASSEGGERI'));

                    if (!empty($price)) {
                        break;
                    }
                }

                if (empty($price)) {
                    $price = $this->findCutSection($value, $this->t('Importo pagato totale'), $this->t('Offerta - Servizio'));
                }
                $this->parseTotalCharge($t, $price);
                $this->parsePassengers($t, $this->findCutSection($value, $this->t('DETTAGLIO PASSEGGERI'), $this->t('Altri dati')));
                $this->iterationSegments($s, $value);

                foreach ($t->getSegments() as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                    $s->getSeats())));
                            }
                            $t->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }
    }

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

    private function iterationSegments($segment, $value): void
    {
        /** @var \AwardWallet\Schema\Parser\Common\TrainSegment $segment */
        $tablePos = [0];

        if (preg_match("/^(.+?[ ]{2}){$this->opt($this->t('Stazione di arrivo'))}/miu", $value, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+?[ ]{2})(?:{$this->opt($this->t('Treno'))}|{$this->opt($this->t('Servizio'))}|{$this->opt($this->t('Carrozza'))})/m", $value, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($value, $tablePos);

        if (count($table) !== 3) {
            $this->logger->alert('Wrong table in segment!');

            return;
        }

        $trainType = null;

        if (preg_match("/^[ ]*(?:{$this->opt($this->t('Treno'))}|{$this->opt($this->t('Autobus'))})(?:\s*\/\s*[^:]+)?:\s*([\s\S]+?)\n+[ ]*{$this->opt($this->t('Servizio'))}/m", $table[2], $m)) {
            $m[1] = preg_replace('/\s+/', ' ', $m[1]);

            if (preg_match("/^(.{2,}?)\s+([-A-Z\d]+)$/s", $m[1], $m2)) {
                $trainType = $m2[1];
                $segment->extra()->type($m2[1])->number($m2[2]);
            } else {
                $trainType = $m[1];
                $segment->extra()->type($m[1])->noNumber();
            }
        }

        $dateDep = $dateArr = null;

        // DepName
        if (preg_match("/^[ ]*{$this->opt($this->t('Stazione di partenza'))}\n+(?<name>.{3,}?)\n+(?:{$this->opt($this->t('Ore'))}|(?<date>\d{1,2}\/\d{1,2}\/\d{2,4}))/imsu", $table[0], $m)) {
            $m['name'] = preg_replace('/\s+/', ' ', $m['name']);
            It6132072::assignRegion($m['name'], $trainType);
            $segment->departure()
                ->name($m['name'])
                ->geoTip(It6132072::$region)
            ;

            if (!empty($m['date'])) {
                $dateDep = $m['date'];
            }
        }

        // ArrName
        if (preg_match("/^[ ]*{$this->opt($this->t('Stazione di arrivo'))}\n+(?<name>.{3,}?)\n+(?:{$this->opt($this->t('Ore'))}|(?<date>\d{1,2}\/\d{1,2}\/\d{2,4}))/imsu", $table[1], $m)) {
            $m['name'] = preg_replace('/\s+/', ' ', $m['name']);
            It6132072::assignRegion($m['name'], $trainType);
            $segment->arrival()
                ->name($m['name'])
                ->geoTip(It6132072::$region)
            ;

            if (!empty($m['date'])) {
                $dateArr = $m['date'];
            }
        }

        $patterns['timeDate'] = "/{$this->opt($this->t('Ore'))}(?:\s*\/\s*\w+)?\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s+-\s+(.+?)(?:\s{2,}|$)/u";

        // DepDate
        if (preg_match($patterns['timeDate'], $table[0], $m)) {
            $segment->departure()
                ->date(strtotime($this->normalizeDate($m[2]) . ' ' . $m[1]));
        } elseif ($dateDep) {
            $segment->departure()->date(strtotime($this->normalizeDate($dateDep)));
        }

        // ArrDate
        if (preg_match($patterns['timeDate'], $table[1], $m)) {
            $segment->arrival()
                ->date(strtotime($this->normalizeDate($m[2]) . ' ' . $m[1]));
        } elseif (!empty($segment->getDepDate()) && $dateArr && strtotime($this->normalizeDate($dateArr)) === $segment->getDepDate()) {
            $segment->arrival()->noDate();
        } elseif ($dateArr) {
            $segment->arrival()->date(strtotime($this->normalizeDate($dateArr)));
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Servizio'))}(?:\s*\/\s*[^:]+)?:\s*([\s\S]+?)\n *(?:{$this->opt($this->t('Carrozza'))}|VIA:|KM:|\n)/", $table[2], $m)) {
            $segment->extra()->cabin(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Carrozza'))}(?:\s*\/\s*[^:]+)?:\s*(\d+)$/m", $table[2], $m)) {
            $segment->extra()->car($m[1]);
        }

        $seats = array_filter(explode(",", $this->re('#' . $this->opt($this->t('Posti')) . ':\s+(.+?)\n#u', $value)));
        $segment->extra()
            ->seats($seats);
    }

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\/(\d+)\/(\d+)\s*$#",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = (float) str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
