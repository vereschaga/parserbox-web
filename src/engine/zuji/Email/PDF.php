<?php

namespace AwardWallet\Engine\zuji\Email;

use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PDF extends \TAccountChecker
{
    public $mailFiles = "zuji/it-24347310.eml";

    private $from = '/[@\.]zuji\.com(?:\.au)?/';

    private $detects = [
        'en' => ['Thank you for your booking. We hope you enjoy your travels'],
    ];

    private $lang = 'en';

    private $prov = 'Zuji';

    private $currencyCodes = [
        'Australian Dollars' => 'AUD',
        'Hong Kong Dollars'  => 'HKD',
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            foreach ($this->detects as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (false !== stripos($body, $phrase)) {
                        $this->lang = $lang;
                        $this->parseEmail($email, $body);
                    } else {
                        $this->logger->info("Can't determine lang");
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (false === stripos($body, $this->prov)) {
                return false;
            }

            foreach ($this->detects as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (false !== stripos($body, $phrase)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email, string $text): Email
    {
        $f = $email->add()->flight();

        $main = $this->findСutSection($text, 'Confirmed Itinerary', 'Flight Details');

        if (!$main) {
            $main = $this->findСutSection($text, 'Booking Acknowledgement', 'Flight Details');
        }

        if ($conf = $this->re('/Zuji reference\s*\:\s*(\d+)/', $main)) {
            $f->general()->confirmation($conf);
        }

        if ($bd = $this->re('/Booking date\s*\:\s*(\w+ \d{1,2} \w+ \d{2,4})/', $main)) {
            $f->general()->date(strtotime($bd));
        }

        $paxText = $this->findСutSection($text, 'Passenger Details', 'Important Information About Your Booking');

        if (preg_match_all('/((?:mister|missis|miss|mr|mrs|mis|master)\s+.+)\s+(?:adult|child)/i', $paxText, $m)) {
            $m[1] = array_filter(array_unique(array_map("trim", $m[1])));

            foreach ($m[1] as $name) {
                $f->addTraveller($name);
            }
        }

        if (preg_match_all('/Frequent Flyer number\s+([A-Z]{2} [\s\d]+)/', $paxText, $m)) {
            $m[1] = array_filter(array_unique(array_map("trim", $m[1])));
            $m[1] = array_map(function ($e) { return preg_replace('/\s+/', '', $e); }, $m[1]);

            foreach ($m[1] as $ff) {
                $f->addAccountNumber($ff, false);
            }
        }

        $totalText = $this->findСutSection($text, 'Total Booking Price', 'Thank you for your booking');

        if ($total = $this->re('/([\d\,\.]+)/', $totalText)) {
            $total = str_replace([','], [''], $total);
            $f->price()
                ->total($total);
        }

        if ($cur = $this->re('/All prices are in (.+)\./', $totalText)) {
            $cur = trim($cur);

            if (isset($this->currencyCodes[$cur])) {
                $f->price()
                    ->currency($this->currencyCodes[$cur]);
            }
        }

        $flightText = $this->findСutSection($text, 'Flight Details', 'Passenger Details');
        $segmentsText = $this->splitter('/(.+\s+(?:[A-Z]{2}|[A-Z]\d)\d+)/u', $flightText);
        // duplicating segments
        $c = [];

        foreach ($segmentsText as $segText) {
            $s = $f->addSegment();

            $table = $this->splitCols($segText);

            if (3 !== count($table)) {
                $this->logger->info("table incorect");

                return $email;
            }

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s+(\w+)(?:\s+Airline reference\:\s*(\w+))?/u', $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->extra()
                    ->cabin($m[3]);

                if (!empty($m[4])) {
                    $s->airline()
                        ->confirmation($m[4]);
                }
            }

            $re = '/(?<Name>.+)\s+\((?<Code>[A-Z]{3})\)\s+(?:Terminal\s+(?<Terminal>[A-Z\d]{1,5})\s+)?(?<Date>\w+ \d{1,2} \w+ \d{2,4})\s+(?<Time>\d{1,2}:\d{2} [AP]M)/u';

            if (preg_match($re, $table[1], $m)) {
                $s->departure()
                    ->name($m['Name'])
                    ->code($m['Code'])
                    ->date(strtotime($m['Date'] . ', ' . $m['Time']));

                if (!empty($m['Terminal'])) {
                    $s->departure()
                        ->terminal($m['Terminal']);
                }
            }

            if (preg_match('/(\d+) stop in/i', $table[1], $m)) {
                $s->extra()
                    ->stops($m[1]);
            }

            if (preg_match($re, $table[2], $m)) {
                $s->arrival()
                    ->name($m['Name'])
                    ->code($m['Code'])
                    ->date(strtotime($m['Date'] . ', ' . $m['Time']));

                if (!empty($m['Terminal'])) {
                    $s->arrival()
                        ->terminal($m['Terminal']);
                }
            }

            if (preg_match('/Flight time\s*\:\s*(.+)/', $table[2], $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            // find duplicating segments
            if (0 < count($f->getSegments())) {
                foreach ($f->getSegments() as $i => $segment) {
                    /** @var FlightSegment $segment */
                    if (
                        !empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && !empty($s->getDepDate())
                        && $s->getAirlineName() === $segment->getAirlineName()
                        && $s->getFlightNumber() === $segment->getFlightNumber()
                        && $s->getDepDate() === $segment->getDepDate()
                    ) {
                        $c[$segment->getFlightNumber()][] = $i;
                    }
                }
            }
        }

        // remove duplicating segments
        foreach ($c as $pos) {
            $pos = array_unique($pos);
            array_shift($pos);

            foreach ($pos as $po) {
                if (isset($f->getSegments()[$po])) {
                    $f->removeSegment($f->getSegments()[$po]);
                }
            }
        }

        // hotel
        if ($hotel = $this->findСutSection($text, 'Hotel Details', 'Important Information About Your Booking')) {
            $this->parseHotel($email, $hotel);
        }

        return $email;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseHotel(Email $email, string $text): Email
    {
        $hotels = $this->splitter('/(Hotel\s+Check In \/ Check Out\s+Room\(s\))/', $text);

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            $table = $this->splitCols($hotel);

            if (3 !== count($table)) {
                $this->logger->info("Hotel table incorect");

                return $email;
            }

            if (preg_match('/Hotel\s+(.+)\s+(.+)\s+Hotel reference\s*\:\s*(\d+)\|(\d+)/u', $table[0], $m)) {
                $h->hotel()->name($m[1])->address($m[2]);
                $h->general()->confirmation($m[3])->confirmation($m[4]);
            }

            if (preg_match('/Check in\s*\:\s*(.+)\s+Check out\s*\:\s*(.+)\s+.+\s+Lead guest\s*\:\s*(.+)/', $table[1], $m)) {
                $h->booked()->checkIn(strtotime($m[1]))->checkOut(strtotime($m[2]));
                $h->addTraveller($m[3]);
            }

            if (preg_match('/(\d+)\s*\S*\s*(.+)/', $table[2], $m)) {
                $h->booked()->rooms($m[1]);
                $h->addRoom()->setDescription($m[2]);
            }
        }

        return $email;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

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
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function splitCols($text, $pos = false)
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
}
