import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import iframeHandler from "../helpers/iframeHandler";
import tests from "../config/tests.json"
import data from "../config/data.json";

const options = {
	"headless": false,
	"defaultViewport": null,
	"args": [
		"--disable-infobars",
		"--disable-web-security",
		"--disable-features=IsolateOrigins,site-per-process"
	]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 4500;
let json = data;

describe("Collector Checkout E2E tests", () => {
	beforeAll(async () => {
		try {
			json = await setup.setupStore(json);

			await utils.setOptions();
		} catch (e) {
			console.log(e);
		}
	}, 250000);

	beforeEach(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
	});

		afterEach(async () => {
			if (!page.isClosed()) {
				browser.close();
			}
			await API.clearWCSession();
		});

		test.each(tests)(
			"$name",
			async (args) => {


			// --------------- GUEST/LOGGED IN --------------- //
			if (args.loggedIn) {
				await page.goto(urls.MY_ACCOUNT);
				await utils.login(page, "admin", "password");
			}

			// --------------- SETTINGS --------------- //
			await utils.setPricesIncludesTax({ value: args.inclusiveTax });
			await utils.setOptions();

			// --------------- ADD PRODUCTS TO CART --------------- //
			await utils.addMultipleProductsToCart(page, args.products, json);
			await page.waitForTimeout( timeOutTime);

			// --------------- GO TO CHECKOUT --------------- //
			await page.goto(urls.CHECKOUT);
			await page.waitForTimeout(timeOutTime);
			await utils.selectShippingMethod(page, args.shippingMethod)
			await utils.selectCollector(page);
			await page.waitForTimeout( timeOutTime);

			// --------------- COUPON HANDLER --------------- //
			await page.waitForTimeout(timeOutTime);
			await utils.applyCoupons(page, args.coupons);

			await page.reload()
			await page.waitForTimeout(timeOutTime);

			let removeCoupon = await page.$('[data-coupon="free"]');

			await page.waitForTimeout(timeOutTime);

			// Check if coupons have disabled Collector
			if (removeCoupon == null ){

				await page.reload()

				// --------------- START OF IFRAME --------------- //
				await page.waitForTimeout(timeOutTime);

				let customerSelectorB2C = await page.$("[data-tab='b2c']")
				let customerSelectorB2B = await page.$("[data-tab='b2b']")

				if(args.customerType === 'company'){
					customerSelectorB2B.click()
				} else if (args.customerType === 'person') {
					customerSelectorB2C.click()
				}

				await page.waitForTimeout(timeOutTime);

				let frameContainer = await page.$('#collector-iframe iframe')
				let collectorIframe = await frameContainer.contentFrame();

				// // --------------- B2B/B2C SELECTOR --------------- //
				await iframeHandler.setCustomerType(page, collectorIframe, args.customerType);

				// --------------- POST PURCHASE CHECKS --------------- //
				await page.waitForTimeout(2 * timeOutTime);

				let checkoutURL = await page.evaluate(()=>window.location.href)

				const orderData = await API.getShippingByOrderId(checkoutURL);
				await page.waitForTimeout(2 * timeOutTime);

				const value = await page.$eval(".entry-title", (e) => e.textContent);
				expect(value).toBe("Order received");
			
				// Get the thankyou page iframe and run checks.
				let frameContainerThankYou = await page.$('.woocommerce-order iframe')
				const collectorIframethankYou = await frameContainerThankYou.contentFrame();
				const collectorOrderData = await iframeHandler.getOrderData(collectorIframethankYou);

				// Add shipping amounts for Collector totals.
				const updatedCollectorData = parseFloat(Number(parseFloat(collectorOrderData).toFixed(2)) + Number(parseFloat(orderData.data.shipping_total).toFixed(2)) + Number(parseFloat(orderData.data.shipping_tax).toFixed(2))).toFixed(2)

			
				const wooOrderTotal = await page.$eval(".woocommerce-Price-amount.amount bdi", (e) => e.innerText)
				const wooOrderTotalAsFloat = utils.convertWooTotalAmountToFloat(wooOrderTotal)

				expect(updatedCollectorData).toBe( wooOrderTotalAsFloat);

			} else {

				removeCoupon.click()
				await page.waitForTimeout(timeOutTime);

				let collectorPaymentMethodSelector = await page.$('label[for="payment_method_collector_checkout"]');
				collectorPaymentMethodSelector.click()
				
				await page.waitForTimeout(timeOutTime);

				let refreshedFrameContainer = await page.$('#collector-container');

				expect(refreshedFrameContainer).toBeTruthy();
			}

		}, 250000);
});
