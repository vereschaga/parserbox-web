<?php

namespace AwardWallet\Engine\ural\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "webmaster@uralairlines.ru",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Регистрация в программе "Крылья"',
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "FirstName",
            "LastName",
            "BirthDate",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        if ($login = re("#Регистрационный номер в программе Крылья\s+(\d+)#", $this->text())) {
            $result['Login'] = beautifulName($login);
        }

        if ($name = re("#Имя\s+(\S+)#", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        if ($name = re("#Фамилия\s+(\S+)#", $this->text())) {
            $result['LastName'] = beautifulName($name);
        }

        if ($date = re("#Дата рождения\s+([\d\.]+)#", $this->text())) {
            $result['BirthDate'] = date('d M Y', strtotime($date));
        }

        if ($pass = re("#Pin-code\s+(\S+)#", $this->text())) {
            $result['Password'] = $pass;
        }

        return $result;
    }
}
