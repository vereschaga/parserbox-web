<?php

namespace AwardWallet\Common\Parsing\Web\Captcha;

interface CaptchaProviderInterface
{

    public function getId() : string;
    /**
     */
    public function recognize(string $key, Context $context, array $options = []): CaptchaProviderResult;

}