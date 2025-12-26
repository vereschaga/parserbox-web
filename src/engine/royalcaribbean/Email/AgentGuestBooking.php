<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: royalcaribbean/It2, celebritycruises/InvoiceAgentGuestPdf, princess/Itinerary, mta/POCruisesPdf

class AgentGuestBooking extends \TAccountChecker
{
    public $mailFiles = "royalcaribbean/it-33762534.eml, royalcaribbean/it-33935402.eml, royalcaribbean/it-38784253.eml";
    public $reFrom = "@rccl.com";
    public $reSubject = [
        "en" => "Guest Invoice for Reservation ID:",
        "Booking Offer for Reservation ID:",
    ];
    public $reBody = 'Royal Caribbean';
    public $reBody2 = [
        "en" => ["Guest Copy", "TRAVEL PARTNER COPY"],
        "es" => ["Copia Pasajero"],
    ];

    public static $dictionary = [
        "en" => [
            'guestsStart'           => ['Guest Name', 'First Name:'],
            'Crown & Anchor Number' => ['Crown & Anchor Number', "Captain's Club Number", "Please ensure that your clients"],
            'Charges'               => ['Charges', 'CRUISE FARE'],
        ],
        "es" => [
            'Reservation ID'           => 'Clave de reservación',
            'guestsStart'              => 'Nombre',
            'Crown & Anchor Number'    => 'Número de Crown and Anchor',
            'Age Range'                => 'Edad',
            'Ship'                     => 'Barco',
            'Itinerary'                => 'Itinerario',
            'Stateroom'                => 'Camarote',
            'Sailing Date'             => 'Fecha de salida',
            'Total Charge'             => 'Total a pagar',
            'Charges'                  => 'Importes',
            'Guest'                    => 'Pasajero',
            'Cruise Itinerary'         => 'Itinerario Crucero',
            'Depart'                   => 'Salida',
            'Post Cruise Arrangements' => 'Servicios Post-Crucero',
        ],
    ];

    public $lang = "";
    /** @var \HttpBrowser */
    private $pdf;
    private $pdfPattern = '(?:Guest_COPY|Copia_Cliente|Agent_Copy)(?:\s*-\s*\d+)?.pdf';
    private $date;

    public function parsePdf(Email $email, $textPdf): void
    {
        $text = $this->pdf->Response["body"];

        $r = $email->add()->cruise();

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('Reservation ID'))}:*\s+(\d+)#", $text));

        $pax = [];
        $paxText = $this->re("#{$this->opt($this->t('guestsStart'))}\n(.+?)\n{$this->opt($this->t('Crown & Anchor Number'))}#s", $text);
        $paxText = preg_replace("#(^|\n){$this->opt($this->t('Last Name'))}:*(?:\n|$)#", '$1', $paxText);

        if (!empty($paxText)) {
            $paxArray = array_filter(explode("\n", $paxText));

            if (($cnt = count($paxArray)) % 2 === 0) {
                $div = $cnt / 2;

                for ($i = 0; $i < $div; $i++) {
                    $pax[] = $paxArray[$i] . ' ' . $paxArray[$i + $div];
                }
            }
        }

        if (count($pax) > 0) {
            $r->general()->travellers($pax);
        }

        $accountsText = $this->re("#{$this->opt($this->t('Crown & Anchor Number'))}\n(.+?)\n{$this->opt($this->t('Age Range'))}#s", $text);

        if (empty($accountsText) && count($r->getTravellers())) {
            $accountsText = $this->re("#{$this->opt($this->t('Crown & Anchor Number'))}:*\n((?:\n*.+){" . count($r->getTravellers()) . "})#", $text);
        }

        if (!empty($accountsText)) {
            $accounts = explode("\n", $accountsText);
            $accounts = array_filter($accounts, function ($item) {
                return preg_match('/^\d{5,}$/', $item) > 0;
            });

            if (count($accounts)) {
                $r->program()->accounts($accounts, false);
            }
        }

        $deck = $this->re("#{$this->t('Stateroom')}\s*\n\s*[\w\d-]+\s+(.+)#", $text);

        $r->details()
            ->ship($this->re("#{$this->t('Ship')}:*[ ]*\n[ ]*(.+)#", $text))
            ->description($this->re("#{$this->t('Itinerary')}:*[ ]*\n[ ]*(.+)#", $text))
            ->deck($deck ? preg_replace("/^(.{2,}?)[ ]+{$this->opt($this->t('Sailing Date'))}.*/", '$1', $deck) : null, false, true)
            ->room($this->re("#{$this->t('Stateroom')}\s*\n\s*([\w\d-]+)#", $text)
                ?? $this->re("#{$this->t('Stateroom Number')}:*[ ]*\n[ ]*(\w+)\b#", $text)
            );

        if ($total = $this->re("#{$this->t('Total Charge')}\n(?:[\d.,]+\s*\n){4}([\d.,]+)\s*\n#", $text)) {
            $r->price()
                ->total($this->amount($total))
                ->currency($this->re("#{$this->opt($this->t('Charges'))}\n([A-Z]{3})\n{$this->t('Guest')}[ ]\#1\n#", $text));
        } elseif ($total = $this->re("#{$this->t('TOTAL')}\n([\d.,]+)\s*\n#", $text)) {
            $r->price()
                ->total($this->amount($total))
                ->currency($this->re("#{$this->opt($this->t('Charges'))}\n([A-Z]{3})\n#", $text));
        }

        $itineraryText = $this->re("#{$this->opt($this->t('Cruise Itinerary'))}:*([\s\S]*?){$this->opt($this->t('Post Cruise Arrangements'))}#i", $textPdf)
            ?? $this->re("#{$this->opt($this->t('Cruise Itinerary'))}:*[\s\S]*?(\n[ ]*\d{2} [[:alpha:]]{3}[ ]+\S[\s\S]*\S[ ]{2,}\d{1,2}:\d{2}(?: ?[AP]M)?(?:\n|$))#iu", $textPdf);

        $points = $this->splitter("/^[ ]*(\d{2} [[:alpha:]]{3} )/mu", 'ctrlStr' . $itineraryText);
        $currentState = 'ashore';

        foreach ($points as $point) {
            if (preg_match("/(?<Date>\d{2} [[:alpha:]]{3})[ ]+"
                . "(?<Port>.{2,}?)[ ]{2,}"
                . "(?<Time1>\d+:\d+(?:[ ]*[AaPp][Mm])?)"
                . "(?:[ ]+(?<Time2>\d+:\d+(?:[ ]*[AaPp][Mm])?))?"
                . "/u", $point, $segment)
            ) {
                if (!isset($s) || $s->getName() !== $segment['Port']) {
                    $s = $r->addSegment();
                    $s->setName($segment['Port']);
                }
                $date = strtotime($this->normalizeDate($segment['Date']));
                $time1 = strtotime($segment['Time1'], $date);

                if (!empty($segment['Time2'])) {
                    $s->setAshore($time1);
                    $s->setAboard(strtotime($segment['Time2'], $date));
                    $currentState = 'aboard';
                } else {
                    if ($currentState === 'ashore') {
                        $s->setAboard($time1);
                        $currentState = 'aboard';
                    } else {
                        $s->setAshore($time1);
                        $currentState = 'ashore';
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        if (strpos($body, $this->reBody) === false
            && strpos($body, 'This holiday is provided by RCL Cruises') === false
        ) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $textPdf = $this->sortedPdf($parser);

        if ($textPdf === null) {
            return $email;
        }

        //$this->logger->debug($this->pdf->Response["body"]);
        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($this->pdf->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug('Can\'t determiane a language');

            return $email;
        }

        $this->parsePdf($email, $textPdf);
        $email->setType('AgentGuestBooking' . ucfirst($this->lang));

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function sortedPdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return null;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as &$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->setEmailBody($res);

        return \PDF::convertToText($parser->getAttachmentBody($pdf));
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
