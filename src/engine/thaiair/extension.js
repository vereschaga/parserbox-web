var plugin = {

    hosts: {'www.thaiairways.com': true},

    getStartingUrl: function (params) {
        return 'https://www.thaiairways.com/en_TH/rop/index.page';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        } else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div#btn_logout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div#navBar-entry-user').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('div#btn_logout')[0].click();
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name = "viewbookingform"]');
        if (form.length > 0) {
            form.find('input[name = "pnrCode"]').val(properties.ConfNo);
            form.find('input[name = "lastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type = "submit"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0      ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.thaiairways.com/en/Manage_My_Booking/My_Booking.page';
            });
            return;
        }

        var form = $('div#navBar-entry-user');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "member_id"]').val(params.account.login);
            form.find('input[id = "member_pin"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[id = "btn_login"]')[0].click();

                setTimeout(function() {
                    provider.setError("Invalid credentials");
                }, 10000);
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        //var errors = $('div#flashMessage');
        //if (errors.length > 0)
        //    provider.setError(errors.text());
        //else
            provider.complete();
    }

};
