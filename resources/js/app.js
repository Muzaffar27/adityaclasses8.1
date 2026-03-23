import "./bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./components/App.vue";
import "../sass/app.scss";
import { useAuthStore } from "./stores/auth";

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Restore session on every page load
const auth = useAuthStore();
auth.fetchUser().finally(() => app.mount("#app"));
