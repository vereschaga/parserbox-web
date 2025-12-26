<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirTicketPlainText extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-10985111.eml, jetstar/it-10985396.eml, jetstar/it-120659656.eml, jetstar/it-12641696.eml, jetstar/it-2158324.eml, jetstar/it-2583145.eml, jetstar/it-4434519.eml, jetstar/it-4434528.eml, jetstar/it-4434530.eml, jetstar/it-4436625.eml, jetstar/it-4445081.eml, jetstar/it-5577061.eml, jetstar/it-5878438.eml, jetstar/it-5938068.eml, jetstar/it-7918666.eml, jetstar/it-7967212.eml, jetstar/it-8003852.eml, jetstar/it-8005616.eml, jetstar/it-9769566.eml";

    private $subject = 'Jetstar Flight Itinerary';
    private $lang = 'en';

    private $detectBody = ['Check in for your flight at', 'Check-in options', 'On Jetstar international flights to and from', 'Unsubscribe from Jetstar Marketing Communications'];

    private $detectKey = 'Jetstar';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $plain = $parser->cacheGeneral->plain ?? $parser->getPlainBody();
        $body = preg_replace("#<https?://(.+\n){0,5}.*\.(gif|png)[^>]*>#", "", $plain);
        $body = str_replace(">", "", text($body));

        if (empty($body) && stripos($parser->getHtmlBody(), '<table') == false) {
            $body = strip_tags(html_entity_decode($parser->getHtmlBody()));
            $body = preg_replace("#\[[^\[]+\]#", '', $body);
        }
        $body = preg_replace("#\[cid:[^\[]+\]#", '', $body);

        $this->parseEmail($email, $body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $plain = $parser->cacheGeneral->plain ?? $parser->getPlainBody();
        $body = str_replace(">", "", text($plain));

        if (empty($body) && stripos($parser->getHtmlBody(), '<table') == false) {
            $body = strip_tags(html_entity_decode($parser->getHtmlBody()));
            $body = preg_replace("#\[[^\[]+\]#", '', $body);
        }
        $body = preg_replace("#\[cid:[^\[]+\]#", '', $body);

        if (stripos($body, $this->detectKey) !== false) {
            if (is_string($this->detectBody) && stripos($body, $this->detectBody) !== false) {
                return true;
            } elseif (is_array($this->detectBody)) {
                foreach ($this->detectBody as $s) {
                    if (stripos($body, $s) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'jetstar') !== false
        && isset($headers['subject']) && stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'jetstar') !== false;
    }

    private function parseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();

        $nbsp = chr(194) . chr(160);
        $text = str_replace($nbsp, ' ', $text);

        $recordLoc = $this->cutText('Booking Reference', 'Your Itinerary', $text);

        if (!empty($recordLoc) && preg_match('/\b([A-Z0-9]{6,7})\b/', $recordLoc, $m)) {
            $f->general()
                ->confirmation($m[1]);
        } else {
            $recordLoc = $this->cutText('Booking Reference', 'Your flights', $text);

            if (!empty($recordLoc) && preg_match('/\b([A-Z0-9]{6,7})\b/', $recordLoc, $m)) {
                $f->general()
                    ->confirmation($m[1]);
            }
        }

        $psng = $this->cutText('In-Flight Services', 'Flight Details', $text);

        $passengerTitles = '(?:mr|miss|mrs|mstr|dr|ms)';
        $travellers = [];

        $this->logger->warning($psng);

        if (!empty($psng) && preg_match_all('/(' . $passengerTitles . ' \w+\s+\w+[[:alpha:]\-\'\s]*?)(?:[ ]{3,}|\t|\n|\s*Add Seat)/iu', $psng, $m, PREG_SPLIT_NO_EMPTY)) {
            $travellers = array_unique($m[1]);
        } else {
            $psng = $this->cutText('Passenger:', 'Save time', $text);

            if (empty($psng)) {
                $psng = $this->cutText('Passenger:', 'Times are local times', $text);
            }

            $this->logger->warning($psng);

            if (!empty($psng) && preg_match_all('/(' . $passengerTitles . ' \w+\s+\w+[[:alpha:]\-\'\s]*?)(?:Choose seat|\()/siu', $psng, $m, PREG_SPLIT_NO_EMPTY)) {
                $travellers = array_unique(array_map(function ($s) {return trim(preg_replace("/\s+/", ' ', $s)); }, $m[1]));
            }
        }

        if (count($travellers) === 0) {
            $psng = $this->cutText('Passenger:', 'Save time', $text);

            if (preg_match_all('/Passenger:\s*(' . $passengerTitles . ' \w+\s+\w+[[:alpha:]\-\'\s]*?)(?:\s+[^\n]*Frequent Flyer number.*?)?\s+Seat:/siu', $text, $m, PREG_SPLIT_NO_EMPTY)) {
                $m[1] = array_unique($m[1]);
                $travellers = array_unique(array_map(function ($s) {return trim(preg_replace("/\s+/", ' ', $s)); }, $m[1]));
            }
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        $f->general()
            ->date(strtotime($this->re('/Booking date: (.+? \d{4})/i', $text)));

        if (preg_match_all('/Frequent\s*Flyer\s*number(?: |\n)([A-Z\d]{5,})\b/u', $psng, $m)) {
            $f->setAccountNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all('/\((\d+[A-z])\)/', $psng, $m)) {
            $seats = $m[1];
        }

        if (preg_match("#Payment\s+of\s+\D+(\d+.\d+)\s*([A-Z]{3})#", $text, $mat)) {
            $f->price()
                ->total($mat[1])
                ->currency($mat[2]);
        }

        $textForSegments = $this->cutText('Flight Number', 'Save time', $text);

        if ($textForSegments == null) {
            $textForSegments = $this->cutText('Flight Number', 'Passenger:', $text);
        }
        $segments = preg_split('/(?:\n\s*\w{3} \d+ \w+ \d{4}\n\s*\w+ Flight|\n\s*\w{3} \d+ \w+ \d{4}\n\s*.+\d+:\d+\n\s*Change flight)/', $textForSegments, -1, PREG_SPLIT_NO_EMPTY);
        array_shift($segments);

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            //$seg = [];
            $re = '/';
            $re .= '(?<AName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<FNum>\d+)(?:\s*\*)?(\[[^\]]*?\])?\s+(?<Aircraft>\w+ [A-Z0-9]+)\s+([^\n]+?\s+)?\D+Duration:\s+';
            $re .= '(?<Duration>\d{1,2}[^\d\W]+\s+\d{1,2}(min\s*|\w+\s+)).*\s+\w+\s+';
            $re .= '/isu';

            if (!empty($segment) && preg_match($re, $segment, $m)) {
                $s->airline()
                    ->name($m['AName'])
                    ->number($m['FNum']);

                $s->extra()
                    ->aircraft($m['Aircraft'])
                    ->duration(trim($m['Duration']));
            }

            $re2 = '#';
            $re2 .= '(?<DepDate>\d+ \w+ \d{4})\s+(?<DepTime>\d{4} hr / \d+:\d+ [ap]m|\d+:\d+\s*[ap]m / \d+:\d+)\s*\n\s*(?<DepName>.+?)\s*\n\s*.+?\s*';
            $re2 .= '(?<ArrDate>\d+ \w+ \d{4})\s+(?<ArrTime>\d{4} hr / \d+:\d+ [ap]m|\d+:\d+\s*[ap]m / \d+:\d+)\s*\n\s*(?<ArrName>.+?)\s*(?:\n|$)';
            $re2 .= '#ms';

            if (!empty($segment) && preg_match($re2, $segment, $m)) {
                $s->departure()
                    ->name(preg_replace('/\s+/', ' ', $m['DepName']));

                if (preg_match("#(.+)(?: - |, )(.*(?:Terminal|T).*)#", $s->getDepName(), $mat)) {
                    $s->departure()
                        ->name($mat[1])
                        ->terminal($mat[2]);
                }
                $s->departure()
                    ->date(strtotime($m['DepDate'] . ' ' . $this->re("#(\d+:\d+\s*[ap]m)#", $m['DepTime'])));

                $s->arrival()
                    ->date(strtotime($m['ArrDate'] . ' ' . $this->re("#(\d+:\d+\s*[ap]m)#", $m['ArrTime'])))
                    ->name($m['ArrName']);

                if (preg_match("#(.+)(?: - |, )(.*(?:Terminal|T).*)#", $s->getArrName(), $mat)) {
                    $s->arrival()
                        ->name($mat[1])
                        ->terminal($mat[2]);
                }
            } else {
                $re2 = '/';
                $re2 .= '(?<DepDate>\d+ \w+ \d{4})\s+.*\s+(?<DepTime>\d{1,2}:\d{2} (?:am|pm))\s+(?<DepName>.+)\s+';
                $re2 .= '\s*.+\s*\w*\s*\s*(?<ArrDate>\b\d+\b \w+ \d{4})\s+.*\s+';
                $re2 .= '(?<ArrTime>\d{1,2}:\d{2} (?:am|pm))\s+(?<ArrName>.+)(\s*)';
                $re2 .= '/';

                if (!empty($segment) && preg_match($re2, $segment, $m)) {
                    $s->departure()
                        ->name(preg_replace('/\s+/', ' ', $m['DepName']));

                    if (preg_match("#(.+)(?: - |, )(.*(?:Terminal|T).*)#", $s->getDepName(), $mat)) {
                        $s->departure()
                            ->name($mat[1])
                            ->terminal($mat[2]);
                    }
                    $s->departure()
                        ->date(strtotime($m['DepDate'] . ' ' . $m['DepTime']));

                    $s->arrival()
                        ->date(strtotime($m['ArrDate'] . ' ' . $m['ArrTime']))
                        ->name($m['ArrName']);

                    if (preg_match("#(.+)(?: - |, )(.*(?:Terminal|T).*)#", $s->getArrName(), $mat)) {
                        $s->arrival()
                            ->name($mat[1])
                            ->terminal($mat[2]);
                    }
                }
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate()) && !empty($s->getFlightNumber())) {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            if (count($segments) == 1 && isset($seats)) {
                $s->extra()
                    ->seats($seats);
            } else {
                $psng = $this->re('/Passenger:([\s\S]+)/', $segment);

                if (preg_match_all('/' . $passengerTitles . ' \w+\s+\w+[[:alpha:]\-\'\s]*?\((\d{1,3}[A-Z])\)/siu', $psng, $m, PREG_SPLIT_NO_EMPTY)) {
                    $s->extra()
                        ->seats($m[1]);
                } elseif (preg_match_all('/Seat:\s*\((\d{1,3}[A-Z])\)/siu', $psng, $m, PREG_SPLIT_NO_EMPTY)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = stristr(stristr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
