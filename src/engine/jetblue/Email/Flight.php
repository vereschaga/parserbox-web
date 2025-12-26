<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-29162655.eml, jetblue/it-74940178.eml, jetblue/it-75282577.eml";

    private $detects = [
        'en'  => 'Your departure time has changed',
        'en2' => 'Your flight is now scheduled to depart at',
        'en3' => 'Updated departure time',
    ];

    private $prov = 'jetblue';

    private $lang = 'en';

    private $from = '/[@\.a-z]+jetblue\.com/';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detects as $lang => $detect) {
            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        if ($pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Hello')][1]", null, true, '/Hello\s+(.+),/')) {
            $f->addTraveller($pax);
        }

        $xpath = "//p[contains(normalize-space(.), 'Flight #') and not(.//p)]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (preg_match('/([a-z]+)\s+Flight \#(\d+)/i', $root->nodeValue, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $date = '';

            if (preg_match('/(\w+ \d{1,2},\s*\d{2,4})/', $root->nodeValue, $m)) {
                $date = str_replace(',', ' ', $m[1]);
            }

            $depTime = $this->http->FindSingleNode("following-sibling::p[" . $this->contains(['New departure time', 'Your flight is now scheduled to depart at', 'Updated departure time']) . "]", $root, true,
                '/(?:New departure time|Your flight is now scheduled to depart at|Updated departure time)\s*\:\s*(\d{1,2}:\d{2}\s*[AP]M)/');

            if (empty($depTime) && preg_match('/\w+ \d{1,2},\s*\d{2,4} at (\d{1,2}:\d{2}(?: *[ap]m)?) /i', $root->nodeValue, $m)) {
                $depTime = $m[1];
            }

            if (preg_match('/\w+ \d{1,2},\s*\d{2,4}\s+(?:at\s+\d{1,2}:\d{2}(?: *[apAP][mM])?\s+)?(.+)\s*\(([A-Z]{3})\) to (.+)\s*\(([A-Z]{3})\)/', $root->nodeValue, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4])
                    ->noDate();
            }

            if (!empty($date) && !empty($depTime)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $depTime));
            }
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), '{$s}')";
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", '{$s}')";
        }, $field)) . ')';
    }
}
