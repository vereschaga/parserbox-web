<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTripFlight extends \TAccountChecker
{
    public $mailFiles = "spirit/it-811756029.eml";
    public $subjects = [
        'Let’s Make Your Trip Count',
        'Add bags or upgrade and save on your flight to ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly.spirit-airlines.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".spirit-airlines.com/") or contains(@href,"save.spirit-airlines.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Copyright ©") and contains(normalize-space(),"Spirit Airlines")]')->length === 0
        ) {
            return false;
        }
        return $this->findSegments()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fly\.spirit\-airlines.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = 'contains(translate(.,"0123456789：. ","∆∆∆∆∆∆∆∆∆∆::"),"∆:∆∆")';
        return $this->http->XPath->query("//*[{$xpathTime} and count(*)=3 and *[2]/descendant::img]");
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'date' => '\b\d{1,2}\s*\/\s*\d{1,2}\s*\/\s*(?:\d{2}|\d{4})\b', // 09/14/24
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space(translate(.,':',''))='Confirmation Number']/ancestor::tr[1]");

        if (preg_match("/(Confirmation Number)[:\s]*([A-Z\d]{6})\s*$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $accountRow = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Free Spirit')]");

        if (preg_match("/^(Free Spirit #)[:\s]*(\d{4,40})(?:\s*\||$)/i", $accountRow, $m)) {
            $f->program()->account($m[2], false, null, $m[1]);
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("*[2]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s+(?<fNumber>\d+)$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $pattern = "/^(?<name>.{2,})\n(?<code>[A-Z]{3})\n(?<date>{$patterns['date']})[,\s]+(?<time>{$patterns['time']})/";

            $depInfo = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $depInfo, $m)) {
                $s->departure()
                    ->name($m['name'])->code($m['code'])
                    ->date(strtotime($m['time'], strtotime($m['date'])));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['name'])->code($m['code'])
                    ->date(strtotime($m['time'], strtotime($m['date'])));
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
}
