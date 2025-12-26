/**
 * this provider will return USD/RUB rate
 */
function start(){
	util.setNextStep(bankLoaded);
	page.open('http://ru.exchange-rates.org/Rate/USD/RUB');
}

function bankLoaded() {
	util.injectJquery();
	util.setBalance(util.querySelector('span#ctl00_M_grid_ctl02_lblResult', true, /(\d+\,\d{2})/im));
	output.keepState = true;
	util.exit();
}

