import "./main.scss";
import createApp from "./lib/create-app";

if (document.getElementById("vipps-mobilepay-recurring-app")) {
  createApp(document.querySelector("#vipps-mobilepay-recurring-app"), <div />);
}
