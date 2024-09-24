import "./main.scss";
import createApp from "../../lib/create-app";
import CheckoutPage from '../../pages/CheckoutPage'

if (document.getElementById("vipps-mobilepay-recurring-checkout")) {
  createApp(document.querySelector("#vipps-mobilepay-recurring-checkout"), <CheckoutPage/>);
}
