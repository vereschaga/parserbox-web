<?php

namespace AwardWallet\Engine\national\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers rentacar/RentalAgreementPDF (in favor of national/RentalAgreement2014Pdf)

class RentalAgreement2014Pdf extends \TAccountChecker
{
    public $mailFiles = "national/it-168354053-rentacar.eml, national/it-1717036.eml, national/it-58647233.eml, national/it-58902438.eml, national/it-619756842-alamo.eml, national/it-66213625.eml, national/it-678502865.eml, national/it-794637795.eml";

    public $reFrom = ["national.", '@Enterprise.com'];
    public $reBody = [
        'en'  => ['Rental Agreement', 'Rental Charges'],
        'en2' => ['Rental Agreement', 'Vehicle Information'],
        'de'  => ['Mietvereinbarungsnr', 'Fahrzeuginformationen'],
        'fr'  => ['Numéro de contrat de location', 'Frais de location'],
    ];
    public $reSubject = [
        'National Rental Agreement',
        // de
        'Mietvereinbarung mit Enterprise',
        'Mietvereinbarung mit Alamo',
    ];
    public $providerCode = '';
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            // 'Rental Agreement' => '',
            // 'Invoice' => '',
            // 'Renter Information' => '',
            // 'Trip Information' => '',
            // 'Renter Name' => '',
            // 'Pickup' => '',
            'Return'       => ['Return', 'Actual Return'],
            'endInfoBlock' => ['Bill-To:', 'Rental Charges', 'Renter Charges', 'Rental Rate'],
            // 'Vehicle Class Driven' => '',
            // 'Vehicle Class Charged' => '',
            // 'Odometer Mileage' => '',
            // 'Rental Rate' => '',
            // 'Discount' => '',
            // 'Taxes and Fees' => '',
            // 'fees' => '',
            // 'Total' => '',
        ],
        'de' => [
            'Rental Agreement' => 'Mietvereinbarungsnr.',
            // 'Invoice' => '',
            'Renter Information'    => 'Mieterdaten',
            'Trip Information'      => 'Reiseinformationen',
            'Renter Name'           => 'Mietername',
            'Pickup'                => 'Abholung',
            'Return'                => ['Rückgabe'],
            'endInfoBlock'          => ['Rechnungsstellung:'],
            'Vehicle Class Driven'  => 'Gefahrene Fahrzeugklasse',
            'Vehicle Class Charged' => 'Berechnete Fahrzeugklasse',
            'Odometer Mileage'      => 'Kilometerstand',
            'Rental Rate'           => 'Mietpreis',
            // 'Discount' => '',
            'Taxes and Fees' => 'Steuern und Gebühren',
            // 'fees' => '',
            'Total' => 'Gesamt',
        ],

        'fr' => [
            'Rental Agreement'      => 'Numéro de contrat de location',
            'Invoice'               => 'Numéro de facture',
            'Renter Information'    => 'Renseignements sur le',
            'Trip Information'      => 'Information sur le voyage',
            'Renter Name'           => 'Nom du locataire',
            'Pickup'                => 'Ramaasage',
            'Return'                => ['Retour'],
            'endInfoBlock'          => ['Frais de location'],
            'Vehicle Class Driven'  => 'Classe de véhicule livré',
            'Vehicle Class Charged' => 'Classe de véhicule facturé',
            'Odometer Mileage'      => 'Compteur kilométrique',
            'Rental Rate'           => 'Tarif de location',
            // 'Discount' => '',
            'Taxes and Fees' => 'Taxes et frais',
            // 'fees' => '',
            'Total' => 'Total',
        ],
    ];

    private $patterns = [
        'date' => '\b[[:alpha:]]{3,15}\s*,?\s*(?:[[:alpha:]]{3,15}\s+\d{1,2}|\d{1,2}[\.]?\s+[[:alpha:]]{3,15})[\s,]+\d{4}\b', // Monday, May 16, 2022    |    Mon,Jun 9 2014
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            return $email;
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (!$this->assignLang($textPdf)) {
                continue;
            }

            if (empty($this->providerCode)) {
                $this->assignProvider($parser->getHeaders(), $textPdf);
            }

            $this->parseCar($textPdf, $email);
        }

        $email->setType('RentalAgreement2014Pdf' . ucfirst($this->lang));

        if ($this->providerCode !== 'national') {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (!$this->assignProvider($parser->getHeaders(), $textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['alamo', 'rentacar', 'national'];
    }

    private function parseCar($textPDF, Email $email): void
    {
        $infoBlock = $this->re("#^ *{$this->opt($this->t('Renter Information'))} +{$this->opt($this->t('Trip Information'))}[^\n]*\s+(.+?)(?:^ *)?{$this->opt($this->t('endInfoBlock'))}#sm",
            $textPDF);

        $tablePos = [0];

        if (preg_match("#^(.{20,}? ){$this->opt($this->t('Pickup'))}(?:[ ]{2}|\n)#m", $infoBlock, $matches)) {
            $tablePos[1] = mb_strlen($matches[1]);

            if (preg_match("#^(.{" . ($tablePos[1] + 10) . ",}? ){$this->opt($this->t('Return'))}(?:[ ]{2}|\n)#m", $infoBlock, $matches2)
                && preg_match_all("#^(.{" . $tablePos[1] . "," . mb_strlen($matches2[1]) . "}? {$this->patterns['time']})(?: |$)#m", $infoBlock, $timeMatches)
            ) {
                // it-619756842-alamo.eml
                $tablePos2Variants = array_map('mb_strlen', $timeMatches[1]);
                rsort($tablePos2Variants);
                $tablePos[2] = $tablePos2Variants[0] + 1;
            }
        }

        $table = $this->splitCols($infoBlock, $tablePos);

        if (count($table) !== 3) {
            $this->logger->debug("other format");

            return;
        }

        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("#{$this->t('Rental Agreement')}[ \#]+([A-Z\d]{5,})#", $textPDF),
                $this->t('Rental Agreement'));

        if ($invoiceNumber = $this->re("#{$this->t('Invoice')}[ \#]+([A-Z\d]{5,})#", $textPDF)) {
            $r->general()
                ->confirmation($invoiceNumber, $this->t('Invoice'));
        }

        // Date
        $datePickupText = $this->re("#^[ ]*{$this->opt($this->t('Pickup'))}((?:\n+.+){1,3})#m", $table[1]);
        $tablePickupPos['date'] = 0;

        if (preg_match("#^(.{6,}? ){$this->patterns['time']}[ ]*$#m", $datePickupText, $matches)) {
            $tablePickupPos['time'] = mb_strlen($matches[1]);
        }

        $tablePickup = $this->splitCols($datePickupText, $tablePickupPos);

        if (count($tablePickup) === 2) {
            $datePickup = $this->normalizeDate(preg_replace('/\s+/', ' ', $this->re("#({$this->patterns['date']})#", $tablePickup['date'])));
            $timePickup = $this->re("#({$this->patterns['time']})#", $tablePickup['time']);
            $r->pickup()->date(strtotime($timePickup, $datePickup));
        }

        $dateDropoffText = $this->re("#^[ ]*{$this->opt($this->t('Return'))}((?:\n+.+){1,3})#m", $table[2]);
        $tableDropoffPos['date'] = 0;

        if (preg_match("#^(.{6,}? ){$this->patterns['time']}[ ]*$#m", $dateDropoffText, $matches)) {
            $tableDropoffPos['time'] = mb_strlen($matches[1]);
        }

        $tableDropoff = $this->splitCols($dateDropoffText, $tableDropoffPos);

        if (count($tableDropoff) === 2) {
            $dateDropoff = $this->normalizeDate(preg_replace('/\s+/', ' ', $this->re("#({$this->patterns['date']})#", $tableDropoff['date'])));
            $timeDropoff = $this->re("#({$this->patterns['time']})#", $tableDropoff['time']);
            $r->dropoff()->date(strtotime($timeDropoff, $dateDropoff));
        }

        // Location
        $pickupLocation = $this->nice($this->re("#^[ ]*{$this->opt($this->t('Pickup'))}\n[\s\S]+{$this->patterns['time']}\n+(?:.{0,15}\b20\d{2}\n+)?[ ]*([\s\S]{3,})#m", $table[1]));
        $dropoffLocation = $this->nice($this->re("#^[ ]*{$this->opt($this->t('Return'))}\n[\s\S]+{$this->patterns['time']}\n+(?:.{0,15}\b20\d{2}\n+)?[ ]*([\s\S]{3,})#m", $table[2]));
        $r->pickup()->location($pickupLocation);
        $r->dropoff()->location($dropoffLocation);

        $r->general()
            ->traveller($this->re("#{$this->opt($this->t('Renter Name'))}\s+(.+)#", $table[0]));

        $str = $this->re("/((?:{$this->t('Renter Information')}|Vehicle (?:Information|Class (?:Driven|Charged)))\s{2,})/", $textPDF);
        $pos2 = strlen($str);
        $table1 = $this->splitCols($textPDF, [0, $pos2]);

        // if (preg_match("#{$this->opt($this->t('Vehicle Class Driven'))}.*\n{1,2}((?:.+\n+){1,3}?)[ ]*{$this->opt($this->t('Vehicle Class Charged'))}#", $table1[0], $m)) {
        //     $r->car()->model(preg_replace('/\s+/', ' ', trim($m[1])));
        // }

        if (preg_match("#{$this->opt($this->t('Vehicle Class Charged'))}.*\n{1,2}((?:.+\n+){1,3}?)[ ]*{$this->opt($this->t('Odometer Mileage'))}#", $table1[0], $m)) {
            $r->car()->type(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        $totalPrice = $this->re("#.{20}[ ]{2,}{$this->opt($this->t('Total'))}.*? ([^\d\s]{0,5} ?\d[,.\'\d]* ?[^\d\s]{0,5})\n#u", $textPDF);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*(?<currency>[^\-\d)(]+?)\s*$/u', $totalPrice, $matches)
        ) {
            // $2,522.67
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (preg_match("#.{20}[ ]{2,}{$this->opt($this->t('Rental Rate'))}.+ (?:" . preg_quote($matches['currency']) . ")?[ ]*(?<amount>\d[,.\'\d]*)\n#u", $textPDF, $m)
                || preg_match("#.{20}[ ]{2,}{$this->opt($this->t('Rental Rate'))}.+ (?<amount>\d[,.\'\d]*) ?(?:" . preg_quote($matches['currency']) . ")?\n#u", $textPDF, $m)
            ) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            if (preg_match("#{$this->opt($this->t('Discount'))}.+ \(? ?(?:" . preg_quote($matches['currency']) . ")?[- ]*(?<amount>\d[,.\'\d]*?)[ )]*\n#", $textPDF, $m)
                || preg_match("#.{20}[ ]{2,}{$this->opt($this->t('Discount'))}.+ (?<amount>\d[,.\'\d]*) ?(?:" . preg_quote($matches['currency']) . ")?\n#u", $textPDF, $m)
            ) {
                $r->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        /*$tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Taxes and Fees'))}.+\s+\D?([\d\.]+)(?:\s+[A-Z]{3})?\n#",
            $textPDF));
        if (!empty($tot['Total'])) {
            $r->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);
        }*/

        // TODO: Sometimes not what was expected is going
        //Fees
        /*$feesText = $this->cutText('Rental Rate', 'Total', $textPDF);
        $table = $this->splitCols($feesText, $this->colsPos($this->re("#(.+Taxes and Fees.+)#", $feesText), 10));

        if (count($table) === 2) {
            $feesName = $table[0];
            $feesSum = $table[1];

            $feesName = preg_split('/\n/', $feesName);
            $feesSum = preg_split('/\n/', $feesSum);

            $feesRows = Array();
            for ($i = 0; $i <= count($feesName); $i++) {
                if (isset($feesName[$i]) && isset($feesSum[$i]))
                    $feesRows[] = $feesName[$i] . ' ' . $feesSum[$i];
            }

            $feesText = implode("\n", $feesRows);
            $feesText = preg_replace('/^((?:.+)?Taxes and Fees\s+)/s', '', $feesText);

            if (preg_match_all("/^(.+?\s+\D?\s?\d[.\d]+)\s?(?:[A-Z]{3})?$/sm", $feesText, $m)) {
                foreach ($m[1] as $fee) {
                    $feeName = $this->re('/^(.+?)\s+\D?\s?\d[.\d]+\s?(?:[A-Z]{3})?$/sm', $fee);
                    $feeSum = $this->re('/^.+?\s+\D?\s?(\d[.\d]+)\s?(?:[A-Z]{3})?$/sm', $fee);
                    if (!empty($feeName) && !empty($feeSum))
                        $r->price()
                            ->fee($feeName, $feeSum);
                }
            }
        }

        if (count($table) === 3) {
            $feesName = $table[1];
            $feesSum = $table[2];

            $feesName = preg_split('/\n/', $feesName);
            $feesSum = preg_split('/\n/', $feesSum);

            $feesRows = Array();
            for ($i = 0; $i <= count($feesName); $i++) {
                if (isset($feesName[$i]) && isset($feesSum[$i]))
                    $feesRows[] = $feesName[$i] . ' ' . $feesSum[$i];
            }

            $feesText = implode("\n", $feesRows);
            $feesText = preg_replace('/^((?:.+)?Taxes and Fees\s+)/s', '', $feesText);

            if (preg_match_all("/^(.+?\s+\D?\s?\d[.\d]+)\s?(?:[A-Z]{3})?$/sm", $feesText, $m)) {
                foreach ($m[1] as $fee) {
                    $feeName = $this->re('/^(.+?)\s+\D?\s?\d[.\d]+\s?(?:[A-Z]{3})?$/sm', $fee);
                    $feeSum = $this->re('/^.+?\s+\D?\s?(\d[.\d]+)\s?(?:[A-Z]{3})?$/sm', $fee);
                    if (!empty($feeName) && !empty($feeSum))
                        $r->price()
                            ->fee($feeName, $feeSum);
                }
            }
        }*/

        /*$fees = $this->t('fees');
        foreach ($fees as $fee) {
            $tot = $this->getTotalCurrency($this->re("#{$fee}.+ {3,}([\$\d\.]+)\n#", $textPDF));
            if (!empty((float)$tot['Total'])) {
                if (preg_match('/\+/', $fee, $m))
                    $fee = 'Fee';
                $r->price()
                    ->fee($fee, $tot['Total']);
            }
        }*/
    }

    /*
    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        return stristr(stristr($text, $start), $end, true);
    }
    */

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider($headers, $text): bool
    {
        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        if (!array_key_exists('subject', $headers)) {
            $headers['subject'] = '';
        }

        if (stripos($headers['from'], '@goalamo.com') !== false || stripos($headers['subject'], 'Alamo Rental Agreement') !== false
            || strpos($text, 'with Alamo') !== false
        ) {
            $this->providerCode = 'alamo';

            return true;
        }

        if (stripos($headers['from'], '@enterprise.com') !== false || stripos($headers['from'], 'www.enterprise.com') !== false
            || stripos($text, 'Enterprise Rent-A-Car') !== false || preg_match('/\bEnterprise\s*Rent[-\s]*A.+[-\s]*Car\b/i', $text) > 0
        ) {
            $this->providerCode = 'rentacar';

            return true;
        }

        if (stripos($headers['from'], '@nationalcar.com') !== false || stripos($headers['subject'], 'National Rental Agreement') !== false
            || stripos($text, 'National Car Rental') !== false || stripos($text, 'nationalcar') !== false
        ) {
            $this->providerCode = 'national';

            return true;
        }

        return false;
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // Freitag, 28. Juli 2023
            "/^\s*[[:alpha:]]{3,15}\s*,?\s*(\d{1,2})[\.]?\s+([[:alpha:]]{3,15})[\s,]+(\d{4})\s*$/",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /*
    private function getTotalCurrency($node): array
    {
        $node = trim($node);
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
//            || preg_match("#(?<c>\-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#^(?<c>.*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
    */

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    /*
    private function colsPos($table, $correct = 5)
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
    */

    private function nice($str)
    {
        return trim(preg_replace([
            "#\s+#",
            '/\d+:\d+ [AP]M \d{4}/',
        ], ' ', $str));
    }
}
