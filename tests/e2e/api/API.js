import axios from "axios";
import urls from "../helpers/urls";
import woocommerce from "./woocommerce";
require("dotenv").config();


const getShippingByOrderId = async (orderId) => {
	return (getWCOrderById(orderId.split('/')[5]))
}

const getWCOrderById = async (id) => woocommerce.getOrderById(id);
const createWCCustomer = async (data) => woocommerce.createCustomer(data);
const getWCCustomers = async () => woocommerce.getCustomers();
const clearWCSession = async () => woocommerce.clearSession();
const updateOptions = async (data) => woocommerce.updateOption(data);
const createWCProduct = async (data) => woocommerce.createProduct(data);
const getWCOrders = async () => woocommerce.getOrders();
const getProducts = async () => woocommerce.getProducts();
const getWCProductById = async (id) => woocommerce.getProductById(id);
const pricesIncludeTax = async (data) => woocommerce.pricesIncludeTax(data);


export default {
	getShippingByOrderId,
	getWCOrderById,
	getWCCustomers,
	createWCCustomer,
	clearWCSession,
	updateOptions,
	createWCProduct,
	getWCOrders,
	getProducts,
	getWCProductById,
	pricesIncludeTax,
};
