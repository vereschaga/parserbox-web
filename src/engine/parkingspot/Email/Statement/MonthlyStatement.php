<?php

namespace AwardWallet\Engine\parkingspot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "parkingspot/statements/it-62834183.eml, parkingspot/statements/it-78140595.eml, parkingspot/statements/it-78332082.eml";
    public $headers = [
        '/^Here is your account statement$/',
    ];

    public $from = '/[@.]email\.theparkingspot\.com$/';

    public $provDetect = 'Spot Club';

    public $body = [
        'Thank you for being a Spot Club member',
        'Member Status',
        'Earning Rate',
        'Total Redeemable Spot Club Points',
        'Thank you for being a Spot Club member',
        'This email was sent to:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.theparkingspot.com') !== false) {
            foreach ($this->headers as $header) {
                if (preg_match($header, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$this->provDetect}')]")->length > 0) {
            foreach ($this->body as $body) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$body}')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for being a Spot Club member'))}]", null, true, "/\s*(\w+)\s*\,?\!?$/");

        if (empty($name) || $name == 'member') {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Spot Club Statement for'))}]/following::text()[normalize-space()][1]");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/\s*(\S+[@]\S+\.\S+)\s*/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/following::text()[contains(normalize-space(), '@')][1]", null, true, "/\s*(\S+[@]\S+\.\S+)\s*/");
        }

        if (!empty($login)) {
            $st->addProperty('Login', $login);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Redeemable Spot Club Points'))}]/following::text()[normalize-space()][1]");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL POINTS'))}]/following::text()[normalize-space()][1]");
        }
        $st->setBalance(str_replace(',', '', $balance));

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Card #')]", null, true, "/{$this->opt($this->t('Card #'))}\s*(\d{16,})/");

        if (!empty($number)) {
            $st->setNumber($number);
        } else {
            //Card # 6220 **** **** **** ***
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Card #')]", null, true, "/{$this->opt($this->t('Card #'))}\s*(\d+)[\s*]+$/");

            if (!empty($number)) {
                $st->setNumber($number)->masked('right');
            }
        }

        /*$status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'stays and') and contains(normalize-space(), 'points for')]/preceding::text()[normalize-space()][1]");
        if (!empty($status))
            $st->addProperty('Status', $status);*/

        $dateBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'TOTAL POINTS')]/following::text()[contains(normalize-space(), 'since')][1]", null, true, "/{$this->opt($this->t('since'))}\s*(\d+\/\d+\/\d+)/");

        if (!empty($dateBalance)) {
            $st->setBalanceDate(strtotime($dateBalance));
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
