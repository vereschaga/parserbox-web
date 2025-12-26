<?php

namespace AwardWallet\Engine\gwrailway\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainTicket extends \TAccountChecker
{
    public $mailFiles = "gwrailway/it-41768000.eml";

    public $lang;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//b[contains(normalize-space(.), 'Your GWR booking confirmation')]")->length > 0
            || stripos($parser->getHTMLBody(), 'Thanks for booking with GWR.com') !== false
            || stripos($parser->getHTMLBody(), 'GWR.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@gwr.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@gwr.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Your GWR booking confirmation') !== false;
    }

    protected function parseEmail(Email $email)
    {
        $t = $email->add()->train();

        $confirmation = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Your reference number')]", null, true, "#\w+\s+is\s+([\w\d]+)#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space(.)='Your reference number is']/following::text()[string-length(normalize-space(.))>1][1]");
        }

        $t->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Hi ')]", null, true, "#Hi (.*?),$#");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode('//text()[contains(normalize-space(.), ", here\'s everything you need")]', null, true, "#^(.+){$this->opt(", here's everything you need")}#");
        }
        $t->general()
            ->traveller($traveller);

        $XPath = "//text()[starts-with(normalize-space(.), 'departs ') or starts-with(normalize-space(.), 'Suggested service departs ')]";
        $roots = $this->http->XPath->query($XPath);

        foreach ($roots as $root) {
            $s = $t->addSegment();

            if (!$date = $this->http->FindSingleNode("./preceding::text()[contains(., 'journey')][string-length(normalize-space(.))>1][1]",
                $root, true, "#(\d+\s+\w+\s+\d{4})#")
            ) {
                $date = $this->http->FindSingleNode("./preceding::text()[contains(., 'journey')][string-length(normalize-space(.))>1][1]/following::text()[normalize-space()!=''][1]",
                    $root, true, "#(\d+\s+\w+\s+\d{4})#");
            }

            $pattern = "#departs\s+(?<depName>.*?)\s+at\s+(?<depTime>\d+:\d+)";
            $pattern .= "\s+.*?\s+to\s+station\s+(?<arrName>.*?)\s+arrives[at\s]+(?<arrTime>\d+:\d+)";
            $pattern .= "(?:\s+\(.*?Coach\s*:\s*(?<m>\w{1})\s+Seats\s*:\s*(?<n>[\d, ]+).+\))?#msi";

            if (preg_match($pattern, $this->http->FindSingleNode(".", $root), $m)) {
                $s->departure()
                    ->date(strtotime($date . ' ' . $m['depTime']))
                    ->name($m['depName'] . ', Europe');

                $s->arrival()
                    ->date(strtotime($date . ' ' . $m['arrTime']))
                    ->name($m['arrName'] . ', Europe');

                if (!empty($m['n']) && !empty($m['n'])) {
                    $seats = array_map('trim', explode(",", $m['n']));

                    foreach ($seats as &$seat) {
                        $seat = $m['m'] . $seat;
                    }
                    $s->extra()
                        ->seats($seats);
                }
                $s->extra()
                    ->noNumber();
            }
        }

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Your Payment')]");
        preg_match("#.*charged (.*)#", $total, $math);

        if (preg_match("#(\S{1,2})\s*([\d.]+)#", $math[1], $var)) {
            $t->price()
                ->total($var[2])
                ->currency($this->currency($var[1]));
        }

        $earned = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You collected')]", null, false,
            "#You\s+collected\s+(\d+ points)#");

        if ($earned) {
            $t->setEarnedAwards($earned);
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        $s = trim($s);
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if (preg_match("/^[A-Z]{3}$/", $s)) {
            return $s;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
