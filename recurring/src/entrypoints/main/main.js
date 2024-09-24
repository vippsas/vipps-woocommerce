import "./main.scss";
import createApp from "../../lib/create-app";
import PaymentRedirectPage from "../../pages/PaymentRedirectPage";

if (document.getElementById("vipps-mobilepay-recurring-app")) {
  createApp(document.querySelector("#vipps-mobilepay-recurring-app"), <PaymentRedirectPage/>);
}
