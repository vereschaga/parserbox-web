<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class RentalAgreementPdf extends \TAccountChecker
{
    public $mailFiles = "national/it-623447035.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pickupDate' => ['RENTAL DATE'],
            'dropoffLoc' => ['RETURN LOCATION'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nationalcar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && preg_match('/(?:National|Enterprise|Alamo) Rental Agreement/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $providerCodes = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf || !$this->assignLang($textPdf)) {
                continue;
            }

            $filename = $this->getAttachmentName($parser, $pdf);

            if (stripos($filename, 'Alamo Rental Agreement') !== false) {
                $providerCode = 'alamo';
            } elseif (stripos($filename, 'Enterprise Rental Agreement') !== false
                   || stripos($textPdf, 'ENTERPRISE HOLDINGS INC') !== false) {
                $providerCode = 'rentacar';
            } elseif (stripos($filename, 'National Rental Agreement') !== false) {
                $providerCode = 'enterprice';
            } else {
                $providerCode = '';
            }

            $providerCodes[] = $providerCode;
            $this->parsePdf($email, $textPdf);
        }

        if (count(array_unique($providerCodes)) === 1) {
            $this->providerCode = $providerCodes[0];
        }

        if (!$this->providerCode) {
            $this->providerCode = RentalAgreement::getProvider($parser->getHeaders(), $this->http);
        }

        $email->setType('RentalAgreementPdf' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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
        return ['alamo', 'rentacar', 'national'];
    }

    private function parsePdf(Email $email, string $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        $mainTableText = $this->re("/(.+[ ]{2}{$this->opt($this->t('RESERVATION#'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('RATE RULES AND QUALIFICATIONS'))}/", $text);

        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('pickupDate'))}/m", $mainTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $mainTable = $this->splitCols($mainTableText, $tablePos);

        if (count($mainTable) !== 2) {
            $this->logger->debug('Wrong main table!');

            return;
        }

        $raNumVal = preg_replace('/\s+/', ' ', $this->re("/^\s*([\s\S]+?)\n+[ ]*{$this->opt($this->t('RENTAL LOCATION'))}/", $mainTable[0]));

        if (preg_match("/^({$this->opt($this->t('RENTAL AGREEMENT SUMMARY NO.'))})[: ]*([A-Z\d]{5,})$/", $raNumVal, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $locationPickup = $this->re("/\n[ ]*{$this->opt($this->t('RENTAL LOCATION'))}\n+[ ]*([\s\S]{3,})\n+[ ]*{$this->opt($this->t('RENTER'))}/", $mainTable[0]);

        if (preg_match($pattern = "/^\s*(?<location>[\s\S]{3,}?)[ ]*\n+[ ]*(?<phone>{$patterns['phone']})[ ]*(?:\n|$)/", $locationPickup, $m)) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } else {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $locationPickup));
        }

        $traveller = $this->re("/\n[ ]*{$this->opt($this->t('RENTER'))}\n+[ ]*({$patterns['travellerName']})$/mu", $mainTable[0]);
        $r->general()->traveller($traveller, true);

        if (preg_match("/^[ ]*({$this->opt($this->t('RESERVATION#'))})[: ]*([A-Z\d]{5,})(?:[ ]{2}|$)/m", $mainTable[1], $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $tds234Text = $this->re("/^([ ]*{$this->opt($this->t('pickupDate'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t("DRIVER'S LICENSE NUMBER"))}/m", $mainTable[1]);

        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('dropoffLoc'))}(?: |$)/m", $tds234Text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('RETURN DATE'))}$/m", $tds234Text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $subTable = $this->splitCols($tds234Text, $tablePos);

        if (count($subTable) !== 3) {
            $this->logger->debug('Wrong sub-table!');

            return;
        }

        $datePickup = strtotime($this->re("/^\s*{$this->opt($this->t('pickupDate'))}\n+[ ]*(.{6,})\n+[ ]*{$this->opt($this->t('RENTAL TIME'))}/", $subTable[0]));
        $timePickup = $this->re("/\n[ ]*{$this->opt($this->t('RENTAL TIME'))}\n+({$patterns['time']})/", $subTable[0]);

        if ($datePickup && $timePickup) {
            $r->pickup()->date(strtotime($timePickup, $datePickup));
        }

        $locationDropoff = $this->re("/^\s*{$this->opt($this->t('dropoffLoc'))}\n+[ ]*([\s\S]{3,})$/", $subTable[1]);

        if (preg_match($pattern, $locationDropoff, $m)) {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } else {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $locationDropoff));
        }

        $dateDropoff = strtotime($this->re("/^\s*{$this->opt($this->t('RETURN DATE'))}\n+[ ]*(.{6,})\n+[ ]*{$this->opt($this->t('RETURN TIME'))}/", $subTable[2]));
        $timeDropoff = $this->re("/\n[ ]*{$this->opt($this->t('RETURN TIME'))}\n+({$patterns['time']})/", $subTable[2]);

        if ($dateDropoff && $timeDropoff) {
            $r->dropoff()->date(strtotime($timeDropoff, $dateDropoff));
        }

        $modelText = $this->re("/\n[ ]{0,20}{$this->opt($this->t('MAKE'))} .+\n+([\s\S]+?)\n+[ ]{0,20}{$this->opt($this->t('COLOR'))} /", $text);

        $tablePos = [0];

        if (preg_match("/^([ ]{0,20}{$this->opt($this->t('MODEL'))})\b/m", $modelText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('STALL'))}\b/m", $modelText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $tableModel = $this->splitCols($modelText, $tablePos);

        if (count($tableModel) === 3) {
            $r->car()->model(preg_replace('/\s+/', ' ', trim($tableModel[1])));
        }

        $totalCurrencies = $totalAmounts = [];

        $paymentsText = $this->re("/\n[ ]*{$this->opt($this->t('PAYMENTS'))}((?:\n+.+){1,10})/", $text);

        if (preg_match_all("/[ ]{2}{$this->opt($this->t('AUTH'))}[ ]{2,}(\S.*)$/m", $paymentsText, $authMatches)) {
            foreach ($authMatches[1] as $totalPrice) {
                if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                    // $763.29
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $totalCurrencies[] = $matches['currency'];
                    $totalAmounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
                } else {
                    $this->logger->debug('Wrong total price!');
                    $totalCurrencies = $totalAmounts = [];

                    break;
                }
            }
        }

        if (count(array_unique($totalCurrencies)) === 1) {
            $r->price()->currency($totalCurrencies[0])->total(array_sum($totalAmounts));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['pickupDate']) || empty($phrases['dropoffLoc'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['pickupDate']) !== false
                && $this->strposArray($text, $phrases['dropoffLoc']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
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

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
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
}
