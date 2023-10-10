import Authentication from "./Authentication";
import Utilities from "../Utilities";

const authHandler = new Authentication(Utilities.setNotification.bind(this));
authHandler.init(true).then(r => console.info(
    'You rock!'
));