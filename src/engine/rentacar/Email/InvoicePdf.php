<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-46077421.eml, rentacar/it-58065787.eml, rentacar/it-58237709.eml, rentacar/it-168751320.eml";

    public $lang = '';
    public $amount = null;
    public $currency;
    public $pdfPages = 0;

    public static $dictionary = [
        'en' => [
            'Rental Agreement #:'    => ['Rental Agreement #:'],
            'Date/Time Out'          => ['Date/Time Out', 'Date / Time Out'],
            'Thank You For Choosing' => ['Thank You For Choosing Enterprise', 'Thank You For Choosing National Car Rental'],
        ],
        'fr' => [
            'Rental Agreement #:'    => ['Rental Agreement #:'],
            'Date/Time Out'          => ['Date/Time Out', 'Date / Time Out'],
            'Thank You For Choosing' => "Merci d'utiliser National Car Rental",
            'Rental Agreement #:'    => 'No Cntrat locatn :',
            'Bill Ref #:'            => '#ref. fac. :',
            //'Account #:' => '',
            'Description'        => 'Description',
            'Amount'             => 'Montant',
            'RENTAL INFORMATION' => 'Infos sur la location',
            'Renter'             => 'Locataire',
            //'Start Charges' => '',
            'Date/Time In'      => 'Date/H arrivée',
            'Date/Time Out'     => 'Date/H départ',
            'CLAIM INFORMATION' => 'Infos réclamation',
            'RENTAL VEHICLES'   => 'Véhicules location',
            //'VIN' => '',
            'License'        => "N° d'imt",
            'Unit'           => 'N°unt',
            'Model'          => 'mod',
            'BILLING DETAIL' => 'Détails factur.',
            //'PAYMENTS' => '',
            'Total Charges' => 'Somme due ()',
            'Paid By'       => 'Payé par',
            'Remit To'      => "Remettre à",
            'Fed Tax Id'    => 'N° de cpte',
            'Miles/Kms'     => 'Mi/km',
            'In'            => 'Ret',
        ],
    ];

    private static $providers = [
        'rentacar' => [
            'from' => ['@ehi.com'],
            'body' => [
                'Thank You For Choosing Enterprise',
            ],
            'subject' => [
                "en" => 'Enterprise Rent-A-Car',
            ],
        ],
        'national' => [
            'from' => ['@ehi.com'],
            'body' => [
                "Thank You For Choosing National Car Rental",
                "Merci d'utiliser National Car Rental",
            ],
            'subject' => [
                "en" => 'National Car Rental',
            ],
        ],
    ];

    /*
    private $detectors = [
        'en' => ['CLAIM INFORMATION'],
        'fr' => ['Infos réclamation', 'Date de perte'],
    ];
    */

    private $body = [
        'en' => ['RENTAL VEHICLES', 'RENTAL INFORMATION', 'Please Return This Portion With Remittance'],
        'fr' => ['Infos sur la location', 'Véhicules location', 'Remettre le détail du paiement'],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $key => $option) {
            foreach ($option['from'] as $reFrom) {
                return strpos($from, $reFrom) !== false;
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['subject'] as $lang=>$subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderByBody($parser)) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!$textPdf) {
                    continue;
                }

                foreach ($this->body as $lang => $body) {
                    if (strpos($textPdf, $body[0]) !== false &
                        strpos($textPdf, $body[1]) !== false &
                        strpos($textPdf, $body[2]) !== false
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $rentals = $this->splitText($textPdf, "/((?:^[ ]*|.+[ ]{2}){$this->opt($this->t('Rental Agreement #:'))}.+)/m", true);

                foreach ($rentals as $rental) {
                    $this->parseCar($email, $rental);
                }
            }
        }

        if (null !== ($code = $this->getProviderByBody($parser))) {
            $email->setProviderCode($code);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('InvoicePdf' . ucfirst($this->lang));

        if ($this->pdfPages > 0 && count($email->getItineraries()) > 1 && $this->amount !== null) {
            $email->price()->currency($this->currency)->total($this->amount);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function parseCar(Email $email, $text): void
    {
        $patterns = [
            'time'      => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'amount-v1' => '\d[,.\'\d]*', // 13,517
            // 'amount-v2' => '\d[,.\'\d ]*', // 13 517
        ];

        $this->pdfPages++;

        $footerPos = $this->strposArray($text, $this->t('Thank You For Choosing'));

        if ($footerPos !== false) {
            $footer = substr($text, $footerPos);
            $text = substr($text, 0, $footerPos);
        } else {
            $footer = $text;
        }

        $tablePos = [0];

        if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Description'))} .+ {$this->opt($this->t('Amount'))}$/m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($text, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('False parsing main table!');

            return;
        }

        $vehicles = $this->re("/^[ ]*{$this->opt($this->t('RENTAL VEHICLES'))}$(\s+[\s\S]+?)\s+^[ ]*{$this->opt($this->t('CLAIM INFORMATION'))}/m", $table[0])
            ?? $this->re("/^[ ]*{$this->opt($this->t('RENTAL VEHICLES'))}$(\s+[\s\S]+?)\s+^[ ]*{$this->opt($this->t('VIN'))}[ ]*:/m", $table[0]);

        $tableHeaders = $this->re("/{$this->opt($this->t('Miles/Kms'))}(\D+{$this->opt($this->t('In'))})/", $vehicles);
        $carInfo = [];

        if (preg_match_all("/^([ ]*[A-Z]+ [^[:lower:]]{25,65} {$patterns['amount-v1']}[ ]+{$patterns['amount-v1']})$/m", $vehicles, $carMatches)) {
            /*
                RED        938J24         WRAU       7S1Y8M      10,647    10,736
                    [or]
                BLACK    RJN315     SUBURBAN7VPTP1               10,202    13,517
            */
            $carInfo = $carMatches[1]; // it-46077421.eml, it-58237709.eml
        }

        foreach ($carInfo as $vehicles) {
            $vehicles = $tableHeaders . "\n" . $vehicles;

            $car = $email->add()->rental();

            if (preg_match("/(?:^[ ]*|.+[ ]{2})({$this->opt($this->t('Rental Agreement #:'))})[ ]*([-A-Z\d]{5,})$/m", $text, $m)) {
                $car->general()->confirmation($m[2], rtrim($m[1], ': '));
            }

            if (preg_match("/(?:^[ ]*|.+[ ]{2})({$this->opt($this->t('Bill Ref #:'))})[ ]*([-A-Z\d]{5,})$/m", $text, $m)) {
                $car->general()->confirmation($m[2], rtrim($m[1], ': '));
            }

            if (preg_match("/(?:^[ ]*|.+[ ]{2}){$this->opt($this->t('Account #:'))}[ ]*([-A-Z\d]{5,})$/m", $text, $m)) {
                $car->program()->account($m[1], false);
            }

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Description'))} .+ {$this->opt($this->t('Amount'))}$/m", $text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($text, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('False parsing main table!');

                return;
            }

            $dates = preg_match("/^[ ]*{$this->opt($this->t('RENTAL INFORMATION'))}$\s+([\s\S]+?)\s+^[ ]*{$this->opt($this->t('Renter'))}$/m", $table[0], $m) ? $m[1] : null;
            $datesTablePos = [0];

            if (preg_match("/^(.+ ){$this->opt($this->t('Start Charges'))}/m", $dates, $matches)) {
                $datesTablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.+ ){$this->opt($this->t('Date/Time In'))}/m", $dates, $matches)) {
                $datesTablePos[] = mb_strlen($matches[1]);
            }
            $datesTable = $this->splitCols($dates, $datesTablePos);
            $dates = implode("\n", $datesTable);

            $pickUp = preg_match("/{$this->opt($this->t('Date/Time Out'))}(?:[ ]+.+)?\n+[ ]*(.+\d{4} {$patterns['time']})/", $dates, $m) ? $m[1] : null;
            $car->pickup()->date2($pickUp);

            $dropOff = preg_match("/{$this->opt($this->t('Date/Time In'))}(?:[ ]+.+)?\n+[ ]*(.+\d{4} {$patterns['time']})/", $dates, $m) ? $m[1] : null;
            $car->dropoff()->date2($dropOff);

            $renter = preg_match("/^[ ]*{$this->opt($this->t('Renter'))}\s+([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])$/mu", $table[0], $m) ? $m[1] : null;
            $car->general()->traveller($renter);
            $vehiclesTablePos = [0];

            if (preg_match("/^(.+ {$this->opt($this->t('License'))})/m", $vehicles, $matches)) {
                $vehiclesTablePos[] = mb_strlen($matches[1]) + 2;
            }

            if (preg_match("/^(.+ ){$this->opt($this->t('Unit'))}/m", $vehicles, $matches)) {
                $vehiclesTablePos[] = mb_strlen($matches[1]);
            }
            $vehiclesTable = $this->splitCols($vehicles, $vehiclesTablePos);
            $vehicles = implode("\n", $vehiclesTable);

            $model = preg_match("/^[ ]*{$this->opt($this->t('Model'))}\n{1,2}(.+)/m", $vehicles, $m) ? $m[1] : null;
            $car->car()->model($model, false, true);

            $footerTablePos = [0];

            if (preg_match("/^(.+  ){$this->opt($this->t('Paid By'))}[ ]*:/m", $footer, $matches)) {
                $footerTablePos[] = mb_strlen($matches[1]);
            }

            $footerTable = $this->splitCols($footer, $footerTablePos);

            if (count($footerTable) !== 2) {
                $this->logger->debug('False parsing footer table!');

                return;
            }

            $remitTo = preg_match("/[ ]*{$this->opt($this->t('Remit To'))}[ ]*:?\s+([\s\S]+?)(?:\n\n|\s+{$this->opt($this->t('Fed Tax Id'))})/m", $footerTable[0], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
            $car->pickup()->location($remitTo);
            $car->dropoff()->noLocation();
        }

        $billingDetail = preg_match("/^[ ]*{$this->opt($this->t('BILLING DETAIL'))}$(\s+[\s\S]+?)\s+^[ ]*{$this->opt($this->t('PAYMENTS'))}$/m", $table[1], $m) ? $m[1] : null;

        if (preg_match("/^[ ]*{$this->opt($this->t('Total Charges'))} \((?<currency>[A-Z]{3})\)[ ]+(?<amount>\d[,.\'\d]*)$/m", $billingDetail, $m)) {
            // Total Charges (USD)    262.57
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $amount = PriceHelper::parse($m['amount'], $currencyCode);

            if (count($carInfo) === 1) {
                $car->price()->currency($m['currency'])->total($amount);
            }

            if ($this->amount !== null) {
                $this->amount += floatval($amount);
            } else {
                $this->amount = floatval($amount);
            }
            $this->currency = $m['currency'];
        }
    }

    private function getProviderByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (self::$providers as $code => $arr) {
                $criteria = $arr['body'];

                if (count($criteria) > 0) {
                    foreach ($criteria as $search) {
                        if (strpos($textPdf, $search) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }

    /*
    private function detectBody($text): bool
    {
        if (empty($text) || !isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
    */

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Rental Agreement #:']) || empty($phrases['Date/Time Out'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Rental Agreement #:']) !== false
                && $this->strposArray($text, $phrases['Date/Time Out']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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
}
