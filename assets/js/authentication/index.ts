import {AuthenticationHandler} from "./AuthenticationHandler";

const authHandler = new AuthenticationHandler((success: boolean, message: string) => {
    const notification = document.querySelector('.notification');
    if (notification) {
        notification.innerHTML = message;
    }
})

authHandler.init(true).then(r => console.log(r));