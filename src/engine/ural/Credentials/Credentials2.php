<?php

namespace AwardWallet\Engine\ural\Credentials;

class Credentials2 extends \TAccountCheckerExtended
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
            'Информация о Вашем бонусном счете',
            'Уральские авиалинии',
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        if ($login = re("#Ваш\s+номер\s+карты\s*:\s+(\d+)#", $this->text())) {
            $result['Login'] = beautifulName($login);
        }

        if ($name = re("#Добрый день,\s+([A-Za-zА-Яа-я]+\s+[A-Za-zА-Яа-я]+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        }

        if ($name = re("#Уважаемый\s+([A-Za-zА-Яа-я]+\s+[A-Za-zА-Яа-я]+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        }

        return $result;
    }
}
