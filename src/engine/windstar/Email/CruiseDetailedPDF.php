<?php

namespace AwardWallet\Engine\windstar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruiseDetailedPDF extends \TAccountChecker
{
    public $mailFiles = "windstar/it-730386156.eml, windstar/it-766755059.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public $cr = null;
    public $isCruiseAdded = false;

    public $isConfirmed = false;

    public static $dictionary = [
        'en' => [
            'Voyage Itinerary' => ['Voyage Itinerary', 'Voyage Plan'],
        ],
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

        if ((stripos($text, 'Windstar') !== false || $this->http->XPath->query("//text()[{$this->contains($this->t('Windstar'))}]")->length > 0)
            && $this->re("/({$this->opt($this->t('Detailed Itinerary Confirmation'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Yacht Voyage Details'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Voyage Itinerary'))})/s", $text) !== null
            && $this->re("/({$this->opt($this->t('Welcome Aboard Windstar'))})/s", $text) == null
            && $this->re("/({$this->opt($this->t('Cruise Itinerary'))})/s", $text) == null
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
        if (preg_match("/\s*(?<desc>{$this->opt($this->t('BOOKING'))})[ ]*\#[ ]*(?<number>\d+)[ ]*(?<status>[a-z]+)\n/i", $text, $m)) {
            $cr->general()
                ->confirmation($m['number'], $m['desc'])
                ->status($m['status']);
        }

        $date = $this->re("/\s{$this->opt($this->t('BOOKING DATE'))}\:?\s+(\d+\/\d+\/\d{4})\s/si", $text);

        if (!empty($date)) {
            $cr->general()
                ->date(strtotime($date));
        }

        // collect travellers
        $travellersText = $this->re("/\n([ ]+{$this->opt($this->t('GUEST'))}[ ]+{$this->opt($this->t('NAME'))}.+?\n+)\s+(?:{$this->opt($this->t('Please carefully review'))}|{$this->opt($this->t('Note:'))})/s", $text);
        $leftPos = strlen($this->re("/(\s+){$this->opt($this->t('GUEST'))}/s", $travellersText));
        $rightPos = strlen($this->re("/(.+?){$this->opt($this->t('M/F'))}/s", $travellersText));

        if (!empty($travellersText)) {
            $travellersCol = $this->splitCols($travellersText, [$leftPos, $rightPos])[0];
            // delete redundant characters from neighboring column
            $travellersCol = preg_replace("/\s[MF]\n/", "\n", $travellersCol);

            // if passenger number splits passenger name into two parts (for first passenger only)
            if (preg_match("/{$this->opt($this->t('NAME'))}\s+(?<part1>(?:[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])+)\s+\d\s+(?<part2>(?:[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])+)/s", $travellersCol, $matches)) {
                $traveller = preg_replace('/\s+/', ' ', $matches['part1'] . ' ' . $matches['part2']);
                $travellersCol = str_replace($matches[0], "\n", $travellersCol);
                $cr->addTraveller($traveller, true);
            }

            // if passenger number is before passenger name
            if (preg_match_all("/\d\s+((?:[[:alpha:]][-.\/\'’[:alpha:]\s]*[[:alpha:]])+)(?:\s|$)/", $travellersCol, $matches)) {
                array_walk($matches[1], function (&$value) {
                    $value = preg_replace('/\s+/', ' ', $value);
                });
                $cr->setTravellers($matches[1], true);
            }
        }

        // collect cruise details
        $cruiseDetails = $this->re("/{$this->opt($this->t('Yacht Voyage Details'))}\n+(.+?)\s+{$this->opt($this->t('Voyage Itinerary'))}/s", $text);
        $detailsCol = '';

        if (!empty($cruiseDetails)) {
            $detailsCol = $this->splitCols($cruiseDetails, [0, 100])[0];
        }

        $ship = $this->re("/{$this->opt($this->t('YACHT:'))}\s+(.+?)\s+{$this->opt($this->t('CRUISE PROGRAM:'))}/", $detailsCol);

        if (!empty($ship)) {
            $cr->setShip($ship);
        }

        $description = $this->re("/{$this->opt($this->t('CRUISE PROGRAM:'))}\s+(\S.+?)\s+{$this->opt($this->t('VOYAGE ID:'))}/s", $detailsCol);

        if (!empty($description)) {
            $description = preg_replace("/\s+/", " ", $description);
            $cr->setDescription($description);
        }

        $voyageNumber = $this->re("/{$this->opt($this->t('VOYAGE ID:'))}\s+(.+?)\s+{$this->opt($this->t('VOYAGE BEGINS:'))}/s", $detailsCol);

        if (!empty($voyageNumber)) {
            $cr->setVoyageNumber($voyageNumber);
        }

        $roomClass = $this->re("/{$this->opt($this->t('CABIN CATEGORY:'))}\s+(.+?)\s+{$this->opt($this->t('CABIN ASSIGNMENT:'))}/", $detailsCol);

        if (!empty($roomClass)) {
            $cr->setClass($roomClass);
        }

        $room = $this->re("/{$this->opt($this->t('CABIN ASSIGNMENT:'))}\s+(\d+)(?:\s|$)/", $detailsCol);

        if (!empty($room)) {
            $cr->setRoom($room);
        }

        // collect cruise itinerary (cruise segments)
        $itineraryText = $this->re("/{$this->opt($this->t('Voyage Itinerary'))}.+?{$this->opt($this->t('DEPARTURE'))}\n+(.+?)\s*(?:{$this->opt($this->t('Vacation Itinerary'))}|{$this->opt($this->t('Although Windstar Cruises'))})/s", $text);

        if (!empty($itineraryText)) {
            $itineraryText = preg_replace("/\n+/", "\n", $itineraryText);
            $itineraryText = preg_replace("/[ ]+{$this->opt($this->t('Page'))}\s*\d\s*\w+\s*\d[ ]*(?:\n|$)/", "", $itineraryText);

            $s = null;
            $lines = array_filter(explode("\n", $itineraryText));

            foreach ($lines as $i => $line) {
                if ($this->re("/({$this->opt($this->t('AT SEA'))})/", $line)) {
                    continue;
                }

                if (preg_match("/^\s*\d{1,2}\s{3,}(?<date>\d+\/\d+\/\d{4})\s{3,}(?<port>[\w\s\,\.\'\/]+?)\s{3,}(?:[\w\s\/]+)(?:\s{3,}(?<time1>\d+\:\d+\s*\w+))?\s{3,}(?<time2>\d+\:\d+\s*\w+)(?:\s|$)/", $line, $m)) {
                    if (empty($s)) {
                        $s = $cr->addSegment();
                        $s->setName($m['port']);

                        if (!empty($m['time1'])) {
                            $s->setAboard(strtotime($m['date'] . ', ' . $m['time2']));
                        }

                        continue;
                    }

                    if ($s->getName() != $m['port']) {
                        $s = $cr->addSegment();
                        $s->setName($m['port']);

                        if (!empty($m['time1'])) {
                            $s->setAshore(strtotime($m['date'] . ', ' . $m['time1']));

                            if ($i != count($lines) - 1) {
                                $s->setAboard(strtotime($m['date'] . ', ' . $m['time2']));
                            }
                        } else {
                            $s->setAshore(strtotime($m['date'] . ', ' . $m['time2']));
                        }
                    } else {
                        if ($i != count($lines) - 1) {
                            $s->setAboard(strtotime($m['date'] . ', ' . $m['time2']));
                        }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '#'));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
}
