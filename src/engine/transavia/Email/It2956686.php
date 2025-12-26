<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2956686 extends \TAccountChecker
{
    public $mailFiles = "transavia/it-147569076.eml, transavia/it-152340684.eml, transavia/it-2956686.eml, transavia/it-2956774.eml, transavia/it-2957489.eml, transavia/it-2996545.eml, transavia/it-3477172.eml, transavia/it-3749137.eml, transavia/it-4004087.eml, transavia/it-4004110.eml, transavia/it-4004321.eml, transavia/it-4044695.eml, transavia/it-5090734.eml, transavia/it-6400545.eml, transavia/it-6400549.eml, transavia/it-6546118.eml, transavia/it-7193322.eml, transavia/it-7480993.eml, transavia/it-7611672.eml, transavia/it-7922522.eml";

    public $reBody = [
        'de' => ['Buchungsdetails', 'Ihre Buchungsbestätigung'],
        'it' => ['Dettagli della prenotazione', 'il suo biglietto elettronico', 'la tua conferma di prenotazione'],
        'fr' => ['Numéro de réservation', 'Votre avis sur cet e-mail nous'],
        'nl' => ['Passagiers', 'Bedankt voor je boeking', 'Dit is een automatisch gegenereerde e-mail'],
        'es' => ['Datos de la reserva', 'Fecha de reserva', 'tu billete con toda la información'],
        'pt' => ['Detalhes da reserva', 'confirmação de reserva'],
        'en' => ['Booking'],
    ];

    public $reSubject = [
        'de' => ['Buchungsbestätigung'],
        'it' => ['conferma della prenotazione'],
        'fr' => ['Transavia référence', 'Confirmation de'],
        'nl' => ['boekingsbevestiging', 'Boekingsnummer'],
        'es' => ['confirmacion de la reserva'],
        'pt' => ['confirmacao de reserva'],
        'en' => ['booking confirmation'],
    ];

    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;

    public static $dictionary = [
        'de' => [
            'RecordLocator'    => 'Buchungsnummer',
            'Booking Date'     => ['Reservierungsdatum', 'Buchungsdatum'],

            'Total'            => 'Gesamt',
            'Depart'           => 'Hinflug',
            'Return'           => 'Rückflug',
            'Flight number'    => 'Flugnummer',
            'Date'             => 'Datum',
            'Arrival time'     => 'Ankunftszeit',
            'seat reservation' => ['Sitzplatzreservierung'],
            'seat'             => ['Seat', 'Platz', 'Sitzplatz'],
        ],
        'it' => [
            'RecordLocator'    => ['Numero di prenotazione', 'Numero della prenotazione'],
            'Total'            => 'Totale',
            'Depart'           => 'Vola di andata',
            'Return'           => 'Volo di ritorno', // need change
            'Flight number'    => 'Numero del volo',
            'Date'             => 'Data',
            'Arrival time'     => 'Orario di arrivo',
            'Booking Date'     => 'Data della prenotazione',
            'seat reservation' => ['Prenotazione del posto'],
            'seat'             => ['Seat', 'Posto', 'posto'],
        ],
        'fr' => [
            'RecordLocator'    => 'Numéro de réservation',
            'Total'            => 'Total',
            'Depart'           => 'Vol aller',
            'Return'           => 'Vol retour',
            'Flight number'    => 'Numéro de vol',
            'Date'             => 'Date',
            'Arrival time'     => 'Heure d\'arrivée',
            'Booking Date'     => 'Date de réservation',
            'seat reservation' => ['Réservation de siège'],
            'seat'             => ['Seat', 'siège'],
        ],
        'nl' => [
            'RecordLocator'    => 'Boekingsnummer',
            'Total'            => 'Totaal',
            'Depart'           => 'Heenvlucht',
            'Return'           => 'Terugvlucht',
            'Flight number'    => 'Vluchtnummer',
            'Date'             => 'Datum',
            'Arrival time'     => 'Aankomsttijd',
            'Booking Date'     => 'Boekingsdatum',
            'seat reservation' => ['Stoelreservering', 'Stoel reservering'],
            'seat'             => ['Seat', 'Stoel', 'zitplaats'],
        ],
        'es' => [
            'RecordLocator'    => 'Número de reserva',
            'Total'            => 'Total',
            'Flight number'    => 'Número de vuelo',
            'Date'             => 'Fecha',
            'Arrival time'     => 'Hora de llegada',
            'Depart'           => 'Destino',
            'Return'           => 'Destino',
            'Booking Date'     => 'Fecha de reserva',
            'seat reservation' => ['Prenotazione del posto'], // check, same as it
            'seat'             => ['Seat', 'Asientos'],
        ],
        'pt' => [
            'RecordLocator'    => 'Número da reserva',
            'Total'            => 'Total',
            'Flight number'    => 'Número do voo',
            'Date'             => 'Data',
            'Arrival time'     => 'Hora de chegada',
            'Depart'           => 'Afastar',
            'Return'           => 'Retorna',
            'Booking Date'     => ['Data de reserva', 'Data da reserva'],
            'seat reservation' => ['Reserva de assento', 'Reserva de lugar'],
            'seat'             => ['Seat', 'assento', 'lugar'],
        ],
        'en' => [
            'RecordLocator'    => 'Booking number',
            'Total'            => 'Total',
            'Depart'           => 'Outbound flight',
            'Return'           => 'Inbound flight', // need change
            'Flight number'    => 'Flight number',
            'Date'             => 'Date',
            'Arrival time'     => 'Arrival time',
            'Booking Date'     => 'Booking date',
            'seat reservation' => ['Reserved seat'],
            'seat'             => ['Seat'],
        ],
    ];

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[" . $this->starts($this->t('RecordLocator')) . "]/following-sibling::tr[normalize-space(.)][1]",
                null, true, '/^\s*([-A-Z\d]{5,})\s*$/'));
        // Price
        $r = $this->getTotalCurrency(preg_replace("/\s+/", ' ',
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/ancestor::table[1]/following-sibling::table[1]")));

        if (!empty($r['Total'])) {
            $f->price()
                ->total($r['Total'])
                ->currency($r['Currency']);
        }

        $xpath = "//text()[" . $this->eq($this->t("Depart")) . " or " . $this->eq($this->t("Depart")) . "]/ancestor::tr[2]/following-sibling::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->noName()
                ->noNumber();

            // Route
            $routes = explode(' - ', $this->http->FindSingleNode(".", $root));

            if (count($routes) === 2) {
                // Departure
                if (preg_match("/^\s*([A-Z]{3})\s*$/", $routes[0])) {
                    $s->departure()
                        ->code($routes[0]);
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($routes[0]);
                }
                // Arrival
                if (preg_match("/^\s*([A-Z]{3})\s*$/", $routes[1])) {
                    $s->arrival()
                        ->code($routes[1]);
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($routes[1]);
                }
            }

            // Dates
            $s->departure()
                ->noDate()
                ->day(strtotime($this->http->FindSingleNode("following-sibling::tr[1]", $root)));
            $s->arrival()
                ->noDate();
        }
    }

    public function parsePdf(Email $email, $pdfText)
    {
//        $this->logger->debug('$pdfText = '.print_r( $this->pdf->Response['body'],true));

        $f = $email->add()->flight();

        // General
        $conf = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t("RecordLocator"))}]",
            null, true, "/{$this->preg_implode($this->t("RecordLocator"))} +([-A-Z\d]+)(?:\s+.*)?\s*$/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t("RecordLocator"))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([-A-Z\d]+)\s*$/u");
        }
        $f->general()
            ->confirmation($conf)
        ;
        $travellers = array_unique(array_filter($this->pdf->FindNodes("//text()[contains(., '.') and contains(., '(') and contains(., ')')]",
            null, "/^\s*(?:M[RSI]+|CHD)\s*\.\s+(.+?)\s*\([\s\d\-\\/]+\)/iu")));

        if (empty($travellers)) {
            $travellers = array_unique(array_filter($this->pdf->FindNodes("//text()[contains(., '.')]",
                null, "/^\s*(?:M[RSI]+|CHD)\s*\.\s+([[:alpha:]]+(?: [[:alpha:]\-]+){1,6})\s*/iu")));
        }
        $f->general()
            ->travellers($travellers);

        $f->general()
            ->date(strtotime($this->pdf->FindSingleNode("//text()[{$this->starts($this->t("Booking Date"))}]",
                null, true, "#{$this->preg_implode($this->t('Booking Date'))}\s+(\d{2}[- ]*\d{2}[- ]*\d{4})#")))
        ;

        // Price
        $totalStr = $this->http->FindSingleNode("//*[{$this->eq($this->t("Total"))}]/ancestor::table[1]/following-sibling::table[1]");

        if (!empty($totalStr)) {
            if (preg_match("/Award Miles: *(\d+[\d\.]*)\b/", $totalStr, $m)) {
                $f->price()
                    ->spentAwards($m[1]);
                $totalStr = preg_replace("/\s*Award Miles:\s*.*\s*$/", '', $totalStr);
            }

            if (preg_match("/^\s*(?<currency>[^\d\s]\D{0,3})\s*(?<total>\d[\d., ]*)\s*$/", $totalStr, $m)
                    || preg_match("/^\s*(?<total>\d[\d., ]*)\s*(?<currency>[^\d\s]\D{0,3})\s*$/", $totalStr, $m)) {
                $total = $m['total'];
                $currency = $m['currency'];
            }
        }

        if (empty($total)) {
            $totalStrs = $this->pdf->FindNodes("//node()[{$this->eq($this->t("Total"))}]/following-sibling::node()[normalize-space(.)][position() < 4]");

            if (preg_match("/Award Miles: *(\d+[\d\.]*)\s*$/", implode(" ", $totalStrs), $m)) {
                $f->price()
                    ->spentAwards($m[1]);
                $totalStrs = preg_replace("/\s*Award Miles:\s*.*\s*$/", '', $totalStrs);
            }

            if (preg_match("/^\s*(?<currency>[^\d\s]\D{0,3})\s*(?<total>\d[\d., ]*)\s*$/", $totalStrs[0] ?? '', $m)
                || preg_match("/^\s*(?<total>\d[\d., ]*)\s*(?<currency>[^\d\s]\D{0,3})\s*$/", $totalStrs[0] ?? '', $m)) {
                $total = $m['total'];
                $currency = $m['currency'];
            } elseif (preg_match("/^\s*(?<currency>[^\d\s]\D{0,3})\s*$/", $totalStrs[0] ?? '', $m1)
                && preg_match("/^\s*(?<total>\d[\d., ]*)\s*$/", $totalStrs[1] ?? '', $m2)) {
                $total = $m2['total'];
                $currency = $m1['currency'];
            } elseif (preg_match("/^\s*(?<total>\d[\d., ]*)\s*$/", $totalStrs[0] ?? '', $m1)
                && preg_match("/^\s*(?<currency>[^\d\s]\D{0,3})\s*$/", $totalStrs[1] ?? '', $m2)) {
                $total = $m1['total'];
                $currency = $m2['currency'];
            }
        }

        if (isset($total, $currency)) {
            $f->price()
                ->total(PriceHelper::parse($total))
                ->currency($this->currency($currency));
        }

        $seats = [];
        $seatsXpath = "//p[" . $this->contains($this->t('seat')) . "]";
        $sNodes = $this->pdf->XPath->query($seatsXpath);

        foreach ($sNodes as $sroot) {
            $value = $this->pdf->FindSingleNode(".", $sroot, true, "/^[-\s]*" . $this->preg_implode($this->t('seat')) . "\s+(\d{1,3}[A-Z])\s*$/");

            if (!empty($value)) {
                $left = $this->pdf->FindSingleNode("./@style", $sroot, true, "#left:(\d+)px;#");
                $fs = array_intersect_key($seats, array_fill_keys(range($left - 20, $left + 20), ''));

                if (!empty($fs)) {
                    $left = array_key_first($fs);
                }
                $seats[$left][] = $value;
            }
        }

        ksort($seats);

        if (count($seats) < 3) {
            $seats = array_values($seats);
        } else {
            $seats = [];
        }

        // Segments
        $table = $this->tablePdf();

        if (empty($table)) {
            return false;
        }

        $count = count($this->pdf->FindNodes("//*[{$this->eq($this->t("Flight number"))}]"));

        if ($count == 1) {
            $segments[] = $table;
        } elseif ($count == 2 && count($table['header']) == 4 && count($table['body']) == 4) {
            $segments[] = ['header' => [$table['header'][0], $table['header'][1]], 'body' => [$table['body'][0], $table['body'][1]]];
            $segments[] = ['header' => [$table['header'][2], $table['header'][3]], 'body' => [$table['body'][2], $table['body'][3]]];
        } else {
            return false;
        }

        foreach ($segments as $i => $seg) {
//            $this->logger->debug('$seg = '.print_r( $seg,true));

            $s = $f->addSegment();

            // Airline
            if (preg_match("/^\s*\S.+\n\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s+/", $seg['body'][0] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $date = $this->re("/{$this->preg_implode($this->t('Date'))}\s+(.+)/", $seg['body'][1] ?? '');
            $route = ($i == 0) ? $this->t("Depart") : $this->t("Return");

            // Departure
            $code = $this->http->FindSingleNode("//*[{$this->eq($route)}]/ancestor::tr[2]/following-sibling::tr[1]", null, true, "#\b([A-Z]{3})\b.*?\b[A-Z]{3}\b#");
            $name = $seg['header'][0];
            $s->departure()
                ->name($name);

            if (!empty($code)) {
                $s->departure()
                    ->code($code);
            } elseif (!empty($name)) {
                $s->departure()
                    ->noCode();
            }
            $time = $this->re("/^(?:\s*\S.+\n+){3}\s*(\d{1,2}\D\d{2}.*)/", $seg['body'][0] ?? '');

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . str_replace('h', ':', $time)));
            }

            // Arrival
            $code = $this->http->FindSingleNode("//*[{$this->eq($route)}]/ancestor::tr[2]/following-sibling::tr[1]", null, true, "#\b[A-Z]{3}\b.*?\b([A-Z]{3})\b#");
            $name = preg_replace("/(.+?)\s+via\s+.+/", '$1', $seg['header'][1]);
            $s->arrival()
                ->name($name);

            if (!empty($code)) {
                $s->arrival()
                    ->code($code);
            } elseif (!empty($name)) {
                $s->arrival()
                    ->noCode();
            }
            $time = $this->re("/^(?:\s*\S.+\n+){3}\s*(\d{1,2}\D\d{2}.*)/", $seg['body'][1] ?? '');

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($date . ', ' . str_replace('h', ':', $time)));
            }

            if (!empty($seats[$i])) {
                $s->extra()
                    ->seats($seats[$i]);
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*?\.pdf');

        if (!empty($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (stripos($body, 'transavia') === false) {
                return false;
            }

            return $this->assignLang($body);
        } else {
            $body = $parser->getHTMLBody();

            if ($this->http->XPath->query('//node()[contains(.,"www.transavia.com")] | //a[contains(@href,"transavia.com/")]')->length === 0) {
                return false;
            }

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/[.@]transavia\.com/', $headers['from']) !== 1 && preg_match('/^\s*Transavia/i', $headers['from']) !== 1) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]transavia\.com/', $from)
            || preg_match('/^\s*Transavia/i', $from);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*? [A-Z\d]+\.pdf');

        $pdfText = '';

        if (isset($pdfs[0]) && ($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_COMPLEX)) !== null) {
            $html = str_replace(['&#160;', '&nbsp;', '  '], ' ', $html);
            $html = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $html);
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($html);

            $this->assignLang($html);

            $type = 'Pdf';
            $this->parsePdf($email, $pdfText);
        } else {
            $this->http->FilterHTML = false;

            $body = $parser->getHTMLBody();

            if (empty($body)) {
                $body = $parser->getPlainBody();
                $this->http->SetBody($body);
            }

            $this->assignLang($body);

            $type = 'Html';

            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ($type ?? '') . ucfirst($this->lang));

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

    protected function assignLang($textBody)
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($textBody, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function tablePdf()
    {
        $table = [];

        $top = $this->pdf->FindSingleNode("(//*[{$this->eq($this->t("Flight number"))}])[1]/@style", null, true, "/top:(\d+)/");
        $bottom = $this->pdf->FindSingleNode("(//*[{$this->eq($this->t("Arrival time"))}])[1]/@style", null, true, "/top:(\d+)/");

        if (!empty($top) && !empty($bottom)) {
            $top -= 100;
            $bottom += 100;

            $leftsHeader = [];
            $leftsBody = [];
            $nodes = $this->pdf->XPath->query("(//*[{$this->eq($this->t("Flight number"))}])[1]/ancestor::div[1]/*[normalize-space()]");

            foreach ($nodes as $node) {
                $topValue = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");

                if ($topValue < $top || $topValue > $bottom) {
                    continue;
                }

                $text = $this->pdf->FindHTMLByXpath(".", null, $node);
                $text = str_ireplace(["<br/>", "<br>"], "\n", $text);
                $text = strip_tags($text);

                $tv = array_intersect_key($grid ?? [], array_fill($topValue - 10, 20, ''));

                if (count($tv) === 1) {
                    $topValue = array_key_first($tv);
                }

                $leftValue = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");

                if ($topValue < $top + 100 - 10) {
                    $fs = array_intersect_key($leftsHeader, array_fill_keys(range($leftValue - 20, $leftValue + 20), ''));

                    if (empty($fs)) {
                        $leftsHeader[$leftValue] = null;
                    }
                } else {
                    $fs = array_intersect_key($leftsBody, array_fill_keys(range($leftValue - 20, $leftValue + 20), ''));

                    if (empty($fs)) {
                        $leftsBody[$leftValue] = null;
                    }
                }

                if (!empty($fs)) {
                    $l = array_key_first($fs);

                    if (!empty($l)) {
                        $leftValue = $l;
                    }
                }

                $grid[$topValue][$leftValue] = $text;
            }

            ksort($grid);
            $columnsH = array_keys($leftsHeader);
            $columnsB = array_keys($leftsBody);
            sort($columnsH);
            sort($columnsB);
            $columnsH = array_values($columnsH);
            $columnsB = array_values($columnsB);

            $table = [];

            foreach ($columnsH as $i => $col) {
                $table['header'][$col] = '';
            }

            foreach ($columnsB as $i => $col) {
                $table['body'][$col] = '';
            }

            foreach ($grid as $row => $c) {
                ksort($c);

                if ($row < $top + 100 - 10) {
                    foreach ($c as $i => $row) {
                        $table['header'][$i] .= "\n" . $row;
                    }
                } else {
                    foreach ($c as $i => $row) {
                        $table['body'][$i] .= "\n" . $row;
                    }
                }
            }

            $table['header'] = array_values($table['header']);
            $table['body'] = array_values($table['body']);
        }

        return $table;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("â¬", "EUR", $node); //xz why so it-2956686.eml
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
