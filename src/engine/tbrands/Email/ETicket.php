<?php

namespace AwardWallet\Engine\tbrands\Email;

use AwardWallet\Schema\Parser\Email\Email;
use TAccountChecker;

class ETicket extends TAccountChecker
{
    public $mailFiles = "tbrands/it-283726942.eml, tbrands/it-46014718.eml, tbrands/it-665455967.eml, tbrands/it-836206711.eml";

    /**
     * @var string
     */
    private $pdfNamePattern = ".*pdf";
    private $reBody = ['TravelBrands file Number', 'ELECTRONIC TICKET'];
    private $reFrom = ['econfirmation@travelbrands.com'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->parseEmailPdf($text, $email);
                }
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->reBody[0]) !== false
                && stripos($text, $this->reBody[1]) !== false) {
                return true;
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

    private function parseEmailPdf($text, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $f->obtainTravelAgency();

        if (preg_match_all('/TravelBrands file Number [\s:]+([-A-Z\d]+)/', $text, $m)) {
            $m[1] = array_unique($m[1]);

            foreach ($m[1] as $conf) {
                $f->ota()
                    ->confirmation($conf, 'TravelBrands file Number');
            }
        }

        if (preg_match_all('/ELECTRONIC TICKET(?: |\n)([\s\S]+?)\n *(?:NOTES|Travel agency)/', $text, $blocks)) {
            foreach ($blocks[0] as $block) {
                if (preg_match('/\n *([A-Z]+[A-Z ]+\/[A-Z][A-z ]+?) {3,}(\d{3}\ ?\d{10}(?:\/\d+)?)(?: {2,}|\n)/', $block, $m)) {
                    $m[1] = preg_replace("/ (Miss|Mrs|Ms|Mr|Mstr|Dr)\s*$/i", '', $m[1]);
                    $m[1] = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/i", '$2 $1', $m[1]);

                    if (!in_array($m[1], array_column($f->getTravellers(), 0))) {
                        $f->general()
                            ->traveller($m[1], true);
                    }

                    if (!in_array($m[2], array_column($f->getTicketNumbers(), 0))) {
                        $f->issued()
                            ->ticket($m[2], true, $m[1]);
                    }
                }

                if (preg_match("/{$this->opt('FLIGHT NO.')}\s+{$this->opt('AIRLINE BOOKING NO.')}\s+FROM\s+TO\s+{$this->opt('FLIGHT TIME EQUIPMENT')}\s+SEAT\s+CLASS +FREE +BAGGAGE((?:\n.*?)+)NOTES/m", $block, $itineraryBlock)) {
                    $segments = array_filter(array_map('trim', $this->split("/(?:^|\n)(.+ \d+h ?\d+min)/", $itineraryBlock[1])));
                    // $this->logger->debug('$segments = '.print_r( $segments,true));

                    foreach ($segments as $segment) {
                        $tableSegment = $this->getTableSegment($segment);
                        // $this->logger->debug('$tableSegment = '.print_r( $tableSegment,true));

                        if (!empty($tableSegment)) {
                            $s = $f->addSegment();

                            if (preg_match('/\b((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(\d{1,4})\s*(?:Operated by\:?\s*(?<operator>.+))?$/s', $tableSegment[0], $m)) {
                                $s->airline()
                                    ->name($m[1])
                                    ->number($m[2]);

                                if (!empty($m['operator'])) {
                                    $s->airline()
                                    ->operator(trim(preg_replace('/\s+/', ' ', $m['operator'])));
                                }
                            }

                            $re = '/^\s*(?<name>[A-z].+?)\s\((?<code>[A-Z\d]{3})\)\s+(?<date>[\s\S]+?)(?<terminal>\s+-\s+\S.*?)?\s*$/s';

                            if (preg_match($re, $tableSegment[2], $m)) {
                                $s->departure()
                                    ->name($m['name'])
                                    ->code($m['code']);

                                if (!empty($m['terminal'])) {
                                    $s->departure()
                                        ->terminal(trim(preg_replace(['/\s*(?:TERMINAL|AEROGARE)\s*/i', '/\s+/'], ' ', $m['terminal']), '- '));
                                }

                                if (preg_match('/^\s*(\w+\,\s\d{1,2}\s[A-z]+\s\d{4})\s((?:0[0-9]|1[0-9]|2[0-3])h[0-5][0-9])\s*$/', $m['date'], $depDateTime)) {
                                    $s->departure()
                                        ->date(strtotime($depDateTime[1] . ' ' . str_replace("h", ":", $depDateTime[2])));
                                } elseif (preg_match('/^\s*(\w+\,\s\d{1,2}\s[A-z]+\s\d{4})\s+(\d{1,2})\s*$/', $m['date'], $depDateTime)) {
                                    $s->departure()
                                        ->date(strtotime($depDateTime[1] . ' ' . $depDateTime[2] . ':00'));
                                } elseif (preg_match('/^\s*\w+\,\s(\d{1,2}\s[A-z]+\s\d{4})\s((\d+\:\d+))\s*$/', $m['date'], $depDateTime)) {
                                    $s->departure()
                                        ->date(strtotime($depDateTime[1] . ' ' . $depDateTime[2]));
                                }
                            }

                            if (preg_match($re, $tableSegment[3], $m)) {
                                $s->arrival()
                                    ->name($m['name'])
                                    ->code($m['code']);

                                if (!empty($m['terminal'])) {
                                    $s->arrival()
                                        ->terminal(trim(preg_replace(['/\s*(?:TERMINAL|AEROGARE)\s*/i', '/\s+/'], ' ', $m['terminal']), '- '));
                                }

                                if (preg_match('/^\s*(\w+\,\s\d{1,2}\s[A-z]+\s\d{4})\s((?:0[0-9]|1[0-9]|2[0-3])h[0-5][0-9])\s*$/', $m['date'], $arrDateTime)) {
                                    $s->arrival()
                                        ->date(strtotime($arrDateTime[1] . ' ' . str_replace("h", ":",
                                                $arrDateTime[2])));
                                } elseif (preg_match('/^\s*(\w+\,\s\d{1,2}\s[A-z]+\s\d{4})\s+(\d{1,2})\s*$/', $m['date'], $arrDateTime)) {
                                    $s->arrival()
                                        ->date(strtotime($arrDateTime[1] . ' ' . $arrDateTime[2] . ':00'));
                                } elseif (preg_match('/^\s*\w+\,\s(\d{1,2}\s[A-z]+\s\d{4})\s((\d+\:\d+))\s*$/', $m['date'], $arrDateTime)) {
                                    $s->arrival()
                                        ->date(strtotime($arrDateTime[1] . ' ' . $arrDateTime[2]));
                                }
                            }

                            if (preg_match('/^Meal[\s:]+([A-a].+$)/m', $tableSegment[4], $meal)) {
                                $s->extra()
                                    ->meal($meal[1]);
                            }

                            if (preg_match('/(\d+[h:]\d+[a-z]+)/', $tableSegment[4], $duration)) {
                                $s->extra()
                                    ->duration($duration[1]);
                            }

                            if (preg_match('/(?:\d+[h:]\d+[a-z]+)\s(.*)/', $tableSegment[4], $aircraft)) {
                                $s->extra()
                                    ->aircraft($aircraft[1]);
                            }

                            if (preg_match('/^([A-Z]{1,2})\b/', $tableSegment[7], $bookingCode)) {
                                $s->extra()
                                    ->bookingCode($bookingCode[1]);
                            }

                            if (preg_match('/^\d{1,2}[A-Z]\s*$/', $tableSegment[6])) {
                                $s->extra()
                                    ->seat(trim($tableSegment[6]));
                            }

                            if (preg_match('/[A-Z\d]{5,6}/', $tableSegment[1], $m)) {
                                $s->airline()
                                    ->confirmation($m[0]);
                            }

                            $fsegments = $f->getSegments();

                            foreach ($fsegments as $fs) {
                                if ($fs->getId() !== $s->getId()) {
                                    if (serialize(array_diff_key($fs->toArray(), ['seats' => [], 'meals' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'meals' => []]))) {
                                        $seat = $s->getSeats();

                                        if (!empty($seat[0])) {
                                            $fs->extra()->seat($seat[0]);
                                        }
                                        $meal = $s->getMeals();

                                        if (!empty($meal[0])) {
                                            $fs->extra()->meal($meal[0]);
                                        }
                                        $f->removeSegment($s);

                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $email;
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }

    private function getTableSegment($segment)
    {
        $table = $this->splitCols($segment);
        $table[4] = $this->mergeCols($table[4], ' ' . $table[5]);
        array_splice($table, 8);

        return $table;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
