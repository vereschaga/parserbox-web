
var plugin = {
    hosts: {'www.striderite.com': true},

    getStartingUrl: function (params) {
        return 'https://www.striderite.com/en/account';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var form = $('#dwfrm_login');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= Login-Logout]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var info = $('.rewards-status-msg').prev('div').text();
        var number = plugin.findRegExp(info, /\d{4}\s+(\d+)\s+\d+$/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start');
        document.location.href = 'https://www.striderite.com/on/demandware.store/Sites-striderite_us-Site/default/Login-Logout';
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function() {
            var form = $('#dwfrm_login');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                hidden = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'dwfrm_login_login').val('login');
                form.append(hidden);
                form.find('input[name *= "username"]').val(params.account.login);
                form.find('input[name *= "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors');
                document.forms['dwfrm_login'].submit();
            }
            else {
                browserAPI.log("Login form not found");
                provider.setError('Login form not found');
                throw 'Login form not found';
            }
        }, 1000)
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.error-message, .errormessage');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function (elem, regExp, required) {
        var matches = regExp.exec(elem);
        if (matches) {
            browserAPI.log('matched regexp: ' + regExp);
            result = util.trim(matches[1]);
        }
        else {
            browserAPI.log('failed regexp: ' + regExp);
            if (required)
                browserAPI.log('regexp not found');
            else
                result = null;
        }
        return result;
    }

}
