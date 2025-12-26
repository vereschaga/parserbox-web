var plugin = {
    // всегда оставлять вкладку открытой, только для дебага!
    //keepTabOpen: true,
    hosts: {
        'hhonors1.hilton.com': true,
        'secure.hilton.com': true,
        'www.hilton.com': true,
        'www3.hilton.com': true,
        'secure3.hilton.com': true,
        'hhonors3.hilton.com': true,
        'hiltonhonors3.hilton.com': true,
        '.hilton.com': true,
        'www.hiltonhotels.de': true,
        'www.hiltonhotels.it': true
    },

    getStartingUrl: function (params) {
        return 'https://www.hilton.com/en/hilton-honors/guest/my-account/';
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
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        browserAPI.log('Current URL: ' + document.location.href);
        if ($('button:contains("Sign Out")').length > 0) {
            provider.logBody("loggedInPage");
            browserAPI.log("logged in");
            return true;
        }
        let frame = $('iframe#hiltonLoginFrame, iframe[data-e2e="loginIframe"]');
        browserAPI.log("frame -> " + frame.length);
        if (frame) {
            browserAPI.log("frame form -> " + frame.contents().find('input[name = "username"]').closest('form').length);
            // browserAPI.log("frame recaptcha -> " + frame.contents().find('iframe[src *= "recaptcha/api2/anchor"]').length);
        }
        if (
            frame.length > 0
            && frame.contents().find('input[name = "username"]').closest('form').length > 0
            // && frame.contents().find('iframe[src *= "recaptcha/api2/anchor"]').length > 0
        ) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('button[aria-controls = "userMenu"]').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        let logout = $('a[href *= "logout"]:visible, button:contains("Sign Out"):visible, button:contains("Sign out"):visible');
        if (logout.length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        // provider error
        let errors = plugin.checkErrors();
        if (errors.length > 0) {
            provider.setError([errors.text(), util.errorCodes.providerError], true);
            return null;
        }
        // No server is available to handle this request.
        if (
            frame.length > 0
            && frame.contents().find('body:contains("No server is available to handle this request.")').length > 0
        ) {
            provider.setError([util.providerErrorMessage, util.errorCodes.providerError], true);
            return null;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        browserAPI.log('Current URL: ' + document.location.href);
        var name = util.findRegExp($('#welcome').text(), /Welcome\s([^<]+)/i);
        browserAPI.log("name: " + name);
        if (!name) {
            name = util.findRegExp($('section>section>section>section:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        if (!name) {
            name = util.findRegExp($('header button:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        var number = util.findRegExp($('span:contains("Hilton Honors number") + span:eq(0)').parent().first().contents().eq(2).text(), /#\s*(\d+)\s*/i);
        if (!number || number === '')
            number = util.findRegExp($('strong:contains("Member number:") + span:eq(0)').text(), /\s*(\d+)\s*/i);
        browserAPI.log("number: " + number);
        if (name)
            name = name.toLowerCase();
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name && number
            && (account.properties.Name.toLowerCase().indexOf(name) !== -1 || name.indexOf(account.properties.Name.toLowerCase()) !== -1)
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (util.filter(number) === account.properties.Number) );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.barclaycardus.com/servicing/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let frame = $('iframe#hiltonLoginFrame, iframe[src="https://www.hilton.com/en/auth2/guest/login/"]').contents();
        browserAPI.log(">>> success code update");
        let form = frame.find('input#username').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");

            alert('submitting saved credentials')
        }
    },

    enterPassword: function (params) {
        browserAPI.log("enterPassword");
        var form = $('form[name=loginForm]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('#loginButton-button').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.passwordFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.errorIndicator');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}