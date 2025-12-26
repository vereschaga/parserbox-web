<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers rapidrewards/InfoPlainText (in favor of mileageplus/FlightPlain)

class FlightPlain extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-27629306.eml, mileageplus/it-27635465.eml, mileageplus/it-28182760.eml";

    private $lang = 'en';

    private $detects = [
        'en' => 'United Airlines Flight Information calendar attachment',
    ];

    private $from = '/[@\.]united\.com/';

    private $prov = 'United Airlines';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    private function parseEmail(Email $email, string $text): void
    {
        $f = $email->add()->flight();

        $text = str_replace(['<br>', '<br />'], ['', ''], $text);

        $itineraryInfo = $this->cutText('United Airlines Flight Information calendar attachment', 'This information is subject to change', $text);

        if ($conf = $this->re('/Confirmation number\s*\:\s*(\w+)/', $text)) {
            $f->general()
                ->confirmation($conf);
        }

        $segments = $this->splitter('/(Flight\s*\:\s*[A-Z\d]{2}\s*\d+)/', $itineraryInfo);

        if (0 === count($segments)) {
            $this->logger->debug('Segments did not found');
        }
        $paxs = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $segment = preg_replace("#\n[>\s]*#", "\n", $segment);

            if (preg_match('/Flight\s*\:\s*([A-Z\d]{2})\s*(\d+)/', $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $re = '/'
                . 'Depart\s*:\s*(?<dcode>[A-Z]{3})\s*-\s*(?<dname>.+)\s+on\s+(?<ddate>[-[:alpha:]]+[ ]*,[ ]*[[:alpha:]]+[ ]+\d{1,2}[ ]+\d{2,4}) at (?<dtime>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)'
                . '\s*Arrive\s*:\s*(?<acode>[A-Z]{3})\s*-\s*(?<aname>.+)\s+on\s+(?<adate>[-[:alpha:]]+[ ]*,[ ]*[[:alpha:]]+[ ]+\d{1,2}[ ]+\d{2,4}) at (?<atime>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)'
                . '/u';

            if (preg_match($re, $segment, $m)) {
                $s->departure()
                    ->code($m['dcode'])
                    ->name($m['dname'])
                    ->date2($m['ddate'] . ' ' . $this->normalizeTime($m['dtime']));
                $s->arrival()
                    ->code($m['acode'])
                    ->name($m['aname'])
                    ->date2($m['adate'] . ' ' . $this->normalizeTime($m['atime']));
            }

            if ($cabin = $this->re('/Fare class\s*:\s*(.+?)(?:\n|\s*Meal)/', $segment)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if ($meal = $this->re('/Meal\s*:\s*(.+)/', $segment)) {
                $s->extra()
                    ->meal($meal);
            }

            if ($pax = $this->re('/Travelers\s*:\s*(.+)/', $segment)) {
                $paxs[] = $pax;
            }
        }

        $paxs = array_filter(array_unique($paxs));

        foreach ($paxs as $pax) {
            if ($travelers = explode(',', $pax)) {
                foreach ($travelers as $traveler) {
                    $f->addTraveller($traveler);
                }
            } else {
                $f->addTraveller($pax);
            }
        }
    }

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1];
        } // 21:51 PM    ->    21:51
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25

        return $s;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
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
}
