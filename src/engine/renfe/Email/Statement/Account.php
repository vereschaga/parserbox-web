<?php

namespace AwardWallet\Engine\renfe\Email\Statement;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class Account extends \TAccountChecker
{
    public $mailFiles = "renfe/statements/it-636354466.eml, renfe/statements/it-636498411.eml";

    public $lang;
    public static $dictionary = [
        'es' => [
        ],
    ];

    private $detectFrom = "ventaonline@renfe.es";
    private $detectSubject = [
        // es
        'Confirmación cambio de contraseña Renfe',
        'Renfe te da la Bienvenida al Programa Más Renfe',
        'Solicitud reseteo de contraseña Renfe',
    ];
    private $detectBody = [
        'es' => [
            'Te confirmamos que los datos de acceso a tu Área Privada de Renfe se han modificado correctamente',
            'Ahora eres parte de esta gran familia, ya puedes aprovechar todos sus beneficios.',
            'Has solicitado restablecer la contraseña de acceso a tu Área Privada de Renfe',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]renfe\.es$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Renfe') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.renfe.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['www.renfe.com'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $detectedSubject = false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        $this->assignLang();

        if ($detectedSubject === false || empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $st = $email->add()->statement();

        $st->setMembership(true);

        $number = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Tu número Más Renfe:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number);

            $st->setNoBalance(true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
