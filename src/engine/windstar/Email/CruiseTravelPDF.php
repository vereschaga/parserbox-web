<?php

namespace AwardWallet\Engine\windstar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruiseTravelPDF extends \TAccountChecker
{
    public $mailFiles = "windstar/it-736606272.eml, windstar/it-744776294.eml, windstar/it-766755055.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public $cr = null;
    public $isCruiseAdded = false;

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (stripos($text, 'Windstar') !== false
            && $this->re("/({$this->opt($this->t('Welcome Aboard Windstar'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Cruise Itinerary'))})/s", $text) !== null
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]windstarcruises\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            // This attachment is parsed by a parser CruiseDetailedPDF
            if ($this->re("/({$this->opt($this->t('Detailed Itinerary Confirmation'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Voyage Itinerary'))})/s", $text) !== null) {
                continue;
            }

            $this->ParseCruisePDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseCruisePDF(Email $email, $text)
    {
        // check that only one cruise has been created
        if (!$this->isCruiseAdded) {
            $this->cr = $email->add()->cruise();
            $this->isCruiseAdded = true;
        }
        $cr = $this->cr;

        // collect total from attachment with prices
        if ($this->re("/({$this->opt($this->t('Booking Summary'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Booking Total'))})/s", $text) !== null) {
            $currency = $this->re("/\d{4}\s+{$this->opt($this->t('Currency'))}\s+(\w+)\s/s", $text);
            $total = $this->re("/\s{$this->opt($this->t('Booking Total:'))}\s+\S([\d\.\,\']+)\s/s", $text);

            if (!empty($currency) && !empty($total)) {
                $email->price()
                    ->total(PriceHelper::parse($total, $this->normalizeCurrency($currency)))
                    ->currency($this->normalizeCurrency($currency));
            }

            return;
        }

        // collect reservation confirmation
        if (preg_match("/\n[ ]*(?<desc>{$this->opt($this->t('Booking Number:'))})\s+(?<number>\d+)\s*\n/i", $text, $m)) {
            $cr->addConfirmationNumber($m['number'], $m['desc']);
        }

        // collect travellers
        $travellersText = $this->re("/\n([ ]*{$this->opt($this->t('Cruise Booking Information'))}.+?){$this->opt($this->t('Please carefully review'))}/s", $text);
        $rightPos = strlen($this->re("/\n([ ]*{$this->opt($this->t('Guest Names'))}[ ]+){$this->opt($this->t('Loyalty Status'))}/s", $text)) - 1;

        if (!empty($travellersText)) {
            $travellersCol = $this->splitCols($travellersText, [0, $rightPos])[0];
            $travellersCol = $this->re("/\n[ ]*{$this->opt($this->t('Guest Names'))}(.+)/s", $travellersCol);

            if (preg_match_all("/^[ ]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/m", $travellersCol, $matches)) {
                $cr->setTravellers($matches[1], true);
            }
        }

        $cruiseDetails = $this->re("/\n([ ]*{$this->opt($this->t('Ship'))}.+?{$this->opt($this->t('Embarkation Port'))}.+?\s+{$this->opt($this->t('Deck'))}.+?{$this->opt($this->t('Time'))})/s", $text);
        $rightPos = strlen($this->re("/\n([ ]*{$this->opt($this->t('Ship'))}.+?){$this->opt($this->t('Embarkation Port'))}/s", $text));
        $detailsCol = '';

        if (!empty($cruiseDetails)) {
            $detailsCol = $this->splitCols($cruiseDetails, [0, $rightPos])[0];
        }

        $ship = $this->re("/{$this->opt($this->t('Ship'))}\s+(.+?)\s+{$this->opt($this->t('Cruise'))}/", $detailsCol);

        if (!empty($ship)) {
            $cr->setShip($ship);
        }

        $description = $this->re("/{$this->opt($this->t('Cruise'))}\s+(.+?)\s+{$this->opt($this->t('Vacation Start'))}/s", $detailsCol);

        if (!empty($description)) {
            $description = preg_replace("/\s+/", " ", $description);
            $cr->setDescription($description);
        }

        $roomClass = $this->re("/{$this->opt($this->t('Cabin Category'))}\s+(.+?)\s+{$this->opt($this->t('Cabin Number'))}/s", $detailsCol);

        if (!empty($roomClass)) {
            $cr->setClass($roomClass);
        }

        $room = $this->re("/{$this->opt($this->t('Cabin Number'))}\s+(.+?)\s+{$this->opt($this->t('Deck'))}/s", $detailsCol);

        if (!empty($room)) {
            $cr->setRoom($room);
        }

        $deck = $this->re("/\s+{$this->opt($this->t('Deck'))}\s+(\d+)(?:\n|$)/", $detailsCol);

        if (!empty($deck)) {
            $cr->setDeck($deck);
        }

        // collect cruise itinerary (cruise segments)
        $itineraryText = $this->re("/\n[ ]*{$this->opt($this->t('Cruise Itinerary'))}\n(.+?){$this->opt($this->t('Although Windstar Cruises'))}/s", $text);
        $itineraryText = preg_replace("/\n {20,}Page d+ of \d+\n/", "\n", $itineraryText);

        if (!empty($itineraryText)) {
            $itineraryText = preg_replace("/\n+/", "\n", $itineraryText);

            // find column positions
            $portPos = strlen($this->re("/(.+?){$this->opt($this->t('Port/Location'))}/s", $itineraryText)) - 1;
            $positionPos = strlen($this->re("/(.+?){$this->opt($this->t('Anchor/Berth'))}/s", $itineraryText)) - 1;
            $arrivalPos = strlen($this->re("/(.+?){$this->opt($this->t('Arrive'))}/s", $itineraryText)) - 1;
            $departPos = strlen($this->re("/(.+?){$this->opt($this->t('Depart'))}/s", $itineraryText)) - 1;

            $headersPos = [0, $portPos, $positionPos, $arrivalPos, $departPos];

            $rows = $this->split("/(?:^|\n)( {0,20}[\d\/]{4,})/", $itineraryText);

            foreach ($rows as $i => $rowtext) {
                $table = array_map('trim', $this->splitCols($rowtext, $headersPos));

                if (preg_match("/^\s*AT SEA\s*$/", $table[1])) {
                    continue;
                }

                if (empty($s) || $s->getName() != $table[1]) {
                    $s = $cr->addSegment();
                    $s->setName($table[1]);
                }

                if (!empty($table[0]) && !empty($table[3]) && $i != 0) {
                    $s->setAshore(strtotime($table[0] . ', ' . $table[3]));
                }

                if (!empty($table[0]) && !empty($table[4]) && $i != (count($rows) - 1)) {
                    $s->setAboard(strtotime($table[0] . ', ' . $table[4]));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            if (in_array($string, $currencyFormats, true)) {
                return $currencyCode;
            }
        }

        return $string;
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
