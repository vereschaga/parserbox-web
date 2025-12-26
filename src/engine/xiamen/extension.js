var plugin = {
    keepTabOpen: false,
    hosts: {'/\\w+\\.xiamenair\\.com/': true},

    getStartingUrl: function (params) {
        return 'https://ffp.xiamenair.com/en-US/Login/Login';
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadAccount: function (params) {
        browserAPI.log('loadAccount');
        provider.setNextStep('start', function () {
            document.location.href = 'https://ffp.xiamenair.com/en-US/MyAccount/Index';
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (document.location.href.indexOf('/MyAccount/Index') === -1) {
                        plugin.loadAccount(params);
                        return;
                    }
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div.login-panel').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/login/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.CardNo) !== 'undefined'
            && account.properties.CardNo != ''
            && $('span:contains("' + account.properties.CardNo + '")').length;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://ffp.xiamenair.com/en-US/login/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('div.login-panel .member-content:eq(0)');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input.account').val(params.account.login);
            form.find('input.password').val(params.account.password);
            form.find('#privacy-checkbox-1').click();
            provider.setNextStep('checkLoginErrors', function () {
                if (provider.isMobile) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    setTimeout(function () {
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }, 60000);
                } else {
                    setTimeout(function () {
                        var captcha = form.find('img.code-img');
                        provider.captchaMessageDesktop();
                        //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                        if (captcha.length > 0) {
                            browserAPI.log("waiting...");

                            var captchaDiv = document.createElement('div');
                            captchaDiv.id = 'captchaDiv';
                            document.body.appendChild(captchaDiv);

                            var canvas = document.createElement('CANVAS'),
                                ctx = canvas.getContext('2d'),
                                img = document.getElementsByClassName('code-img')[0];

                            canvas.height = img.height;
                            canvas.width = img.width;
                            ctx.drawImage(img, 0, 0);
                            var dataURL = canvas.toDataURL('image/png');
                            //console.log("dataURL: " + dataURL);
                            browserAPI.send("awardwallet", "recognizeCaptcha", {
                                captcha: dataURL,
                                "extension": "jpg"
                            }, function (response) {
                                browserAPI.log(JSON.stringify(response));
                                if (response.success === true) {
                                    browserAPI.log("Success: " + response.success);
                                    form.find('input.code.code1').val(response.recognized);
                                    form.find('#login-btn-1').click();
                                }// if (response.success === true))
                                if (response.success === false) {
                                    browserAPI.log("Success: " + response.success);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }// if (response.success === false)
                            });
                        }// if (captcha.length > 0)
                        else {
                            browserAPI.log("captcha is not found");
                            form.find('#login-btn-1').click();
                        }
                    }, 1000);
                }
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-msg:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://et.xiamenair.com/xiamenair/myorder/MyOrderList.action';
            });
            return;
        }
        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
