var plugin = {

    hosts: {'www.huggies.com': true, 'www.registration.kimberly-clark.com': true, 'www.huggies.ru': true},

    getStartingUrl: function (params) {
        return 'https://www.huggies.com/en-us/?modal=true';
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
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= LogOut]:visible').text() || $('span#scphheader_0_ctl05_RewardsBalanceSpan:visible').find('span.points').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form.consumer-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // TODO: Name not equals Full Name
        return false;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('div[id *= divAuthenticated]').find('a#lnkAction').text(), /Hi\s*([^!]*)!/i );
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('a:contains("Sign Out")');
            if (logout.length > 0) {
                browserAPI.log("click 'Sign Out'");
                logout.eq(0).get(0).click();
            }
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.consumer-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "consumer_email"]').val(params.account.login);
            form.find('input[name = "consumer_password"]').val(params.account.password);

            var login = setTimeout(function () {
                var captcha = form.find('iframe[src ^= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                if (captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        }
                        counter++;
                    }, 1000);

                    provider.setNextStep('checkLoginErrors', function () {
                    });
                } else {
                    provider.setNextStep('checkLoginErrors', function () {
                        form.submit();
                    });
                }
            }, 1000);
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var error = $('.signin label.error:first').contents()
            .filter(function () {
                return this.nodeType === 3; //Node.TEXT_NODE
            });
        if (error.length > 0 && util.trim(error.text()) !== '')
            provider.setError(error.text());
        else
            provider.complete();
    }
};