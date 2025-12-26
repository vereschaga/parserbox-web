<?php

namespace AwardWallet\Engine\panorama\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "panorama/it-74386300.eml";
    public $subjects = [
        '/your On Business statement is ready to view$/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'Thank you for choosing UIA' => ['Thank you for choosing UIA', 'Due to attractive fares our flights', 'Thank you for your recent interest in the flight below', 'found out that you were trying to buy a flight ticket'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyuia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'UKRAINE INTERNATIONAL AIRLINES')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing UIA'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('UIA Contacts'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyuia\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Complete the booking')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Booking Reference')]")->length === 0) {
            $email->setIsJunk(true);
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $this->logger->error($date);
        $in = [
            '#^\w+\,\s*(\d+)\s*(\w+)\,\s*([\d\:]+)$#',
        ];
        $out = [
            "$1 $2 $year, $3",
        ];

        return strtotime(preg_replace($in, $out, $date));
    }
}
