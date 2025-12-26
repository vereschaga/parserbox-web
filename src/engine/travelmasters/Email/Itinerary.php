<?php
/**
 * Created by PhpStorm.
 * User: rshakirov.
 */

namespace AwardWallet\Engine\travelmasters\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "travelmasters/it-14473489.eml";

    private $detects = [
        'We thank you for choosing to book with Travel Masters We wish you a very happy and enjoyable holiday',
    ];

    private $from = '//';

    private $lang = 'en';

    private $prov = 'Travel Masters';

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)))) && !empty($body)) {
                $this->parseEmail($email, $body);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (false === stripos($body, $this->prov)) {
                return false;
            }

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email, string $text): Email
    {
        $f = $email->add()->flight();

        $info = $this->cutText('ITINERARY', ['Depart'], $text);

        if (preg_match('/Booking Number\s*:\s*(\w+)/', $info, $m)) {
            $f->general()
                ->confirmation($m[1]);
        }

        if (preg_match('/Travel Itinerary prepared especially for\s*:\s*(.+)/', $info, $m)) {
            $f->general()
                ->traveller($m[1]);
        }

        $segTexts = $this->splitText($text, '/([a-z]+\s+\d{1,2} [a-z]+ \d{2,4})/iu', true);
        $segments = [];
        $hotels = [];
        $cruises = [];

        foreach ($segTexts as $i => $segText) {
            $this->logger->info("SEGMENT {$i}: " . $segText);

            if (preg_match('/[a-z]+\s+(\d{1,2} [a-z]+) (\d{2,4})/iu', $segText, $m)) {
                $dateForSegment = strtotime($m[1] . ' ' . $m[2]);
                $year = strtotime($m[2]);

                // AIRS
                preg_match_all('/(\d{1,2}:\d{2}\s+Depart\s+.+?\s+(?:\d{1,2}:\d{2}\s+Arrive\s+[^\n]+|[a-z]+\s+\d{1,2} [a-z]+\s+Arrive\s+[^\n]+\s+\d{1,2}:\d{2}|[a-z]+\s+\d{1,2} [a-z]+\s+Arrive\s+[^\n]+\s+Customs and Immigration\s+\d{1,2}:\d{2}))/msui', $segText, $m);

                if (!empty($m[1]) && 0 < count($m[1])) {
                    foreach ($m[1] as $s) {
                        $segments[$dateForSegment][] = $s;
                    }
                }

                // HOTELS
                preg_match_all('/(\w+\.?\s+\d{1,2} \w+\s+Accommodation has been reserved for \d+ night[s]? at\s*:\s*\w+\.?\s+\d{1,2} \w+\s+.+?\s+Confirmation No\.?\s*:\s*[\/A-Z\d]+)/msu', $segText, $math);

                if (!empty($math[1]) && 0 < count($math[1])) {
                    foreach ($math[1] as $hotel) {
                        $hotels[] = $hotel;
                    }
                }

                //CRUISES
                preg_match_all('/(\w+\.?\s+\d{1,2} \w+\s+A Cruise has been booked for \d+ nights with\s*:\s*\w+\.?\s+\d{1,2} \w+\s+.+?\s+Confirmation No\.?\s*:\s*[\dA-Z\/]+\s+.*?\s*Start Details\s*:\s*.+?\s+Finish Details\s*:\s*[^\n])/msu', $segText, $ma);

                if (!empty($ma[1]) && 0 < count($ma[1])) {
                    foreach ($ma[1] as $cruise) {
                        $cruises[] = $cruise;
                    }
                }
            }
        }

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            if (!empty($year) && preg_match('/\w+\.?\s+(\d{1,2} \w+)\s+Accommodation has been reserved for \d+ night[s]? at\s*:\s*\w+\.?\s+(\d{1,2} \w+)\s+(.+)\s+Confirmation No\.?\s*:\s*(.+)/msu', $hotel, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1], $year))
                    ->checkOut(strtotime($m[2], $year));
                $h->hotel()->name($m[3])->noAddress();
                $h->general()->confirmation($m[4]);
            }
        }

        $re = '/\w+\.?\s+(?<ashore>\d{1,2} \w+)\s+A Cruise has been booked for \d+ nights with\s*:\s*\w+\s+(?<aboard>\d{1,2} \w+)\s+(?<desc>.+?)\s+Confirmation No\.?\s*:\s*(?<conf>[\dA-Z\/]+)\s+(?:(?:Vessel|Ship)\s*:\s*(?<ship>[^\n]+)\s+(?:Category)?\s*(?<class>.+)\s+Cabin\s+(?<room>\w+)\s+)?.*\s*Start Details\s*:\s*(?<name>.+?)\s+Finish Details\s*:\s*([^\n]+)/msu';

        foreach ($cruises as $cruise) {
            $c = $email->add()->cruise();

            if (!empty($year) && preg_match($re, $cruise, $m)) {
                $c->general()
                    ->confirmation($m['conf']);
                $c->details()
                    ->description($m['desc']);

                if (!empty($m['class']) && !empty($m['ship']) && !empty($m['room'])) {
                    $c->details()
                        ->roomClass($m['class'])
                        ->ship($m['ship'])
                        ->room($m['room']);
                }
                $cs = $c->addSegment();
                $cs->setName($m['name'])
                    ->setAshore(strtotime($m['ashore'], $year))
                    ->setAboard(strtotime($m['aboard'], $year));
            }
        }

        foreach ($segments as $date => $segment) {
            foreach ($segment as $st) {
                $s = $f->addSegment();

                $re = '/(?<dtime>\d{1,2}:\d{2})\s+Depart\s+(?<dname>.+)\s+Flight\s+(?<an>[A-Z\d]{2})\s*(?<fn>\d+)\s+(?:\((?<aircraft>.+)\))?\s+(?<class>.+)\s+Seating\s+Flying Time:\s+(?<duration>.+)\s+Meals Served:\s+(?<meal>.+)\s+Status\s+\-\s+(?<status>\w+)\s+(?<stops>.+)\s+(?<atime>\d{1,2}:\d{2}|\d{1,2} \w+)\s+Arrive\s+(?<aname>[^\n]+(?:\s+.+?\s+(?<arrtime>\d{1,2}:\d{2}))?)/ums';

                if (preg_match($re, $st, $m)) {
                    $s->departure()
                        ->date(strtotime($m['dtime'], $date));
                    $s->airline()
                        ->number($m['fn'])
                        ->name($m['an']);
                    $s->extra()
                        ->meal($m['meal'])
                        ->duration($m['duration']);

                    if (false === stripos($m['stops'], 'non-stop')) {
                        $s->extra()
                            ->stops($m['stops']);
                    }
                    $s->arrival()
                        ->date(strtotime($m['atime'], $date));

                    if (!empty($m['arrtime'])) {
                        $s->arrival()
                            ->date(strtotime($m['arrtime'], $s->getArrDate()));
                    }

                    if (!empty($m['aircraft'])) {
                        $s->extra()
                            ->aircraft($m['aircraft']);
                    }

                    $re1 = '/(.+),\s+Terminal\s+([A-Z\d]{1,4})(?:\s+on\s+(.+))?/';

                    if (preg_match($re1, $m['dname'], $math)) {
                        $s->departure()
                            ->name($math[1])
                            ->terminal($math[2]);

                        if (!empty($math[3])) {
                            $s->airline()
                                ->operator($math[3]);
                        }
                    } elseif (preg_match('/(.+)\s+on\s+(.+)/', $m['dname'], $math)) {
                        $s->departure()
                            ->name($math[1]);
                        $s->airline()
                            ->operator($math[2]);
                    }

                    if (preg_match($re1, $m['aname'], $math)) {
                        $s->arrival()
                            ->name($math[1])
                            ->terminal($math[2]);
                    } elseif (preg_match('/(.+)\s+where\s+.+/', $m['aname'], $math)) {
                        $s->arrival()
                            ->name($math[1]);
                    }

                    $s->departure()
                        ->noCode();
                    $s->arrival()
                        ->noCode();
                }
            }
        }

        return $email;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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
                if (isset($pos[$k - 1]) && $p >= ((int) $pos[$k - 1] * 2)) {
                    $cols[10][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } // dirty hack
                else {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function cutText(string $start = '', array $ends = [], string $text = '')
    {
        if (!empty($start) && 0 < count($ends) && !empty($text)) {
            foreach ($ends as $end) {
                if (($cuttedText = stristr(stristr($text, $start), $end, true)) && is_string($cuttedText) && 0 < strlen($cuttedText)) {
                    break;
                }
            }

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function re(string $re, string $str, int $i = 1): ?string
    {
        if (preg_match($re, $str, $m) && !empty($m[$i])) {
            return $m[$i];
        }

        return null;
    }
}
