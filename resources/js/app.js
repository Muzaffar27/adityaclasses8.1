import "./bootstrap";
import { createApp } from "vue";
import router from "./router"; // Import your router configuration
import App from "./components/App.vue";
import "../sass/app.scss";

const app = createApp(App);

app.use(router);
app.mount("#app");
