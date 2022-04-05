
const timeOutTime = 3000;

const setCustomerType = async (page, collectorIframe, customerType) => {

	if (customerType === "person") {

		await collectorIframe.type('#customer-identify--input-email','e3e@test.se')
		await collectorIframe.type('#customer-identify--input-phone','+46701234567')
		await collectorIframe.$eval('#customer-identify--button-submit', (e) => e.click() )

		await collectorIframe.waitForTimeout(timeOutTime)
		await collectorIframe.type('#customer-complement--input-registration-number','198010011016')
		await collectorIframe.type('#customer-complement--input-postal-code','53431')

		await collectorIframe.click('#customer-complement--button-submit')
		await collectorIframe.waitForTimeout(timeOutTime)
		await collectorIframe.click('#purchase-method-select--button-direct-invoice')
		await collectorIframe.waitForTimeout(timeOutTime)
		await collectorIframe.click('#purchase-perform-direct-invoice--button-submit')

	} else if (customerType === "company") {

		await collectorIframe.type('#customer-identify--input-organization-number','5562000116')
		await collectorIframe.type('#customer-identify--input-given-name','Ruben')
		await collectorIframe.type('#customer-identify--input-family-name','Henrikson')
		await collectorIframe.type('#customer-identify--input-email','e2e@test.se')
		await collectorIframe.type('#customer-identify--input-phone','+46701234567')
		await collectorIframe.click('#customer-identify--button-submit')

		await collectorIframe.waitForTimeout(timeOutTime)
		await collectorIframe.click('#purchase-method-select--button-direct-invoice')
		await collectorIframe.waitForTimeout(timeOutTime)
		await collectorIframe.click('#purchase-perform-direct-invoice--button-submit')
	}
}


const processShipping = async (page, collectorIframe, shippingMethod, shippingInIframe) => {
	if ( shippingInIframe === "yes" ) {
		let shippingSelection = await collectorIframe.$$(
			'[data-cid="SHIPMO-shipping-option-basic"]'
		);

		if (shippingMethod === "flat_rate") {
			await shippingSelection[0].click();
		} else if (shippingMethod === "free_shipping") {
			await shippingSelection[1].click();
		}
	} else {
		let shippingMethodTarget = `[id*="_${shippingMethod}"]`;

		if (shippingMethod !== "") {
			await page.waitForSelector(shippingMethodTarget).id;
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(2 * timeOutTime);
		}
	}
}


const getOrderData = async (thankyouIframe) => {

	let collectorTotalAmount = await thankyouIframe.$eval("#completed-direct-invoice--output-total-amount", (e)=>{
		return (e.childNodes[0].innerHTML)
	})

	collectorTotalAmount=collectorTotalAmount.replace(/\s+/g, '');
	collectorTotalAmount=collectorTotalAmount.replace(',', '.');


	return parseFloat(collectorTotalAmount);
}

export default {
	setCustomerType,
	processShipping,
	getOrderData,
}
