<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOrCancellation extends \TAccountChecker
{
    public $mailFiles = "sixt/it-678222078.eml";
    public $subjects = [
        '/Confirmation of cancellation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sixt.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'thank you for your reservation with Sixt')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Vehicle category'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to your next reservation!'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sixt\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->rental();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Confirmation of cancellation')]")->length > 0) {
            $r->general()
                ->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\,/");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller)
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation number')]/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", null, true, "/([\d\-]{8,})/"));
        }

        $carModel = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][1]");

        if (!empty($carModel)) {
            $r->setCarModel($carModel);
        }

        $carType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number'))}]/ancestor::tr[1]/following::tr[2]/descendant::td[normalize-space()][1][{$this->contains($this->t('|'))}]");

        if (!empty($carType)) {
            $r->setCarType($carType);
        }

        $infoPath = "//text()[starts-with(normalize-space(), 'Pick-up')]/ancestor::tr[1]/";

        $r->pickup()
            ->location($this->http->FindSingleNode($infoPath . "following::tr[1]/descendant::td[normalize-space()][1]"));

        $r->dropoff()
            ->location($this->http->FindSingleNode($infoPath . "following::tr[1]/descendant::td[normalize-space()][2]"));

        $dateStart = $this->http->FindSingleNode($infoPath . "following::tr[2]/descendant::td[normalize-space()][1]");
        $startYear = $this->http->FindSingleNode($infoPath . "following::tr[3]/descendant::td[normalize-space()][1]", null, true, "/^(\d{4})\s/");

        $dateEnd = $this->http->FindSingleNode($infoPath . "following::tr[2]/descendant::td[normalize-space()][2]");
        $endYear = $this->http->FindSingleNode($infoPath . "following::tr[3]/descendant::td[normalize-space()][2]", null, true, "/^(\d{4})\s/");

        $r->pickup()
            ->date($this->normalizeDate($dateStart . ', ' . $startYear));

        $r->dropoff()
            ->date($this->normalizeDate($dateEnd . ', ' . $endYear));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s+(\d+)\s+([\d\:]+)\,\s*(\d{4})$#u", //Feb 12 15:00, 2024
        ];
        $out = [
            "$2 $1 $4, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
