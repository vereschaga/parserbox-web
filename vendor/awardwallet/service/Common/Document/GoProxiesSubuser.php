<?php

namespace AwardWallet\Common\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations\Field;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;

/**
 * @Document
 */
class GoProxiesSubuser
{

    /** @Id(strategy="NONE", type="string") */
    private string $username;
    /**
     * @Field(type="string")
     */
    private string $password;

    public final function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

}