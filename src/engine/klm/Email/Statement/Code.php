<?php

namespace AwardWallet\Engine\klm\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "klm/statements/it-261665923.eml, klm/statements/it-707430538.eml, klm/statements/it-707532930.eml, klm/statements/it-712473920.eml";
    public $subjects = [
        // de
        "Wir müssen nur Ihre Identität sicherstellen",
        // nl
        "We willen gewoon zeker weten dat u het bent",
        // it
        "Codice PIN di sicurezza",
        // en
        "We just need to know it’s you",
        // fr
        'Nous devons nous assurer de votre identité',
        // ro
        'Trebuie doar să ne asigurăm că ești tu',
        // es
        'Solo queremos confirmar que es usted',
        // zh
        '我們需要確認您的身份',
        '我们只是需要知道是您本人在操作',
        // ja
        'ご本人様確認が必要です',
        // pt
        'Só precisamos confirmar que é você',
        // ko
        '저희는 고객님이 맞다는 것을 확인해야 합니다',
        // ru
        'Нам необходимо убедиться, что это Вы.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "de" => [
            'Your PIN code:'                                              => 'Ihr PIN-Code:',
            'You received this one-time PIN code to verify your identity' => 'Sie haben diesen einmaligen PIN-Code erhalten, um Ihre Identität zu verifizieren',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "nl" => [
            'Your PIN code:'                                              => 'Uw pincode:',
            'You received this one-time PIN code to verify your identity' => 'U heeft deze eenmalige pincode ontvangen om uw identiteit te verifiëren',
            'KLM Royal Dutch Airlines'                                    => 'KLM Koninklijke Luchtvaart Maatschappij',
        ],
        "it" => [
            'Your PIN code:'                                              => 'Il suo codice PIN:',
            'You received this one-time PIN code to verify your identity' => 'Le abbiamo inviato questo codice PIN monouso per verificare la sua identità',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "en" => [
            'Your PIN code:'                                              => 'Your PIN code:',
            'You received this one-time PIN code to verify your identity' => 'You received this one-time PIN code to verify your identity',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "fr" => [
            'Your PIN code:'                                              => 'Votre code PIN :',
            'You received this one-time PIN code to verify your identity' => 'Vous avez reçu ce code PIN à usage unique afin de vérifier votre identité',
            'KLM Royal Dutch Airlines'                                    => 'KLM Lignes aériennes royales néerlandaises',
        ],
        "ro" => [
            'Your PIN code:'                                              => 'Codul tău PIN:',
            'You received this one-time PIN code to verify your identity' => 'Ai primit acest cod PIN unic pentru a îți verifica identitatea',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "es" => [
            'Your PIN code:'                                              => 'Su código PIN:',
            'You received this one-time PIN code to verify your identity' => 'Ha recibido este código PIN de un solo uso para verificar su identidad',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "zh" => [
            'Your PIN code:'                                              => ['您的 PIN 碼：', '您的 PIN 码：'],
            'You received this one-time PIN code to verify your identity' => ['您收到的這個一次性 PIN 碼，用於驗證您的身份',
                '您收到的这个一次性 PIN 码，用于验证您的身份。', ],
            'KLM Royal Dutch Airlines'                                    => ['荷蘭皇家航空公司', '荷兰皇家航空公司'],
        ],
        "ja" => [
            'Your PIN code:'                                              => 'お客様のPINコード：',
            'You received this one-time PIN code to verify your identity' => 'ご本人確認のため、このワンタイムPINコードをお送りいたしました',
            'KLM Royal Dutch Airlines'                                    => 'KLMオランダ航空会社',
        ],
        "pt" => [
            'Your PIN code:'                                              => 'Seu código PIN:',
            'You received this one-time PIN code to verify your identity' => 'Você recebeu esse código PIN de uso único para a verificação de sua identidade.',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
        "ko" => [
            'Your PIN code:'                                              => '고객님의 PIN 코드:',
            'You received this one-time PIN code to verify your identity' => '고객님은 본인 확인을 위해 이 일회용 PIN 코드를 받았습니다.',
            'KLM Royal Dutch Airlines'                                    => 'KLM 네덜란드 항공은',
        ],
        "ru" => [
            'Your PIN code:'                                              => 'Ваш ПИН-код:',
            'You received this one-time PIN code to verify your identity' => 'Вы получили одноразовый ПИН-код для подтверждения Вашей личности.',
            'KLM Royal Dutch Airlines'                                    => 'KLM Royal Dutch Airlines',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@service-flyingblue.com') !== false) {
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
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['KLM Royal Dutch Airlines']) && !empty($dict['Your PIN code:']) && !empty($dict['You received this one-time PIN code to verify your identity'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['KLM Royal Dutch Airlines'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your PIN code:'])}]/following::text()[{$this->contains($dict['You received this one-time PIN code to verify your identity'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]service\-flyingblue\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your PIN code:']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your PIN code:'])}]")->length > 0) {
                $this->lang = $lang;
                $code = $email->add()->oneTimeCode();

                $code->setCode($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your PIN code:'))}]",
                    null, true, "/^{$this->opt($this->t('Your PIN code:'))}\s*(\d+)$/"));

                $st = $email->add()->statement();

                $st->setNoBalance(true);

                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your PIN code:'))}]/preceding::text()[normalize-space()][1]",
                    null, true, "/^[[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]]$/u");

                if (!empty($name)) {
                    $st->addProperty('Name', trim($name, ','));
                }
            }
        }

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
        return 0;
    }

    public static function getEmailProviders()
    {
        return ['klm', 'airfrance'];
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
}
