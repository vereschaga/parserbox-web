var plugin = {

    hosts: {
        'shop.safeway.com': true, 'auth.safeway.com': true, 'rss.safeway.com': true, 'www.safeway.com': true,
        'shop.vons.com': true, 'auth.vons.com': true, 'rss.vons.com': true, 'www.vons.com': true,
        'shop.tomthumb.com': true, 'auth.tomthumb.com': true, 'rss.tomthumb.com': true, 'www.tomthumb.com': true,
        'albertsons.okta.com': true,
        'shop.acmemarkets.com': true, 'auth.acmemarkets.com': true, 'rss.acmemarkets.com': true, 'www.acmemarkets.com': true,
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        var domain = params.account.login2;
        if (domain == '')
            domain = 'safeway';
        return 'https://www.' + domain + '.com/account/sign-in.html';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        setTimeout(function () {
            provider.setNextStep('login', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000);
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
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
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('#idform:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        var domain = params.account.login2;
        if (domain == '')
            domain = 'safeway';
        if ($('a[href="https://auth.' + domain + '.com/opensso/UI/Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('#sign-in-profile-text').text(), /Hi\s*([^<]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
        && (typeof(account.properties.Name) != 'undefined')
        && (account.properties.Name != '')
        && (account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            // if (provider.isMobile)
            //     $('a#mobile-register-logout').get(0).click();
            // else {
                //.auth-flyout-signout
                // var domain = params.account.login2;
                // if (domain == '')
                //     domain = 'safeway';

                //if (domain === 'tomthumb') {
                    var logout = $('a.auth-flyout-signout:contains("Sign Out")');
                    if (logout.length)
                        logout.get(0).click();
                //} else
                //    document.location.href = 'https://albertsons.okta.com/login/signout?fromURI=https://www.' + domain + '.com/bin/' + domain + '/logout';
            // }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#idform');
        if (form.length) {
            browserAPI.log("submitting saved credentials");
            $('input[name="userId"]', form).val(params.account.login);
            $('input[name="inputPassword"]', form).val(params.account.password);
            util.sendEvent($('input[name="userId"]', form).get(0), 'input');
            util.sendEvent($('input[name="inputPassword"]', form).get(0), 'input');

            return provider.setNextStep('checkLoginErrors', function () {
                $('#btnSignIn').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 3000);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("p.help-block span");
        if (errors.length && '' != util.trim(errors.text()))
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
