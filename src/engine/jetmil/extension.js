var plugin = {

    hosts: {
        'atlasglb.com': true,
        'www.atlasglb.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.atlasglb.com/en/atlasmiles';
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
                    if (document.location.href !== 'https://www.atlasglb.com/en/atlasmiles/my-Account') {
                        provider.setNextStep('start', function () {
                            document.location.href = 'https://www.atlasglb.com/en/atlasmiles/my-Account';
                        });
                        return;
                    }
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        var login = $('#loginwrapper:visible');
        if (login.length) {
            browserAPI.log('isLoggedIn: false');
            login.get(0).click();
            return false;
        }
        if ($('#milesUsername:visible').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return true;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('input[name="cardNo"]').val();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
        && (typeof(account.properties.CardNumber) != 'undefined')
        && (account.properties.CardNumber != '')
        && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            provider.eval('gf.milesLogout()');
        });
    },

    login: function (params) {
        browserAPI.log('login');
        setTimeout(function () {
            var form = $('#atlasmiles:visible');
            if (form.length) {
                browserAPI.log("submitting saved credentials");
                $('input[name="milesCardNo"]', form).val(params.account.login);
                $('input[name="milesPassword"]', form).val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    $('button[type="button"][onclick="gf.milesSignin()"]', form).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 3000);
                });

            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('#standart-md-message:visible');
        if (errors.length && '' != util.trim(errors.text())) {
            provider.setError(errors.text());
        } else {
            provider.complete();
        }
    }

};