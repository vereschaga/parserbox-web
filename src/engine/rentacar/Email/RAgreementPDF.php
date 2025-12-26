<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// for HTML use parser national/RentalAgreement

class RAgreementPDF extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-5214204.eml, rentacar/it-59207681.eml, rentacar/it-72511686.eml, rentacar/it-73024136.eml";

    public $langDetect = [
        "de"  => "Mietbeginn",
        "es"  => "Vehículo",
        "fr"  => "Véhicule",
        "en"  => "Pick up",
        "en2" => "Vehicle",
    ];

    public static $dictionary = [
        "de" => [
            "Confirmation"  => "MV-Nr.",
            "Location"      => "Standort",
            "Pick up"       => "Mietbeginn",
            "Start Charges" => "Gebührenstart",
            "Return"        => "Rückgabe",
            "Renter"        => "Mieter",
            "Vehicle"       => "Fahrzeug",
            //"Make" => "",
            //"Model" => "",
            "Total Estimated Charge:" => "Geschätzte Gesamtgebühren:",
        ],
        "es" => [
            "Confirmation"  => "Núm. de contrato de alquiler",
            "Location"      => "Oficina",
            "Pick up"       => "Recogida",
            "Start Charges" => "Cargos",
            "Return"        => "Devolución anticipada",
            "Renter"        => "Arrendatario",
            "Vehicle"       => "Vehículo",
            //            "Make" => "",
            //            "Model" => "",
            "Total Estimated Charge:" => "Cargo total estimado:",
        ],
        "fr" => [
            "Confirmation"  => "N° RA",
            "Location"      => "Agence",
            "Pick up"       => "Prise en charge",
            "Start Charges" => "Frais",
            "Return"        => "Retour anticipé",
            "Renter"        => "Locataire",
            "Vehicle"       => "Véhicule",
            //            "Make" => "",
            //            "Model" => "",
            "Total Estimated Charge:" => "Frais totaux estimés:",
        ],
        "en" => [
            "Confirmation" => "RA#",
            //"Location" => "",
            //"Pick up" => "",
            //"Start Charges" => "",
            //"Return" => "",
            //"Renter" => "",
            //"Vehicle" => "",
            //"Make" => "",
            //"Model" => "",
            //"Total Estimated Charge:" => ""
        ],
    ];

    public $lang = 'en';
    public $text;

    /** @var \HttpBrowser */
    public $pdf;

    private static $providers = [
        'alamo' => [
            'from'          => ['@goalamo.com'],
            'bodyHTML-prov' => [
                'Alamo',
            ],
            'bodyPDF-prov'   => [],
            'bodyPDF-format' => [
                'es' => ['Resumen Contrato Alquiler', 'Núm. de contrato de alquiler'],
                'fr' => ['Contrat de location ', 'N° RA:'],
            ],
            'subject' => [
                'en' => 'Alamo Rental Agreement',
                'fr' => 'Contrat de location Alamo',
            ],
        ],

        'rentacar' => [
            'from'          => ['@enterprise.com', 'www.enterprise.com'],
            'bodyHTML-prov' => [
                'Enterprise',
            ],
            'bodyPDF-prov' => [
                'Owner: ENTERPRISE RENT-A-CAR',
            ],
            'bodyPDF-format' => [
                'en' => ['Rental Agreement Summary', 'RA#'],
            ],
            'subject' => [
                'en' => 'Enterprise Rental Agreement',
            ],
        ],

        // last always!
        'national' => [
            'from'          => ['@nationalcar.com'],
            'bodyHTML-prov' => [
                'National',
            ],
            'bodyPDF-prov'   => [],
            'bodyPDF-format' => [
                'en' => ['Rental Agreement Summary', 'RA#'],
                'de' => ['Geschätzte Gesamtgebühren', 'MV-Nr.'],
            ],
            'subject' => [
                'en' => 'National Rental Agreement',
                'de' => 'National Rental Agreement',
            ],
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?',
        'phone' => '[+(\d][-. \d)(]{5,}[\d)]',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName("*.*pdf");

        if (count($pdfs) > 0) {
            $this->pdf = clone $this->http;
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }
            $NBSP = chr(194) . chr(160);
            $textPdf = str_replace($NBSP, ' ', html_entity_decode($textPdf));

            if (!$this->assignLang($textPdf)) {
                continue;
            }

            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }

            $htmlPdf = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));
            $this->pdf->SetEmailBody($htmlPdf);
            $this->parseCar($email, $textPdf, $parser->getCleanFrom());
        }

        $email->setType('RAgreementPDF' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('*.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
            $NBSP = chr(194) . chr(160);
            $text = str_replace($NBSP, ' ', html_entity_decode($text));

            $detectProv = $detectFormat = false;

            foreach (self::$providers as $option) {
                foreach ($option['bodyHTML-prov'] as $phrase) {
                    if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                        $detectProv = true;
                    }
                }

                foreach ($option['bodyPDF-format'] as $list) {
                    if (preg_match('/' . preg_quote($list[0], '/') . '/iu', $text)
                        && preg_match('/' . preg_quote($list[1], '/') . '/iu', $text)
                    ) {
                        $detectFormat = true;
                    }
                }
            }

            if (!$detectProv) {
                foreach (self::$providers as $option) {
                    foreach ($option['bodyPDF-prov'] as $phrase) {
                        if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/', $text)) {
                            $detectProv = true;
                        }
                    }
                }
            }

            if (!$detectProv && $this->detectEmailFromProvider($parser->getHeader('from'))) {
                $detectProv = true;
            }

            if ($detectProv && $detectFormat) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $option) {
            foreach ($option['subject'] as $subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $option) {
            foreach ($option['from'] as $reFrom) {
                if (stripos($from, $reFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseCar(Email $email, string $textPdf, ?string $from): void
    {
        $r = $email->add()->rental();
        $r->setProviderCode($this->getProvider($textPdf, $from));

        $r->general()
            ->confirmation($this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]", null, true, "/{$this->opt($this->t('Confirmation'))}\s*:\s*([-A-Z\d]+)$/"));

        $pickupDate = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Pick up'))})][1]", null, true, "/^(.{6,}?)(?:\s*{$this->opt($this->t('Start Charges'))}|$)/u");
        $pickupDatetime = $this->normalizeDate($pickupDate);

        if (!empty($pickupDatetime)) {
            $r->pickup()
                ->date($pickupDatetime);
        }

        $pattern = "/^"
            . "(?<location>[\s\S]{3,}?)"
            . "(?:\n(?<phone>{$this->patterns['phone']}))?"
            . "$/";

        $node = implode("\n", $this->pdf->FindNodes("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Pick up'))})][2]/descendant::text()"));

        if (preg_match($pattern, $node, $m)) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $m['location']));

            if (!empty($m['phone'])) {
                $r->pickup()->phone($m['phone']);
            }
        }

        $dropoffDate = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Return'))})][3]", null, true, '/(\w+, \w+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}\s+[ap]m)/i');

        if (empty($dropoffDate)) {
            $dropoffDate = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Return'))})][4]");
        }
        $r->dropoff()
            ->date($this->normalizeDate($dropoffDate));

        $node = $this->re('/([\s\S]{3,}?)\s*(?:(\(\d+.+)|$)/', implode("\n", $this->pdf->FindNodes("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Return'))})][4]/descendant::text()")));

        if (empty($node) || preg_match("/{$this->patterns['time']}/", $node) > 0) {
            $node = implode("\n", $this->pdf->FindNodes("//p[{$this->starts($this->t('Location'))}]/following::p[not({$this->contains($this->t('Return'))})][5]/descendant::text()"));
        }

        if (preg_match($pattern, $node, $m)) {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']));

            if (!empty($m['phone'])) {
                $r->dropoff()->phone($m['phone']);
            }
        }

        $pickupLocation = $this->http->FindSingleNode("//tr[{$this->contains($this->t('Location'))} and not(descendant::tr)]/following::tr[normalize-space(.)][1]/td[2]/descendant::node()[1]");

        if (!empty($pickupLocation) && stripos($r->getPickUpLocation(), $pickupLocation) === false) {
            $r->pickup()->location($pickupLocation . ', ' . $r->getPickUpLocation());
        }

        $dropoffLocation = $this->http->FindSingleNode("//tr[{$this->contains($this->t('Location'))} and not(descendant::tr)]/following::tr[normalize-space(.)][2]/td[2]/descendant::node()[1]");

        if (!empty($dropoffLocation) && stripos($r->getDropOffLocation(), $dropoffLocation) === false) {
            $r->dropoff()->location($dropoffLocation . ', ' . $r->getDropOffLocation());
        }

        $r->general()
            ->traveller($this->pdf->FindSingleNode("(//text()[{$this->starts($this->t('Renter'))}])[1]", null, true, "#{$this->t('Renter')}\s*:\s*(.+)#i"));

        $carModel = $this->pdf->FindSingleNode("//*[{$this->eq($this->t('Vehicle'))}]/following::text()[normalize-space(.)][1]", null, true, "#(?:{$this->t('Make')}\s*\/\s*{$this->t('Model')}\s*:)?\s*(.+)#i");

        if (empty($carModel)) {
            $carModel = $this->pdf->FindSingleNode("//*[{$this->eq($this->t('Pick up'))}][1]/following::*[{$this->eq($this->t('Vehicle'))}][1]/following::text()[normalize-space(.)][1]", null, true, "#(?:{$this->t('Make')}\s*\/\s*{$this->t('Model')}\s*:)?\s*(.+)#i");
        }
        $r->car()
            ->model($carModel);

        $node = $this->pdf->FindSingleNode("//*[{$this->starts($this->t('Total Estimated Charge:'))}]/following::text()[string-length(normalize-space(.))>3][1]");

        if ($this->re("/^([\d\.\,]+)$/", $node) !== '0,00' && $this->re("/^([\d\.\,]+)$/", $node) !== '0.00') {
            if (!empty($this->re("/^([\d\.\,]+)$/", $node))) {
                $nodeCurrency = $this->pdf->FindSingleNode("//*[{$this->starts($this->t('Total Estimated Charge:'))}]/following::text()[string-length(normalize-space(.))>=1][1]");
                $node = $nodeCurrency . $node;
            }
            $tot = $this->getTotalCurrency($node);

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        //fee and cost for en only, for any lang no examples
        $feesText = $this->cutText("Charges", "Deposit", $textPdf);

        if (!empty($feesText)) {
            $feesTable = $this->splitCols($feesText, $this->ColsPos($this->re("/(Total Estimated Charge:.+)/", $feesText)));
            $feeName = explode("\n", $feesTable[0]);
            $countRowsFee = count($feeName);
            $feeSum = explode("\n", $feesTable[1]);
            $feesText = '';

            for ($i = 0; $i <= $countRowsFee - 1; $i++) {
                $feesText .= $feeName[$i] . '-' . preg_replace("/[ ]{2,}\S.*/", '', $feeSum[$i]) . "\n";
            }

            if (preg_match_all("/([-.%&.\/)(A-Z\d\s]+:.+-.+)/", $feesText, $feeMatches)) {
                foreach ($feeMatches[1] as $match) {
                    $name = preg_replace('/\s+/', ' ', $this->re("/([-.%&.\/)(A-Z\d\s]+):/s", $match));
                    $summ = $this->re("/[-.%&.\/)(A-Z\d\s]+:.+-(.+)/s", $match);

                    if (!empty($this->re("/(TIME & DISTANCE)/", $name))) {
                        $cost = $this->re("/([\d\.]+)/", $summ);

                        if (!empty($cost) && $cost !== '0.00') {
                            $r->price()
                                ->cost($cost);
                        }

                        continue;
                    }

                    $name = preg_replace(['/-/', '/\s+/'], ['', ' '], $name);
                    $summ = $this->re("/([\d\.]+)/", $summ);

                    if (!empty($summ) && $summ !== '0.00') {
                        $r->price()
                            ->fee($name, $summ);
                    }
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            // Saturday, February 15, 2020 11:30 PM
            "/^[-[:alpha:]]+[,.\s]+([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})[,.\s]+({$this->patterns['time']})$/u",
            // Sonntag, 22. März 2020 20:19; miércoles 16 de diciembre de 2020 17:00
            "/^[-[:alpha:]]+[,.\s]+(\d{1,2})(?:\s+de)?[,.\s]+([[:alpha:]]+)(?:\s+de)?[,.\s]+(\d{4})[,.\s]+({$this->patterns['time']})$/u",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function getProvider(string $textPdf, ?string $from = null): ?string
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['from'] as $phrase) {
                if (strpos($from, $phrase) !== false) {
                    return $prov;
                }
            }

            foreach ($option['bodyHTML-prov'] as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return $prov;
                }
            }
        }

        foreach (self::$providers as $prov => $option) {
            foreach ($option['bodyPDF-prov'] as $phrase) {
                if (strpos($textPdf, $phrase) !== false) {
                    return $prov;
                }
            }
        }

        return null;
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
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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

    private function assignLang(string $text): bool
    {
        foreach ($this->langDetect as $lang => $option) {
            if (strpos($text, $option) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
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

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
