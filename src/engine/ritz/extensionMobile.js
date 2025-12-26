var plugin = {
	autologin: {
//		url: "https://mobile.ritzcarlton.com/mt/rewards.ritzcarlton.com/ritz/ritzSignIn.mi",
		url: "https://mobile.ritzcarlton.com/mt/rewards.ritzcarlton.com/clearRememberMe.mi",

        start: function () {
			if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if ($('#sign-in-form').length > 0) {
                return false;
            }

            if ($('a[href *= "signOut"]').length > 0) {
                return true;
            }
            if ($('#my-account-summary').length > 0) {
                return true;
            }
            throw "can't determine login state";
        },

        login: function () {
            var form = $('#sign-in-form');
            if(form.length == 1){
                form.find('input[name = "userID"]').val(params.login);
                form.find('input[name = "password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    HTMLFormElement.prototype.submit.call(form.get(0));
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('div:contains("' + params.properties.Number + '")').length > 0)
        },

        checkLoginErrors: function () {
            var error = $('.signInError');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                this.finish();
            }
        },

        logout: function (){
            api.setNextStep('toLoginPage', function () {
                if ($('a[href *= "signOut"]').length > 0)
                    document.location.href = $('a[href *= "signOut"]').attr('href');
                else
                    document.location.href = 'https://mobile.ritzcarlton.com/mt/rewards.ritzcarlton.com/SignOutServlet?logoutExitPage=%2flogout.mi';
            });
        },

        toLoginPage: function (){
            api.setNextStep('login', function (){
                document.location.href = 'https://mobile.ritzcarlton.com/mt/rewards.ritzcarlton.com/ritz/ritzSignIn.mi';
            })
        },

        finish: function () {
            api.complete();
        }
	}
};