import API from "../api/API";
import urls from "./urls";

const timeOutTime = 2500;
const collectorSettingsArray = {
	woocommerce_collector_checkout_settings: {
		enabled: "yes",
		title: "Collector",
		collector_username: process.env.COLLECTOR_USERNAME,
		collector_password: process.env.COLLECTOR_PASSWORD,
		collector_shared_key: process.env.SHARED_KEY,
		se_settings_title: "Sweden",
		collector_merchant_id_se_b2c: process.env.MERCHANT_ID_SWEDEN_B2C,
		collector_merchant_id_se_b2b: process.env.MERCHANT_ID_SWEDEN_B2B,
		collector_delivery_module_se: "",
		no_settings_title: "Norway",
		collector_merchant_id_no_b2c: "",
		collector_merchant_id_no_b2b: "",
		collector_delivery_module_no: "",
		fi_settings_title: "Finland",
		collector_merchant_id_fi_b2c: "",
		collector_merchant_id_fi_b2b: "",
		collector_delivery_module_fi: "",
		dk_settings_title: "Denmark",
		collector_merchant_id_dk_b2c: "",
		collector_delivery_module_dk: "",
		checkout_settings_title: "Checkout settings",
		checkout_version: "",
		checkout_layout: "",
		collector_invoice_fee: "no",
		collector_default_customer: "no",
		checkout_button_color: "",
		activate_validation_callback: "",
		requires_electronic_id_fields: "",
		order_management_settings_title: "",
		manage_collector_orders: "",
		display_invoice_no: "",
		test_mode_settings_title: "",
		test_mode: "yes",
		debug_mode: "yes",
	},
};

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		for (const appliedCoupon of appliedCoupons) {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', appliedCoupon);
			await page.click('[name="apply_coupon"]');
		}
	}
	await page.waitForTimeout(3 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	try {
		await page.goto(`${urls.ADD_TO_CART}${productId}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 1000);
	let ids = [];

	products.forEach( name => {
		data.products.simple.forEach(product => {
			if(name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach(product => {
			product.attribute.options.forEach(variation => {
				if(name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	for (const id of ids) {
		await addSingleProductToCart(page, id);
	}

	await page.waitForTimeout(timer * 1000);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};

const selectCollector = async (page) => {
	if (await page.$('input[id="payment_method_collector_checkout"]')) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$('input[id="payment_method_collector_checkout"]')
		);
	}
}

const setOptions = async () => {
	await API.updateOptions(collectorSettingsArray);
};

const selectShippingMethod = async (page, shippingMethod) => {

	let searchString

	if (shippingMethod === 'free_shipping') {
		searchString = 'input[value*="free_shipping"]'
	} else if (shippingMethod === 'flat_rate') {
		searchString = 'input[value*="flat_rate"]'
	}

	let shippingMethodSelector = await page.$(searchString)

	if (shippingMethodSelector) {
		await shippingMethodSelector.focus()
		await shippingMethodSelector.click()
	}
}

const convertWooTotalAmountToFloat = (totalString) => {
	return parseFloat((((totalString.substring(totalString.indexOf('\n') + 1)).split('kr')[0]).replace('.', '')).replace(',', '.')).toFixed(2)
}

export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
	selectShippingMethod,
	convertWooTotalAmountToFloat,
	
	selectCollector,
	setOptions
};
