<?php

namespace AwardWallet\Engine\azamara\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruiseBookingPDF extends \TAccountChecker
{
    public $mailFiles = "azamara/it-733497957.eml, azamara/it-762817571.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Departure Date' => ['Departure Date', 'Embarkation Date'],
            'Total Charge'   => ['Total Charge', 'Gross Charges'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Azamara') !== false
                && $this->re("/({$this->opt($this->t('Booking Charges - Currency:'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Cancellation Schedule'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Booking Itinerary'))})/s", $text) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]azamara\.com$/', $from) > 0;
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
        $cr = $email->add()->cruise();

        // collect reservation confirmation
        if (preg_match("/\s*(?<desc>{$this->opt($this->t('Reservation ID'))})\:\s*(?<number>\d+)\s/", $text, $m)) {
            $cr->general()
                ->confirmation($m['number'], $m['desc']);
        }

        $date = $this->re("/\s{$this->opt($this->t('Booking Date'))}\:\s*(\d+\s*\w+\s*\d{4})(?:\s|$)/s", $text);

        if (!empty($date)) {
            $cr->general()
                ->date(strtotime($date));
        }

        $status = $this->re("/\s{$this->opt($this->t('Booking Status'))}\:\s*(\w+)(?:\s|$)/s", $text);

        if (!empty($date)) {
            $cr->general()
                ->status($status);
        }

        // collect cruise details
        $cruiseDetails = $this->re("/{$this->opt($this->t('General Information'))}\n+(.+?)\s+{$this->opt($this->t('INCLUSIVE AMENITIES FOR ALL GUESTS'))}/s", $text);
        $detailsCol = '';

        if (!empty($cruiseDetails)) {
            $rightPos = strlen($this->re("/([ ]+){$this->opt($this->t('Reservation ID'))}\:/s", $cruiseDetails));
            $detailsCol = $this->splitCols($cruiseDetails, [0, $rightPos])[1];
        }

        $ship = $this->re("/{$this->opt($this->t('Ship'))}\:\s+(.+?)\s+{$this->opt($this->t('Itinerary'))}\:/s", $detailsCol);

        if (!empty($ship)) {
            $cr->setShip($ship);
        }

        $description = $this->re("/{$this->opt($this->t('Itinerary'))}\:\s+(.+?)\s+{$this->opt($this->t('Departure Date'))}\:/s", $detailsCol);

        if (!empty($description)) {
            $description = preg_replace("/\s+/", " ", $description);
            $cr->setDescription($description);
        }

        if (preg_match("/{$this->opt($this->t('Stateroom'))}\:\s+(?<roomClass>\w{2})\-(?<room>\d{4})\s*\w/su", $detailsCol, $m)) {
            $cr->details()
                ->deck($m['room'][0])
                ->room($m['room'])
                ->roomClass($m['roomClass']);
        }

        // collect travellers
        $travellersText = $this->re("/{$this->opt($this->t('Guest Name'))}(?:\s{3,}([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]))+\s+{$this->opt($this->t('Azamara Circle Number'))}/s", $text);

        if (!empty($travellersText)) {
            $travellers = preg_split('/\s{3,}/', $travellersText);
            array_walk($travellers, function (&$value) {
                $value = trim($value);
            });

            if (!empty($travellers)) {
                $cr->setTravellers($travellers, true);
            }
        }

        // collect prices
        $currency = $this->re("/{$this->opt($this->t('Booking Charges - Currency'))}\:\s*([A-Z]{3})\s/s", $text);
        $total = $this->re("/^\s*{$this->opt($this->t('Total Charge'))}\s+.+\s+\D\s*([\d\.\,\']+)(?:\s|$)/um", $text);

        if (!empty($currency) && $total !== null) {
            $cr->price()
                ->total(PriceHelper::parse($total, $currency));

            $cost = $this->re("/^\s*{$this->opt($this->t('CRUISE FARE'))}\s+.+\s+\D\s*([\d\.\,\']+)(?:\s|$)/um", $text);

            if ($cost !== null) {
                $cr->price()
                    ->cost(PriceHelper::parse($cost, $currency))
                    ->currency($currency);
            }

            $discount = $this->re("/^\s*{$this->opt($this->t('Early Booking Bonus'))}\s+.+\s+\-\D\s*([\d\.\,\']+)(?:\s|$)/um", $text);

            if ($discount !== null) {
                $cr->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }

            $feesText = $this->re("/{$this->opt($this->t('CRUISE FARE'))}(?:.+?{$this->opt($this->t('Early Booking Bonus'))})?.+?\n(.+?)\n{$this->opt($this->t('Total Charge'))}/su", $text);

            if (!empty($feesText)) {
                $fees = array_filter(explode("\n", $feesText));

                foreach ($fees as $fee) {
                    if (preg_match("/^\s*(?<feeName>.+?)\s{5,}.+\s+\D\s*(?<feeValue>[\d\.\,\']+)(?:\s|$)/um", $fee, $m)) {
                        $cr->price()
                            ->fee($m['feeName'], PriceHelper::parse($m['feeValue'], $currency));
                    }
                }
            }
        }

        // collect cruise itinerary (cruise segments)
        $itineraryText = $this->re("/{$this->opt($this->t('Booking Itinerary'))}.+?{$this->opt($this->t('Depart'))}\n+(.+?)\s+{$this->opt($this->t('Health Acknowledgment'))}/s", $text);

        $s = null;
        $lines = array_filter(explode("\n", $itineraryText));

        foreach ($lines as $i => $line) {
            if ($this->re("/({$this->opt($this->t('AT SEA'))})/", $line)) {
                continue;
            }

            if (preg_match("/^\s*(?<date>\d+\s*\w+\s*\d{4})\s{3,}(?<port>.+?)(?:\s{3,}(?<time1>\d+\:\d+\s*[apm\. ]+))?\s{3,}(?<time2>\d+\:\d+\s*[apm\. ]+)(?:\s|$)/im", $line, $m)) {
                if (empty($s)) {
                    $s = $cr->addSegment();
                    $s->setName($m['port']);

                    if (!empty($m['time2'])) {
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
