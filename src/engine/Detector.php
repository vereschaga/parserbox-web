<?php

namespace AwardWallet\Engine;

abstract class Detector extends \TAccountChecker
{
    final public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    final public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && $this->checkAddress($headers['from']);
    }

    final public function detectEmailFromProvider($from)
    {
        return $this->checkAddress($from);
    }

    final public static function getEmailTypesCount()
    {
        return 0;
    }

    final public static function getEmailLanguages()
    {
        return [];
    }

    final public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, \AwardWallet\Schema\Parser\Email\Email $email)
    {
        return $email;
    }

    abstract protected function getFrom(): array;

    private function checkAddress($from)
    {
        foreach ($this->getFrom() as $check) {
            if ((stripos($check, '/') === 0 || stripos($check, '#') === 0) && preg_match($check, $from) || stripos($from, $check) !== false) {
                return true;
            }
        }

        return false;
    }
}
