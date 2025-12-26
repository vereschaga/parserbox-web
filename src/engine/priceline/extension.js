var plugin = {
    hosts: {'travelb.priceline.com': true, 'travela.priceline.com': true, 'www.priceline.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.priceline.com/profile/#/mytrips';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        const form = $('form[id = "global-modal-sign-in-form"]:visible, form[action *= "/login"]:visible');
        if (form.length === 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (form.length === 1) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        return false;

        let name = $('#contactright div/b:contains(Name)').children('span').text()
            .trim()
            .replace(/\n*/g, '')
            .replace(/\s+/g, ' ');
        name = name + 'b';
        if (name == account.properties.Name)
            browserAPI.log("name: " + name + ' == ' + account.properties.Name);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name === account.properties.Name));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('#nav-menu-sign-out-link').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[id = "global-modal-sign-in-form"], form[action *= "/login"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('#remember-email').prop('checked', false);
        form.find('input[name = "first-name"], input[name = "identifier"], input[id = "username"]').val(params.account.login);
        form.find('input[name = "password"], input[name = "credentials.passcode"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button#button-sign-in, input[data-testid = "button-sign-in"], button[data-testid="button-sign-in"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('button[class *= "button-error"]:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};