<?php

namespace AwardWallet\Engine\national\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithMemberNumber extends \TAccountChecker
{
    public $mailFiles = "national/statements/it-62794312.eml, national/statements/it-62997563.eml, national/statements/it-63100776.eml";
    public $from = '/[@.]nationalcar\.com$/';

    public $subjects = [
        '/^[A-Z]+\,.+Emerald\sClub$/',
        '/^Select Your Emerald Club Preferences$/',
    ];

    public $provDetect = 'National Car Rental';

    public $lang = '';

    public static $dictionary = [
        "en" => [
            "Member Number:"   => ['Member Number:'],
            'Member Name:'     => 'Member Name:',
            'EmeralClubDetect' => ['Please take a moment to choose your rental preferences'],
        ],
        "pt" => [
            "Member Number:" => 'Número de membro:',
            'Member Name:'   => 'Nome do membro:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectLang() !== true) {
            $this->logger->error('Lang - Not Found!');

            return false;
        }

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//td/p[{$this->contains($this->t('Member Number:'))}]/following::p[1]", null, true, "/^(\d+)$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Member Number:')]", null, true, "/^{$this->opt($this->t('Member Number:'))}\s*(\d+)$/");
        }
        $st->addProperty('Number', $number);

        $name = $this->http->FindSingleNode("//td/p[{$this->contains($this->t('Member Name:'))}]/following::p[1]", null, true, "/^[A-Z\s]+$/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $st->setNoBalance(true);

        $this->logger->notice($parser->getSubject());
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@nationalcar.com') !== false) {
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
        if ($this->detectLang() == true) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$this->provDetect}')]")->length > 0) {
                if (
                    (
                        $this->http->XPath->query("//text()[contains(normalize-space(), 'Emerald Club')]")->count() > 0
                        || $this->http->XPath->query("//text()[{$this->contains($this->t('EmeralClubDetect'))}]")->count() > 0
                    )
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Member Number:'))}]")->count() > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$dictionary as $lang => $detects) {
            foreach ($detects as $detect) {
                if (is_array($detect)) {
                    foreach ($detect as $word) {
                        if (stripos($body, $word) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
